package webrtc_signaling

import (
	"github.com/gorilla/websocket"
	"log"
	"net/http"
	"strconv"
)

// хранение информации о комнатах (временно глобальная переменная)
var rds = roomDataStorage{rooms: make(map[int]*roomData)}

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func RoomHandler() http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		// получение девайса из запроса
		userDevice, err := strconv.Atoi(r.URL.Query().Get("device"))
		// Валидация девайса
		if err != nil {
			errorJsonResponse(w, r, "INVALID_DEVICE")
			return
		}

		// как будто комнату из БД получили
		roomInfoDB, err := getRoom(userDevice)
		if err != nil {
			errorJsonResponse(w, r, "ERROR_DATABASE")
			return
		}

		rds.Lock()
		defer rds.Unlock()

		// TODO: OTHER_DEVICE добавить
		// проверяем, есть ли уже такая комната в rds
		if _, ok := rds.rooms[roomInfoDB.ID]; !ok {
			// комнаты в rds еще нет, создаем ее.
			rds.rooms[roomInfoDB.ID] = newRoomData(roomInfoDB)
			// если комнаты в rds нет, значит, этот юзер подключился первым и будет ждать второго
			rds.rooms[roomInfoDB.ID].status = WAIT_SECOND_USER
			log.Println("created room data id", rds.rooms[roomInfoDB.ID])
		} else {
			// Если комната найдена в rds, это подключился второй юзер. Меняем статус.
			rds.rooms[roomInfoDB.ID].status = WAIT_OFFER
		}

		// Установка вебсокет-соединения
		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Println(err)
			//TODO: добавить removeAllConnections? Это случай, когда какой-то юзер не смог подключиться, но один уже есть в памяти ГО
			return
		}

		// Если хоть один юзер отключился, отключаем всех.
		exitFunc := func() {
			// Отключился один - отключаются все. Функция корректно закрывает соединения, закрывает каналы,
			// если они не закрыты, чистит информацию из памяти go.
			removeAllConnections(conn, roomInfoDB.ID, &rds, rds.rooms[roomInfoDB.ID])
		}

		// Определяем, к какому каналу (инициатор или респондер) нужно привязать conn.
		var userChannel chan []byte = nil
		if userDevice == roomInfoDB.initiatorDevice {
			userChannel = rds.rooms[roomInfoDB.ID].initiatorChannel
		} else if userDevice == roomInfoDB.responderDevice {
			userChannel = rds.rooms[roomInfoDB.ID].responderChannel
		}

		// Запуск горутин на слушание и отправку сообщений
		go readMessagesFromWebsocket(conn, rds.rooms[roomInfoDB.ID], userDevice, exitFunc) // from webSocket
		go writeMessagesToWebsocket(conn, userChannel, exitFunc)
	}
}

func readMessagesFromWebsocket(conn *websocket.Conn, rd *roomData, userDevice int, exitFunc func()) {
	// Отложенный вызов функции, закрывающей вебсокет-соединение и удаляющей юзера из комнаты.
	defer exitFunc()

	defer panicHandler("readMessagesFromWebsocket")

	// Один раз читаем мапу для получения каналов инициатора и респондера

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			return // разорвано соединение, конец горутины
		}
		// TODO: добавить проверку, не закрыты ли каналы
		rd.Lock()

		if rd.status == WAIT_SECOND_USER {
			if userDevice == rd.initiatorDevice && string(msg) == "offer" {
				// Статус WAIT_SECOND_USER, попытка отправить offer. Ошибка
				rd.initiatorChannel <- []byte("CANNOT_SEND_OFFER_BEFORE_SECOND_USER")
			} else if userDevice == rd.responderDevice && string(msg) == "answer" {
				// Статус WAIT_SECOND_USER, попытка отправить answer. Ошибка
				rd.responderChannel <- []byte("CANNOT_SEND_ANSWER_BEFORE_SECOND_USER")
			}
		} else if rd.status == WAIT_OFFER {
			if userDevice == rd.initiatorDevice && string(msg) == "offer" {
				// Статус WAIT_OFFER, инициатор отправляет offer, верный сценарий. Смена статуса.
				rd.responderChannel <- msg
				rd.status = WAIT_ANSWER
			} else if userDevice == rd.responderDevice && string(msg) == "answer" {
				// Статус WAIT_OFFER, респондер пытается отправить answer. Ошибка
				rd.responderChannel <- []byte("CANNOT_SEND_ANSWER_BEFORE_OFFER")
			}
		} else if rd.status == WAIT_ANSWER {
			if userDevice == rd.initiatorDevice && string(msg) == "offer" {
				// Статус WAIT_ANSWER, инициатор пытается дублировать offer. Ошибка
				rd.initiatorChannel <- []byte("CANNOT_SEND_OFFER_TWICE")
			} else if userDevice == rd.responderDevice && string(msg) == "answer" {
				// Статус WAIT_ANSWER, респондер отправляет answer. Верный сценарий. Смена статуса.
				rd.initiatorChannel <- msg
				rd.status = WAIT_ICE_CANDIDATES
			}
		} else if rd.status == WAIT_ICE_CANDIDATES {
			if userDevice == rd.initiatorDevice && string(msg) == "ice_candidates" {
				// Инициатор отправил ic, передаем ic респондеру
				rd.responderChannel <- msg
			} else if userDevice == rd.responderDevice && string(msg) == "ice_candidates" {
				// Респондер отправил ic, передаем ic инициатору
				rd.initiatorChannel <- msg
			} else if userDevice == rd.initiatorDevice && string(msg) == "FINISH_SEND_ICE_CANDIDATES" {
				// Инициатор закончил отправлять ic, ставим соответствующий флаг.
				rd.isFinishSendIceCandidatesInitiator = true
			} else if userDevice == rd.responderDevice && string(msg) == "FINISH_SEND_ICE_CANDIDATES" {
				// Респондер закончил отправлять ic, ставим соответствующий флаг.
				rd.isFinishSendIceCandidatesResponder = true
			}

			if rd.isFinishSendIceCandidatesInitiator && rd.isFinishSendIceCandidatesResponder {
				// Обмен кандидатами окончен, закрываем соединение.
				rd.Unlock()
				return
			}
		}
		// TODO: Стоит ли ограничить отправку кандидатов после флажка финиша отправки кандидатов?
		rd.Unlock()
	}
}

func writeMessagesToWebsocket(conn *websocket.Conn, userChannel chan []byte, exitFunc func()) {
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

// Возврат JSON с ошибкой. Открытие-JSON-закрытие вебсокет-соединения.
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
func removeAllConnections(conn *websocket.Conn, roomID int, rdStorage *roomDataStorage, rd *roomData) {

	// Если ошибка, значит, закрытие уже отработало, делаем return
	if err := conn.Close(); err != nil {
		return
	}

	rdStorage.Lock()
	defer rdStorage.Unlock()
	// Проверка, осталась ли еще эта комната в rds.
	if _, ok := rdStorage.rooms[roomID]; !ok {
		// Комната уже удалена из памяти
		return
	} else {
		// закрываем каналы внутри комнаты, внутри метода обеспечена потокобезопасность внутри комнаты.
		rd.closeConnections()
		delete(rdStorage.rooms, roomID)
		log.Println("room", roomID, "was deleted")
	}
}
