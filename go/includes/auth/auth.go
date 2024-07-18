package auth

import (
	"database/sql"
	"nasotku/includes/api_root_classes"
	"nasotku/includes/db"
	"slices"
	"strconv"
	"time"
)

// User структура для представления текущего пользователя
type User struct {
	UserVkId            int
	NasotkuToken        string
	UserType            string
	UserBlocked         int
	UserName            string
	UserStartCourseDate string
	BnBalance           sql.NullInt64
}

// AuthError структура ошибки с релизацией интерфейса error (чтобы ее можно было возвращать как тип error)
type AuthError struct {
	err     string
	Success string
}

// Error реализация интерфейса error
func (ae AuthError) Error() string {
	return ae.err
}

func newAuthError(error string, success string) *AuthError {
	return &AuthError{
		err:     error,
		Success: success,
	}
}

// CheckUser проверка пользователя на основе signature и device
// mainRequestClass - основной класс запроса, где указаны Signature и Device текущего запроса
// data - данные, на основе которых нужно проверить, соответствует ли им Signature и Device (нужно передать полностью класс запроса для текущего вызванного метода)
func CheckUser(mainRequestClass *api_root_classes.MainRequestClass, data interface{}) (*User, *AuthError) {
	_, err := strconv.Atoi(mainRequestClass.Device) // приводим device к строке
	if err != nil {
		return nil, newAuthError("параметр 'device' задан некорретно", api_root_classes.ErrorResponse)
	}

	// получаем пользователя, токен (и другие данные) по девайсу
	rows, err := db.Db.Query(
		"SELECT `app_tokens`.`user_vk_id`,`app_tokens`.`nasotku_token`, `users`.`user_type`, `users`.`user_blocked`, `users`.`user_name`, `users`.`user_start_course_date`, `balance_now`.`bn_balance` FROM `app_tokens` INNER JOIN `users` ON `users`.`user_vk_id` = `app_tokens`.`user_vk_id` LEFT JOIN `balance_now` ON `balance_now`.`bn_user_id` = `users`.`user_vk_id` WHERE `device` = ?;",
		mainRequestClass.Device,
	)
	if err != nil {
		return nil, newAuthError("ошибка БД: "+err.Error(), api_root_classes.ErrorResponse)
	}
	defer rows.Close()

	if !rows.Next() { // проверяем наличие результата
		return nil, newAuthError("токен не найден", api_root_classes.LogoutResponse)
	}

	// биндим результат последовательно к каждому полю структуры (для этого передаем указатель на поле)
	currentUser := &User{}
	rows.Scan(&currentUser.UserVkId, &currentUser.NasotkuToken, &currentUser.UserType, &currentUser.UserBlocked, &currentUser.UserName, &currentUser.UserStartCourseDate, &currentUser.BnBalance)

	// проверка подписи
	if !mainRequestClass.CheckSignature(data, currentUser.NasotkuToken) {
		return nil, newAuthError("неверная подпись запроса", api_root_classes.LogoutResponse)
	}
	// обновляем время последнего использования токена
	_, err = db.Db.Exec(
		"UPDATE `app_tokens` SET `when_used` = NOW() WHERE `device` = ?;",
		mainRequestClass.Device,
	)
	if err != nil {
		return nil, newAuthError("ошибка БД: "+err.Error(), api_root_classes.ErrorResponse)
	}

	// проверяем пользователя на блокировку
	if currentUser.UserBlocked == 1 {
		return nil, newAuthError("Пользователь заблокирован", api_root_classes.LogoutResponse)
	}

	// проверяем тип пользователя
	allowedUserTypes := []string{"Частичный", "Интенсив", "Куратор", "Админ", "Демо", "Пакетник"} // разрешенные типы пользователей
	if !slices.Contains(allowedUserTypes, currentUser.UserType) {
		return nil, newAuthError("Неправильный тип пользователя", api_root_classes.LogoutResponse)
	}

	// доп. проверка для пользователя с типом "Интенсив"
	if currentUser.UserType == "Интенсив" {
		startCourse, _ := time.Parse(time.Layout, "2023-04-28") // переводим строковую дату в структуру Time (время начала курса для "Интенсив")

		// если текущая дата еще не дошла до времени старта курса - возвращаем ошибку
		if time.Now().Before(startCourse) {
			return nil, newAuthError("Доступ откроется 28 апреля", api_root_classes.ErrorResponse)
		}
	}

	return currentUser, nil
}
