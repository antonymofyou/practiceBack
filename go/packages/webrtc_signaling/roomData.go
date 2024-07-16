package webrtc_signaling

import (
	"errors"
	"log"
	"nasotku/includes/db"
	"sync"
)

// статусы комнаты
const (
	RoomStatusWaitSecondUser    = "WAIT_SECOND_USER"
	RoomStatusWaitOffer         = "WAIT_OFFER"
	RoomStatusWaitAnswer        = "WAIT_ANSWER"
	RoomStatusFinishReceiveData = "FINISH_RECEIVE_DATA"
)

// Мапа rooms хранит указатели на rd, так как rd - тяжелая структура, будет затратно каждый раз копировать ее
// из функции в функцию. Тем более в горутине мы изменяем статус уже созданной rd, поэтому указатель необходим.
type roomDataStorage struct {
	sync.Mutex                   // потокобезопасность для rooms
	rooms      map[int]*roomData // ключ - id комнаты
}

type roomInfoFromDB struct {
	ID              int    `sql:"room_id"`
	initiatorDevice string `sql:"initiator_device"`
	responderDevice string `sql:"responder_device"`
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

// функция получения информации о комнате из БД
func getRoomByDevice(device string) (*roomInfoFromDB, error) {
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
