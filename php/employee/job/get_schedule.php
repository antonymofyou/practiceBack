<?php // Получение графика сотрудника

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class JobGetSchedule extends MainRequestClass {
    public $staffId = ''; // ID менеджера, по которому нужно вывести расписание, 
    //при пустом значении выводится расписание текущего пользователя
    public $filterStartDate = ''; // Дата начала за какой период нужно вывести расписание включительно в формате yyyy-mm-dd 
    public $filterEndDate = ''; // Дата конца за какой период нужно вывести расписание включительно в формате yyyy-mm-dd

}
$in = new JobGetSchedule();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class JobGetScheduleResponse extends MainResponseClass {
    /*
     * Массив словарей, где каждый словарь содержит следующие поля:
     *  - dayId - id дня
     *  - date - дата дня в формате yyyy-mm-dd
     *  - workTime - сколько времени потратил за день (пользователь заполняет самостоятельно)
     *  - report - текст отчета за дату (может быть пустой строкой, если отчет отсутствует)
     *  - reportId - id отчёта (может быть пустой строкой, если отчёт отсутствует)
     *  - isWeekend - является ли этот день выходным (0/1)
     *  - comment - комментарий для дня
     */
    public $days = [];

    /*
     * Словарь, где ключ это dayId (ID дня), а значение это массив словарей, где каждый словарь имеет следующие поля:
     *  - periodId - id периода
     *  - periodStart - начало рабочего периода
     *  - periodEnd - конец рабочего периода
     */
    public $periodsTimes = []; // словарь с данными периодов (сортировка по periodStart) (может быть пустым словарем, если периодов нет)
    
    /*
     * Cловарь, который имеет следующие поля:
     *  - staffId - ID сотрудника
     *  - userVkId - ID страницы VK сотрудника
     *  - name - имя и фамилия сотрудника
     *  - isWorkingTimeNow - находится ли сотрудник онлайн (исходя из графика на текущее время)
     */
    public $staffData = []; // данные сотрудника, для которого получен график

}

$out = new JobGetScheduleResponse();

//--------------------------------Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//--------------------------------Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/manager_check_user.inc.php';

//--------------------------------Валидация $in->staffId и получение имени  сотрудника
$staffData = [];
$staffId = '';
if (empty($in->staffId)) {//Если $in->staffId пустая, то получаем имя текущего пользователя
    $staffId = $user['id'];

} else {
    if ($user['type'] != 'Админ') {
        $out->make_wrong_resp('Вам нельзя смотреть чужое расписание');
    }
    $staffId = $in->staffId;
}
$stmt = $pdo->prepare("
    SELECT `managers`.`name`
    FROM `managers`
    WHERE `managers`.`id` = :id;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'id' => $staffId,
]) or $out->make_wrong_resp("Ошибка базы данных: выполнение запроса (1)");
if ($stmt->rowCount() == 0) {
    $out->make_wrong_resp("Сотрудник с id {$staffId} не найден");
}
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
$staffData['name'] = (string) $staff['name'];
$staffData['staffId'] = (string) $staffId;
$staffData['userVkId'] = (string) $user['id'];
$stmt->closeCursor();
unset($stmt, $data);

//--------------------------------Получение текущей даты и времени
$nowDateUnformatted = date_create(null, new DateTimeZone("Europe/Moscow"));
$nowDate = $nowDateUnformatted->format('Y-m-d');
$nowTime = $nowDateUnformatted->format('H:i:s');

//--------------------------------Валидация $in->filterStartDate
$filterStartDate = date_create($in->filterStartDate, new DateTimeZone("Europe/Moscow")); // при неправильной дате принимает значение false
if (!$filterStartDate && !empty($in->filterStartDate)) {
    $filterStartDate = date_create($nowDate, new DateTimeZone("Europe/Moscow")); // по умолчанию значение начала фильтра = текущая дата - 7 дней
    $filterStartDate->modify("-7 day");
}
$filterStartDate = $filterStartDate->format('Y-m-d');

//--------------------------------Валидация $in->filterEndDate
$filterEndDate = date_create($in->filterEndDate, new DateTimeZone("Europe/Moscow"));
if (!$filterEndDate && !empty($in->filterStartDate)) {
    $filterEndDate = date_create("3000-01-01", new DateTimeZone("Europe/Moscow"));
} elseif ($filterStartDate > $filterEndDate && !empty($fin->filterStartDate)) {
    $out->make_wrong_resp("Параметр 'filterEndDate' задан меньше начальной даты фильтра"); // конец фильтра не может быть меньше начала
}
$filterEndDate = $filterEndDate->format('Y-m-d');

//--------------------------------Заполнение periodsTimes
$periodsTimes = [];
$usedDates = []; // дни, которые есть в периодах у сотрудника

$stmt = $pdo->prepare("
    SELECT `managers_job_time_periods`.`id`, `managers_job_time_periods`.`period_start`, `managers_job_time_periods`.`period_end`, 
    `managers_job_days`.`date`, `managers_job_days`.`id` AS `day_id`
    FROM `managers_job_time_periods`
    LEFT JOIN `managers_job_days` ON `managers_job_days`.`id` = `managers_job_time_periods`.`day_id` 
    WHERE `managers_job_days`.`manager_id`= :manager_id
    AND `managers_job_days`.`date` >= :filter_start_date
    AND `managers_job_days`.`date` <= :filter_end_date;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'manager_id' => $staffId,
    'filter_start_date' => $filterStartDate,
    'filter_end_date' => $filterEndDate,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
while ($periodsTimesData = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!in_array($periodsTimesData['date'], $usedDates)) {
        $usedDates[] = $periodsTimesData['date']; // заполняем днями, которые были в выборке
    }

    $periodsTimes[$periodsTimesData['day_id']][] = [
        'periodId' => (string) $periodsTimesData['id'],
        'periodStart' => (string) $periodsTimesData['period_start'],
        'periodEnd' => (string) $periodsTimesData['period_end'],
    ];
}
$stmt->closeCursor();
unset($stmt);

//--------------------------------Заполнение days

$days = [];
$usedDatesFormatted =  join(", ", array_map(function ($element) { // форматируем даты для правильного запроса под формат - 'yyyy-mm-dd', 'yyyy-mm-dd', 'yyyy-mm-dd'
    return "'" . $element . "'";
},$usedDates));

$stmt = $pdo->prepare("
    SELECT `managers_job_days`.`id`, `managers_job_days`.`date`, `managers_job_days`.`report`, `managers_job_days`.`is_weekend`, `managers_job_days`.`comment`, 
    `managers_job_reports`.`id` AS `report_id`, `managers_job_reports`.`work_time` AS `work_time`  
    FROM `managers_job_days`
    LEFT JOIN `managers_job_reports` ON `managers_job_days`.date = `managers_job_reports`.`for_date`
    WHERE `managers_job_days`.`manager_id`= :manager_id
    AND `managers_job_days`.`date` IN ({$usedDatesFormatted});
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)'); // вставляем даты, которые были в выборке периодов

$stmt->execute([
    'manager_id' => $staffId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');

while ($daysData = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $days[] = [
        'dayId'=> (string) $daysData['id'],
        'date'=> (string) $daysData['date'],
        'workTime'=> (string) $daysData['work_time'],
        'report'=> (string) $daysData['report'],
        'reportId'=> (string) $daysData['report_id'],
        'isWeekend'=> (string) $daysData['is_weekend'],
        'comment'=> (string) $daysData['comment'],
    ];
}
$stmt->closeCursor();
unset($stmt);

//--------------------------------Проверка,находится ли сотрудник онлайн
$stmt = $pdo->prepare("
    SELECT `managers_job_time_periods`.`id`
    FROM `managers_job_days`
    JOIN `managers_job_time_periods` ON `managers_job_days`.`id` = `managers_job_time_periods`.`day_id`
    WHERE `managers_job_days`.`manager_id`= :manager_id
    AND `managers_job_days`.`date` = :now_date
    AND `managers_job_time_periods`.`period_start` >= :now_time
    AND `managers_job_time_periods`.`period_end` <= :now_time;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)'); // если есть период текущей даты и времени ставит у словаря staffData параметр isWorkingTimeNow значение 1 иначе 0
$stmt->execute([
    'manager_id' => $staffId,
    'now_date' => $nowDate,
    'now_time' => $nowTime,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
if ($stmt->rowCount() > 0) {
    $staffData['isWorkingTimeNow'] = '1';
} else {
    $staffData['isWorkingTimeNow'] = '0';
}
$stmt->closeCursor();
unset($stmt);
//--------------------------------Формирование ответа
$out->success = '1';
$out->days = $days;
$out->periodsTimes = $periodsTimes;
$out->staffData = (object) $staffData;
$out->make_resp('');