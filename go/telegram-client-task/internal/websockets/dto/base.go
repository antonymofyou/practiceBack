package dto

// Request базовая структура запроса
type Request struct {
	Method string `json:"method"` // Method: название метода, указывающий на handler и структуру запроса
}

// Response базовая структура ответа
type Response struct {
	Success string `json:"success"` // Success: "1" - если ответ запрос успешно выполнен, "0" - если нет
	Message string `json:"message"` // Message: пуст если ответ успешен, в противном случае содержит сообщение об ошибке
}
