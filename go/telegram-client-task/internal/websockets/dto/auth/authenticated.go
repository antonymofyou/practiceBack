package auth

import "telegram/internal/websockets/dto"

// IsAuthenticatedRequest структура запроса аутентификации
type IsAuthenticatedRequest struct {
	dto.Request
}

// IsAuthenticatedResponse структура ответа аутентификации
type IsAuthenticatedResponse struct {
	dto.Response
	IsAuthenticated string `json:"isAuthenticated"` // IsAuthenticated: "1" - если пользователь аутентифицирован, "0" - если нет
}
