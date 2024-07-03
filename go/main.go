package main

import (
	"log"
	"nasotku/includes/config"
	"nasotku/includes/db"
	"nasotku/includes/routes"
	"net/http"
)

func main() {
	// инициализация конфига
	config.Cfg = config.NewConfig()

	// подключение к базе данных
	var err error
	db.Db, err = db.NewDb()
	if err != nil {
		log.Fatal("Не удалось подключиться к базе данных:", err.Error())
	}
	defer db.Db.Close()

	// инициализация роутов
	routes.InitRoutes()

	// запуск обработки запросов
	log.Println("[APP] Start")
	if err := http.ListenAndServe(config.Cfg.Addr, nil); err != nil {
		log.Fatal(err)
	}
}
