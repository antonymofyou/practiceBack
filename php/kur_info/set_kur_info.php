<?php // Редактирование информации для кураторов

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class SetInfoRequest extends MainRequestClass
{
    public $id = ''; // ID информации для кураторов
    public $info = []; /* Словарь данных для обновления информации для кураторов, имеющий поля:
    - header      - Заголовок (Сочетание header и page должно быть уникальным)
    - body        - Текст
    - public      - Опубликована ли запись? (0/1)
    - page        - Страница (1/2), только при создании
    */
    public $action = ''; // Действие с информацией для кураторов (create - создание, update - изменение, delete - удаление)
}

$in = new SetInfoRequest();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class SetInfoResponse extends MainResponseClass
{
    public $id = ''; // ID отредактированной информации для кураторов
}

$out = new SetInfoResponse();

// Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if ($user_type != 'Админ') $out->make_wrong_resp("Ошибка доступа");

// Проверка поля action
if ($in->action != "create" && $in->action != "update" && $in->action != "delete") $out->make_wrong_resp("Неверное действие");

// Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp("Нет соединения с базой данных");
}

// Создание строки информации для кураторов
if ($in->action == "create") {
    // Подготовка запроса для создания записи
    $stmt = $pdo->prepare("
        INSERT INTO `info` (`header`, `body`, `who_changed`, `when_changed`, `public`, `page`) 
        VALUES (:header, :body, :whoChanged, NOW(), '0', :page)
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

    // Выполнение запроса для создания записи
    $execute = $stmt->execute([
        'header' => $in->info['header'],
        'body' => $in->info['body'],
        'whoChanged' => $user_vk_id,
        'page' => $in->info['page'],
    ]);

    // Проверка ошибки выполнения запроса
    if (!$execute) {
        $driverErrorCode = $stmt->errorInfo()[1]; // В элементе по индексу 1 находится код ошибки драйвера

        // Если ошибка произошла из-за того, что такой доступ уже существует - обрабатываем с конкретным текстом ошибки, иначе возвращаем общую ошибку БД
        // Error 1062: Duplicate entry for key: https://mariadb.com/kb/en/e1062/
        if ($driverErrorCode == 1062) {
            $out->make_wrong_resp("Информация для кураторов с сочетанием заголовка {$in->info['header']} и страницы {$in->info['page']} уже существует");
        } else {
            $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
        }
    }

    $stmt->closeCursor();
    unset($stmt);

    // Получение ID созданной записи
    $in->id = $pdo->lastInsertId();
    if (!$in->id) $out->make_wrong_resp("Произошла ошибка при создании информации для кураторов");

    // Ответ
    $out->success = "1";
    $out->id = $in->id;
    $out->make_resp("");
}

// Обновление строки информации для кураторов
if ($in->action == "update") {
    // Валидация $in->id
    if (((string)(int)$in->id) !== ((string)$in->id) || (int)$in->id <= 0) $out->make_wrong_resp("Id информации для кураторов задан некорректно");

    $changes = []; // Словарь с валидированными изменениями

    // Валидация содержимого $in->info[], если поле указано, то валидируем и добавляем в список изменений
    // Валидация header
    if (isset($in->info['header'])) {
        if (!(is_string($in->info['header']) && mb_strlen($in->info['header']) <= 100)) $out->make_wrong_resp("Поле 'header' задано некорректно");
        $changes['header'] = $in->info['header'];
    }

    // Валидация body
    if (isset($in->info['body'])) {
        if (!(is_string($in->info['body']) && mb_strlen($in->info['body']) <= 16777215)) $out->make_wrong_resp("Поле 'body' задано некорректно");
        $changes['body'] = $in->info['body'];
    }

    // Валидация public
    if (isset($in->info['public'])) {
        if (((string)(int)$in->info['public']) !== ((string)$in->info['public'])) $out->make_wrong_resp("Поле 'public' задано некорректно");
        if (!in_array($in->info['public'], [0, 1])) $out->make_wrong_resp("Поле 'public' задано некорректно");
        $changes['public'] = $in->info['public'];
    }

    // Валидация page
    if (isset($in->info['page'])) {
        if (((string)(int)$in->info['page']) !== ((string)$in->info['page'])) $out->make_wrong_resp("Поле 'page' задано некорректно");
        if (!(0 < $in->info['page'] && $in->info['page'] <= 5)) $out->make_wrong_resp("Поле 'page' задано некорректно");
        $changes['page'] = $in->info['page'];
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
    $params['id'] = $in->id;
    $params['whoChanged'] = $user_vk_id;

    // Подготовка запроса для обновления записи
    $stmt = $pdo->prepare("UPDATE `info` SET $values, `who_changed` = :whoChanged, `when_changed` = NOW() WHERE `id` = :id")
    or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');

    // Выполнение запроса для обновления записи
    $execute = $stmt->execute($params);

    // Проверка ошибки выполнения запроса
    if (!$execute) {
        $driverErrorCode = $stmt->errorInfo()[1]; // В элементе по индексу 1 находится код ошибки драйвера

        // Если ошибка произошла из-за того, что такой доступ уже существует - обрабатываем с конкретным текстом ошибки, иначе возвращаем общую ошибку БД
        // Error 1062: Duplicate entry for key: https://mariadb.com/kb/en/e1062/
        if ($driverErrorCode == 1062) {
            $out->make_wrong_resp("Информация для кураторов с сочетанием заголовка {$in->info['header']} и страницы {$in->info['page']} уже существует");
        } else {
            $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
        }
    }

    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Информация с ID {$in->id} не найдена");

    $stmt->closeCursor();
    unset($stmt);

    // Ответ
    $out->success = "1";
    $out->make_resp("");
}

// Удаление строки информации для кураторов
if ($in->action == "delete") {
    // Валидация $in->id
    if (((string)(int)$in->id) !== ((string)$in->id) || (int)$in->id <= 0) $out->make_wrong_resp("ID информации для кураторов задан некорректно");

    // Подготовка запроса для удаления записи
    $stmt = $pdo->prepare("DELETE FROM `info` WHERE `id` = :id")
    or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');

    // Выполнение запроса для удаления записи
    $stmt->execute(['id' => $in->id]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');

    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Информация с ID {$in->id} не найдена");

    $stmt->closeCursor();
    unset($stmt);

    // Ответ
    $out->success = "1";
    $out->make_resp("");
}
