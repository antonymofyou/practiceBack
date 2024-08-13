<?php // Добавление, удаление, обновление и получение данных о сотрудниках

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class EmployeeManagersSetManager extends MainRequestClass {
    public $managerId = ''; // Идентификатор сотрудника

    public $action = ''; // Кодовое слово для одного из действий: create - добавление данных сотрудника, update - обновление данных сотрудника или delete - удаление данных сотрудника

    /* Словарь со следующими полями:
        - userVkId - Идентификатор ВК сотрудника
        - type - Тип сотрудника: Админ, Менеджер или Сотрудник
        - name - ФИО сотрудника
    */
    public $set = []; //Словарь с данными сотрудника для создания или обновления

}
$in = new EmployeeManagersSetManager();
$in->from_json(file_get_contents('php://input'));

class EmployeeManagersSetManagerResponse extends MainResponseClass {

    /* Словарь с данными сотрудника
        - managerId - Идентификатор сотрудника
        - userVkId - Идентификатор профиля ВК сотрудника
        - name - ФИО сотрудника
        - type - Тип сотрудника: Админ, Менеджер или Сотрудник
        - createdAt - Дата и время создания записи о сотруднике
    */
    public $info = []; // Словарь с информацией о сотруднике

    /* Массив словарей со следующими полями:
        - managerId - Идентификатор соответствующего сотрудника
        - field - Наименование личных данных 
        - value - Значение личных данных, необязательно
        - comment - Комментарий к полю, необязательно
    */
    public $fields = []; // Поля с личными данными
}
$out = new EmployeeManagersSetManagerResponse();

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

//Если действие указано неверно, то сразу выдаём ошибку
if(!in_array($in->action, ['create', 'delete', 'update'])) $out->make_wrong_resp('Неверное действие');

if($in->action == 'delete') //Удаляем сотрудника
{
    //Валидация managerId
    if (((string) (int) $in->managerId) !== ((string) $in->managerId) || (int) $in->managerId <= 0) $out->make_wrong_resp("Параметр 'managerId' задан некорректно или отсутствует (1)");
    $stmt = $pdo->prepare("
        SELECT `id`
        FROM `managers`
        WHERE `id` = :managerId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'managerId' => $in->managerId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Сотрудник с ID {$in->managerId} не найден (1)");
    $stmt->closeCursor(); unset($stmt);

    //Удаляем данные сотрудника по номеру
    $stmt = $pdo->prepare("
        DELETE FROM `managers`
        WHERE `id` = :managerId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    $stmt->execute([
        'managerId' => $in->managerId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    $stmt->closeCursor(); unset($stmt);
    
    //Удаляем личные данные этого сотрудника
    $stmt = $pdo->prepare("
        DELETE FROM `manager_pers_data`
        WHERE `manager_id` = :managerId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    $stmt->execute([
        'managerId' => $in->managerId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
    $stmt->closeCursor(); unset($stmt);

    $out->success = "1";
    $out->make_resp('');
}

if($in->action == 'create') //Создаём сотрудника
{
    $set = []; //Словарь с валидированными значениями для создания

    //managerId - Ставим null чтобы ID сам создался в базе
    $set['managerId'] = null;
    
    //Валидация $in->set[...], если хоть одно поле не задано - выводим ошибку, кроме blocked
    //userVkId - проверяем, нет ли ещё сотрудников с таким же userVkId
    if (!isset($in->set['userVkId'])) $out->make_wrong_resp("Поле 'userVkId' не задано");
    if(!is_string($in->set['userVkId'])) $out->make_wrong_resp("Поле 'userVkId' задано некорректно (1)");
        $stmt = $pdo->prepare("
            SELECT `user_vk_id`
            FROM `managers`
            WHERE `user_vk_id` = :userVkId;
            ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
        $stmt->execute([
            'userVkId' => $in->set['userVkId']
            ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
        if ($stmt->rowCount() != 0) $out->make_wrong_resp("Ошибка: Сотрудник с ВК ID {$in->set['userVkId']} уже существует (1)");
        $stmt->closeCursor(); unset($stmt);
    $set['userVkId'] = $in->set['userVkId'];

    //name
    if (!isset($in->set['name'])) $out->make_wrong_resp("Поле 'name' не задано");
    if (!is_string($in->set['name']) || mb_strlen($in->set['name']) > 255) $out->make_wrong_resp("Поле 'name' задано некорректно (1)");
    $set['name'] = $in->set['name'];

    //type
    if (!isset($in->set['type'])) $out->make_wrong_resp("Поле 'type' не задано");
    if (!in_array($in->set['type'], ['Админ', 'Менеджер', 'Сотрудник'])) $out->make_wrong_resp("Поле 'type' задано некорректно (1)");
    $set['type'] = $in->set['type'];

    //Проводим запрос о добавлении данных сотрудника
    $stmt = $pdo->prepare("
        INSERT INTO `managers`
        (`id`, `user_vk_id`, `name`, `type`) 
        VALUES (:managerId, :userVkId, :name, :type);
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');
    $stmt->execute($set) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
    $stmt->closeCursor(); unset($stmt);

    //Берём ID созданного сотрудника, чтобы вернуть его данные
    $in->managerId = $pdo->lastInsertId(); if(!$in->managerId) $out->make_wrong_resp('Произошла ошибка при добавлении сотрудника'); 
}

if($in->action == 'update'){ //Обновляем данные существующего сотрудника
    //Валидация managerId
    if (((string) (int) $in->managerId) !== ((string) $in->managerId) || (int) $in->managerId <= 0) $out->make_wrong_resp("Параметр 'managerId' задан некорректно или отсутствует (2)");
    $stmt = $pdo->prepare("
        SELECT `id`
        FROM `managers`
        WHERE `id` = :managerId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
    $stmt->execute([
        'managerId' => $in->managerId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Сотрудник с ID {$in->managerId} не найден (2)");
    $stmt->closeCursor(); unset($stmt);

    $set = []; //Словарь с валидированными изменениями

    //Валидация $in->set[...], незаданные поля пропускаем
    //userVkId - также проверяем, чтобы не был задан ВК ИД другого сотрудника
    if (isset($in->set['userVkId'])) {
        if(!is_string($in->set['userVkId'])) $out->make_wrong_resp("Поле 'userVkId' задано некорректно");
        $stmt = $pdo->prepare("
                SELECT `id`, `user_vk_id`
                FROM `managers`
                WHERE `user_vk_id` = :userVkId;
            ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (7)');
            $stmt->execute([
                'userVkId' => $in->set['userVkId']
            ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (7)');
            //Если найден сотрудник с таким же ВК ID и это не этот сотрудник - то выдаём ошибку
            if ($stmt->rowCount() != 0 && $stmt->fetch(PDO::FETCH_ASSOC)['id'] != $in->managerId) $out->make_wrong_resp("Ошибка: Сотрудник с ВК ID {$in->set['userVkId']} уже существует (2)");
            $stmt->closeCursor(); unset($stmt);
        $set['user_vk_id'] = $in->set['userVkId'];
    }

    //name
    if (isset($in->set['name'])) {
        if (!is_string($in->set['name']) || mb_strlen($in->set['name']) > 255) $out->make_wrong_resp("Поле 'name' задано некорректно (2)");
        $set['name'] = $in->set['name'];
    }

    //type
    if (isset($in->set['type'])) {
        if (!in_array($in->set['type'], ['Админ', 'Менеджер', 'Сотрудник'])) $out->make_wrong_resp("Поле 'type' задано некорректно (2)");
        $set['type'] = $in->set['type'];
    }

    // если ничего обновлять не нужно - то выводим ошибку
    if (empty($set)) $out->make_wrong_resp('Ни для одного поля не было запрошено обновление');


    //Формируем запрос на обновление данных сотрудника и проводим его
    $values = [];
    $params = [];
    foreach ($set as $key => $value) { 
        $values[] = "`$key` = :$key";
        $params[$key] = $value;
    }
    $values = join(', ', $values);
    $params['managerId'] = $in->managerId;

    $stmt = $pdo->prepare("
        UPDATE `managers` 
        SET $values 
        WHERE `id` = :managerId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');
    $stmt->execute($params) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (8)');
    $stmt->closeCursor(); unset($stmt);
}


//Получаем данные о сотруднике, который был создан или обновлён
$stmt = $pdo->prepare("
    SELECT `id`, `user_vk_id`, `name`, `type`
    FROM `managers`
    WHERE `id` = :managerId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (9)');
$stmt->execute([
    'managerId' => $in->managerId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (9)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$info = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Из полученных данных формируем словарь в вывод
$out->info = [
    'managerId' => (string) $info['id'],
    'userVkId' => (string) $info['user_vk_id'],
    'name' => (string) $info['name'],
    'type' => (string) $info['type']
];

//Получаем поля с личными данными по managerId
$stmt = $pdo->prepare("
    SELECT `manager_id`, `field`, `value`, `comment`
    FROM `manager_pers_data`
    WHERE `manager_id` = :managerId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (10)');
$stmt->execute([
    'managerId' => $in->managerId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (10)');
//Формируем ответ с личными данными сотрудника
$fields = [];
while ($field = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $fields[] = [
        'managerId' => (string) $field['manager_id'],
        'field' => (string) $field['field'],
        'value' => (string) $field['value'],
        'comment' => (string) $field['comment'],
    ];
}
$out->fields = $fields;
$stmt->closeCursor(); unset($stmt);

$out->success = "1";
$out->make_resp('');