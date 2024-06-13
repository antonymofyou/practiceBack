package chat

import (
	"fmt"
	"github.com/gorilla/websocket"
	"net/http"
	"strconv"
)

func CreateChatHandler(w http.ResponseWriter, r *http.Request) {
	// Создаем подключение
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		fmt.Println(err)
	}
	newChatID := chatCounter // Получаем ID чата из счетчиков чатов.
	chatCounter++
	// Получение удаленного адреса подключения
	userAddr := conn.RemoteAddr().String()
	// Создание нового чата в памяти
	newChat := Chat{
		ID:          newChatID,
		Connections: make(map[string]*websocket.Conn),
	}
	// Добавление нового чата в map. id уникальный
	chats[newChatID] = &newChat
	// Добавляем созданное подключение в подключения нового чата (первый клиент)
	chats[newChatID].Connections[userAddr] = conn
	fmt.Println("Created chat", newChatID)
	// Слушаем сообщения и рассылаем их всем клиентам (подключениям)
	chats[newChatID].listenAndSendMessages(conn)
	// Если подключение разорвано, закрываем соедение с клиентом и удаляем его из подключений.
	defer func() {
		if err := conn.Close(); err != nil {
			fmt.Println(err)
		}
		fmt.Println(conn.RemoteAddr(), "(creator) leaved chat with ID", newChatID)
		delete(chats[newChatID].Connections, userAddr)
		// Чат удалится из памяти, если в нем больше нет подключений
		chats[newChatID].deleteIfNoConnections()
	}()
}

func ConnectChatHandler(w http.ResponseWriter, r *http.Request) {
	currentID, err := strconv.Atoi(r.URL.Query().Get("id")) // получение id чата из параметра GET-запроса.
	// Проверка на валидность ID из GET-запроса
	if err != nil {
		w.WriteHeader(400)
		fmt.Println("No valid ID: ", r.URL.Query().Get("id"))
		return
	}
	// Проверка, существует ли чат с id из запроса.
	if _, ok := chats[currentID]; !ok {
		w.WriteHeader(400)
		fmt.Println("Try to connect to no existing chat with id", currentID)
		return
	}
	// Если id валидно и чат с таким id существует, создаем подключение.
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		fmt.Println(err)
		return
	}
	userAddr := conn.RemoteAddr().String() // получение удаленного адреса клиента
	// Добавление нового подключения в текущий чат
	chats[currentID].Connections[userAddr] = conn
	fmt.Println(conn.RemoteAddr(), "connected to chat", currentID)
	// Слушаем сообщения с рассылаем их всем клиентам (подключениям)
	chats[currentID].listenAndSendMessages(conn)
	// Если подключение разорвано, закрываем соедение с клиентом и удаляем его из подключений.
	defer func() {
		if err := conn.Close(); err != nil {
			fmt.Println(err)
		}
		delete(chats[currentID].Connections, userAddr)
		fmt.Println(conn.RemoteAddr(), "leaved chat with ID", currentID)
		chats[currentID].deleteIfNoConnections()
	}()
}
