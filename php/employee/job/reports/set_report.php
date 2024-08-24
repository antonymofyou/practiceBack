<?php //---Создание, удаление и обновление отчётов

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('UTC'); // Установка нулевого часового пояса для верности рассчётов с функцией date() для отработанного времени, используется при $action == create

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class JobReportsGetReport extends MainRequestClass {
    public $reportId = ''; //Идентификатор отчёта для удаления или обновления
    public $managerId = ''; //Идентификатор сотрудника, отчёт которого надо создать, при пустом поле принимается id текущего пользователя
    public $forDate = ''; //Дата, за которую нужно создать отчёт
    public $report = ''; //Отчёт
    public $action = ''; //Тип действия: create - создать отчёт, delete - удалить отчёт, update - обновить отчёт
}
$in = new JobReportsGetReport();
$in->from_json(file_get_contents('php://input'));


class JobReportsGetReportResponse extends MainResponseClass {
    /* Словарь со следующими полями:
    - reportId    - Идентификатор отчёта
    - managerId   - Идентификатор сотрудника, которому принадлежит отчёт
    - forDate     - Дата, за которую написан отчёт
    - workTime    - Всего отработано времени за день отчёта
    - report      - Отчёт
    - createdAt   - Дата и время создания отчёта
    - updatedAt   - Дата и время последнего обновления отчёта
    */
    public $report = []; //Словарь созданного или обновлённого отчёта, в случае удаления возращается пустой словарь
}
$out = new JobReportsGetReportResponse();

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

//---Проверка пользователя: Если передан id, то проверяем на админа, иначе считаем id авторизованного пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/manager_check_user.inc.php';
if(!empty($in->managerId)) {
    if ($user['type'] == 'Админ') $out->make_wrong_resp('Ошибка доступа'); 
} else $in->managerId = $user['id'];


//Валидация действия
if(!in_array($in->action, ['create', 'delete', 'update'])) $out->make_wrong_resp('Неверное действие');

//---Удаление отчёта
if($in->action == "delete") {
    
    //Валидация reportId и проверка возможности удаления
    if (((string) (int) $in->reportId) !== ((string) $in->reportId) || (int) $in->reportId <= 0) $out->make_wrong_resp("Параметр 'reportId' задан неверно");
    $stmt = $pdo->prepare("
        SELECT `id`, `manager_id`, `created_at`
        FROM `managers_job_reports`
        WHERE `id` = :reportId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'reportId' => $in->reportId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Отчёт не найден");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); unset($stmt);

    if ($data['manager_id'] == $user['id'] && (int) date_diff(date_create(), date_create($data['created_at']))->format('%r%a') <= -2) $out->make_wrong_resp('Нельзя удалять отчёт, если прошло более двух дней');

    //Удаление отчёта
    $stmt = $pdo->prepare("
        DELETE FROM `managers_job_reports`
        WHERE `id` = :reportId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    $stmt->execute([
        'reportId' => $in->reportId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    $stmt->closeCursor(); unset($stmt);

    $out->success = "1";
    $out->make_resp('');
}

//---Создание отчёта
if($in->action == 'create') { 

    //Валидация managerId
    if (((string) (int) $in->managerId) !== ((string) $in->managerId) || (int) $in->managerId <= 0) $out->make_wrong_resp("Параметр 'managerId' задан неверно");
    $stmt = $pdo->prepare("
        SELECT `id`
        FROM `managers`
        WHERE `id` = :managerId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
    $stmt->execute([
        'managerId' => $in->managerId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Сотрудник с номером $in->managerId не найден");
        $stmt->closeCursor(); unset($stmt);

    //Валидация forDate
    if (!is_string($in->forDate) || empty($in->forDate)) $out->make_wrong_resp("Параметр 'forDate' задан неверно или не задан");
    //Нельзя создать отчёт за день, который ещё не наступил
    if ((int) date_diff(date_create(date('Y-m-d')), date_create($in->forDate))->format('%r%a') > 0) $out->make_wrong_resp('Нельзя создать отчёт за день, который ещё не наступил');

    //Проверка, существует ли уже отчёт с переданным id сотрудника и датой
    $stmt = $pdo->prepare("
        SELECT `id`
        FROM `managers_job_reports`
        WHERE `manager_id` = :managerId AND `for_date` = :forDate;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');
    $stmt->execute([
        'managerId' => $in->managerId,
        'forDate' => $in->forDate
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
    if ($stmt->rowCount() != 0) $out->make_wrong_resp("Ошибка: Отчёт уже существует");
        $stmt->closeCursor(); unset($stmt);

    //Расчёт workTime, формируем запрос на начало и конец периодов за день
    $stmt = $pdo->prepare("
        SELECT `period_start` AS `start`, `period_end` as `end`
        FROM `managers_job_periods`
        WHERE `manager_id` = :managerId AND `for_date` = :forDate;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
    $stmt->execute([
        'managerId' => $in->managerId,
        'forDate' => $in->forDate
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');
    //Получение разницу между началом и концом периода
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Отсутствуют рабочие периоды за эту дату");
    $diffs = []; //Массив с разницами
    while ($diff = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $diffs[] = strtotime($diff["end"]) - strtotime($diff["start"]); //сравнение времени в unix timestamp
    }
    $stmt->closeCursor(); unset($stmt);

    $workTime = date('H:i', array_sum($diffs)); //Общее отработанное время за день в формате часы:минуты

    //Валидация report
    if(!empty($in->report)) {
        if (!is_string($in->report)) $out->make_wrong_resp("Параметр 'report' задан неверно");
    } else $in->report = null; //Если отчёт не передали, то задаём его как null

    //Создание данных в БД
    $stmt = $pdo->prepare("
        INSERT INTO `managers_job_reports`
        (`id`, `manager_id`, `for_date`, `work_time`, `report`)
        VALUES (NULL, :managerId, :forDate, :workTime, :report);
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (7)');
    $stmt->execute([
        'managerId' => $in->managerId,
        'forDate' => $in->forDate,
        'workTime' => $workTime,
        'report' => $in->report
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (7)');

    //Получение ID созданного отчёта, чтобы вернуть его данные
    $reportId = $pdo->lastInsertId('id');
    if($reportId) $out->make_wrong_resp('Произошла ошибка при добавлении отчёта'); 
    $in->reportId = $reportId;
} 

//---Обновление отчёта
if ($in->action == 'update') {

    //Валидация reportId
    if (((string) (int) $in->reportId) !== ((string) $in->reportId) || (int) $in->reportId <= 0) $out->make_wrong_resp("Параметр 'reportId' задан неверно");
    $stmt = $pdo->prepare("
        SELECT `id`, `manager_id`, `for_date`, `created_at`
        FROM `managers_job_reports`
        WHERE `id` = :reportId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');
    $stmt->execute([
        'reportId' => $in->reportId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (8)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Отчёт не найден");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); unset($stmt);

    //Сравнение текущей даты с датой создания отчёта. Форматирование с помощью функции DateInterval::format с стилем: "%r%a", где '%r' - знак минус при отрицательном результате date_diff(...), '%a' - разница в количестве полных дней.
    if ($data['manager_id'] == $user['id'] && (int) date_diff(date_create(), date_create($data['created_at']))->format('%r%a') <= -2) $out->make_wrong_resp('Нельзя обновить отчёт, если прошло более двух дней');

    //Валидация report
    if(empty($in->report)) {
        $out->make_wrong_resp("Параметр 'report' не задан");
    } else if (!is_string($in->report)) $out->make_wrong_resp("Параметр 'report' задан неверно");
    
    //Формирование запроса на обновление данных сотрудника и проведение
    $stmt = $pdo->prepare("
        UPDATE `managers_job_reports` 
        SET `report` = :report 
        WHERE `id` = :reportId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (10)');
    $stmt->execute([
        'reportId' => $in->reportId,
        'report' => $in->report
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (10)');
    $stmt->closeCursor(); unset($stmt);
} 

//Получение данных созданного/обновлённого отчёта в возврат
$stmt = $pdo->prepare("
    SELECT `id`, `manager_id`, `for_date`, `work_time`, `report`, `created_at`, `updated_at`
    FROM `managers_job_reports`
    WHERE `id` = :reportId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (11)');
$stmt->execute([
    'reportId' => $in->reportId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (11)');
if($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Не найден отчёт с ID $in->reportId");
$report = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Формирование ответа
$out->report = [
    'reportId' => (string) $report['id'],
    'managerId' => (string) $report['manager_id'],
    'forDate' => (string) $report['for_date'],
    'workTime' => (string) $report['work_time'],
    'report' => (string) $report['report'],
    'createdAt' => (string) $report['created_at'],
    'updatedAt' => (string) $report['updated_at']
];

$out->success = "1";
$out->make_resp('');