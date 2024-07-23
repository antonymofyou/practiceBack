<?php // Редактирование записей со страницы "Информация"

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class SetInfoRequest extends MainRequestClass {
    public $infoId = ''; // Идентификатор "информации" (обяз.)

    public $infoUpdate = []; /* Словарь данных для обновления "информации"
    header      varchar(100) DEFAULT NULL                  - Заголовок (Сочетание header и page должно быть уникальным)
    body        mediumtext   DEFAULT NULL                  - Текст
    whoChanged  bigint(20)   UNSIGNED NOT NULL             - Id пользователя внесшего правки
    whenChanged datetime     DEFAULT NULL                  - Когда были внесены правки
    public      tinyint(1)   DEFAULT 0 NOT NULL            - Опубликована ли запись? (0|1)
    page        smallint(5)  UNSIGNED NOT NULL             - Страница (1|2)
    */

    public $action = ''; // Действие с "информацией" (create - создание, update - изменение, delete - удаление)
}

// Класс ответа
class SetInfoResponse extends MainResponseClass {
    public $info = []; /* Словарь данных "информации"
    id          int(10)      UNSIGNED NOT NULL PRIMARY KEY - Id "информации"
    header      varchar(100) DEFAULT NULL                  - Заголовок (Сочетание header и page должно быть уникальным)
    body        mediumtext   DEFAULT NULL                  - Текст
    whoChanged  bigint(20)   UNSIGNED NOT NULL             - Id пользователя внесшего правки
    whenChanged datetime     DEFAULT NULL                  - Когда были внесены правки
    public      tinyint(1)   DEFAULT 0 NOT NULL            - Опубликована ли запись? (0|1)
    page        smallint(5)  UNSIGNED NOT NULL             - Страница (1|2)
    */
}

// Создание запроса
$in = new SetInfoRequest();
$in->from_json(file_get_contents('php://input'));

// Создание ответа
$out = new SetInfoResponse();

// Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Админ'])) $out->make_wrong_resp('Ошибка доступа');

// Проверка поля action
if($in->action != "create" && $in->action != "update" && $in->action != "delete") $out->make_wrong_resp('Неверное действие');

// Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

// Создание строки "информации"
if($in->action == "create") {
    // Валидация infoId, только если infoId задан
    if($in->infoId != '') {
        if (((string) (int) $in->infoId) !== ((string) $in->infoId) || (int) $in->infoId <= 0) $out->make_wrong_resp("Номер задания задан некорректно");
            $stmt = $pdo->prepare("SELECT `id` FROM `info` WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
            
            $stmt->execute([
                'infoId' => $in->infoId
            ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
            
            if ($stmt->rowCount() != 0) $out->make_wrong_resp("Ошибка: Домашнее задание с номером {$in->infoId} уже существует");
            
            $stmt->closeCursor(); unset($stmt);
    } else $in->infoId = null; //иначе в запрос передаётся null, чтобы создать задание с новым номером

    //Создаём массивы с данными для запроса INSERT со всеми полями и словарь с данными для этих полей
    $columns = ['id', 'header', 'body', 'who_changed', 'when_changed', 'public', 'page'];
    $columns = join(', ', $columns);

    $values = [':infoId', ':header', ':body', ':'.$user_vk_id, ':whenChanged', ':public', ':page'];
    $values = join(', ', $values);

    $params = [
        'infoId' => $in->infoId,
        'header' => null,
        'body' => null,
        'whoChanged' => $user_vk_id,
        'whenChanged' => null,
        'public' => null,
        'page' => null
    ];

    $stmt = $pdo->prepare("INSERT INTO `info` ($columns) VALUES ($values)") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');
    $stmt->execute($params) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
    $stmt->closeCursor(); unset($stmt);

    $in->infoId = $pdo->lastInsertId(); if(!$in->infoId) $out->make_wrong_resp('Произошла ошибка при создании "информации"');
}

// Обновление строки "информации"
if ($in->action == "update") {
    //Валидация $in->infoId
    if (((string) (int) $in->infoId) !== ((string) $in->infoId) || (int) $in->infoId <= 0) $out->make_wrong_resp("Номер задания задан некорректно или отсутствует");
    
    $stmt = $pdo->prepare("SELECT `id` FROM `info` WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');
    
    $stmt->execute([
        'infoId' => $in->infoId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (8)');
    
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Домашнее задание с номером {$in->infoId} не найдено");
    
    $stmt->closeCursor(); unset($stmt);

    $changes = []; //Словарь с валидированными изменениями

    // Валидация $in->infoUpdate[], если поле указано, то валидируем и добавляем в список изменений
    // Валидация header
    if (isset($in->infoUpdate['header'])) {
        if (is_string($in->infoUpdate['header']) && mb_strlen($in->infoUpdate['header']) <= 100) $out->make_wrong_resp("Поле 'header' задано некорректно");
        $changes['header'] = $in->infoUpdate['header'];
    }

    // Валидация body
    if (isset($in->infoUpdate['body'])) {
        if (is_string($in->infoUpdate['body']) && mb_strlen($in->infoUpdate['body']) <= 16777215) $out->make_wrong_resp("Поле 'body' задано некорректно");
        $changes['body'] = $in->infoUpdate['body'];
    }

    // Валидация whoChanged
    if (isset($in->infoUpdate['whoChanged'])) {
        if (((string) (int) $in->infoUpdate['whoChanged']) !== ((string) $in->infoUpdate['whoChanged']) || $in->infoUpdate['whoChanged'] < 0) $out->make_wrong_resp("Поле 'whoChanged' задано некорректно");
        $changes['who_changed'] = $user_vk_id;
    }

    // Валидация whenChanged
    if (isset($in->infoUpdate['whenChanged'])) {
        if (!is_string($in->infoUpdate['whenChanged'])) $out->make_wrong_resp("Поле 'whenChanged' задано некорректно (1)");
        $changes['when_changed'] = $in->infoUpdate['whenChanged'];
    }

    // Валидация public
    if (isset($in->infoUpdate['public'])) {
        if (((string) (int) $in->infoUpdate['public']) !== ((string) $in->infoUpdate['public'])) $out->make_wrong_resp("Поле 'public' задано некорректно (1)");
        if (!in_array($in->infoUpdate['public'], [0, 1])) $out->make_wrong_resp("Поле 'public' задано некорректно (2)");

        $changes['public'] = $in->infoUpdate['public'];
    }

    // Валидация page
    if (isset($in->infoUpdate['page'])) {
        if (((string) (int) $in->infoUpdate['page']) !== ((string) $in->infoUpdate['page'])) $out->make_wrong_resp("Поле 'page' задано некорректно (1)");
        if (0 < $in->infoUpdate['page'] <= 5) $out->make_wrong_resp("Поле 'page' задано некорректно (2)");
        $changes['page'] = $in->infoUpdate['page'];
    }

    // Если ничего обновлять не нужно - то выводим ошибку
    if (empty($changes)) $out->make_wrong_resp('Ни для одного поля не было запрошено обновление');


    $values = [];
    $params = [];

    foreach ($changes as $key => $value) { 
        $values[] = "`$key` = :$key";
        $params[$key] = $value;
    }
    
    $values = join(', ', $values);
    $params['infoId'] = $in->infoId;

    $stmt = $pdo->prepare("UPDATE `info` SET $values WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (9)');
    $stmt->execute($params) or $out->make_wrong_resp("Ошибка базы данных: выполнение запроса (9)");
    $stmt->closeCursor(); unset($stmt);  
}

// Удаление строки "информации"
if($in->action == "delete"){ // Удаляем "информацию" по id
    // Валидация $in->infoId
    if (((string) (int) $in->infoId) !== ((string) $in->infoId) || (int) $in->infoId <= 0) $out->make_wrong_resp('Параметр "infoId" задан некорректно или отсутствует');
    
    $stmt = $pdo->prepare("SELECT `id` FROM `info` WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    
    $stmt->execute([
        'infoId' => $in->infoId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: информация для куратора {$in->infoId} не найдена");
        $stmt->closeCursor(); unset($stmt);

    // Удаляем "информацию" по id
    $stmt = $pdo->prepare("DELETE FROM `info` WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    
    $stmt->execute([
        'infoId' => $in->infoId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    
    $stmt->closeCursor(); unset($stmt);

    $out->message = "Информация для куратора $in->infoId удалена";
    $out->success = "1";
    $out->make_resp('');
}
