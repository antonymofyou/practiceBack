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
     *  - workTimeSum - общее время работы за дату
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
if (!in_array($user['type'], ['Админ', 'Сотрудник'])) $out->make_wrong_resp('Нет доступа'); // Какой тип пользователей бывает?

//--------------------------------Валидация $in->managerId и получение имён сотрудников
if (empty($in->managerId)) {//Если входной массив пустой, то получаем имя текущего пользователя
    $params = [
        'id' => $user['id']
    ];
        
} else {
    $params = [
        'id' => $in->managerId
    ];
}
$stmt = $pro->prepare($query);
$stmt->execute($params);
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Сотрудник с id {$params['id']} не найден");
$data = $stmt->fetch(PDO::FETCH_ASSOC);
$managerData['name'] = $data['name'];
$stmt->closeCursor(); unset($stmt, $data);

//--------------------------------Валидация $in->filterStartDate
$filterStartDate = date_create($in->filterStartDate, new DateTimeZone("Europe/Moscow"));
if (!$filterStartDate && !empty($in->filterStartDate)) $out->make_wrong_resp("Параметр 'filterStartDate' задан некорректно");
else $filterStartDate = date_create('1000-01-01', new DateTimeZone("Europe/Moscow"));
$filterStartDate = $filterStartDate->format('Y-m-d');

//--------------------------------Валидация $in->filterEndDate
$filterEndDate = date_create($in->filterEndDate, new DateTimeZone("Europe/Moscow"));
if (!$filterEndDate && !empty($in->filterStartDate)) $out->make_wrong_resp("Параметр 'filterEndDate' задан некорректно");
else $filterEndDate = date_create('3000-01-01', new DateTimeZone("Europe/Moscow"));
if($filterStartDate > $filterEndDate && !empty($fin->filterStartDate)) $out->make_wrong_resp("Параметр 'filterEndDate' задан некорректно");
$filterEndDate = $filterEndDate->format('Y-m-d');

//--------------------------------Получение текущей даты и времени
$nowDateUnformatted = date_create(null, new DateTimeZone("Europe/Moscow"));
$nowDate = $nowDateUnformatted->format('Y-m-d');
$nowTime = $nowDateUnformatted->format('H:i:s');

//--------------------------------Заполнение dataByPeriods
$dataByPeriods = [];
$dataByDate = [];
$managerData = [];
if (empty($in->managerId)) { //Если входной массив пустой, то получаем график текущего пользователя
    //получение периодов
    $queryPeriods = "SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
		FROM `managers_job_periods` 
		WHERE `managers_job_periods`.`manager_id` = :manager_id
		AND `managers_job_periods`.`for_date` >= :filter_start_date
		AND `managers_job_periods`.`for_date` <= :filter_end_date;";
	$paramsPeriods = [
		'manager_id' => $user['id'],
		'filter_start_date' => $filterStartDate,
		'filter_end_date' => $filterEndDate,
	];

    $stmt = $pdo->prepare($queryPeriods) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
    $stmt->execute($paramsPeriods) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
    while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($data['for_date'] == $nowDate) $managerData['isOnline'] = '1';
        $dataByPeriods[] = $data;
    }
    $stmt->closeCursor(); unset($stmt);
}
else{ //Если входной массив не имеет 0 и не пустой, то получаем график всех перечисленных сотрудников
    //получение периодов
    $queryPeriods = "SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
		FROM `managers_job_periods` 
		WHERE `managers_job_periods`.`manager_id`= :manager_id
		AND `managers_job_periods`.`for_date` >= :filter_start_date
		AND `managers_job_periods`.`for_date` <= :filter_end_date;";
    $paramsPeriods = [
        'manager_id' => $id,
        'filter_start_date' => $filterStartDate,
        'filter_end_date' => $filterEndDate,
    ];
    $stmt = $pdo->prepare($queryPeriods) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (7)');
    $stmt->execute($paramsPeriods) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (7)');
    while($data = $stmt->fetch(PDO::FETCH_ASSOC)) $managerShedule[$id]['dataByPeriods'][] = $data;
    $stmt->closeCursor(); unset($stmt);
}

//--------------------------------Заполнение dataByDate
$stmt = $pdo->prepare("SELECT `managers_job_reports`.`work_time`
		FROM `managers_job_reports` 
		WHERE `managers_job_reports`.`manager_id`= :manager_id
		AND `managers_job_reports`.`for_date` = :for_date;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');

$datesByManager = [];
foreach($managerShedule as $manager){
    foreach($manager['dataByPeriods'] as $data) if(!in_array($data['for_date'], $datesByManager)) $datesByManager[$data['manager_id']] = $data['for_date'];
}
foreach($datesByManager as $id => $date){
    $stmt->execute([
        'manager_id' => $id,
        'for_date' => $date
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (8)');
    if($stmt->rowCount() == 0) {
        $managerShedule[$id]['dataByDate'][$date] = ['0', '0'];
    }
    else{
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $managerShedule[$id]['dataByDate'][$date] = [$data['work_time'], '1'];
    }
    $stmt->closeCursor(); unset($stmt);
}

//--------------------------------Проверка,находится ли сотрудник онлайн
$stmt = $pdo->prepare("SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
		FROM `managers_job_periods` 
		WHERE `managers_job_periods`.`manager_id`= :manager_id
		AND `managers_job_periods`.`for_date` = :now_date
		AND `managers_job_periods`.`period_start` <= :now_time
		AND `managers_job_periods`.`period_end` >= :now_time;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (9)');
foreach(array_keys($managerShedule) as $id){
    $stmt->execute([
        'manager_id' => $id,
        'now_date' => $nowDate,
        'now_time' => $nowTime,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (9)');
    if ($stmt->rowCount() > 0) $managersInfo[$user['id']]['isOnline'] = '1';
    else $managersInfo[$user['id']]['isOnline'] = '0';
    $stmt->closeCursor(); unset($stmt);
}
//--------------------------------Формирование ответа
$out->success = '1';
$out->managerShedule = $managerShedule;
$out->managersInfo = (object) $managersInfo;
$out->make_resp('');