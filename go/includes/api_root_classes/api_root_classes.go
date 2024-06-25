package api_root_classes

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"nasotku/includes/config"
	"nasotku/includes/utils"
	"reflect"
	"slices"
	"sort"
	"strings"
)

// Т.к. в Go "классы" именуются структурами, когда в коде что-то называется "классом", то с технической стороны понимается именно структура

// варианты поля success в ответе
const (
	SuccessResponse = "1"  // все хорошо
	ErrorResponse   = "0"  // произошла ошибка
	LogoutResponse  = "-1" // разлогин пользователя
)

//--------------------------------API_root_class (корнейвой класс, который встраивают все остальные классы)

type API_root_class struct {
	Signature string `json:"signature,omitempty"`
}

// CalcSignature Вычисление подписи на основе data (необходимо передавать struct/map) и nasotkuToken
func (a *API_root_class) CalcSignature(data interface{}, nasotkuToken string) string {

	// переводим data в мапу
	// способ через сериализацию и десерилизацию самый простой и удобный, т.к. все вложенные (встроенные) структуры будут на одном уровне
	// альтернатива - использование рефлекции (пакет reflex), но в этом случае нужно решать эту задачу через рекурсию, т.к. поля втраиваемых структур находятся не на одном уровне (например, будет не data.signature, а data.MainRequestClass.Signature и это все нужно правильно распарсить. Через пакет json это решается на много проще)
	var v map[string]interface{}
	dataJson, _ := json.Marshal(data) // сериализация
	json.Unmarshal(dataJson, &v)      // десериализация (все поля будут привязаны к мапе v

	// массив (слайс) типов, которые принимают участие в формировании подписи
	includeTypes := []reflect.Kind{reflect.String, reflect.Int, reflect.Float64, reflect.Float32}

	// функция для получения отсортированных ключей мапы
	getSortedMapKeys := func(elements map[string]interface{}) []string {
		var keys []string
		for key := range elements {
			keys = append(keys, key)
		}

		sort.Strings(keys)

		return keys
	}

	// составляем массив (слайс) строк, которые будут участвовать в формировании подписи
	// здесь будут хранится значения полей мапы, которую мы ранее распарсили
	var fields []string
	for _, vKey := range getSortedMapKeys(v) { // итерируемся не по самому v, а по отсортированным ключам, дабы подпись формировалась корректно (сразу сортировка мапы работает некорректно, поэтому здесь отсортированы ключи и представлены в виде массива)
		value := v[vKey]
		field := reflect.ValueOf(value) // получаем экземпляр рефлекции значения поля
		vType := field.Kind()           // получаем его тип

		// поле signature не участвует в формировании подписи - пропускаем
		if vKey == "signature" {
			continue
		}

		// если тип поля НЕ находится в массиве (слайсе) типов, которые используются для формирования подписи - пропускаем
		if !slices.Contains(includeTypes, vType) {
			continue
		}

		// добавляем провалидированное значение текущего поля в конечный список значений
		fields = append(fields, fmt.Sprintf("%v", value))
	}

	concat := strings.Join(fields, "")                                                                       // объединяем все строки в одну
	concat += fmt.Sprintf("%d%s%s", config.Cfg.AppSecretKeyInt, config.Cfg.AppSecretKeyString, nasotkuToken) // добавляем к получившейся строке в конец соль из int и string значений, а также переданный в функцию токен

	// хэширование
	hash := sha256.Sum256([]byte(concat))   // перевод concat в байты, формирование из байтов хэша
	signCalc := hex.EncodeToString(hash[:]) // перевод байтов в строку

	return signCalc
}

// CheckSignature проверка соответствия текущей подписи, заданной в текущем классе, с той подписью, которая будет сформирована на основе переданных data (struct/map) и nasotkuToken
func (a *API_root_class) CheckSignature(data interface{}, nasotkuToken string) bool {
	return a.Signature == a.CalcSignature(data, nasotkuToken)
}

// FromJson чтение запроса (r) и десериализация его в переданную структуру v
// bytes - это байты для декодирования их по формату JSON
// структура v здесь - это класс запроса. При успешном выполнении к значениям этого класса будут присвоены поля из запроса
func (a *API_root_class) FromJson(bytes []byte, v interface{}) error {
	acceptFieldsNames := utils.GetStructFieldNames(v)     // получение полей переданной структуры (если в запросе будет присутствовать что-то лишнее, чего нет в структуре v, то будет ошибка wrong object)
	if !slices.Contains(acceptFieldsNames, "signature") { // отдельно добавляем signature, т.к. у API_Root_class.Signature стоит параметр omitempty, из-за чего, если оно пустое, то при сериализации/десерилизации пропадает)
		acceptFieldsNames = append(acceptFieldsNames, "signature")
	}

	// десериализуем тело запроса
	var vInterface interface{}
	if err := json.Unmarshal(bytes, &vInterface); err != nil {
		return errors.New("error parse json 1: " + err.Error())
	}
	requestFields, _ := vInterface.(map[string]interface{}) // переводим распаршенную структуру в мапу

	// валидация запроса (нет ли там лишних полей)
	for key, _ := range requestFields {
		if !slices.Contains(acceptFieldsNames, key) { // если в запросе есть поле, которого нет в переданном классе запроса - возвращаем ошибку
			return errors.New("wrong object")
		}
	}

	// еще раз выполняем десерилизацию, дабы присвоить все поля запроса к переданной структуре
	vInterfaceJson, _ := json.Marshal(vInterface)              // сериализация
	if err := json.Unmarshal(vInterfaceJson, &v); err != nil { // десерилизация
		return errors.New("error parse json 2: " + err.Error())
	}

	return nil
}

// MakeResponse создание JSON ответа
// v - класс ответа, который нужно перевести в строку
func (a *API_root_class) MakeResponse(v interface{}, nasotkuToken string) []byte {
	a.makeSignature(v, nasotkuToken)
	return a.toJson(v)
}

// MakeWrongResponse создание JSON ответа с ошибкой
// message - текст
// success - статус
func (a *API_root_class) MakeWrongResponse(message string, success string) []byte {
	out := MainResponseClass{ // экземпляр класса ответа
		API_root_class: API_root_class{},
		Success:        success,
		Message:        message,
	}

	out.Signature = "" // задаем подпись как пустую строку, а т.к. у Signature есть параметр 'omitempty' в классе - она не попадает в конечный ответ при сериализации

	return a.toJson(out)
}

// makeSignature установка нового значения для Signature
func (a *API_root_class) makeSignature(data interface{}, nasotkuToken string) {
	a.Signature = a.CalcSignature(data, nasotkuToken)
}

// toJson сериализация v в JSON
func (*API_root_class) toJson(v interface{}) []byte {
	bytes, err := json.Marshal(v) // сериализация
	if err != nil {
		return []byte{}
	}

	return bytes
}

//--------------------------------MainResponseClass

type MainResponseClass struct {
	API_root_class
	Success string `json:"success"`
	Message string `json:"message"`
}

//--------------------------------MainRequestClass

type MainRequestClass struct {
	API_root_class
	Device string `json:"device"`
}

//--------------------------------Доп. функции

// RequestBodyToBytes функция переводит тело запроса в байты. Она написана, дабы логику перевода в байты не писать в кадом методе API
// body - передается из структуры http.Request (r.Body)
func RequestBodyToBytes(body io.ReadCloser) ([]byte, error) {
	defer body.Close()

	bytes, err := io.ReadAll(body)
	if err != nil {
		return nil, err
	}

	return bytes, nil
}
