package messages

import "telegram/internal/websockets/dto"

type Message struct {
	ID     string `json:"id"`
	Text   string `json:"text"`
	Date   string `json:"date"`
	FromID string `json:"fromID"`
	ToID   string `json:"toID"`
}

// GetHistoryRequest запрос на получение истории сообщений с пользователем
type GetHistoryRequest struct {
	dto.Request
	Domain     string `json:"domain"`     // Domain: username пользователя/чата/канала историю сообщений от которого нужно получить
	ID         string `json:"id"`         // ID: telegram id пользователя/чата/канала историю сообщений от которого нужно получить
	Phone      string `json:"phone"`      // Phone: телефон пользователя историю сообщений от которого нужно получить
	Limit      string `json:"limit"`      // Limit: лимит на количество получаемых сообщений
	OffsetID   string `json:"offsetID"`   // OffsetID: уникальный идентификатор сообщения с которого начнется отсчет
	OffsetDate string `json:"offsetDate"` // OffsetDate: количество секунд от Unix времени
}

// GetHistoryResponse ответ, содержащий массив сообщений
type GetHistoryResponse struct {
	dto.Response
	Messages []Message `json:"message"` // Messages: массив сообщений
}
