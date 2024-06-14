package websocket_room

import (
	"fmt"
	"github.com/gorilla/websocket"
	"log"
	"net/http"
	"strconv"
	"sync"
)

// Объявление менеджера вебсокетов для работы с ним в main
var WebsocketManager *Manager

// Инициализация апгрейдера для работы с вебсокетами
var (
	websocketUpgrader = websocket.Upgrader{
		ReadBufferSize:  1024,
		WriteBufferSize: 1024,
	}
)

// Manager для управления вебсокетами.
// Имеет доступ ко всем юзерам и комнатам, выполняет операции над ними
type Manager struct {
	users        UserList // информация о текущих подключениях к серверу
	rooms        RoomList // информация о текущих активных комнатах
	sync.RWMutex          // Включает в себя структуру RWMutex, чтобы лочить потоки
}

// Конструктор менеджера
func NewManager() *Manager {
	return &Manager{
		users: make(UserList),
		rooms: make(RoomList),
	}
}

// Обработчик эндпоинта go/room. Принимает GET-запрос от юзера с параметром device.
// Создает юзеров, распределяет их по комнатам, вызывает горутины для слушания и распределения сообщений.
func (m *Manager) RoomHandler(w http.ResponseWriter, r *http.Request) {
	log.Println("New connection")
	// Обработка случая, когда нет device в параметре GET-запроса
	if !r.URL.Query().Has("device") {
		log.Println("error! try connecting without param device")
		w.WriteHeader(400)
		return
	}
	// Создание вебсокет-подключения
	conn, err := websocketUpgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Println(err)
		return
	}
	userDevice := Device{}
	userDevice.ID, _ = strconv.Atoi(r.URL.Query().Get("device"))
	// Создание юзера и добавление его в users у менеджера.
	user := NewUser(conn, m, &userDevice)
	m.addUser(user)
	// Проверка, есть ли свободная комната. Определяет юзера в первую попавшуюся комнату, если в ней есть одно место.
	if ok, room := m.hasFreeRoom(); ok {
		m.addUserInRoom(room, user)
		user.isInitiator = false
		user.isResponder = true
		userLog(user)
		fmt.Println("-----connected to room-----")
		roomLog(room)
	} else {
		// Если свободных комнат нет, создается новая комната. В нее определяется юзер.
		room := NewRoom(roomCounter)
		roomCreatedLog(room)
		roomCounter++

		m.addUserInRoom(room, user)
		m.addRoom(room)

		user.isInitiator = true
		user.isResponder = false

		userLog(user)
		fmt.Println("-----created room-----")
		roomLog(room)
	}

	// Вызов горутины для слушания сообщений от этого юзера
	go user.readMessages()

	// Вызов горутины для отправки сообщений этому юзеру
	go user.writeMessages()
}

// Метод для добавления юзера в users
func (m *Manager) addUser(user *User) {
	m.Lock()
	defer m.Unlock()

	m.users[user] = true
}

// Метод удаления юзера из users, если вебсокетное соединение потеряно.
func (m *Manager) removeUser(user *User) {
	m.Lock()
	defer m.Unlock()
	if _, ok := m.users[user]; ok {
		if err := user.connection.Close(); err != nil {
			log.Println(err)
		}
		delete(m.users, user)
	}
}

// Метод добавления комнаты в rooms.
func (m *Manager) addRoom(room *Room) {
	m.rooms[room.ID] = room
}

// Метод добавления юзера в определенную комнату
func (m *Manager) addUserInRoom(room *Room, user *User) {
	m.Lock()
	defer m.Unlock()

	room.users[user.device] = user
	user.inRoomNow = room
}

// Метод удаления юзера из определенной комнаты.
func (m *Manager) removeUserFromRoom(room *Room, user *User) {
	m.Lock()
	defer m.Unlock()

	if _, ok := room.users[user.device]; ok {
		delete(room.users, user.device)
	}
}

// Удаление комнаты, если в ней не осталось юзеров.
func (m *Manager) removeRoomIfNoConnections(room *Room) {
	m.Lock()
	defer m.Unlock()

	if _, ok := m.rooms[room.ID]; ok {
		if len(room.users) == 0 {
			log.Println("Deleted room with ID", room.ID)
			delete(m.rooms, room.ID)
		}
	}
}

// Метод проверки, есть ли на данный момент свободная комната.
// Если нашлась комната, в которой участников меньше константы maxUsersInRoom, возвращает true и ссылку на эту комнату.
func (m *Manager) hasFreeRoom() (bool, *Room) {
	m.Lock()
	defer m.Unlock()

	for _, room := range m.rooms {
		if len(room.users) < maxUsersInRoom {
			return true, room
		}
	}
	return false, nil
}

// Метод распространения сообщения по комнате с определенным ID.
func (m *Manager) sendMessageToRoom(roomID int, payload []byte) {
	m.Lock()
	defer m.Unlock()
	// Итерируем текущих юзеров в комнате, в канал каждого юзера отправляем сообщение.
	for _, client := range m.rooms[roomID].users {
		client.msgChannel <- payload
	}
}
