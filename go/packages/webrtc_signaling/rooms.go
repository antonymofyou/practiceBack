package webrtc_signaling

import (
	"encoding/json"
	"fmt"
	"github.com/gorilla/websocket"
	"log"
	"net/http"
	"strconv"
	"sync"
)

// ключ - device юзера
type ReceiveChannels map[int]chan []byte

// хранение информации о комната (временно глобальная переменная)
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
			log.Println("no correct device", r.URL.Query().Get("device"))
			return
		}

		// как будто комнату из БД получили
		rd := getRoom(userDevice)

		// проверяем, есть ли уже такая комната в rds
		rds.Lock()
		if rdPtr, ok := rds.rooms[rd.ID]; ok {
			// если комната найдена, то даем юзерам указатель на одну и ту же комнату
			rd = rdPtr
		} else {
			// если комнаты нет, создаем ее
			rds.rooms[rd.ID] = rd
			log.Println("created room data id", rd.ID)
		}
		rds.Unlock()

		// Метод добавит в rds комнату, если она еще не добавлена.
		//rds.addRoomData(rd)

		// обеспечение потокобезопасности
		mu.Lock()
		defer mu.Unlock()

		// Если пользователь уже есть в комнате, не создаем новое подключение
		if _, ok := rooms[rd.ID][userDevice]; ok {
			jsonResponse, _ := json.Marshal(struct {
				Status string `json:"status"`
			}{"OTHER_DEVICE"})
			if _, err := w.Write(jsonResponse); err != nil {
				log.Println(err)
			}
			log.Println("error: OTHER_DEVICE")
			return
		}

		// Если комната на сервере еще не создана (первое подключение), создаем ее.
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

		exitFunc := func() {
			removeDeviceFromRoom(rooms[rd.ID], userDevice, mu, conn)
			removeRoomIfNoConnections(rooms, rd.ID, mu)
		}

		// Запуск горутин на слушание и отправку сообщений
		go readMessagesFromWebsocket(conn, rooms[rd.ID], exitFunc, rd, userDevice) // from webSocket
		go writeMessagesToWebsocket(conn, rooms[rd.ID][userDevice], exitFunc, rd, userDevice)
	}
}

func readMessagesFromWebsocket(conn *websocket.Conn, channels ReceiveChannels, exitFunc func(), rd *roomData, userDevice int) {
	// Какие могут возникнуть ошибки?
	// Классификации ошибок

	// Отложенный вызов функции, закрывающей вебсокет-соединение и удаляющей юзера из комнаты.
	defer exitFunc()

	defer panicHandler("readMessagesFromWebsocket")

	if userDevice == rd.initiatorDevice && rd.Answer != "" { // Если текущий пользователь является инициатором и answer уже задан, отправляем answer инициатору
		channels[userDevice] <- []byte(rd.Answer)
		return // разрыв подключения, все данные были переданы
	} else if userDevice == rd.responderDevice && rd.Offer != "" { // Если текущий пользователь является респондером и оффер уже задан, отправляем оффер респондеру
		channels[userDevice] <- []byte(rd.Offer)
	}

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			return // разорвано соединение, конец горутины
		}

		if string(msg) == "offer" && userDevice == rd.initiatorDevice { // Если от инициатора пришел оффер - отправляем его респондеру
			rd.Offer = string(msg)
			if responderChannel, ok := channels[rd.responderDevice]; ok {
				responderChannel <- []byte(rd.Offer)
				fmt.Println(rd)
			}

		} else if string(msg) == "answer" && rd.responderDevice == userDevice { // Если от респондера пришел answer - отправляем его инициатору
			if rd.Offer == "" {
				channels[userDevice] <- []byte("OFFER_NOT_SET")
				continue
			}

			rd.Answer = string(msg)

			if initiatorChannel, ok := channels[rd.initiatorDevice]; ok {
				initiatorChannel <- []byte(rd.Answer)
				fmt.Println(rd)
				return
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
			}
			if userDevice == rd.initiatorDevice && rd.Answer != "" { // Если текущий пользователь инициатор и ответ на его оффер уже получен, то закрываем соединение с ним
				return
			}
		}
	}
}

func removeDeviceFromRoom(room ReceiveChannels, userDevice int, mu *sync.Mutex, conn *websocket.Conn) {
	// Закрываем вебсокет-соединение
	if err := conn.Close(); err != nil {
		// если есть ошибка, значит, функция уже отработала.
		return
	}

	// Обеспечиваем потокобезопасность
	mu.Lock()
	defer mu.Unlock()

	delete(room, userDevice) // удаление юзера из комнаты.
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
