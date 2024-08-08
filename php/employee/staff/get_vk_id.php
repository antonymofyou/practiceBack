<?php

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class EmployeeStaffGetVkId extends MainRequestClass {
    public $url = ''; //Ссылка на профиль ВК
}

$in = new EmployeeStaffGetVkId();
$in->from_json(file_get_contents('php://input'));

class EmployeeStaffGetVkIdResponse extends MainResponseClass {
    /* Словарь со следующими полями:
        - vkId - Идентификатор ВК
        - vkName - Имя профиля ВК
        - vkLast - Фамилия профиля ВК
        - vkNick - Отчество или прозвище профиля ВК, может быть пустым
    */
    public $vkInfo = []; //Имя, Фамилия, Отчество и Идентификатор ВК
}
$out = new EmployeeStaffGetVkIdResponse();

//Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Админ'])) $out->make_wrong_resp('Ошибка доступа'); //Доступ только у админа

$screenName = ltrim(parse_url($in->url, PHP_URL_PATH), "/"); //Парсим ссылку, убираем слева лишний / и получаем короткое имя профиля

//Делаем запрос в АПИ ВК на метод users.get на короткое имя профиля, задаём запрос на отчество и на ответ на русском
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, 'https://api.vk.com/method/users.get?user_id=' . $screenName . '&fields=nickname' . '&lang=ru' . '&access_token=' . VK_SERVICE_KEY . "&v=" . VK_API_VERSION);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = json_decode(curl_exec($curl), true); //Проводим запрос и получем словарь с одним массивом с одним словарём
curl_close($curl);

//Если ВК ответил ошибкой, то выводим ошибку
if(array_key_exists('error', $response)) {
    $errorCode = $response['error']['error_code'];
    $errorMessage = $response['error']['error_msg'];
    $out->make_wrong_resp("Произошла ошибка VK, код ошибки: [$errorCode]: {$errorMessage}");
}
//Если получен пустой ответ, то выводим ошибку
if(empty($response['response'])) $out->make_wrong_resp('Пользователь ВК не найден');

//Вкладываем данные в возврат
$out->vkInfo['vkId'] = $response['response'][0]['id'];
$out->vkInfo['vkName'] = $response['response'][0]['first_name'];
$out->vkInfo['vkLast'] = $response['response'][0]['last_name'];
$out->vkInfo['vkNick'] = $response['response'][0]['nickname'];

$out->success = "1";
$out->make_resp('');