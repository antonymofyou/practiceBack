package chat

import (
	"fmt"
	"github.com/gorilla/websocket"
)

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

// Chat тип. Каждый чат хранит слайс подключений.
type Chat struct {
	ID          int
	Connections map[string]*websocket.Conn // Ключ - удаленный адрес клиента.
}

// Хранение чатов в памяти.
var chats = make(map[int]*Chat)

// Счетчик чатов для присвоения id (временное решение, пока нет БД)
var chatCounter = 0

// Метод проходит по подключениям чата и каждому клиенту отправляет сообщение.
func (c *Chat) sendMessage(messageType int, message []byte) {
	for _, client := range c.Connections {
		if err := client.WriteMessage(messageType, message); err != nil {
			fmt.Println(err)
			return
		}
	}
}

// Метод отслеживает поступающие сообщения. Если пришло сообщение, оно отправляется всем клиентам (sendMessage)
func (c *Chat) listenAndSendMessages(connectionOwner *websocket.Conn) {
	for {
		// Read message from browser
		msgType, msg, err := connectionOwner.ReadMessage()
		if err != nil {
			return
		}
		fmt.Printf("Chat with ID %d | %s sent: %s\n", c.ID, connectionOwner.RemoteAddr(), string(msg))
		c.sendMessage(msgType, msg)
	}
}

// Метод, который удаляет чат из памяти, если в нем не осталось подключений (клиентов)
func (c *Chat) deleteIfNoConnections() {
	if len(c.Connections) == 0 {
		fmt.Println("Chat with ID", c.ID, "was deleted (no connections)")
		delete(chats, c.ID)
	}
}
