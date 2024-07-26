<?php

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class GetStaff extends MainRequestClass {
    public $url = ''; //Получаем ссылку на профиль ВК
}

$in = new GetStaff();
$in->from_json(file_get_contents('php://input'));

class GetStaffResponse extends MainResponseClass {
    public $vkId = ''; //Отдаём ВК id
}
$out = new GetStaffResponse();

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

//Делаем запрос в АПИ ВК на метод users.get на короткое имя профиля
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, 'https://api.vk.com/method/users.get?user_id=' . $screenName . '&access_token=' . VK_SERVICE_KEY . "&v=" . VK_API_VERSION);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = (array) json_decode(curl_exec($curl)); //Проводим запрос и получем словарь с одним массивом с одним словарём
curl_close($curl);

//Добираемся до ВК ID
$response = (array) $response['response'];
$response = (array) $response[0];
$response = $response['id'];

//Возвращаем ВК ID
$out->vkId = $response;

$out->success = "1";
$out->make_resp('');