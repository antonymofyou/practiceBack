package config

import (
	"log"
	"os"
	"strconv"
)

// Cfg экспортируемая переменная, которая инициализируется в main.go
var Cfg *Config

type Config struct {
	AppSecretKeyInt    int
	AppSecretKeyString string
	DbUser             string
	DbPassword         string
	DbHost             string
	DbName             string
	ServerAddr         string
}

// NewConfig функция для создания экземпляра конфига
func NewConfig() *Config {
	appSecretKeyInt, err := strconv.Atoi(os.Getenv("APP_SECRET_KEY_INT"))
	if err != nil {
		log.Fatal("Некорректно задан APP_SECRET_KEY_INT, приведение к int не может быть выполнено")
	}

	return &Config{
		AppSecretKeyInt:    appSecretKeyInt,
		AppSecretKeyString: os.Getenv("APP_SECRET_KEY_STRING"),
		DbUser:             os.Getenv("DB_USER"),
		DbPassword:         os.Getenv("DB_PASSWORD"),
		DbHost:             os.Getenv("DB_HOST"),
		DbName:             os.Getenv("DB_NAME"),
		ServerAddr:         os.Getenv("SERVER_ADDR"),
	}
}
