package webrtc_signaling

import (
	"sync"
)

type roomDataStorage struct {
	sync.Mutex                   // потокобезопасность для rooms
	rooms      map[int]*roomData // ключ - id комнаты
}

// Структура для хранения информации о комнате
type roomData struct {
	ID              int `sql:"room_id"`
	initiatorDevice int `sql:"initiator_device"`
	responderDevice int `sql:"responder_device"`
	// еще что-то для хранения оффера (информации)
	// TODO: Продумать статусы, при которых меняется логика работы с офферами и ансерами.
	Offer  string `json:"offer"`
	Answer string `json:"answer"`
}

// функция получения информации о комнате из БД
func getRoom(userDevice int) *roomData {

	return &roomData{
		ID:              1,
		initiatorDevice: 1,
		responderDevice: 2,
	}
}
