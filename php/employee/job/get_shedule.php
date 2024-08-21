<?php // Получение графика сотрудника

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class JobGetShedule extends MainRequestClass {
	public $managerId = ''; // ID менеджера, по которому нужно вывести расписание, 
                            //при пустом значении выводится расписание текущего пользователя
	public $filterStartDate = ''; // Дата начала за какой период нужно вывести расписание в формате yyyy-mm-dd
	public $filterEndDate = ''; // Дата конца за какой период нужно вывести расписание в формате yyyy-mm-dd
	
}
$in = new JobGetShedule();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class JobGetSheduleResponse extends MainResponseClass {
	/*
     * Массив словарей, где каждый словарь имеет имеет следующие поля:
     *  - managerId - идентификатор менеджера
     *  - forDate - на какую дату приходятся периоды в формате yyyy-mm-dd
     *  - periodStart - начало рабочего периода
     *  - periodEnd - конец рабочего периода
     *  - createdAt - когда создан
     *  - updatedAt - когда изменен
	 *  
     */
    public $dataByPeriods = []; // массив словарей с данными для графика
    /* 
     * Массив словарей, где каждый словарь имеет следующие поля:
     *  - workTime - общее время работы за дату
     *  - haveReport - есть ли отчет за дату
     */
    public $dataByDate = []; // массив словарей с данными для графика
    /* 
     * Cловарь, который имеет следующие поля:
     *  - name - имя и фамилия сотрудника
     *  - isOnline - находится ли сотрудник онлайн
     */
    public $managerData = []; // массив словарей с данными для графика
}

$out = new JobGetSheduleResponse();

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
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user_manager.inc.php';
if (!in_array($user['type'], ['Админ', 'Сотрудник'])) {
    $out->make_wrong_resp('Нет доступа');
}

//--------------------------------Валидация $in->managerId и получение имени сотрудника
$managerData = [];
$managerId = '';
$query = "
    SELECT `managers`.`name`
    FROM `managers`
    WHERE `managers`.`id` = :id;
";
if (empty($in->managerId)) {//Если входной массив пустой, то получаем имя текущего пользователя
    $managerId = $user['id'];
        
} else {
    if ($user['type'] != 'Админ') {
        $out->make_wrong_resp('Вам нельзя смотреть чужое расписание');
    }
    $managerId = $in->managerId;
}
$stmt = $pdo->prepare($query);
$stmt->execute([
    'id' => $managerId,
]);
if ($stmt->rowCount() == 0) {
    $out->make_wrong_resp("Сотрудник с id {$managerId} не найден");
}
$data = $stmt->fetch(PDO::FETCH_ASSOC);
$managerData['name'] = $data['name'];
$stmt->closeCursor(); unset($stmt, $data);

//--------------------------------Валидация $in->filterStartDate
$filterStartDate = date_create($in->filterStartDate, new DateTimeZone("Europe/Moscow"));
if (!$filterStartDate && !empty($in->filterStartDate)) {
    $out->make_wrong_resp("Параметр 'filterStartDate' задан некорректно");
} else { 
    $filterStartDate = date_create('1000-01-01', new DateTimeZone("Europe/Moscow"));
}
$filterStartDate = $filterStartDate->format('Y-m-d');

//--------------------------------Валидация $in->filterEndDate
$filterEndDate = date_create($in->filterEndDate, new DateTimeZone("Europe/Moscow"));
if (!$filterEndDate && !empty($in->filterStartDate)) {
    $out->make_wrong_resp("Параметр 'filterEndDate' задан некорректно");
} else {
    $filterEndDate = date_create('3000-01-01', new DateTimeZone("Europe/Moscow"));
}
if($filterStartDate > $filterEndDate && !empty($fin->filterStartDate)) $out->make_wrong_resp("Параметр 'filterEndDate' задан некорректно");
$filterEndDate = $filterEndDate->format('Y-m-d');

//--------------------------------Получение текущей даты и времени
$nowDateUnformatted = date_create(null, new DateTimeZone("Europe/Moscow"));
$nowDate = $nowDateUnformatted->format('Y-m-d');
$nowTime = $nowDateUnformatted->format('H:i:s');

//--------------------------------Заполнение dataByPeriods
$dataByPeriods = [];
$query = "
    SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
    FROM `managers_job_periods` 
    WHERE `managers_job_periods`.`manager_id`= :manager_id
    AND `managers_job_periods`.`for_date` >= :filter_start_date
    AND `managers_job_periods`.`for_date` <= :filter_end_date;
";
$params = [
    'manager_id' => $managerId,
    'filter_start_date' => $filterStartDate,
    'filter_end_date' => $filterEndDate,
];
$stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute($params) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dataByPeriods[] = [
            'managerId' => $data['manager_id'],
            'forDate' => $data['for_date'],
            'periodStart' => $data['period_start'],
            'periodEnd' => $data['period_end'],
        ];
    }
    $stmt->closeCursor(); unset($stmt);

//--------------------------------Заполнение dataByDate
$dataByDate = [];
$stmt = $pdo->prepare("
    SELECT `managers_job_reports`.`work_time`
    FROM `managers_job_reports` 
    WHERE `managers_job_reports`.`manager_id`= :manager_id
    AND `managers_job_reports`.`for_date` = :for_date;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');

$usedDates = [];
foreach ($dataByPeriods as $period) {
    if (!in_array($period['forDate'], $usedDates)) {
        $usedDates[] = $period['forDate'];
    }
}
foreach($usedDates as $date){
    $stmt->execute([
        'manager_id' => $managerId,
        'for_date' => $date
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!empty($data)) {
        $dataByDate[] = [
            'workTime' => $data['work_time'],
            'haveReport' => '1',
        ];
    } else {
        $dataByDate[] = [
            'workTime' => '0',
            'haveReport' => '0',
        ];
    }
}
$stmt->closeCursor(); unset($stmt);

//--------------------------------Проверка,находится ли сотрудник онлайн
$stmt = $pdo->prepare("
    SELECT `managers_job_periods`.`id`
    FROM `managers_job_periods` 
    WHERE `managers_job_periods`.`manager_id`= :manager_id
    AND `managers_job_periods`.`for_date` = :now_date
    AND `managers_job_periods`.`period_start` <= :now_time
    AND `managers_job_periods`.`period_end` >= :now_time;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
$stmt->execute([
    'manager_id' => $managerId,
    'now_date' => $nowDate,
    'now_time' => $nowTime,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
if ($stmt->rowCount() > 0) {
    $managerData['isOnline'] = '1';
} else { 
    $managerData['isOnline'] = '0';
}
$stmt->closeCursor(); unset($stmt);
//--------------------------------Формирование ответа
$out->success = '1';
$out->dataByPeriods = $dataByPeriods;
$out->dataByDate = $dataByDate;
$out->managerData = (object) $managerData;
$out->make_resp('');