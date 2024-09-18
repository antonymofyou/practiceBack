package getenv

import (
	"fmt"
	"os"
	"strconv"
	"strings"
)

// GetEnv является вспомогательной функцией для чтения переменной окружения в string.
// Паникует, если значение переменной окружения пустое
func GetEnv(key string) string {
	if value, exists := os.LookupEnv(key); exists {
		return value
	}

	panic(fmt.Sprintf("Environment variable %s is not set", key))
}

// AsInt является вспомогательной функцией для чтения переменной окружения в int.
// Паникует, если значение переменной окружения пустое
func AsInt(key string) int {
	stringValue := GetEnv(key)
	if value, err := strconv.Atoi(stringValue); err == nil {
		return value
	}

	panic(fmt.Sprintf("Environment variable %s is not int", key))
}

// AsFloat64 является вспомогательной функцией для чтения переменной окружения в float64.
// Паникует, если значение переменной окружения пустое
func AsFloat64(key string) float64 {
	stringValue := GetEnv(key)
	if value, err := strconv.ParseFloat(stringValue, 64); err == nil {
		return value
	}

	panic(fmt.Sprintf("Environment variable %s is not float", key))
}

// AsBool является вспомогательной функцией для чтения переменной окружения в bool.
// Паникует, если значение переменной окружения пустое
func AsBool(key string) bool {
	stringValue := GetEnv(key)
	if value, err := strconv.ParseBool(stringValue); err == nil {
		return value
	}

	panic(fmt.Sprintf("Environment variable %s is not bool", key))
}

// AsSlice является вспомогательной функцией для чтения переменной окружения в string slice.
// Паникует, если значение переменной окружения пустое
func AsSlice(key string, sep string) []string {
	stringValue := GetEnv(key)
	value := strings.Split(stringValue, sep)

	return value
}
