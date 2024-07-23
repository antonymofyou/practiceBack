<?php // CRUD для страницы "Информация"

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class InfoRequest extends MainRequestClass {
    public $infoId = ''; // Идентификатор "информации" (обяз.)

    public $info = []; /* Словарь данных "информации"
    id          int(10)      UNSIGNED PRIMARY KEY - Id "информации"
    header      varchar(100) UNIQUE               - Заголовок
    body        mediumtext                        - Текст
    whoChanged  bigint(20)   UNSIGNED             - Id пользователя внесшего правки
    whenChanged datetime                          - Когда были внесены правки
    public      tinyint(1)   DEFAULT 0            - Опубликована ли запись?
    page        smallint(5)  UNSIGNED UNIQUE      - Страница
    */

    public $infoUpdate = []; /* Словарь данных для обновления "информации"
    id          int(10)      UNSIGNED PRIMARY KEY - Id "информации"
    header      varchar(100) UNIQUE               - Заголовок
    body        mediumtext                        - Текст
    whoChanged  bigint(20)   UNSIGNED             - Id пользователя внесшего правки
    whenChanged datetime                          - Когда были внесены правки
    public      tinyint(1)   DEFAULT              - Опубликована ли запись?
    page        smallint(5)  UNSIGNED UNIQUE      - Страница
    */

    public $action = ''; // Create, get, update, delete (lowercase)
}

$in = new InfoRequest();
$in->from_json(file_get_contents('php://input'));

// Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Админ', 'Куратор'])) $out->make_wrong_resp('Ошибка доступа');

// Проверка поля action
if($in->action != "create" && $in->action != "get" && $in->action != "update" && $in->action != "delete") $out->make_wrong_resp('Неверное действие');

// Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_HR . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}
