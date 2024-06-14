package websocket_room

const maxUsersInRoom = 2

// Мапа id комнаты: ссылка на комнату (тип)
type RoomList map[int]*Room

// Счетчик комнат для присвоения каждой новой комнате уникального ID
var roomCounter = 0

// Структура Комната. Имеет свой ID и мап подключенных к комнате юзеров.
type Room struct {
	ID    int
	users map[*Device]*User
}

// Конструктор комнаты.
func NewRoom(id int) *Room {
	return &Room{
		ID:    id,
		users: make(map[*Device]*User),
	}
}
