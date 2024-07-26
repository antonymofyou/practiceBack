<?php

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class GetStaff extends MainRequestClass {
    public $id = ''; //Ключ сотрудника
}

$in = new GetStaff();
$in->from_json(file_get_contents('php://input'));

class GetStaffResponse extends MainResponseClass {
    public $info = []; /* Словарь с данными сотрудника
    id
    vkId
    type
    firstName
    lastName
    middleName
    blocked
    */
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
if (!in_array($user_type, ['Админ', 'Куратор'])) $out->make_wrong_resp('Ошибка доступа');

//Валидация id
if (((string) (int) $in->id) !== ((string) $in->id) || (int) $in->id <= 0) $out->make_wrong_resp("Номер сотрудника задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `id`
    FROM `staff`
    WHERE `id` = :id
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
$stmt->execute([
    'id' => $in->id
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Сотрудник с номером {$in->id} не найден");
$stmt->closeCursor(); unset($stmt);

//Возвращаем данные по id
$stmt = $pdo->prepare("
    SELECT `id`, `vk_id`, `type`, `first_name`, `last_name`, `middle_name`, `blocked`
    FROM `staff`
    WHERE `id` = :id
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');
$stmt->execute([
    'id' => $in->id
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (8)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$info = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Из полученных данных формируем словарь в вывод
$out->info = [
    'id' => (string) $info['id'],
    'vkId' => (string) $info['vk_id'],
    'type' => (string) $info['type'],
    'firstName' => (string) $info['first_name'],
    'lastName' => (string) $info['last_name'],
    'middleName' => (string) $info['middle_name'],
    'blocked' => (string) $info['blocked']
];

$out->success = "1";
$out->make_resp('');