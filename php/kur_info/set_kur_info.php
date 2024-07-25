<?php // Редактирование информации для кураторов

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class SetInfoRequest extends MainRequestClass {
    public $infoId = ''; // Id информации для кураторов

    public $infoUpdate = []; /* Словарь данных для обновления информации для кураторов, имеющий поля:
    - header      - Заголовок (Сочетание header и page должно быть уникальным)
    - body        - Текст
    - whoChanged  - Id пользователя внесшего правки
    - whenChanged - Когда были внесены правки
    - public      - Опубликована ли запись? (0/1)
    - page        - Страница (1/2)
    */

    public $action = ''; // Действие с информацией для кураторов (create - создание, update - изменение, delete - удаление)
}

$in = new SetInfoRequest();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class SetInfoResponse extends MainResponseClass {
    public $info = []; /* Словарь данных информации для кураторов, имеющий поля:
    - id          - Id информации для кураторов
    - header      - Заголовок (Сочетание header и page должно быть уникальным)
    - body        - Текст
    - whoChanged  - Id пользователя внесшего правки
    - whenChanged - Когда были внесены правки
    - public      - Опубликована ли запись? (0/1)
    - page        - Страница (1/2)
    */
}

$out = new SetInfoResponse();

// Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if ($user_type != 'Админ') $out->make_wrong_resp('Ошибка доступа');

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

// Создание строки информации для кураторов
if($in->action == "create") {
    // Создаём массивы с данными для запроса INSERT со всеми полями и словарь с данными для этих полей
    $columns = ['id', 'header', 'body', 'who_changed', 'when_changed', 'public', 'page'];
    $columns = join(', ', $columns);

    $values = [':infoId', ':header', ':body', ':whoChanged', ':whenChanged', ':public', ':page'];
    $values = join(', ', $values);

    $params = [
        'infoId' => null,
        'header' => null,
        'body' => null,
        'whoChanged' => $user_vk_id,
        'whenChanged' => null,
        'public' => null,
        'page' => null
    ];

    $stmt = $pdo->prepare("INSERT INTO `info` ($columns) VALUES ($values)") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute($params) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    $stmt->closeCursor(); unset($stmt);

    $in->infoId = $pdo->lastInsertId(); if(!$in->infoId) $out->make_wrong_resp('Произошла ошибка при создании информации для кураторов');
}

// Обновление строки информации для кураторов
if ($in->action == "update") {
    //Валидация $in->infoId
    if (((string) (int) $in->infoId) !== ((string) $in->infoId) || (int) $in->infoId <= 0) $out->make_wrong_resp("Id информации для кураторов задан некорректно");
    
    $stmt = $pdo->prepare("SELECT `id` FROM `info` WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    
    $stmt->execute([
        'infoId' => $in->infoId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Информация с Id {$in->infoId} не найдена");
    
    $stmt->closeCursor(); unset($stmt);

    $changes = []; // Словарь с валидированными изменениями

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
        if (!is_string($in->infoUpdate['whenChanged'])) $out->make_wrong_resp("Поле 'whenChanged' задано некорректно");
        $changes['when_changed'] = $in->infoUpdate['whenChanged'];
    }

    // Валидация public
    if (isset($in->infoUpdate['public'])) {
        if (((string) (int) $in->infoUpdate['public']) !== ((string) $in->infoUpdate['public'])) $out->make_wrong_resp("Поле 'public' задано некорректно");
        if (!in_array($in->infoUpdate['public'], [0, 1])) $out->make_wrong_resp("Поле 'public' задано некорректно");

        $changes['public'] = $in->infoUpdate['public'];
    }

    // Валидация page
    if (isset($in->infoUpdate['page'])) {
        if (((string) (int) $in->infoUpdate['page']) !== ((string) $in->infoUpdate['page'])) $out->make_wrong_resp("Поле 'page' задано некорректно");
        if (0 < $in->infoUpdate['page'] <= 5) $out->make_wrong_resp("Поле 'page' задано некорректно");
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

    $stmt = $pdo->prepare("UPDATE `info` SET $values WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    $stmt->execute($params) or $out->make_wrong_resp("Ошибка базы данных: выполнение запроса (3)");
    $stmt->closeCursor(); unset($stmt);  
}

// Удаление строки информации для кураторов
if($in->action == "delete"){
    // Валидация $in->infoId
    if (((string) (int) $in->infoId) !== ((string) $in->infoId) || (int) $in->infoId <= 0) $out->make_wrong_resp('Id информации для кураторов задан некорректно');
    
    $stmt = $pdo->prepare("SELECT `id` FROM `info` WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
    
    $stmt->execute([
        'infoId' => $in->infoId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
    
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: информация с Id {$in->infoId} не найдена");
        $stmt->closeCursor(); unset($stmt);

    // Удаляем информацию по id
    $stmt = $pdo->prepare("DELETE FROM `info` WHERE `id` = :infoId") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');
    
    $stmt->execute([
        'infoId' => $in->infoId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
    
    $stmt->closeCursor(); unset($stmt);

    $out->message = "Информация для куратора с Id {$in->infoId} удалена";
    $out->success = "1";
    $out->make_resp('');
}
