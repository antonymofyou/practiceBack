package auth

import "telegram/internal/websockets/dto"

// QrLoginRequest запрос url для генерации qr-кода
type QrLoginRequest struct {
	dto.Request
}

// QrLoginResponse ответ, содержащий url для генерации qr-кода
type QrLoginResponse struct {
	dto.Response
	Url string `json:"url"` // Url: ссылка (deeplink) из которой генерируется QR-код
}
