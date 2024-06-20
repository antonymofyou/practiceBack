package webrtc_signaling

import (
	"log"
	"sync"
)

type roomDataStorage struct {
	sync.Mutex                   // потокобезопасность для rooms
	rooms      map[int]*roomData // ключ - id комнаты
}

// Добавляет информацию о комнате, если комната еще не создана
func (r *roomDataStorage) addRoomData(data *roomData) {
	r.Lock()
	defer r.Unlock()

	if _, ok := r.rooms[data.ID]; ok {
		return
	}
	r.rooms[data.ID] = data
	log.Println("created room data id", data.ID)
}

// Структура для хранения информации о комнате
type roomData struct {
	ID              int `sql:"room_id"`
	initiatorDevice int `sql:"initiator_device"`
	responderDevice int `sql:"responder_device"`
	// еще что-то для хранения оффера (информации)
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

func (r *roomData) setOffer(responderChannel chan []byte) {
	// логика для создания оффера внутри комнаты
	r.Offer = "Offer to user"
	responderChannel <- []byte(r.Offer)
	log.Println("Offer created")
}

func (r *roomData) setAnswer(initiatorChannel chan []byte) {
	// логика принятия оффера
	r.Answer = "answer"
	initiatorChannel <- []byte(r.Answer)
	log.Println("Answer was sent")
}
