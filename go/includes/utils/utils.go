package utils

import "encoding/json"

// GetStructFieldNames функция для получения всех полей структуры (только публичных)
func GetStructFieldNames(v interface{}) []string {
	var structKeyValue map[string]interface{}
	fieldsJson, _ := json.Marshal(v)
	json.Unmarshal(fieldsJson, &structKeyValue)

	var structKeys []string
	for key := range structKeyValue {
		structKeys = append(structKeys, key)
	}

	return structKeys
}
