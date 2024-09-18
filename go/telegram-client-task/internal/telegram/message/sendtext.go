package message

import (
	"context"
	"github.com/gotd/td/telegram/message"
)

func SendText(ctx context.Context, sender *message.Sender, username string, text string) error {
	if _, err := sender.Resolve(username).Text(ctx, text); err != nil {
		return err
	}

	return nil
}
