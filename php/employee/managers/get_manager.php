<?php

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class EmployeeManagersGetManager extends MainRequestClass {
    public $managerId = ''; //Идентификатор сотрудника
}
$in = new EmployeeManagersGetManager();
$in->from_json(file_get_contents('php://input'));

class EmployeeManagersGetManagerResponse extends MainResponseClass {
    /* Словарь с данными сотрудника
        - managerId - Идентификатор сотрудника
        - userVkId - Идентификатор профиля ВК сотрудника
        - name - ФИО сотрудника
        - type - Тип сотрудника: Админ, Менеджер или Сотрудник
        - createdAt - Дата и время создания записи о сотруднике
    */
    public $info = []; // Словарь с возвращаемыми данными сотрудника

    /* Массив словарей со следующими полями:
        - managerId - Идентификатор соответствующего сотрудника
        - field - Наименование личных данных 
        - value - Значение личных данных, необязательно
        - comment - Комментарий к полю, необязательно
    */
    public $fields = []; // Массив словарей с личными данными
}
$out = new EmployeeManagersGetManagerResponse();


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
if (!in_array($user_type, ['Админ', 'Менеджер'])) $out->make_wrong_resp('Ошибка доступа'); 

//Валидация managerId
if (((string) (int) $in->managerId) !== ((string) $in->managerId) || (int) $in->managerId <= 0) $out->make_wrong_resp("Параметр 'managerId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `id`
    FROM `managers`
    WHERE `id` = :managerId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
    'managerId' => $in->managerId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Сотрудник с ID {$in->managerId} не найден");
$stmt->closeCursor(); unset($stmt);

//Получаем данные по managerId
$stmt = $pdo->prepare("
    SELECT `id`, `user_vk_id`, `type`, `name`, `created_at`
    FROM `managers`
    WHERE `id` = :managerId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'managerId' => $in->managerId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$info = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Из полученных данных формируем словарь в вывод
$out->info = [
    'managerId' => (string) $info['managerId'],
    'userVkId' => (string) $info['user_vk_id'],
    'name' => (string) $info['name'],
    'type' => (string) $info['type'],
    'createdAt' => (string) $info['created_at']
];

//Получаем поля с личными данными по managerId
$stmt = $pdo->prepare("
    SELECT `manager_id`, `field`, `value`, `comment`
    FROM `managers_pers_data`
    WHERE `manager_id` = :managerId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
$stmt->execute([
    'managerId' => $in->managerId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
//Формируем ответ с личными данными сотрудника
$fields = [];
while ($field = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fields[] = [
        'managerId' => (string) $field['managers_id'],
        'field' => (string) $field['field'],
        'value' => (string) $field['value'],
        'comment' => (string) $field['comment'],
    ];
}
$out->fields = $fields;
$stmt->closeCursor(); unset($stmt);


$out->success = "1";
$out->make_resp('');