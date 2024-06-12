package ws

import (
	"github.com/gorilla/websocket"
	"log"
	"net/http"
)

var upgrader = websocket.Upgrader{
	CheckOrigin: func(r *http.Request) bool { // разрешаем запросы с любых доменов
		return true
	},
}

func Echo(w http.ResponseWriter, r *http.Request) {
	c, err := upgrader.Upgrade(w, r, nil) // отправляем upgrade ответ на upgrade запрос
	if err != nil {
		log.Print("[ERROR UPGRADE]", err)
		return
	}
	log.Println("[UPGRADE] Success")

	// логирование закрытия соединения
	c.SetCloseHandler(func(code int, text string) error {
		log.Printf("[CLOSE] code: %d, text: %s", code, text)
		return nil
	})

	defer c.Close() // закрытие соединения при выходе из функции
	for {           // бесконечный цикл
		messageType, message, err := c.ReadMessage() // читаем сообщение
		if err != nil {
			log.Println("[ERROR READ]", err)
			break
		}

		// логируем сообщение
		log.Printf("[MESSAGE] text: %s, type: %d", message, messageType)

		err = c.WriteMessage(messageType, message) // отвечаем
		if err != nil {
			log.Println("[ERROR WRITE]", err)
			break
		}
	}
}
