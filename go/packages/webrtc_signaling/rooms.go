package webrtc_signaling

import (
	"encoding/json"
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
		userDevice, err := strconv.Atoi(r.URL.Query().Get("device"))

		// Валидация девайса
		if err != nil {
			log.Println("no correct device", r.URL.Query().Get("device"))
			return
		}

		// как будто ид комнаты из БД получили
		roomID := getRoomID(userDevice)
		// если roomID не будет найден в БД, сделать return, вернуть response ошибку

		// Тормозят ли мютексы СРАЗУ ВСЕ ПОТОКИ?
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
			log.Println("error: OTHER_DEVICE")
			return
		}

		// Если комната на сервере еще не создана (первое подключение), создаем ее.
		if _, ok := rooms[roomID]; !ok {
			rooms[roomID] = make(ReceiveChannels)
			log.Println("room", roomID, "was created")
		}

		// Установка вебсокет-соединения
		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Println(err)
			return
		}

		// Создания канала для доставки сообщения вебсокет-соединению
		userChannel := make(chan []byte)

		// Привязка канала юзера к конкретной комнате
		rooms[roomID][userDevice] = userChannel
		log.Println("Device", userDevice, "connected to room", roomID)

		// Запуск горутин на слушание и отправку сообщений
		go readMessagesFromWebsocket(conn, rooms[roomID], func() {
			removeDeviceFromRoom(rooms[roomID], userDevice, mu, conn)
			removeRoomIfNoConnections(rooms, roomID, mu)
		}) // from webSocket
		go writeMessagesToWebsocket(conn, rooms[roomID][userDevice], func() {
			removeDeviceFromRoom(rooms[roomID], userDevice, mu, conn)
			removeRoomIfNoConnections(rooms, roomID, mu)
		})
	}
}

func readMessagesFromWebsocket(conn *websocket.Conn, channels ReceiveChannels, exitFunc func()) {
	// Какие могут возникнуть ошибки?
	// Как справляться с паниками?
	// Классификации ошибок

	// Отложенный вызов функции, закрывающей вебсокет-соединение и удаляющей юзера из комнаты.
	defer exitFunc()

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			return // разорвано соединение, конец горутины
		}
		for _, userChannel := range channels {
			userChannel <- msg
		}
	}
}

func writeMessagesToWebsocket(conn *websocket.Conn, userChannel chan []byte, exitFunc func()) {
	defer exitFunc()

	// Потокобезопасное чтение мапы

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
func removeRoomIfNoConnections(rooms map[int]ReceiveChannels, roomID int, mu *sync.Mutex) {
	// Потокобезопасность
	mu.Lock()
	defer mu.Unlock()
	// проверка, существует ли еще эта комната
	if _, ok := rooms[roomID]; !ok {
		return // возврат из функции, если комната была удалена в другой горутине
	}
	// Удаление комнаты, если в ней больше не осталось подключенных девайсов
	if len(rooms[roomID]) == 0 {
		delete(rooms, roomID)
		log.Println("room", roomID, "was deleted (no connections)")
	}
}
