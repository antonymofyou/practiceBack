<?php // Получение информации для кураторов

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class GetInfoByPageRequest extends MainRequestClass
{
    public $page = '';  // Страница "1" - информация для кураторов, страница "2" - скрипты диалогов
    public $mode = '';  // Режим получения данных (доступные значения: "view": для просмотра; "change": для изменения, доступ только админу)
}

$in = new GetInfoByPageRequest();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class GetInfoByPageResponse extends MainResponseClass
{
    /*
    * Массив словарей данных информации для кураторов, где каждый словарь имеет следующие поля:
    * id          - Id информации для кураторов
    * header      - Заголовок
    * body        - Текст
    * whoChanged  - Id пользователя внесшего правки
    * whenChanged - Когда были внесены правки
    * public      - Опубликована ли запись? (0/1) (Только в $mode="change")
    */
    public $infos = [];
}

$out = new GetInfoByPageResponse();

// Валидация $in->page
if (((string)(int)$in->page) !== ((string)$in->page) || (int)$in->page <= 0) $out->make_wrong_resp('Параметр {page} задан некорректно или отсутствует');

// Валидация $in->mode
if (!in_array($in->mode, ['view', 'change'])) $out->make_wrong_resp('Параметр {mode} задан некорректно или отсутствует');

// Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Куратор', 'Админ'])) $out->make_wrong_resp('Ошибка доступа');

// Проверка доступа к режиму редактирования
if ($in->mode == 'change' && $user_type != 'Админ') $out->make_wrong_resp('Ошибка доступа. Режим редактирования доступен только администратору');

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

// Получаем информацию для кураторов
// Режим "view"
if ($in->mode == 'view') {
    // Подготовка запроса для получения записи
    $stmt = $pdo->prepare("SELECT `id`, `header`, `body`, `when_changed`, `who_changed` FROM `info` WHERE `page` = :page AND `public` = '1'")
    or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

    // Выполнение запроса для получения записи
    $stmt->execute(['page' => $in->page]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

    // Проверка наличия записей
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Информация для кураторов на странице {$in->page} не найдена");

    $infos = [];
    while ($info = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $infos[] = [
            'id' => (string)$info['id'],
            'header' => (string)$info['header'],
            'body' => (string)$info['body'],
            'whenChanged' => (string)$info['when_changed'],
            'whoChanged' => (string)$info['who_changed'],
        ];
    }

    $stmt->closeCursor();
    unset($stmt);
}

// Режим "change"
if ($in->mode == "change") {
    // Подготовка запроса для получения записи
    $stmt = $pdo->prepare("SELECT `id`, `header`, `body`, `when_changed`, `who_changed`, `public` FROM `info` WHERE `page` = :page")
    or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');

    // Выполнение запроса для получения записи
    $stmt->execute(['page' => $in->page]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');

    // Проверка наличия записей
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Информация для кураторов на странице {$in->page} не найдена");

    $infos = [];
    while ($info = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $infos[] = [
            'id' => (string)$info['id'],
            'header' => (string)$info['header'],
            'body' => (string)$info['body'],
            'whenChanged' => (string)$info['when_changed'],
            'whoChanged' => (string)$info['who_changed'],
            'public' => (string)$info['public'],
        ];
    }

    $stmt->closeCursor();
    unset($stmt);
}

// Ответ
$out->success = '1';
$out->infos = $infos;
$out->make_resp('');
