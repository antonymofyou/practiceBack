package main

import (
	"context"
	"fmt"
	"github.com/gotd/contrib/middleware/floodwait"
	"github.com/gotd/contrib/middleware/ratelimit"
	"github.com/gotd/td/telegram"
	"github.com/gotd/td/telegram/peers"
	"github.com/gotd/td/telegram/query"
	"github.com/gotd/td/telegram/query/messages"
	"github.com/gotd/td/telegram/updates"
	"github.com/gotd/td/telegram/updates/hook"
	"github.com/gotd/td/tg"
	"github.com/joho/godotenv"
	"golang.org/x/time/rate"
	"log"
	"os"
	"os/signal"
	"telegram/internal/config"
	"telegram/internal/telegram/login"
	"time"
)

func main() {
	// Создание переменных окружения из указанного .env файла
	err := godotenv.Load()
	if err != nil {
		log.Fatal("Ошибка при загрузке .env файла", err)
	}

	// Создание и заполнение config из окружения
	cfg := config.NewConfig()
	cfg.FromEnv()

	ctx, cancel := signal.NotifyContext(context.Background(), os.Interrupt)
	defer cancel()

	// Dispatcher содержит обработчики событий обновления
	dispatcher := tg.NewUpdateDispatcher()
	gaps := updates.New(updates.Config{
		Handler: dispatcher,
	})

	// Обработчик FLOOD_WAIT, который будет автоматически повторять запрос
	waiter := floodwait.NewWaiter().WithCallback(func(ctx context.Context, wait floodwait.FloodWait) {
		fmt.Println("Получен FLOOD_WAIT. Повторение попытки после", wait.Duration)
	})

	// Заполнение client options
	options := telegram.Options{
		UpdateHandler: gaps,
		Middlewares: []telegram.Middleware{
			// Установка gaps обработчика обновлений
			hook.UpdateHook(gaps.Handle),
			// Установка обработчика FLOOD_WAIT для автоматического ожидания и повторной попытки запроса
			waiter,
			// Установка общих ограничений частоты запросов для уменьшения вероятности возникновения FLOOD_WAIT ошибок
			ratelimit.New(rate.Every(time.Millisecond*100), 5),
		},
	}

	// Создание telegram client
	client := telegram.NewClient(cfg.Telegram.AppId, cfg.Telegram.AppHash, options)

	// Создание peer manager
	manager := peers.Options{}.Build(client.API())

	// Обработчик события new channel message
	dispatcher.OnNewChannelMessage(func(ctx context.Context, e tg.Entities, update *tg.UpdateNewChannelMessage) error {
		return nil
	})

	// Обработчик события new message
	dispatcher.OnNewMessage(func(ctx context.Context, e tg.Entities, update *tg.UpdateNewMessage) error {
		switch msg := update.Message.(type) {
		case *tg.MessageEmpty: // messageEmpty#90a6ca84
			msg = update.Message.(*tg.MessageEmpty)
			switch recipient := msg.PeerID.(type) {
			case *tg.PeerUser: // peerUser#59511722
				log.Println("USER: ", recipient.UserID)
			case *tg.PeerChat: // peerChat#36c6019a
				log.Println("CHAT: ", recipient.ChatID)
			case *tg.PeerChannel: // peerChannel#a2a5371e
				log.Println("CHANNEL: ", recipient.ChannelID)
			default:
				log.Println("DEFAULT: ", recipient)
			}
		case *tg.Message: // message#94345242
			msg = update.Message.(*tg.Message)
			switch recipient := msg.PeerID.(type) {
			case *tg.PeerUser: // peerUser#59511722
				log.Println("USER: ", recipient.UserID)
			case *tg.PeerChat: // peerChat#36c6019a
				log.Println("CHAT: ", recipient.ChatID)
			case *tg.PeerChannel: // peerChannel#a2a5371e
				log.Println("CHANNEL: ", recipient.ChannelID)
			default:
				log.Println("DEFAULT: ", recipient)
			}

			switch sender := msg.FromID.(type) {
			case *tg.PeerUser: // peerUser#59511722
				log.Println("USER: ", sender.UserID)
			case *tg.PeerChat: // peerChat#36c6019a
				log.Println("CHAT: ", sender.ChatID)
			case *tg.PeerChannel: // peerChannel#a2a5371e
				log.Println("CHANNEL: ", sender.ChannelID)
			default:
				log.Println("DEFAULT: ", sender)
			}
		case *tg.MessageService: // messageService#2b085862
			msg = update.Message.(*tg.MessageService)
			switch recipient := msg.PeerID.(type) {
			case *tg.PeerUser: // peerUser#59511722
				log.Println("USER: ", recipient.UserID)
			case *tg.PeerChat: // peerChat#36c6019a
				log.Println("CHAT: ", recipient.ChatID)
			case *tg.PeerChannel: // peerChannel#a2a5371e
				log.Println("CHANNEL: ", recipient.ChannelID)
			default:
				log.Println("DEFAULT: ", recipient)
			}

			switch sender := msg.FromID.(type) {
			case *tg.PeerUser: // peerUser#59511722
				log.Println("USER: ", sender.UserID)
			case *tg.PeerChat: // peerChat#36c6019a
				log.Println("CHAT: ", sender.ChatID)
			case *tg.PeerChannel: // peerChannel#a2a5371e
				log.Println("CHANNEL: ", sender.ChannelID)
			default:
				log.Println("DEFAULT: ", sender)
			}
		default:
			panic(msg)
		}

		//log.Printf("[MESSAGE] %s [DATE] %s [RECIPIENT] %v [SENDER] %v", msg.Message, time.Unix(int64(msg.Date), 0), recipient.UserID)

		return nil
	})

	if err = run(ctx, waiter, client, dispatcher, manager, gaps); err != nil {
		panic(err)
	}
}

func run(ctx context.Context, waiter *floodwait.Waiter, client *telegram.Client, dispatcher tg.UpdateDispatcher, manager *peers.Manager, gaps *updates.Manager) error {
	return waiter.Run(ctx, func(ctx context.Context) error {
		return client.Run(ctx, func(ctx context.Context) error {
			err := login.QR(ctx, client, dispatcher)
			if err != nil {
				return err
			}

			//raw := tg.NewClient(client)

			err = manager.Init(ctx)
			if err != nil {
				return err
			}

			peer, err := manager.Resolve(ctx, "@USER")
			//err = sendText(ctx, client)
			//if err != nil {
			//	return err
			//}
			raw := tg.NewClient(client)
			//
			//gd := query.GetDialogs(raw)
			//count, err := gd.Count(ctx)
			//if err != nil {
			//	return err
			//}
			//
			//fmt.Println("Count of your dialogs is ", count)
			//user, err := manager.ResolveUserID(ctx, peer.ID())
			//if err != nil {
			//	return err
			//}

			err = query.Messages(raw).GetHistory(peer.InputPeer()).ForEach(ctx,
				func(ctx context.Context, elem messages.Elem) error {
					msg, ok := elem.Msg.(*tg.Message)
					msgDate := msg.GetDate()

					if !ok {
						return nil
					}
					fmt.Println(msg.Message, time.Unix(int64(msgDate), 0))

					return nil
				})
			if err != nil {
				return err
			}

			// Получение информации о пользователе
			user2, err := client.Self(ctx)
			if err != nil {
				return err
			}

			err = gaps.Run(ctx, client.API(), user2.ID, updates.AuthOptions{
				OnStart: func(ctx context.Context) {
					log.Println("Gaps started")
				},
			})
			if err != nil {
				return err
			}

			return nil
		})
	})
}
