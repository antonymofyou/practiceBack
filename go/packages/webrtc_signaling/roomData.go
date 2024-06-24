package webrtc_signaling

import (
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

// TODO: мьютексы
/*type ReceiveChannels struct {
	sync.Mutex
	channels map[int]chan []byte
}*/

// Структура для хранения информации о комнате
type roomData struct {
	ID                                 int `sql:"room_id"`
	initiatorDevice                    int `sql:"initiator_device"`
	responderDevice                    int `sql:"responder_device"`
	isFinishSendIceCandidatesInitiator bool
	isFinishSendIceCandidatesResponder bool
	status                             string
}

// функция получения информации о комнате из БД
func getRoom(userDevice int) (*roomData, error) {
	return &roomData{
		ID:              1,
		initiatorDevice: 1,
		responderDevice: 2,
	}, nil
}
