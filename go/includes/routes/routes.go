package routes

import (
	"fmt"
	"nasotku/packages/users"
	"nasotku/packages/webrtc_signaling"
	"nasotku/packages/ws"
	"net/http"
)

// InitRoutes функция для инициализации маршрутов пакета http
func InitRoutes() {

	// пример роута с использованием анонимной функции
	http.HandleFunc("/go/", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintf(w, "Ты на главной странице")
	})

	// пример роута с использованием именованной функции
	http.HandleFunc("/go/users/get", users.Get)

	// роут вебсокета (эхо-метод, что пришло, то и вернул)
	http.HandleFunc("/go/ws/echo", ws.Echo)

	// роут вебсокета для тренировки устного зачёта 1 на 1
	http.HandleFunc("/go/train_zachet_verbal_chat/ws", webrtc_signaling.RoomHandler)
}
