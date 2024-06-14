package websocket_room

import (
	"github.com/gorilla/websocket"
	"log"
)

type Device struct {
	ID int
}

// Тип для хранения подключенных юзеров.
type UserList map[*User]bool

type User struct {
	device      *Device
	connection  *websocket.Conn // вебсокетное подключение
	manager     *Manager        // менеджер для организации работы с юзерами (встраивается в каждого юзера)
	msgChannel  chan []byte     // канал для передачи сообщений подключению
	isInitiator bool            // true, если создал новую комнату
	isResponder bool            // true, если подключился к существующей комнате
	inRoomNow   *Room           // ссылка на комнату, в которой находится в текущий момент
}

// Конструктор юзера.
func NewUser(conn *websocket.Conn, manager *Manager, userDevice *Device) *User {
	return &User{
		connection: conn,
		manager:    manager,
		device:     userDevice,
		msgChannel: make(chan []byte),
	}
}

// Бесконечный цикл (вызывается как горутина). Слушает сообщения, которые приходят от конкретного юзера.
// Распределяет сообщения по нужным чатам.
func (u *User) readMessages() {
	defer func() {
		u.manager.removeUser(u)
		u.manager.removeUserFromRoom(u.manager.rooms[u.inRoomNow.ID], u)
		u.manager.removeRoomIfNoConnections(u.inRoomNow)
	}()

	for {
		_, payload, err := u.connection.ReadMessage()

		if err != nil {
			log.Println("user with device", u.device.ID, "leaved room with ID", u.inRoomNow.ID)
			break
		}
		// отправляет сообщение в нужную комнату.
		u.manager.sendMessageToRoom(u.inRoomNow.ID, payload)
		log.Println("msg in room", u.inRoomNow.ID, "| device", u.device.ID, ":", string(payload))
	}
}

// Бесконечный цикл (вызывается как горутина). Получает сообщение из канала юзера, отправляет юзеру сообщение.
func (u *User) writeMessages() {
	defer func() {
		u.manager.removeUser(u)
		u.manager.removeUserFromRoom(u.manager.rooms[u.inRoomNow.ID], u)
		u.manager.removeRoomIfNoConnections(u.inRoomNow)
	}()

	for {
		select {
		// case, когда метод менеджера sendMessageToRoom отправил сообщение в канал
		case message, ok := <-u.msgChannel:
			if !ok {
				if err := u.connection.WriteMessage(websocket.CloseMessage, nil); err != nil {
					log.Println("Connection closed:", err)
				}
				return
			}

			if err := u.connection.WriteMessage(websocket.TextMessage, message); err != nil {
				log.Printf("failed to send message: %v", err)
			}
		}
	}
}
