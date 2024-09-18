package handlers

import (
	"context"
	"github.com/gotd/td/tg"
)

// OnNewMessageHandler обрабатывает новые сообщения в private chat/basic group
func OnNewMessageHandler(ctx context.Context, e tg.Entities, update *tg.UpdateNewMessage) error {
	//msg := query.Message
	//switch msg.(type) {
	//case *tg.MessageEmpty: // messageEmpty#90a6ca84
	//	msg = query.Message.(*tg.MessageEmpty)
	//case *tg.Message: // message#94345242
	//	msg = query.Message.(*tg.Message)
	//case *tg.MessageService: // messageService#2b085862
	//	msg = query.Message.(*tg.MessageService)
	//default:
	//	panic(msg)
	//}
	//
	//switch recipient := msg.PeerID.(type) {
	//case *tg.PeerUser: // peerUser#59511722
	//	log.Println("USER: ", recipient.UserID)
	//case *tg.PeerChat: // peerChat#36c6019a
	//	log.Println("CHAT: ", recipient.ChatID)
	//case *tg.PeerChannel: // peerChannel#a2a5371e
	//	log.Println("CHANNEL: ", recipient.ChannelID)
	//default:
	//	log.Println("DEFAULT: ", recipient)
	//}
	//
	//switch sender := msg.FromID.(type) {
	//case *tg.PeerUser: // peerUser#59511722
	//	log.Println("USER: ", sender.UserID)
	//case *tg.PeerChat: // peerChat#36c6019a
	//	log.Println("CHAT: ", sender.ChatID)
	//case *tg.PeerChannel: // peerChannel#a2a5371e
	//	log.Println("CHANNEL: ", sender.ChannelID)
	//default:
	//	log.Println("DEFAULT: ", sender)
	//}
	//
	////log.Printf("[MESSAGE] %s [DATE] %s [RECIPIENT] %v [SENDER] %v", msg.Message, time.Unix(int64(msg.Date), 0), recipient.UserID)
	//
	return nil
}

// OnNewChannelMessageHandler обрабатывает новые сообщения в channel/supergroup
func OnNewChannelMessageHandler(ctx context.Context, e tg.Entities, update *tg.UpdateNewChannelMessage) error {
	return nil
}
