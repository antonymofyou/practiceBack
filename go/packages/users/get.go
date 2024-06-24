package users

import (
	"nasotku/includes/api_root_classes"
	"nasotku/includes/auth"
	"net/http"
)

type UsersGetRequest struct {
	api_root_classes.MainRequestClass
	Id int `json:"id"`
}

var in = UsersGetRequest{}

type UsersGetResponse struct {
	api_root_classes.MainResponseClass
	UserNames []string `json:"userNames"`
}

var out = UsersGetResponse{
	UserNames: []string{},
}

func Get(w http.ResponseWriter, r *http.Request) {
	//--------------------------------Парсим запрос
	if err := in.FromJson(r, &in); err != nil {
		w.Write(out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse))
		return
	}

	//--------------------------------Проверка пользователя
	if err := auth.CheckUser(in.MainRequestClass, in); err != nil {
		w.Write(out.MakeWrongResponse(err.Error(), err.Success))
		return
	}

	//--------------------------------Ответ
	out.Success = "1"
	w.Write(out.MakeResponse(&out, ""))
}
