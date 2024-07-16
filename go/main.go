package main

import (
	"github.com/joho/godotenv"
	"log"
	"nasotku/includes/config"
	"nasotku/includes/db"
	"nasotku/includes/routes"
	"net/http"
)

func main() {
	// загружаем переменные окружения
	err := godotenv.Load(".env")
	if err != nil {
		log.Fatal("Произошла ошибка загрузки переменных окружения:", err.Error())
	}

	// инициализация конфига
	config.Cfg = config.NewConfig()

	// подключение к базе данных
	db.Db, err = db.NewDb()
	if err != nil {
		log.Fatal("Не удалось подключиться к базе данных:", err.Error())
	}
	defer db.Db.Close()

	// инициализация роутов
	routes.InitRoutes()

	// запуск обработки запросов
	log.Println("[APP] Start")
	if err := http.ListenAndServe(config.Cfg.ServerAddr, nil); err != nil {
		log.Fatal(err)
	}
}
