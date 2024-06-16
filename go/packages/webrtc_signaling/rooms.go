package webrtc_signaling

import (
	"encoding/json"
	"fmt"
	"github.com/gorilla/websocket"
	"log"
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
		// получение девайса из запроса
		userDevice, _ := strconv.Atoi(r.URL.Query().Get("device"))
		roomID := getRoomID(userDevice) // как будто ид комнаты из БД получили
		// обеспечение потокобезопасности
		mu.Lock()
		defer mu.Unlock()
		// Если пользователь уже есть в комнате, не создаем новое подключение
		if _, ok := rooms[roomID][userDevice]; ok {
			jsonResponse, _ := json.Marshal(struct {
				Status string `json:"status"`
			}{"OTHER_DEVICE"})
			if _, err := w.Write(jsonResponse); err != nil {
				log.Println(err)
			}
			return
		}
		// Если комната на сервере еще не создана (первое подключение), создаем ее.
		if _, ok := rooms[roomID]; !ok {
			rooms[roomID] = make(ReceiveChannels)
		}
		// Установка вебсокет-соединения
		conn, _ := upgrader.Upgrade(w, r, nil)
		// Создания канала для доставку сообщения вебсокет-соединению
		userChannel := make(chan []byte)
		// Привязка канала юзера к конкретной комнате
		rooms[roomID][userDevice] = userChannel

		log.Println("Device", userDevice, "connected to room", roomID)

		go readMessagesFromWebsocket(conn, rooms[roomID], userDevice, mu) // from webSocket
		go writeMessagesToWebsocket(conn, rooms[roomID], userDevice, mu)
	}
}

func readMessagesFromWebsocket(conn *websocket.Conn, channels ReceiveChannels, userDevice int, mu *sync.Mutex) {
	// Отложенный вызов функции, закрывающей вебсокет-соединение и удаляющей юзера из комнаты.
	defer removeDeviceFromRoom(channels, userDevice, mu, conn)

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			return // разорвано соединение, конец горутины
		}
		fmt.Println(msg)
		for _, userChannel := range channels {
			userChannel <- msg
		}
	}
}

func writeMessagesToWebsocket(conn *websocket.Conn, channels ReceiveChannels, userDevice int, mu *sync.Mutex) {
	defer removeDeviceFromRoom(channels, userDevice, mu, conn)

	// Потокобезопасное чтение мапы
	mu.Lock()
	userChannel := channels[userDevice]
	mu.Unlock()

	for {
		select {
		case msg, ok := <-userChannel:
			if !ok {
				return // разорвано соединение
			}
			if err := conn.WriteMessage(websocket.TextMessage, msg); err != nil {
				log.Println(err)
			}
		}
	}
}

func removeDeviceFromRoom(room ReceiveChannels, userDevice int, mu *sync.Mutex, conn *websocket.Conn) {
	// Закрываем вебсокет-соединение
	if err := conn.Close(); err != nil {
		// если есть ошибка, значит, функция уже отработала.
		return
	}

	// Обеспечиваем потокобезопасность
	mu.Lock()
	defer mu.Unlock()

	delete(room, userDevice) // удаление юзера из комнаты.
	log.Println(userDevice, "deleted from room (disconnected)")
}

// В каком случае удаляем комнату?
func removeRoom() {

}
