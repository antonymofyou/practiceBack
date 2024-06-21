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
			// Если комната найдена в rds, это подключился второй юзер. Меняем статус.
			rd.status = WAIT_OFFER
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
			// Отключился один - отключаются все. Функция корректно закрывает соединения, закрывает каналы,
			// если они не закрыты, чити информацию из памяти go.
			removeAllConnections(conn, rooms, mu, rd.ID, &rds)
		}

		// Запуск горутин на слушание и отправку сообщений
		go readMessagesFromWebsocket(conn, rooms[rd.ID], exitFunc, rd, userDevice, mu) // from webSocket
		go writeMessagesToWebsocket(conn, rooms[rd.ID][userDevice], exitFunc, rd, userDevice)
	}
}

func readMessagesFromWebsocket(conn *websocket.Conn, channels ReceiveChannels, exitFunc func(), rd *roomData, userDevice int, mu *sync.Mutex) {
	// Отложенный вызов функции, закрывающей вебсокет-соединение и удаляющей юзера из комнаты.
	defer exitFunc()

	defer panicHandler("readMessagesFromWebsocket")

	// Один раз читаем мапу для получения каналов инициатора и респондера

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			return // разорвано соединение, конец горутины
		}
		mu.Lock()
		if rd.status == WAIT_SECOND_USER {
			if userDevice == rd.initiatorDevice && string(msg) == "offer" {
				// Статус WAIT_SECOND_USER, попытка отправить offer. Ошибка
				channels[rd.initiatorDevice] <- []byte("CANNOT_SEND_OFFER_BEFORE_SECOND_USER")
			} else if userDevice == rd.responderDevice && string(msg) == "answer" {
				// Статус WAIT_SECOND_USER, попытка отправить answer. Ошибка
				channels[rd.responderDevice] <- []byte("CANNOT_SEND_ANSWER_BEFORE_SECOND_USER")
			}
		} else if rd.status == WAIT_OFFER {
			if userDevice == rd.initiatorDevice && string(msg) == "offer" {
				// Статус WAIT_OFFER, инициатор отправляет offer, верный сценарий. Смена статуса.
				channels[rd.responderDevice] <- msg
				rd.status = WAIT_ANSWER
			} else if userDevice == rd.responderDevice && string(msg) == "answer" {
				// Статус WAIT_OFFER, респондер пытается отправить answer. Ошибка
				channels[rd.responderDevice] <- []byte("CANNOT_SEND_ANSWER_BEFORE_OFFER")
			}
		} else if rd.status == WAIT_ANSWER {
			if userDevice == rd.initiatorDevice && string(msg) == "offer" {
				// Статус WAIT_ANSWER, инициатор пытается дублировать offer. Ошибка
				channels[rd.initiatorDevice] <- []byte("CANNOT_SEND_OFFER_TWICE")
			} else if userDevice == rd.responderDevice && string(msg) == "answer" {
				// Статус WAIT_ANSWER, респондер отправляет answer. Верный сценарий. Смена статуса.
				channels[rd.initiatorDevice] <- msg
				rd.status = WAIT_ICE_CANDIDATES
			}
		}
		mu.Unlock()
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

// Закрытие всех подключений и очистка данных о комнате
func removeAllConnections(conn *websocket.Conn, rooms map[int]ReceiveChannels, mu *sync.Mutex, roomID int, rdStorage *roomDataStorage) {

	mu.Lock()
	defer mu.Unlock()

	// Если ошибка, значит, закрытие уже отработало, делаем return
	if err := conn.Close(); err != nil {
		return
	}

	rdStorage.Lock()
	// Если комната есть в rds, удаляем ее
	if _, ok := rdStorage.rooms[roomID]; ok {
		delete(rdStorage.rooms, roomID)
		log.Println("room data", roomID, "was deleted")
	}
	rdStorage.Unlock()

	// делаем закрытие каналов и очистку channels в закрываемой комнате
	// закрытие каналов триггернет всех участников удаляемой комнаты, и они закроют свои подключения
	if userChannels, ok := rooms[roomID]; ok { // если комната еще не очищена
		for userDevice, userChannel := range userChannels {
			close(userChannel)
			delete(rooms[roomID], userDevice)
			log.Println("user", userDevice, "was disconnected")
		}
		delete(rooms, roomID)
		log.Println("room", roomID, "was deleted")
	}
}
