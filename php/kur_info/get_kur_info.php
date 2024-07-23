<?php // Get для страницы "Информация"

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class GetInfoRequest extends MainRequestClass {
    public $infoId = '';  // Идентификатор "информации" (обяз.)
    public $mode   = '';  // Режим получения данных (доступные значения: "view": для просмотра; "change": для изменения, доступ только админу)
    public $page   = '';  // Страница "1" - общая информация, страница "2" - скрипты диалогов
}

// Класс ответа
class GetInfoResponse extends MainResponseClass {
    public $info = []; /* Словарь данных "информации"
    id          int(10)      UNSIGNED NOT NULL PRIMARY KEY - Id "информации"
    header      varchar(100) UNIQUE DEFAULT NULL           - Заголовок
    body        mediumtext   DEFAULT NULL                  - Текст
    whoChanged  bigint(20)   UNSIGNED NOT NULL             - Id пользователя внесшего правки
    whenChanged datetime     DEFAULT NULL                  - Когда были внесены правки
    public      tinyint(1)   DEFAULT 0 NOT NULL            - Опубликована ли запись? (0|1)
    */
}

// Создание запроса
$in = new GetInfoRequest();
$in->from_json(file_get_contents('php://input'));

// Создание ответа
$out = new GetInfoResponse();

// Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';

// Установка режима просмотра
switch ($user_type) {
    case 'Админ':
        $in->mode = 'change';
        break;
    case 'Куратор':
        $in->mode = 'view';
        break;
    default:
        $out->make_wrong_resp('Ошибка доступа');
        break;
}

// Валидация $in->infoId
if (((string) (int) $in->infoId) !== ((string) $in->infoId) || (int) $in->infoId <= 0) $out->make_wrong_resp('Параметр "infoId" задан некорректно или отсутствует');

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

// Получаем "информацию" по id
// Режим "view"
if($in->mode == "view") {
    $stmt = $pdo->prepare("SELECT `id`, `header`, `body`, `when_changed`, `who_changed` FROM `info` WHERE `id` = :infoId AND `page` = '$in->page' AND `public` = '1'") 
    or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    
    $stmt->execute(['id' => $in->infoId]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Информация для куратора с ID {$in->infoId} не найдена");
    
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); unset($stmt);
    $info = [
        'id'          => (string) $info['id'],
        'header'      => (string) $info['header'],
        'body'        => (string) $info['body'],
        'whenChanged' => (string) $info['when_changed'],
        'whoChanged'  => (string) $info['who_changed'],
    ];
}

// Режим "change"
if($in->mode == "change") {
    $stmt = $pdo->prepare("SELECT `id`, `header`, `body`, `public` FROM `info` WHERE `id` = :infoId AND `page` = '$in->page'") 
    or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    
    $stmt->execute(['id' => $in->infoId]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Информация для куратора с ID {$in->infoId} не найдена");
    
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); unset($stmt);
    $info = [
        'id'     => (string) $info['id'],
        'header' => (string) $info['header'],
        'body'   => (string) $info['body'],
        'public' => (string) $info['public'],
    ];
}

// Формируем ответ
$out->success = '1';
$out->info = (object) $info;
$out->make_resp('');
