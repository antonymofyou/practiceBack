package config

// Cfg экспортируемая переменная, которая инициализируется в main.go
var Cfg *Config

type Config struct {
	AppSecretKeyInt    int
	AppSecretKeyString string
	DbUser             string
	DbPassword         string
	DbHost             string
	DbName             string
	Addr               string
}

// NewConfig функция для создания экземпляра конфига
func NewConfig() *Config {
	return &Config{
		AppSecretKeyInt:    0,
		AppSecretKeyString: "",
		DbUser:             "",
		DbPassword:         "",
		DbHost:             "",
		DbName:             "",
		Addr:               ":9990",
	}
}
