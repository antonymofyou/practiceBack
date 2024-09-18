package message

import (
	"telegram/internal/websockets/dto/updates"
)

// SubscribeOnNewMessageRequest comm
type SubscribeOnNewMessageRequest struct {
	updates.SubscribeRequest
}

// SubscribeOnNewMessageResponse comm
type SubscribeOnNewMessageResponse struct {
	updates.SubscribeResponse
}

// SubscribeOnChannelMessageRequest comm
type SubscribeOnChannelMessageRequest struct {
	updates.SubscribeRequest
}

// SubscribeOnChannelMessageResponse comm
type SubscribeOnChannelMessageResponse struct {
	updates.SubscribeResponse
}
