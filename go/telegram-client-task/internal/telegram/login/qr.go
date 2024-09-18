package login

import (
	"context"
	"fmt"
	"github.com/gotd/td/telegram"
	"github.com/gotd/td/telegram/auth/qrlogin"
	"github.com/gotd/td/tg"
	"github.com/jedib0t/go-pretty/v6/text"
	"github.com/skip2/go-qrcode"
	"strings"
)

func QR(ctx context.Context, client *telegram.Client, dispatcher tg.UpdateDispatcher) error {
	if _, err := client.QR().Auth(ctx, qrlogin.OnLoginToken(dispatcher), func(ctx context.Context, token qrlogin.Token) error {
		qr, err := qrcode.New(token.URL(), qrcode.Medium)
		if err != nil {
			return err
		}

		code := qr.ToSmallString(false)
		lines := strings.Count(code, "\n")

		fmt.Print(code)
		fmt.Print(strings.Repeat(text.CursorUp.Sprint(), lines))
		return nil
	}); err != nil {
		return err
	}

	fmt.Print(text.EraseLine.Sprint())

	if status, err := client.Auth().Status(ctx); status.Authorized {
		fmt.Printf(
			"Login successfully!\n"+
				"ID: %v,\n"+
				"Username: %s,\n"+
				"First name: %s,\n"+
				"Last name: %s,\n"+
				"Status: %s,\n"+
				"Premium: %v,\n",
			status.User.ID,
			status.User.Username,
			status.User.FirstName,
			status.User.LastName,
			status.User.Status,
			status.User.Premium,
		)
	} else {
		return err
	}

	return nil
}
