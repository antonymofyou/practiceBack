package webrtc_signaling

import (
	"encoding/json"
	"errors"
	"github.com/gorilla/websocket"
	"log"
	"nasotku/includes/api_root_classes"
	"nasotku/includes/auth"
	"nasotku/includes/db"
	"net/http"
	"strconv"
	"sync"
)

//--------------------------------Классы запросов

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
	DataTypeRole                    = "ROLE"
	DataTypeTrainInfo               = "TRAIN_INFO"
	DataTypeOffer                   = "OFFER"
	DataTypeAnswer                  = "ANSWER"
	DataTypeIceCandidates           = "ICE_CANDIDATE"
	DataTypeFinishSendIceCandidates = "FINISH_SEND_ICE_CANDIDATES"
)

//--------------------------------Комнаты

// Мапа rooms хранит указатели на rd, так как rd - тяжелая структура, будет затратно каждый раз копировать ее
// из функции в функцию. Тем более в горутине мы изменяем статус уже созданной rd, поэтому указатель необходим.
type roomDataStorage struct {
	sync.Mutex                   // потокобезопасность для rooms
	rooms      map[int]*roomData // ключ - id комнаты
}

type roomInfoFromDB struct {
	ID              int
	userInitiator   int
	userResponder   int
	initiatorDevice string
	responderDevice string
}

type trainInfoFromDB struct {
	userQuestioner     int
	userAnswerer       int
	userQuestionerName string
	userAnswererName   string
	question           string
	answer             string
}

// Структура для хранения информации о комнате
type roomData struct {
	*roomInfoFromDB
	sync.Mutex
	initiatorChannel                   chan []byte
	responderChannel                   chan []byte
	initiatorConnected                 bool
	responderConnected                 bool
	isFinishSendIceCandidatesInitiator bool
	isFinishSendIceCandidatesResponder bool
	status                             string
}

// статусы комнаты
const (
	RoomStatusWaitSecondUser    = "WAIT_SECOND_USER"
	RoomStatusWaitOffer         = "WAIT_OFFER"
	RoomStatusWaitAnswer        = "WAIT_ANSWER"
	RoomStatusFinishReceiveData = "FINISH_RECEIVE_DATA"
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

//--------------------------------Обработка запроса

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
	currentUser, errCheckUser := auth.CheckUser(in, in)
	if errCheckUser != nil {
		errorJsonResponse(conn, out.MakeWrongResponse(errCheckUser.Error(), errCheckUser.Success))
		return
	}

	// получаем из базы информацию о тренировке и комнате
	trainInfo, roomInfoDB, err := getTrainAndRoomByUser(currentUser.UserVkId)
	if err != nil {
		errorJsonResponse(conn, out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse))
		return
	}

	// проверка на OTHER_DEVICE
	if roomInfoDB.initiatorDevice != in.Device && roomInfoDB.responderDevice != in.Device {
		errorJsonResponse(conn, out.MakeWrongResponse("тренировка начата, но текущее устройство не равено тому, с которого она начата (OTHER_DEVICE)", api_root_classes.ErrorResponse))
		return
	}

	rds.Lock()
	defer rds.Unlock()

	// проверяем, есть ли уже такая комната в rds
	if _, ok := rds.rooms[roomInfoDB.ID]; !ok {
		// комнаты в rds еще нет, создаем ее.
		rds.rooms[roomInfoDB.ID] = newRoomData(roomInfoDB)
		// если комнаты в rds нет, значит, этот юзер подключился первым и будет ждать второго
		rds.rooms[roomInfoDB.ID].status = RoomStatusWaitSecondUser
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
		rds.rooms[roomInfoDB.ID].status = RoomStatusWaitOffer

		// когда оба пользователя подключились, отправляем им данные о тренировке

		// сообщение для спрашивающего в тренировке
		trainForQuestioner := map[string]string{
			"status":             "ANSWERING",
			"userQuestioner":     strconv.Itoa(trainInfo.userQuestioner),
			"userAnswerer":       strconv.Itoa(trainInfo.userAnswerer),
			"userNameQuestioner": trainInfo.userQuestionerName,
			"userNameAnswerer":   trainInfo.userAnswererName,
			"role":               "questioner",
			"questionText":       trainInfo.question,
			"questionAnswer":     trainInfo.answer,
		}
		trainJsonForQuestioner, _ := json.Marshal(trainForQuestioner)
		outTrainForQuestioner := &WebrtcSignalingResponse{
			MainResponseClass: &api_root_classes.MainResponseClass{
				API_root_class: &api_root_classes.API_root_class{Signature: ""},
			},
			DataType: DataTypeTrainInfo,
			Data:     string(trainJsonForQuestioner),
		}
		outTrainForQuestionerBytes := outTrainForQuestioner.MakeResponse(outTrainForQuestioner, "")

		// сообщение для ответчика тренировки
		trainForAnswerer := map[string]string{
			"status":             "ANSWERING",
			"userQuestioner":     strconv.Itoa(trainInfo.userQuestioner),
			"userAnswerer":       strconv.Itoa(trainInfo.userAnswerer),
			"userNameQuestioner": trainInfo.userQuestionerName,
			"userNameAnswerer":   trainInfo.userAnswererName,
			"role":               "answerer",
			"questionText":       trainInfo.question,
		}
		trainJsonForAnswerer, _ := json.Marshal(trainForAnswerer)
		outTrainForAnswerer := &WebrtcSignalingResponse{
			MainResponseClass: &api_root_classes.MainResponseClass{
				API_root_class: &api_root_classes.API_root_class{Signature: ""},
			},
			DataType: DataTypeTrainInfo,
			Data:     string(trainJsonForAnswerer),
		}
		outTrainForAnswererBytes := outTrainForQuestioner.MakeResponse(outTrainForAnswerer, "")

		// находим канал для спрашивающего
		var userQuestionerChannel chan []byte
		if rds.rooms[roomInfoDB.ID].userInitiator == trainInfo.userQuestioner {
			userQuestionerChannel = rds.rooms[roomInfoDB.ID].initiatorChannel
		} else {
			userQuestionerChannel = rds.rooms[roomInfoDB.ID].responderChannel
		}

		// находим канал для отвечающего
		var userAnswererChannel chan []byte
		if rds.rooms[roomInfoDB.ID].userInitiator == trainInfo.userAnswerer {
			userAnswererChannel = rds.rooms[roomInfoDB.ID].initiatorChannel
		} else {
			userAnswererChannel = rds.rooms[roomInfoDB.ID].responderChannel
		}

		// отложенный вызов отправки данных. Данные отправятся в канал после запуска горутин в конце хендлера
		defer func() {
			userQuestionerChannel <- outTrainForQuestionerBytes
			userAnswererChannel <- outTrainForAnswererBytes
		}()
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
	var userRole string
	if in.Device == rds.rooms[roomInfoDB.ID].initiatorDevice {
		userRole = "initiator"
	} else if in.Device == rds.rooms[roomInfoDB.ID].responderDevice {
		userChannel = rds.rooms[roomInfoDB.ID].responderChannel
		userRole = "responder"
	}

	// информируем пользователя о том, какая у него роль
	outRole := &WebrtcSignalingResponse{
		MainResponseClass: &api_root_classes.MainResponseClass{
			API_root_class: &api_root_classes.API_root_class{Signature: ""},
		},
		DataType: DataTypeRole,
		Data:     userRole,
	}
	outRoleBytes := outRole.MakeResponse(outRole, "")

	// отложенный вызов отправки данных. Данные отправятся в канал после иницилазции горутин в конце хендлера
	defer func() {
		userChannel <- outRoleBytes
	}()

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

		rd.Lock()

		in := &WebrtcSignalingRequest{
			MainRequestClass: &api_root_classes.MainRequestClass{
				API_root_class: &api_root_classes.API_root_class{Signature: ""},
			},
		}
		out := &WebrtcSignalingResponse{
			MainResponseClass: &api_root_classes.MainResponseClass{
				API_root_class: &api_root_classes.API_root_class{Signature: ""},
			},
		}

		// Распаршиваем пришедший json
		if err := in.FromJson(msg, &in); err != nil {
			userChannel <- out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse)
			rd.Unlock()
			continue
		}

		// проверка пользователя
		if _, err := auth.CheckUser(in.MainRequestClass, in); err != nil {
			userChannel <- out.MakeWrongResponse(err.Error(), err.Success)
			rd.Unlock()
			continue
		}

		if in.DataType == DataTypeOffer {
			if device != rd.initiatorDevice {
				userChannel <- out.MakeWrongResponse("Отправлять offer может только инициатор", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			if rd.status != RoomStatusWaitOffer {
				userChannel <- out.MakeWrongResponse("Отправлять offer можно только в статусе WAIT_OFFER", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			out.DataType = in.DataType
			out.Data = in.Data
			rd.responderChannel <- out.MakeResponse(out, "")
			rd.status = RoomStatusWaitAnswer
		} else if in.DataType == DataTypeAnswer {
			if device != rd.responderDevice {
				userChannel <- out.MakeWrongResponse("Отправлять answer может только респондер", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			if rd.status != RoomStatusWaitAnswer {
				userChannel <- out.MakeWrongResponse("Отправлять answer можно только в статусе WAIT_ANSWER", api_root_classes.ErrorResponse)
				rd.Unlock()
				continue
			}

			out.DataType = in.DataType
			out.Data = in.Data
			rd.initiatorChannel <- out.MakeResponse(out, "")
			rd.status = RoomStatusFinishReceiveData
		} else if in.DataType == DataTypeIceCandidates {
			if rd.status == RoomStatusWaitSecondUser {
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
		} else if in.DataType == DataTypeFinishSendIceCandidates {
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

// функция получения информации о комнате из БД
func getTrainAndRoomByUser(userVkId int) (*trainInfoFromDB, *roomInfoFromDB, error) {
	// получаем сессию пользователя по device (пользователь может быть как инициатором, так и респондером)
	rows, err := db.Db.Query(`
		SELECT wrtc_sessions.id AS wrtc_session_id, wrtc_sessions.user_initiator, wrtc_sessions.user_responder, wrtc_sessions.user_initiator_device, wrtc_sessions.user_responder_device,
			train_zachet_verbal_chat_users.user_questioner, train_zachet_verbal_chat_users.user_answerer, zachet_train.question, zachet_train.answer
		FROM train_zachet_verbal_chat_users
		INNER JOIN zachet_train ON zachet_train.id = train_zachet_verbal_chat_users.question_id
		INNER JOIN wrtc_sessions ON wrtc_sessions.id = train_zachet_verbal_chat_users.wrtc_session_id
		WHERE user_questioner = ?
        	OR user_answerer = ?;
		`,
		userVkId,
		userVkId,
	)
	defer rows.Close()
	if err != nil {
		return nil, nil, errors.New("ошибка БД: " + err.Error())
	}

	if !rows.Next() { // проверяем наличие результата
		return nil, nil, errors.New("тренировка для пользователя не найдена")
	}

	// биндим результат последовательно к каждому полю структуры (для этого передаем указатель на поле)
	roomInfo := &roomInfoFromDB{}
	trainInfo := &trainInfoFromDB{}
	rows.Scan(
		&roomInfo.ID, &roomInfo.userInitiator, &roomInfo.userResponder, &roomInfo.initiatorDevice, &roomInfo.responderDevice,
		&trainInfo.userQuestioner, &trainInfo.userAnswerer, &trainInfo.question, &trainInfo.answer,
	)

	return trainInfo, roomInfo, nil
}

// функция получения информации о тренировке из БД
func getTrainInfoBySessionId(device string) (*roomInfoFromDB, error) {
	// получаем сессию пользователя по device (пользователь может быть как инициатором, так и респондером)
	rows, err := db.Db.Query(
		"SELECT `id`, `user_initiator_device`, `user_responder_device` FROM `wrtc_sessions` WHERE `user_initiator_device` = ? OR `user_responder_device` = ? LIMIT 1;",
		device,
		device,
	)
	if err != nil {
		return nil, errors.New("ошибка БД: " + err.Error())
	}
	defer rows.Close()

	if !rows.Next() { // проверяем наличие результата
		return nil, errors.New("комната для переданного device не найдена")
	}

	// биндим результат последовательно к каждому полю структуры (для этого передаем указатель на поле)
	roomInfo := &roomInfoFromDB{}
	rows.Scan(&roomInfo.ID, &roomInfo.initiatorDevice, &roomInfo.responderDevice)

	return roomInfo, nil
}

func newRoomData(roomInfo *roomInfoFromDB) *roomData {
	return &roomData{
		roomInfoFromDB:   roomInfo,
		initiatorChannel: make(chan []byte),
		responderChannel: make(chan []byte),
	}
}

// корректное закрытие каналов текущей комнаты
func (rd *roomData) closeConnections() {
	rd.Lock()
	defer rd.Unlock()

	if rd.initiatorConnected {
		// Если инициатор еще не отключен, отключаем его, ставим флажок
		close(rd.initiatorChannel)
		rd.initiatorConnected = false
		log.Println(rd.initiatorDevice, "disconnected")
	}
	if rd.responderConnected {
		// Если респондер еще не отключен, отключаем его, ставим флажок
		close(rd.responderChannel)
		rd.responderConnected = false
		log.Println(rd.responderDevice, "disconnected")
	}
}
