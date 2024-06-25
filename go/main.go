package main

import (
	"fmt"
	"log"
	"nasotku/includes/config"
	"nasotku/packages/users"
	"nasotku/packages/webrtc_signaling"
	"nasotku/packages/ws"
	"net/http"
)

func main() {
	// инициализация конфига
	config.Cfg = config.NewConfig()

	// подключение к базе данных
	/*var err error
	db.Db, err = db.NewDb()
	if err != nil {
		log.Fatal("Не удалось подключиться к базе данных:", err.Error())
	}
	defer db.Db.Close()*/

	// инициализация роутов

	// пример роута с использованием анонимной функции
	http.HandleFunc("/go", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintf(w, "Ты на главной странице")
	})

	// пример роута с использованием именованной функции
	http.HandleFunc("/go/users/get", users.Get)

	// роут вебсокета (эхо-метод, что пришло, то и вернул)
	http.HandleFunc("/go/ws/echo", ws.Echo)

	// роут вебсокета (создание комнат)
	// device - обязательный параметр запроса
	http.HandleFunc("/go/room", webrtc_signaling.RoomHandler())

	// запуск обработки запросов
	log.Println("[APP] Start")
	if err := http.ListenAndServe(config.Cfg.Addr, nil); err != nil {
		log.Fatal(err)
	}
}
