package main

import (
	"fmt"
	"log"
	"nasotku/includes/config"
	"nasotku/packages/webrtc_signaling"
	"nasotku/packages/ws"
	"net/http"
)

func main() {
	// инициализация конфига
	config.Cfg = config.NewConfig()

	// инициализация комнат
	rooms := webrtc_signaling.RoomChannels{
		Rooms: make(map[int]webrtc_signaling.ReceiveChannels),
	}

	// инициализация роутов

	// пример роута с использованием анонимной функции
	http.HandleFunc("/go", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintf(w, "Ты на главной странице")
	})

	// роут вебсокета (эхо-метод, что пришло, то и вернул)
	http.HandleFunc("/go/ws/echo", ws.Echo)

	// роут вебсокета (создание комнат)
	// device - обязательный параметр запроса
	http.HandleFunc("/go/room", webrtc_signaling.RoomHandler(rooms, mutex))

	// запуск обработки запросов
	log.Println("[APP] Start")
	if err := http.ListenAndServe(config.Cfg.Addr, nil); err != nil {
		log.Fatal(err)
	}
}
