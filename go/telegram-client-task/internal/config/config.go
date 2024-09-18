package config

import (
	"encoding/json"
	"io"
	"log"
	"os"
	"telegram/pkg/getenv"
)

// AppConfig структура конфигурации приложения
type AppConfig struct {
	Addr               string `json:"addr"`               // ADDR
	AppSecretKeyInt    int    `json:"appSecretKeyInt"`    // APP_SECRET_KEY_INT
	AppSecretKeyString string `json:"appSecretKeyString"` // APP_SECRET_KEY_STRING
}

// DatabaseConfig структура конфигурации базы данных
type DatabaseConfig struct {
	User     string `json:"user"`     // DB_USER
	Password string `json:"password"` // DB_PASSWORD
	Host     string `json:"host"`     // DB_HOST
	Name     string `json:"name"`     // DB_NAME
}

// TelegramConfig структура конфигурации телеграмма
type TelegramConfig struct {
	AppId       int    `json:"appId"`       // TG_APP_ID (получаются здесь - https://my.telegram.org/apps)
	AppHash     string `json:"appHash"`     // TG_APP_HASH (получаются здесь - https://my.telegram.org/apps)
	SessionFile string `json:"sessionFile"` // TG_SESSION_FILE - путь к файлу в который сохраняется сессия
}

// Config структура конфигурации
type Config struct {
	App      AppConfig
	Database DatabaseConfig
	Telegram TelegramConfig
}

// NewConfig возвращает ссылку на структуру Config
func NewConfig() *Config {
	return &Config{}
}

// FromEnv заполняет структуру Config значениями из среды
func (config *Config) FromEnv() {
	// App
	config.App.Addr = getenv.GetEnv("APP_ADDR")
	config.App.AppSecretKeyInt = getenv.AsInt("APP_SECRET_KEY_INT")
	config.App.AppSecretKeyString = getenv.GetEnv("APP_SECRET_KEY_STRING")

	// Database
	config.Database.User = getenv.GetEnv("DB_USER")
	config.Database.Password = getenv.GetEnv("DB_PASSWORD")
	config.Database.Host = getenv.GetEnv("DB_HOST")
	config.Database.Name = getenv.GetEnv("DB_NAME")

	// Telegram
	config.Telegram.AppId = getenv.AsInt("TG_APP_ID")
	config.Telegram.AppHash = getenv.GetEnv("TG_APP_HASH")
	config.Telegram.SessionFile = getenv.GetEnv("TG_SESSION_FILE")
}

// FromJson заполняет структуру Config значениями из json
func (config *Config) FromJson(filename string) {
	file, err := os.Open(filename)
	if err != nil {
		log.Fatalf("Ошибка при открытии JSON файла: %s", err)
	}
	defer func(file *os.File) {
		err := file.Close()
		if err != nil {
			log.Fatalf("Ошибка при закрытии JSON файла: %s", err)
		}
	}(file)

	// Чтение содержимого файла
	bytes, err := io.ReadAll(file)
	if err != nil {
		log.Fatalf("Ошибка при чтении JSON файла: %s", err)
	}

	// Парсим JSON данные в структуру
	err = json.Unmarshal(bytes, &config)
	if err != nil {
		log.Fatalf("Ошибка при парсинге структуры из JSON: %s", err)
	}
}
