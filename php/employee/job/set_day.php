<?php //---Создание, обновление, удаление дня

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class JobSetDay extends MainRequestClass {
    public $action = ''; // Тип действия: create - создать рабочий день, delete - удалить рабочий день, update - обновить рабочий день
    public $dayId = ''; // ID дня, для которого нужно выполнить действие, не требуется при $action == 'create'

    /*  Словарь, который может содержать следующие поля:
     *  - date - дата дня в формате yyyy-mm-dd (доступно при $action == 'create' обязательно, при 'update' не обязательно)
     *  - spentTime - сколько времени сотрудник потратил за день в минутах (доступно при $action == 'update', не обязательно)
     *  - report - текст отчёта (доступно при $action == 'update', не обязательно)
     *  - isWeekend - является ли этот день выходным (0/1) (доступно при $action == 'create' or 'update', обязательно)
     *  - comment - комментарий для дня (доступно при $action == 'create' or 'update', обязательно)
     */
    public $setDay = []; // данные для создания/обновления дня (при $action == 'delete' пустой словарь)
}
$in = new JobSetDay();
$in->from_json(file_get_contents('php://input'));

//---Класс ответа
$out = new MainResponseClass();

//---Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//---Сотрудник может изменять только свои дни, поэтому дальше используется ID текущего пользователя из переменной $user['id']
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/manager_check_user.inc.php';

//Валидация действия
if(!in_array($in->action, ['create', 'delete', 'update'])) $out->make_wrong_resp('Неверное действие');

//---Удаление рабочего дня
if($in->action == "delete") {
    
    //Валидация dayId
    if (((string) (int) $in->dayId) !== ((string) $in->dayId) || (int) $in->dayId <= 0) $out->make_wrong_resp("Параметр 'dayId' задан неверно");
    $stmt = $pdo->prepare("
        SELECT `id`, `manager_id`
        FROM `managers_job_days`
        WHERE `id` = :dayId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'dayId' => $in->dayId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Отчёт не найден");
    $day = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); unset($stmt);

    //Проверка соответствия пользователя дня и текущего пользователя
    if ($day['manager_id'] != $user['id']) $out->make_wrong_resp('Нельзя удалить отчёт другого сотрудника');

    //Удаление отчёта
    $stmt = $pdo->prepare("
        DELETE FROM `managers_job_days`
        WHERE `id` = :dayId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    $stmt->execute([
        'dayId' => $in->dayId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    $stmt->closeCursor(); unset($stmt);

    $out->success = "1";
    $out->make_resp('');
}

//---Создание рабочего дня
if($in->action == "create") {

    //Валидация setDay['date']
    if (!is_string($in->setDay['date']) || empty($in->setDay['date'])) $out->make_wrong_resp("Параметр 'date' задан неверно или не задан");

    //Проверка, существует ли уже день 
    $stmt = $pdo->prepare("
        SELECT `id`
        FROM `managers_job_days`
        WHERE `manager_id` = :userId AND `for_date` = :date;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    $stmt->execute([
        'userId' => $user['id'],
        'date' => $in->setDay['date']
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
    if ($stmt->rowCount() != 0) $out->make_wrong_resp("Ошибка: Отчёт уже существует");
    $stmt->closeCursor(); unset($stmt);

    //Валидация setDay['spentTime']
    if (!empty($in->setDay['spentTime'])) {
        if (((string) (int) $in->setDay['spentTime']) !== ((string) $in->setDay['spentTime']) || (int) $in->setDay['spentTime'] <= 0) $out->make_wrong_resp("Параметр 'spentTime' задан неверно");
    }

    //Валидация setDay['report'], нельзя создать отчёт за день, который ещё не наступил. date_diff(...)->format('%r%a') возвращает разницу между текущей датой на сервере(гггг-мм-дд) и датой рабочего дня(гггг-мм-дд) в формате полных дней(знак $a). В случае, если текущая дата больше, чем дата рабочего дня, ставится знак минус(%r)
    if (!empty($in->setDay['report'])) {
        if ((int) date_diff(date_create(date('Y-m-d')), date_create($in->setDay['date']))->format('%r%a') > 0) $out->make_wrong_resp('Нельзя создать отчёт за день, который ещё не наступил');
        if (!is_string($in->setDay['report'])) $out->make_wrong_resp("Параметр 'report' задан неверно");
    }

    //Валидация setDay['isWeekend']
    if (!isset($in->setDay['isWeekend']) || !in_array($in->setDay['isWeekend'], [0, 1])) $out->make_wrong_resp("Параметр 'isWeekend' не задан или задан неверно");

    //Валидация setDay['comment']
    if (!empty($in->setDay['comment'])) {
        if (!is_string($in->setDay['comment'])) $out->make_wrong_resp("Параметр 'comment' задан неверно");
    }

}

