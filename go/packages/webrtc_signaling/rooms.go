package webrtc_signaling

import (
	"fmt"
	"github.com/gorilla/websocket"
	"net/http"
	"strconv"
	"sync"
)

// ключ - device юзера
type ReceiveChannels map[int]chan []byte

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func getRoomID(device int) int {

	return 1
}

func RoomHandler(rooms map[int]ReceiveChannels, mu *sync.Mutex) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		// МЬЮТЕКСЫ СДЕЛАТЬ
		userDevice, _ := strconv.Atoi(r.URL.Query().Get("device"))
		conn, _ := upgrader.Upgrade(w, r, nil)
		roomID := getRoomID(userDevice) // как будто ид комнаты из БД получили
		// Добавить проверку на существование канала.
		// закрывать канал
		// случай, когда с 1 девайса 2+ подключения, закрыть старый, открыть новый
		mu.Lock()
		defer mu.Unlock()
		if rooms[roomID] == nil {
			rooms[roomID] = make(ReceiveChannels)
		}
		userChannel := make(chan []byte)
		rooms[roomID][userDevice] = userChannel
		go readMessagesFromWebsocket(conn, rooms[roomID]) // from webSocket
		go writeMessagesToWebsocket(conn, userChannel)
	}
}

// Сделать функцию закрытия соединения и управления каналами

func readMessagesFromWebsocket(conn *websocket.Conn, channels ReceiveChannels) {
	defer func() {
		err := conn.Close()
		if err != nil {
			return
		}
		// Нужно ли закрывать канал? Почитать.
	}()
	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			break // разорвано соединение, конец горутины
		}
		fmt.Println(msg)
		for _, userChannel := range channels {
			userChannel <- msg
		}
	}
}

func writeMessagesToWebsocket(conn *websocket.Conn, userChannel chan []byte) {
	defer func() {
		err := conn.Close()
		if err != nil {
			return
		}
	}()
	for {
		select {
		case msg, ok := <-userChannel:
			if !ok {
				return // разорвано соединение
			}
			conn.WriteMessage(websocket.TextMessage, msg)
		}
	}
}
