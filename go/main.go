package main

import (
	"fmt"
	"log"
	"nasotku/includes/config"
	"nasotku/packages/websocket_room"
	"nasotku/packages/ws"
	"net/http"
)

func main() {
	// инициализация конфига
	config.Cfg = config.NewConfig()

	// Инициализация менеджера вебсокет-соединений
	websocket_room.WebsocketManager = websocket_room.NewManager()

	// инициализация роутов

	// пример роута с использованием анонимной функции
	http.HandleFunc("/go", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintf(w, "Ты на главной странице")
	})

	// роут вебсокета (эхо-метод, что пришло, то и вернул)
	http.HandleFunc("/go/ws/echo", ws.Echo)

	// роут вебсокета (создание комнат)
	// device - обязательный параметр GET-запроса
	http.HandleFunc("/go/room", websocket_room.WebsocketManager.RoomHandler)

	// запуск обработки запросов
	log.Println("[APP] Start")
	if err := http.ListenAndServe(config.Cfg.Addr, nil); err != nil {
		log.Fatal(err)
	}
}
