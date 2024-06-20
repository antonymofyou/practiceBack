package webrtc_signaling

import (
	"github.com/gorilla/websocket"
	"log"
	"net/http"
	"strconv"
	"sync"
)

// ключ - device юзера
type ReceiveChannels map[int]chan []byte

// хранение информации о комнатах (временно глобальная переменная)
var rds = roomDataStorage{rooms: make(map[int]*roomData)}

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func RoomHandler(rooms map[int]ReceiveChannels, mu *sync.Mutex) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		// получение девайса из запроса
		userDevice, err := strconv.Atoi(r.URL.Query().Get("device"))
		// Валидация девайса
		if err != nil {
			errorJsonResponse(w, r, "INVALID_DEVICE")
			return
		}

		// TODO: добавить проверку, чтобы было только 2 подключения в комнате

		// как будто комнату из БД получили
		rd, err := getRoom(userDevice)
		if err != nil {
			errorJsonResponse(w, r, "ERROR_DATABASE")
			return
		}

		// TODO: Если в БД нет комнаты, возврат ошибки

		// обеспечение потокобезопасности
		mu.Lock()
		defer mu.Unlock()

		// Провека, если пользователь уже есть в комнате, не создаем новое подключение
		if _, ok := rooms[rd.ID][userDevice]; ok {
			errorJsonResponse(w, r, "OTHER_DEVICE")
			return
		}

		// проверяем, есть ли уже такая комната в rds
		rds.Lock()
		if rdPtr, ok := rds.rooms[rd.ID]; ok {
			// если комната найдена, то даем юзерам указатель на одну и ту же комнату
			rd = rdPtr
			// Если комната найдена в rds, скорее всего, это подключился второй юзер. Меняем статус.
			if rd.status == WAIT_SECOND_USER { // Проверка на всякий случай
				rd.status = WAIT_OFFER
			}
		} else {
			// если комнаты в rds нет, значит, этот юзер подключился первым и будет ждать второго
			rd.status = WAIT_SECOND_USER
			// добавляем комнату в rds
			rds.rooms[rd.ID] = rd
			log.Println("created room data id", rd.ID)
		}
		rds.Unlock()

		// Проверка, Если комната в памяти go еще не создана (первое подключение), создаем ее.
		if _, ok := rooms[rd.ID]; !ok {
			rooms[rd.ID] = make(ReceiveChannels)
			log.Println("room", rd.ID, "was created")
		}

		// Установка вебсокет-соединения
		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Println(err)
			return
		}

		// Создания канала для доставки сообщения вебсокет-соединению
		userChannel := make(chan []byte)

		// Привязка канала юзера к конкретной комнате
		rooms[rd.ID][userDevice] = userChannel
		log.Println("Device", userDevice, "connected to room", rd.ID)

		// Если хоть один юзер отключился, отключаем всех.
		exitFunc := func() {
			// Закрытие каналов и удаление каналов из комнаты
			mu.Lock()
			for k, ch := range rooms[rd.ID] {
				close(ch)
				delete(rooms[rd.ID], k)
			}
			mu.Unlock()
			removeDeviceFromRoom(userDevice, conn)      // закрываем вебсокет-подключение
			removeRoomIfNoConnections(rooms, rd.ID, mu) // удаляется комната (каналы)
			// Сносим roomData из rds
			rds.Lock()
			if _, ok := rds.rooms[rd.ID]; ok {
				delete(rds.rooms, rd.ID)
				log.Println("room data id", rd.ID, "was deleted")
			}
			rds.Unlock()
		}

		// Запуск горутин на слушание и отправку сообщений
		go readMessagesFromWebsocket(conn, rooms[rd.ID], exitFunc, rd, userDevice) // from webSocket
		go writeMessagesToWebsocket(conn, rooms[rd.ID][userDevice], exitFunc, rd, userDevice)
	}
}

func readMessagesFromWebsocket(conn *websocket.Conn, channels ReceiveChannels, exitFunc func(), rd *roomData, userDevice int) {
	// Отложенный вызов функции, закрывающей вебсокет-соединение и удаляющей юзера из комнаты.
	defer exitFunc()

	defer panicHandler("readMessagesFromWebsocket")

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {

			return // разорвано соединение, конец горутины
		}

		if rd.status == WAIT_OFFER {
			if userDevice == rd.initiatorDevice {
				if string(msg) == "offer" {
					// Статус WAIT_OFFER, инициатор отправляет offer, верный сценарий. Смена статуса.
					channels[rd.responderDevice] <- msg
					rd.status = WAIT_ANSWER
				} else if string(msg) == "answer" {
					// Статус WAIT_OFFER, инициатор отправляет answer. Ошибка
					channels[rd.initiatorDevice] <- []byte("INITIATOR_CANNOT_SEND_ANSWER")
				}
			} else if userDevice == rd.responderDevice {
				if string(msg) == "offer" {
					// Статус WAIT_OFFER, респондер отправляет offer. Ошибка
					channels[rd.responderDevice] <- []byte("RESPONDER_CANNOT_SEND_OFFER")
				} else if string(msg) == "answer" {
					// Статус WAIT_OFFER, респондер отправляет answer. Ошибка
					channels[rd.responderDevice] <- []byte("CANNOT_SEND_ANSWER_BEFORE_OFFER")
				}
			}
		} else if rd.status == WAIT_ANSWER {
			if userDevice == rd.initiatorDevice {
				if string(msg) == "offer" {
					// Статус WAIT_ANSWER, инициатор отправляет offer. Ошибка
					channels[rd.initiatorDevice] <- []byte("CANNOT_SEND_OFFER_TWICE")
				} else if string(msg) == "answer" {
					// Статус WAIT_ANSWER, инициатор отправляет answer. Ошибка
					channels[rd.initiatorDevice] <- []byte("INITIATOR_CANNOT_SEND_ANSWER")
				}
			} else if userDevice == rd.responderDevice {
				if string(msg) == "offer" {
					// Статус WAIT_OFFER, респондер отправляет offer. Ошибка
					channels[rd.responderDevice] <- []byte("RESPONDER_CANNOT_SEND_OFFER")
				} else if string(msg) == "answer" {
					// Статус WAIT_ANSWER, респондер отправляет answer. Верный сценария.
					channels[rd.initiatorDevice] <- msg
				}
			}
		}

		// Закомментировано, потому что обмен всеми сообщениями больше не нужен.
		/*for _, userChannel := range channels {
			userChannel <- msg
		}*/
	}
}

func writeMessagesToWebsocket(conn *websocket.Conn, userChannel chan []byte, exitFunc func(), rd *roomData, userDevice int) {
	defer exitFunc()

	defer panicHandler("writeMessagesToWebsocket")

	for {
		select {
		case msg, ok := <-userChannel:
			if !ok {
				return // разорвано соединение
			}
			if err := conn.WriteMessage(websocket.TextMessage, msg); err != nil {
				log.Println(err)
				return
			}
		}
	}
}

func removeDeviceFromRoom(userDevice int, conn *websocket.Conn) {
	// Закрываем вебсокет-соединение
	if err := conn.Close(); err != nil {
		// если есть ошибка, значит, функция уже отработала.
		return
	}

	log.Println(userDevice, "deleted from room (disconnected)")
}

func removeRoomIfNoConnections(rooms map[int]ReceiveChannels, roomID int, mu *sync.Mutex) {
	// Потокобезопасность
	mu.Lock()
	defer mu.Unlock()
	// проверка, существует ли еще эта комната
	if _, ok := rooms[roomID]; !ok {
		return // возврат из функции, если комната была удалена в другой горутине
	}
	// Удаление комнаты, если в ней больше не осталось подключенных девайсов
	if len(rooms[roomID]) == 0 {
		delete(rooms, roomID)
		log.Println("room", roomID, "was deleted (no connections)")
	}
}

func panicHandler(place string) {
	if err := recover(); err != nil {
		log.Println("recover panic in", place, ":", err)
	}
}

// Возврат JSON с ошибкой. Открытие-JSON-закрытия вебсокет-соединения.
func errorJsonResponse(w http.ResponseWriter, r *http.Request, status string) {
	jsonResponse := &struct {
		Status string `json:"status"`
	}{status}

	if conn, err := upgrader.Upgrade(w, r, nil); err == nil {
		if e := conn.WriteJSON(jsonResponse); e != nil {
			log.Println(e)
		}
		if e := conn.Close(); e != nil {
			log.Println(e)
		}
	}
}
