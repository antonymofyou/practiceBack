<?php // Добавление, удаление, обновление и получение данных о сотрудниках
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class SetStaff extends MainRequestClass {
    public $id = ''; //Ключ сотрудника

    public $action = ''; /*Кодовое слово для выбора нужного действия 

    create - Добавление данных
    delete - Удаление данных
    update - Обновление данных

    */
 
    public $set = []; /* Словарь с данными сотрудника для создания или обновления

    vkId - Идентификатор ВК сотрудника
    type
    firstName
    lastName
    middleName
    blocked - используется только в update

    */

}
$in = new SetStaff();
$in->from_json(file_get_contents('php://input'));

class SetStaffResponse extends MainResponseClass {

    public $info = []; /* Словарь с информацией о сотруднике

    id
    vk_id
    type
    first_name
    last_name
    middle_name
    blocked

    */

}
$out = new SetStaffResponse();

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

if(!in_array($action, ['create', 'delete', 'update'])) $out->make_wrong_resp('Неверное действие');

if($in->action == 'delete') //Удаляем сотрудника
{
    //Валидация id
    if (((string) (int) $in->id) !== ((string) $in->id) || (int) $in->id <= 0) $out->make_wrong_resp("Номер сотрудника задан некорректно или отсутствует");
    $stmt = $pdo->prepare("
        SELECT `id`
        FROM `staff`
        WHERE `id` = :id
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'id' => $in->id
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Сотрудник с номером {$in->id} не найден");
    $stmt->closeCursor(); unset($stmt);

    //Удаляем данные сотрудника по номеру
    $stmt = $pdo->prepare("
    DELETE FROM `staff` WHERE `id` = :id
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    $stmt->execute([
        'id' => $in->id
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    $stmt->closeCursor(); unset($stmt);
    
    //Удаляем персональные данные этого сотрудника
    $stmt = $pdo->prepare("
    DELETE FROM `staff_pers_data` WHERE `user_id` = :id
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    $stmt->execute([
        'id' => $in->id
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
    $stmt->closeCursor(); unset($stmt);

    $out->success = "1";
    $out->make_resp('');
}

if($in->action == 'create') //Создаём сотрудника
{
    //Валидация id, только если id задан
    if($in->id != '') {
        if (((string) (int) $in->id) !== ((string) $in->id) || (int) $in->id <= 0) $out->make_wrong_resp("Номер задания задан некорректно");
            $stmt = $pdo->prepare("
                SELECT `id`
                FROM `staff`
                WHERE `id` = :id
            ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
            $stmt->execute([
                'id' => $in->id
            ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
            if ($stmt->rowCount() != 0) $out->make_wrong_resp("Ошибка: Сотрудник с номером {$in->id} уже существует");
            $stmt->closeCursor(); unset($stmt);
    } else $in->id = null; //иначе в запрос передаётся null, чтобы создать задание с новым номером


    $set = []; //Словарь с валидированными значениями для создания

    //id - Уже валидировано
    $set['id'] = $in->id;
    
    //Валидация $in->set[...]
    //vkId
    if (!isset($in->set['vkId'])) $out->make_wrong_resp("Поле 'vkId' не задано");
    if(!is_string($in->set['vkId'])) $out->make_wrong_resp("Поле 'vkId' задано некорректно");
    $set['vk_id'] = $in->set['vkId'];

    //type
    if (!isset($in->set['type'])) $out->make_wrong_resp("Поле 'type' не задано");
    if (!in_array($in->set['type'], ['Админ', 'Куратор', 'Частичный', 'Интенсив'])) $out->make_wrong_resp("Поле 'type' задано некорректно");
    $set['type'] = $in->set['type'];

    //firstName
    if (!isset($in->set['firstName'])) $out->make_wrong_resp("Поле 'firstName' не задано");
    if (!is_string($in->set['firstName']) || mb_strlen($in->set['firstName']) > 255) $out->make_wrong_resp("Поле 'firstName' задано некорректно");
    $set['first_name'] = $in->set['firstName'];

    //lastName
    if (!isset($in->set['lastName'])) $out->make_wrong_resp("Поле 'lastName' не задано");
    if (!is_string($in->set['lastName']) || mb_strlen($in->set['lastName']) > 255) $out->make_wrong_resp("Поле 'lastName' задано некорректно");
    $set['last_name'] = $in->set['lastName'];

    //middleName
    if (!isset($in->set['middleName'])) $out->make_wrong_resp("Поле 'middleName' не задано");
    if (!is_string($in->set['middleName']) || mb_strlen($in->set['middleName']) > 255) $out->make_wrong_resp("Поле 'middleName' задано некорректно");
    $set['middle_name'] = $in->set['middleName'];

    //blocked - Пользователь создаётся незаблокированным
    $set['blocked'] = 0;

    $columns = ['id', 'vk_id', 'type', 'first_name', 'last_name', 'middle_name', 'blocked'];
    $columns = '`' . join('`, `', $columns) . '`'; //Ставим апострофы по краям и внутри

    $values = [':id', ':vkId', ':type', ':firstName', ':lastName', ':middleName', ':blocked'];
    $values = join(', ', $values);

    $stmt = $pdo->prepare("INSERT INTO `staff` ($columns) VALUES ($values)") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');
    $stmt->execute($set) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
    $stmt->closeCursor(); unset($stmt);
}

if($in->action == 'update'){ //Обновляем данные существующего сотрудника
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

    $set = []; //Словарь с валидированными изменениями

    //Валидация $in->set[...], незаданные поля пропускаем
    //vkId
    if (isset($in->set['vkId'])) {
        if(!is_string($in->set['vkId'])) $out->make_wrong_resp("Поле 'vkId' задано некорректно");
        $set['vk_id'] = $in->set['vkId'];
    }

    //type
    if (isset($in->set['type'])) {
        if (!in_array($in->set['type'], ['Админ', 'Куратор'])) $out->make_wrong_resp("Поле 'type' задано некорректно");
        $set['type'] = $in->set['type'];
    }

    //firstName
    if (isset($in->set['firstName'])) {
        if (!is_string($in->set['firstName']) || mb_strlen($in->set['firstName']) > 255) $out->make_wrong_resp("Поле 'firstName' задано некорректно");
        $set['first_name'] = $in->set['firstName'];
    }

    //lastName
    if (isset($in->set['lastName'])) {
        if (!is_string($in->set['lastName']) || mb_strlen($in->set['lastName']) > 255) $out->make_wrong_resp("Поле 'lastName' задано некорректно");
        $set['last_name'] = $in->set['lastName'];
    }

    //middleName
    if (isset($in->set['middleName'])) {
        if (!is_string($in->set['middleName']) || mb_strlen($in->set['middleName']) > 255) $out->make_wrong_resp("Поле 'middleName' задано некорректно");
        $set['middle_name'] = $in->set['middleName'];
    }

    //blocked
    if (isset($in->set['blocked'])) {
        if(!in_array($in->set['blocked'], [0, 1])) $out->make_wrong_resp("Поле 'blocked' задано некорректно");
        $set['blocked'] = $in->set['blocked'];
    }

    // если ничего обновлять не нужно - то выводим ошибку
    if (empty($set)) $out->make_wrong_resp('Ни для одного поля не было запрошено обновление');


    //Формируем запрос на обновление данных сотрудника и проводим его
    $values = [];
    $params = [];
    foreach ($changes as $key => $value) { 
        $values[] = "`$key` = :$key";
        $params[$key] = $value;
    }
    $values = join(', ', $values);
    $params['id'] = $in->id;

    $stmt = $pdo->prepare("UPDATE `staff` SET $values WHERE `id` = :id") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (7)');
    $stmt->execute($params) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (7)');
    $stmt->closeCursor(); unset($stmt);
}