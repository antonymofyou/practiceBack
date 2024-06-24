package db

import (
	"database/sql"
	"github.com/go-sql-driver/mysql"
	"nasotku/includes/config"
)

// Db экспортируемая переменная, которая инициализируется в main.go
var Db *sql.DB

// NewDb функция для создания экземпляра базы данных
func NewDb() (*sql.DB, error) {
	dbConfig := mysql.Config{ // формируем конфиг для базы
		User:                 config.Cfg.DbUser,
		Passwd:               config.Cfg.DbPassword,
		Net:                  "tcp",
		Addr:                 config.Cfg.DbHost,
		DBName:               config.Cfg.DbName,
		AllowNativePasswords: true,
	}

	// переводим конфиг в строку и подключаемся
	db, err := sql.Open("mysql", dbConfig.FormatDSN())
	if err != nil {
		return nil, err
	}

	// пингуем базу, дабы проверить, что с подключением все нормально
	if err := db.Ping(); err != nil {
		return nil, err
	}

	return db, nil
}
