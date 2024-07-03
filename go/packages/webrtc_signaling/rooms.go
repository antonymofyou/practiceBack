package webrtc_signaling

import (
	"github.com/gorilla/websocket"
	"log"
	"nasotku/includes/api_root_classes"
	"nasotku/includes/auth"
	"net/http"
)

// класс запроса
type WebrtcSignalingRequest struct {
	*api_root_classes.MainRequestClass
	DataType string `json:"dataType"`
	Data     string `json:"data"`
}

// класс ответа
type WebrtcSignalingResponse struct {
	*api_root_classes.MainResponseClass
	DataType string `json:"dataType"`
	Data     string `json:"data"`
}

// типы данных, которые принимают и возвращают классы запросов (dataType)
const (
	DATA_TYPE_OFFER                      = "OFFER"
	DATA_TYPE_ANSWER                     = "ANSWER"
	DATA_TYPE_ICE_CANDIDATES             = "ICE_CANDIDATE"
	DATA_TYPE_FINISH_SEND_ICE_CANDIDATES = "FINISH_SEND_ICE_CANDIDATES"
)

// хранение информации о комнатах (аллокация сразу под 1000 элементов)
var rds = roomDataStorage{
	rooms: make(map[int]*roomData, 1000),
}

// структура для перехода на websocket соединение
var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func RoomHandler(w http.ResponseWriter, r *http.Request) {
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
	if err := auth.CheckUser(in, in); err != nil {
		errorJsonResponse(conn, out.MakeWrongResponse(err.Error(), err.Success))
		return
	}

	// как будто комнату из БД получили
	roomInfoDB, err := getRoomByDevice(in.Device)
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

func readMessagesFromWebsocket(conn *websocket.Conn, rd *roomData, device string, exitFunc func()) {
	// Отложенный вызов функции, закрывающей вебсокет-соединение и удаляющей юзера из комнаты.
	defer exitFunc()

	defer panicHandler("readMessagesFromWebsocket")

	// определяем канал текущего пользователя для удобного обращения к нему без if'ов
	var userChannel chan []byte
	if device == rd.initiatorDevice {
		userChannel = rd.initiatorChannel
	} else if device == rd.responderDevice {
		userChannel = rd.responderChannel
	}

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			return // разорвано соединение, конец горутины
		}
		// TODO: добавить проверку, не закрыты ли каналы

		rd.Lock()

		in := &WebrtcSignalingRequest{}
		out := &WebrtcSignalingResponse{}

		// Распаршиваем пришедший json
		if err := in.FromJson(msg, &in); err != nil {
			userChannel <- out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse)
			rd.Unlock()
			continue
		}

		// проверка пользователя
		if err := auth.CheckUser(in.MainRequestClass, in); err != nil {
			errorJsonResponse(conn, out.MakeWrongResponse(err.Error(), err.Success))
			return
		}

		if in.DataType == DATA_TYPE_OFFER {
			if device != rd.initiatorDevice {
				userChannel <- out.MakeWrongResponse("Отправлять offer может только инициатор", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			if rd.status != WAIT_OFFER {
				userChannel <- out.MakeWrongResponse("Отправлять offer можно только в статусе WAIT_OFFER", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			out.DataType = in.DataType
			out.Data = in.Data
			rd.responderChannel <- out.MakeResponse(out, "")
			rd.status = WAIT_ANSWER
		} else if in.DataType == DATA_TYPE_ANSWER {
			if device != rd.responderDevice {
				userChannel <- out.MakeWrongResponse("Отправлять answer может только респондер", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			if rd.status != WAIT_ANSWER {
				userChannel <- out.MakeWrongResponse("Отправлять answer можно только в статусе WAIT_ANSWER", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			out.DataType = in.DataType
			out.Data = in.Data
			rd.initiatorChannel <- out.MakeResponse(out, "")
			rd.status = FINISH_RECEIVE_DATA
		} else if in.DataType == DATA_TYPE_ICE_CANDIDATES {
			if rd.status == WAIT_SECOND_USER {
				userChannel <- out.MakeWrongResponse("Ожидание второго пользователя. Обмен данными невозможен", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			out.DataType = in.DataType
			out.Data = in.Data
			message := out.MakeResponse(out, "")

			// отправляем ic пользователю, противоположному текущему (в случае, если текущий пользователь еще не закончил отправку ic)
			if device == rd.initiatorDevice && !rd.isFinishSendIceCandidatesInitiator {
				rd.responderChannel <- message
			} else if device == rd.responderDevice && !rd.isFinishSendIceCandidatesResponder {
				rd.initiatorChannel <- message
			} else {
				userChannel <- out.MakeWrongResponse("Ты уже закончил отправку ice кандидатов", api_root_classes.ErrorResponse)
			}
		} else if in.DataType == DATA_TYPE_FINISH_SEND_ICE_CANDIDATES {
			if device == rd.initiatorDevice {
				rd.isFinishSendIceCandidatesInitiator = true
			} else if device == rd.responderDevice {
				rd.isFinishSendIceCandidatesResponder = true
			}

			// если оба пользователя закончили отправку ic - закрываем соединение
			if rd.isFinishSendIceCandidatesInitiator && rd.isFinishSendIceCandidatesResponder {
				rd.Unlock()
				return
			}
		} else {
			userChannel <- out.MakeWrongResponse("Пришел неизвестный dataType", api_root_classes.ErrorResponse)
		}

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
