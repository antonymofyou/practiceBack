<?php

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class EmployeeStaffGetStaff extends MainRequestClass {
    public $staffId = ''; //Идентификатор сотрудника
}
$in = new EmployeeStaffGetStaff();
$in->from_json(file_get_contents('php://input'));

class EmployeeStaffGetStaffResponse extends MainResponseClass {
    /* Словарь с данными сотрудника
        - staffId - Идентификатор сотрудника
        - vkId - Идентификатор профиля ВК сотрудника
        - type - Тип сотрудника: Админ или Куратор ?
        - firstName - Имя сотрудника
        - lastName - Фамилия сотрудника
        - middleName - Отчество сотрудника
        - blocked - Заблокирован ли сотрудник или нет, значение 0 или 1 соотвественно
    */
    public $info = []; // Словарь с возвращаемыми данными сотрудника

    /* Массив словарей со следующими полями:
        - staffId - Идентификатор соответствующего сотрудника
        - field - Наименование личных данных 
        - value - Значение личных данных, необязательно
        - comment - Комментарий к полю, необязательно
    */
    public $fields = []; // Массив словарей с личными данными
}
$out = new EmployeeStaffGetStaffResponse();


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

//Валидация staffId
if (((string) (int) $in->staffId) !== ((string) $in->staffId) || (int) $in->staffId <= 0) $out->make_wrong_resp("Параметр 'staffId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `id`
    FROM `staff`
    WHERE `id` = :staffId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
$stmt->execute([
    'staffId' => $in->staffId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Сотрудник с ID {$in->staffId} не найден");
$stmt->closeCursor(); unset($stmt);

//Получаем данные по staffId
$stmt = $pdo->prepare("
    SELECT `id`, `vk_id`, `type`, `first_name`, `last_name`, `middle_name`, `blocked`
    FROM `staff`
    WHERE `id` = :staffId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (7)');
$stmt->execute([
    'staffId' => $in->staffId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (7)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$info = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Из полученных данных формируем словарь в вывод
$out->info = [
    'staffId' => (string) $info['staffId'],
    'vkId' => (string) $info['vk_id'],
    'type' => (string) $info['type'],
    'firstName' => (string) $info['first_name'],
    'lastName' => (string) $info['last_name'],
    'middleName' => (string) $info['middle_name'],
    'blocked' => (string) $info['blocked']
];

//Получаем поля с личными данными по staffId
$stmt = $pdo->prepare("
    SELECT `staff_id`, `field`, `value`, `comment`
    FROM `staff_pers_data`
    WHERE `staff_id` = :staffId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');
$stmt->execute([
    'staffId' => $in->staffId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (8)');
//Формируем ответ с личными данными сотрудника
$fields = [];
while ($field = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fields[] = [
        'staffId' => (string) $field['staff_id'],
        'field' => (string) $field['field'],
        'value' => (string) $field['value'],
        'comment' => (string) $field['comment'],
    ];
}
$out->fields = $fields;
$stmt->closeCursor(); unset($stmt);


$out->success = "1";
$out->make_resp('');