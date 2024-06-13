package main

import (
	"fmt"
	"log"
	"nasotku/includes/config"
	"nasotku/packages/chat"
	"nasotku/packages/ws"
	"net/http"
)

func main() {
	// инициализация конфига
	config.Cfg = config.NewConfig()

	// инициализация роутов

	// пример роута с использованием анонимной функции
	http.HandleFunc("/go", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintf(w, "Ты на главной странице")
	})

	// роут вебсокета (эхо-метод, что пришло, то и вернул)
	http.HandleFunc("/go/ws/echo", ws.Echo)

	// роут вебсокета (создание нового чата, хендшейк)
	http.HandleFunc("/chat/create", chat.CreateChatHandler)

	// роут вебсокета (подключение к чату, если он уже существует)
	// передавать параметр id (отсчет с нуля)
	http.HandleFunc("/chat/connect", chat.ConnectChatHandler)

	// запуск обработки запросов
	log.Println("[APP] Start")
	if err := http.ListenAndServe(config.Cfg.Addr, nil); err != nil {
		log.Fatal(err)
	}
}
