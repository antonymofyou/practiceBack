package websocket_room

import (
	"fmt"
	"log"
)

// Лог, отображающий поля Юзера
func userLog(u *User) {
	fmt.Printf(
		"USER: device: %d | addr: %s | is initiaror: %t | is responder: %t\nin room with ID now: %d\n",
		u.device.ID, u.connection.RemoteAddr().String(), u.isInitiator, u.isResponder, u.inRoomNow.ID,
	)
}

// Лог, отображающий поля Комнаты
func roomLog(r *Room) {
	fmt.Printf(
		"ROOM: ID: %d | Users count: %d\n",
		r.ID, len(r.users),
	)
	fmt.Print("Users devices: ")
	for device, _ := range r.users {
		fmt.Print(device.ID, "; ")
	}
	fmt.Println()
}

// Лог, отображающий создание новой комнаты
func roomCreatedLog(r *Room) {
	log.Println("Created room with ID", r.ID)
}
