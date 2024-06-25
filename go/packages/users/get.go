package users

import (
	"nasotku/includes/api_root_classes"
	"net/http"
)

type UsersGetRequest struct {
	api_root_classes.MainRequestClass
	Id int `json:"id"`
}

type UsersGetResponse struct {
	api_root_classes.MainResponseClass
	UserNames []string `json:"userNames"`
}

func Get(w http.ResponseWriter, r *http.Request) {
	var in = UsersGetRequest{}
	var out = UsersGetResponse{
		UserNames: []string{},
	}

	//--------------------------------Парсим запрос
	bytes, err := api_root_classes.RequestBodyToBytes(r.Body)
	if err != nil {
		w.Write(out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse))
		return
	}
	if err := in.FromJson(bytes, &in); err != nil {
		w.Write(out.MakeWrongResponse(err.Error(), api_root_classes.ErrorResponse))
		return
	}

	//--------------------------------Проверка пользователя
	//if err := auth.CheckUser(in.MainRequestClass, in); err != nil {
	//	w.Write(out.MakeWrongResponse(err.Error(), err.Success))
	//	return
	//}

	//--------------------------------Ответ
	out.Success = "1"
	w.Write(out.MakeResponse(&out, ""))
}
