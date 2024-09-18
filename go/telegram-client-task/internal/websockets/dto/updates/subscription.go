package updates

import "telegram/internal/websockets/dto"

// SubscribeRequest базовая структура запроса подписки на событие
type SubscribeRequest struct {
	dto.Request
	Event string `json:"event"` // Название события на которое добавляется подписка
}

// SubscribeResponse базовая структура ответа подписки на событие
type SubscribeResponse struct {
	dto.Response
	Event          string `json:"event"`          // Название события на которое добавлена подписка
	SubscriptionID string `json:"subscriptionID"` // Уникальный идентификатор добавленной подписки
}

type UnsubscribeRequest struct {
	dto.Request
	SubscriptionID string `json:"subscriptionID"` // Уникальный идентификатор отмененной подписки
}
