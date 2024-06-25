package webrtc_signaling

import (
	"log"
	"sync"
)

// Статусы для roomData
const (
	WAIT_SECOND_USER    = "WAIT_SECOND_USER"
	WAIT_OFFER          = "WAIT_OFFER"
	WAIT_ANSWER         = "WAIT_ANSWER"
	WAIT_ICE_CANDIDATES = "WAIT_ICE_CANDIDATES"
)

type roomDataStorage struct {
	sync.Mutex                   // потокобезопасность для rooms
	rooms      map[int]*roomData // ключ - id комнаты
}

type roomInfoFromDB struct {
	ID              int `sql:"room_id"`
	initiatorDevice int `sql:"initiator_device"`
	responderDevice int `sql:"responder_device"`
}

// Структура для хранения информации о комнате
type roomData struct {
	*roomInfoFromDB
	sync.Mutex
	initiatorChannel                   chan []byte
	responderChannel                   chan []byte
	initiatorDisconnected              bool
	responderDisconnected              bool
	isFinishSendIceCandidatesInitiator bool
	isFinishSendIceCandidatesResponder bool
	status                             string
}

// функция получения информации о комнате из БД
func getRoom(userDevice int) (*roomInfoFromDB, error) {
	return &roomInfoFromDB{
		ID:              1,
		initiatorDevice: 1,
		responderDevice: 2,
	}, nil
}

func newRoomData(roomInfo *roomInfoFromDB) *roomData {
	return &roomData{
		roomInfoFromDB:   roomInfo,
		initiatorChannel: make(chan []byte),
		responderChannel: make(chan []byte),
	}
}

func (rd *roomData) closeConnections() {
	rd.Lock()
	defer rd.Unlock()

	if !rd.initiatorDisconnected {
		// Если инициатор еще не отключен, отключаем его, ставим флажок
		close(rd.initiatorChannel)
		rd.initiatorDisconnected = true
		log.Println(rd.initiatorDevice, "disconnected")
	}
	if !rd.responderDisconnected {
		// Если респондер еще не отключен, отключаем его, ставим флажок
		close(rd.responderChannel)
		rd.responderDisconnected = true
		log.Println(rd.responderDevice, "disconnected")
	}
}