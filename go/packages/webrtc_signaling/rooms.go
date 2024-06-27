package webrtc_signaling

import (
	"github.com/gorilla/websocket"
	"log"
	"nasotku/includes/api_root_classes"
	"net/http"
)

type WebrtcSignalingOfferRequest struct {
	api_root_classes.MainRequestClass
	Offer string `json:"offer"`
}

type WebrtcSignalingOfferResponse struct {
	api_root_classes.MainResponseClass
	Offer string `json:"offer"`
}

/*
* Принимать ic в статусах wait_offer, wait_answer, wait_ice_candidates.
* Добавить возможность ставить флаги окончания отправки ic в этих статусах.
*
* Переименовать статус wait_ice_candidates в FINISH_RECEIVE_DATA (?) (окончание отправки offer/answer).
 */

// хранение информации о комнатах (временно глобальная переменная)
// на проде делать аллокацию для 1000+ значений.
var rds = roomDataStorage{rooms: make(map[int]*roomData)}

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func RoomHandler() http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		in := &api_root_classes.MainRequestClass{}
		out := &api_root_classes.MainResponseClass{}

		// Установка вебсокет-соединения
		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Println(err)
			return
		}

		if err := in.FromJson([]byte(r.URL.Query().Get("data")), &in); err != nil {
			errorJsonResponse(conn, out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse))
			return
		}

		//--------------------------------Проверка пользователя
		/*if err := auth.CheckUser(in, in); err != nil {
			errorJsonResponse(conn, out.MakeWrongResponse(err.Error(), err.Success))
			return
		}*/

		// TODO: userDeivce теперь string везде!
		// как будто комнату из БД получили
		roomInfoDB, err := getRoom(in.Device)
		if err != nil {
			errorJsonResponse(conn, out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse))
			return
		}

		rds.Lock()
		defer rds.Unlock()

		// проверяем, есть ли уже такая комната в rds
		if _, ok := rds.rooms[roomInfoDB.ID]; !ok {
			// комнаты в rds еще нет, создаем ее.
			rds.rooms[roomInfoDB.ID] = newRoomData(roomInfoDB)
			// если комнаты в rds нет, значит, этот юзер подключился первым и будет ждать второго
			rds.rooms[roomInfoDB.ID].status = WAIT_SECOND_USER
			log.Println("created room data id", roomInfoDB.ID)
		} else {
			// Если комната уже создана и идет попытка сделать OTHER_DEVICE (сделлать второе подключение с одного девайса)
			if (in.Device == roomInfoDB.initiatorDevice && rds.rooms[roomInfoDB.ID].initiatorConnected) ||
				(in.Device == roomInfoDB.responderDevice && rds.rooms[roomInfoDB.ID].responderConnected) {
				// функция отправит ошибку и закроет conn
				errorJsonResponse(conn, out.MakeWrongResponse("OTHER_DEVICE", api_root_classes.ErrorResponse))
				return
			}
			// Если комната найдена в rds и это не OTHER_DEVICE, значит подключился второй юзер. Меняем статус.
			rds.rooms[roomInfoDB.ID].status = WAIT_OFFER
		}

		// Ставим соответствующий флажок, что подключился либо инициатор либо респондер и заодно определяем,
		// с каким каналом передать conn в горутину
		var userChannel chan []byte = nil
		if in.Device == roomInfoDB.initiatorDevice {
			rds.rooms[roomInfoDB.ID].initiatorConnected = true
			userChannel = rds.rooms[roomInfoDB.ID].initiatorChannel
			log.Println("initiator ID", roomInfoDB.initiatorDevice, "connected")
		} else if in.Device == roomInfoDB.responderDevice {
			rds.rooms[roomInfoDB.ID].responderConnected = true
			userChannel = rds.rooms[roomInfoDB.ID].responderChannel
			log.Println("responder ID", roomInfoDB.responderDevice, "connected")
		}

		// Если хоть один юзер отключился, отключаем всех.
		exitFunc := func() {
			// Отключился один - отключаются все. Функция корректно закрывает соединения, закрывает каналы,
			// если они не закрыты, чистит информацию из памяти go.
			removeAllConnections(conn, roomInfoDB.ID, &rds, rds.rooms[roomInfoDB.ID])
		}

		// Запуск горутин на слушание и отправку сообщений
		go readMessagesFromWebsocket(conn, rds.rooms[roomInfoDB.ID], in.Device, exitFunc) // from webSocket
		go writeMessagesToWebsocket(conn, userChannel, exitFunc)
	}
}

func readMessagesFromWebsocket(conn *websocket.Conn, rd *roomData, device string, exitFunc func()) {
	// Отложенный вызов функции, закрывающей вебсокет-соединение и удаляющей юзера из комнаты.
	defer exitFunc()

	defer panicHandler("readMessagesFromWebsocket")

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			return // разорвано соединение, конец горутины
		}
		// TODO: добавить проверку, не закрыты ли каналы
		rd.Lock()

		if rd.status == WAIT_SECOND_USER {
			if device == rd.initiatorDevice && string(msg) == "offer" {
				// Статус WAIT_SECOND_USER, попытка отправить offer. Ошибка
				rd.initiatorChannel <- []byte("CANNOT_SEND_OFFER_BEFORE_SECOND_USER")
			} else if device == rd.responderDevice && string(msg) == "answer" {
				// Статус WAIT_SECOND_USER, попытка отправить answer. Ошибка
				rd.responderChannel <- []byte("CANNOT_SEND_ANSWER_BEFORE_SECOND_USER")
			} else {
				if device == rd.initiatorDevice {
					rd.initiatorChannel <- []byte("Пришли некорректные данные для текущего статуса")
				}
			}
		} else if rd.status == WAIT_OFFER {
			if device == rd.initiatorDevice {
				//--------------------------------Статус WAIT_OFFER, инициатор отправляет offer
				in := &WebrtcSignalingOfferRequest{}
				out := &WebrtcSignalingOfferResponse{}

				if err := in.FromJson(msg, &in); err != nil {
					rd.initiatorChannel <- out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse)
					rd.Unlock()
					continue
				}

				//--------------------------------Проверка пользователя
				/*if err := auth.CheckUser(in.MainRequestClass, in); err != nil {
					rd.initiatorChannel <- out.MakeWrongResponse(err.Error(), err.Success)
					rd.Unlock()
					continue
				}*/

				//--------------------------------Валидация in.Offer
				if in.Offer == "" {
					rd.initiatorChannel <- out.MakeWrongResponse("Параметр 'offer' отсутствует или задан некорректно", api_root_classes.ErrorResponse)
					rd.Unlock()
					continue
				}

				out.Offer = in.Offer
				rd.responderChannel <- out.MakeResponse(out, "")
				rd.status = WAIT_ANSWER
			} else if device == rd.responderDevice {
				// Статус WAIT_OFFER, респондер пытается отправить answer. Ошибка
				rd.responderChannel <- []byte("CANNOT_SEND_ANSWER_BEFORE_OFFER")
			}
		} else if rd.status == WAIT_ANSWER {
			if device == rd.initiatorDevice && string(msg) == "offer" {
				// Статус WAIT_ANSWER, инициатор пытается дублировать offer. Ошибка
				rd.initiatorChannel <- []byte("CANNOT_SEND_OFFER_TWICE")
			} else if device == rd.responderDevice && string(msg) == "answer" {
				// Статус WAIT_ANSWER, респондер отправляет answer. Верный сценарий. Смена статуса.
				rd.initiatorChannel <- msg
				rd.status = WAIT_ICE_CANDIDATES
			}
		} else if rd.status == WAIT_ICE_CANDIDATES {
			if device == rd.initiatorDevice && string(msg) == "ice_candidates" {
				// Инициатор отправил ic, передаем ic респондеру
				rd.responderChannel <- msg
			} else if device == rd.responderDevice && string(msg) == "ice_candidates" {
				// Респондер отправил ic, передаем ic инициатору
				rd.initiatorChannel <- msg
			} else if device == rd.initiatorDevice && string(msg) == "FINISH_SEND_ICE_CANDIDATES" {
				// Инициатор закончил отправлять ic, ставим соответствующий флаг.
				rd.isFinishSendIceCandidatesInitiator = true
			} else if device == rd.responderDevice && string(msg) == "FINISH_SEND_ICE_CANDIDATES" {
				// Респондер закончил отправлять ic, ставим соответствующий флаг.
				rd.isFinishSendIceCandidatesResponder = true
			}

			if rd.isFinishSendIceCandidatesInitiator && rd.isFinishSendIceCandidatesResponder {
				// Обмен кандидатами окончен, закрываем соединение.
				rd.Unlock()
				return
			}
		}
		// TODO: Стоит ли ограничить отправку кандидатов после флажка финиша отправки кандидатов? (Да, запретить)
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

// Отправка ошибки в виде Json и закрытие вебсокет-подключения.
func errorJsonResponse(conn *websocket.Conn, errorResponse []byte) {
	if err := conn.WriteMessage(websocket.TextMessage, errorResponse); err != nil {
		log.Println(err)
	}
	if err := conn.Close(); err != nil {
		log.Println(err)
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
