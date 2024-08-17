<?php // Получение списка общих и уникальных вопросов

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class JobGetShedule extends MainRequestClass {
	public $managersId = []; // ID менеджеров, по которым нужно вывести расписание, при наличии нулевого значения выводятся все расписания, доступно если пользователь - админ, 
                            //при пустом значении выводится расписание текущего пользователя
	public $filterStartDate = '1000-01-01'; // Дата начала за какой период нужно вывести расписание в формате yyyy-mm-dd
	public $filterEndDate = '3000-01-01'; // Дата конца за какой период нужно вывести расписание в формате yyyy-mm-dd
	
}
$in = new JobGetShedule();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class JobGetSheduleResponse extends MainResponseClass {
	/*
     * Массив словарей, где каждый словарь имеет ключ ID менеджера и имеет следующие поля:
     *  - dataByPeriods - Массив словарей, где каждый словарь имеет следующие поля:
     *       - manager_id - идентификатор менеджера
     *       - for_date - на какую дату приходятся периоды в формате yyyy-mm-dd
     *       - period_start - начало рабочего периода
     *       - period_end - конец рабочего периода
     *       - created_at - когда создан
     *       - updated_at - когда изменен
	 *  - dataByDate - Массив словарей, где каждый словарь имеет следующие поля:
     *       - workTimeSum - общее время работы за дату
     *       - haveReport - есть ли отчет за дату
     */
    public $managerShedule = []; // массив словарей с данными для графика
    /*
     * Словарь, где каждое поле имеет ключ ID менеджера и имеет следующие поля:
     *  - name - имя и фамилия
     *  - isOnline - находится ли сотрудник онлайн 
     */
    public $managersInfo = []; // словарь с данными имён для графика
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
if (!(in_array($user['type'], ['Админ'])) && !empty($in->managerId)) $out->make_wrong_resp('Нет доступа');

//--------------------------------Валидация $in->managersId и получение имён сотрудников
$managersInfo = [];
if (empty($in->managersId)){//Если входной массив пустой, то получаем имя текущего пользователя
    $stmt = $pdo->prepare("SELECT `managers`.`name`
        FROM `managers`
        WHERE `id` = :id
    ;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
        $stmt->execute([
            'id' => $user['id']
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
        if ($stmt->rowCount() == 0) $out->make_wrong_resp("Сотрудник с id {$id} не найден");
        while($data = $stmt->fetch(PDO::FETCH_ASSOC))$managersInfo[$user['id']]['name'] = $data['name'];
        $stmt->closeCursor(); unset($stmt);
}
elseif(array_search('0', $in->managersId)){//Если входной массив имеет 0, то получаем имена всех сотрудников
    $stmt = $pdo->prepare("SELECT `managers`.`name`, `managers`.`id`
    FROM `managers`
    ;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    $stmt->execute() or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Пустая таблица сотрудников");
    while($data = $stmt->fetch(PDO::FETCH_ASSOC))$managersInfo[$data['id']]['name'] = $data['name'];
    $stmt->closeCursor(); unset($stmt);
}
else{//Если входной массив не пустой и не имеет 0, то получаем имена перечисленных пользователей
    $stmt = $pdo->prepare("SELECT `managers`.`name`
    FROM `managers`
    WHERE `id` = :id;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    foreach($in->managersId as $id){
        if(((string) (int) $id) !== ((string) $id) || (int) $id < 0) $out->make_wrong_resp("Параметр managersId задан некорректно(Значение {$id})");
        $stmt->execute([
            'id' => $id
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
        if ($stmt->rowCount() == 0) $out->make_wrong_resp("Сотрудник с id {$id} не найден");
        while($data = $stmt->fetch(PDO::FETCH_ASSOC))$managersInfo[$id]['name'] = $data['name'];
        $stmt->closeCursor(); unset($stmt);
    } 
}

//--------------------------------Валидация $in->filterStartDate
if (!($filterStartDate = date_create($in->filterStartDate, new DateTimeZone("Europe/Moscow")))) $out->make_wrong_resp("Параметр 'filterStartDate' задан некорректно");
$filterStartDate = $filterStartDate->format('Y-m-d');

//--------------------------------Валидация $in->filterEndDate
if (!($filterEndDate = date_create($in->filterEndDate, new DateTimeZone("Europe/Moscow")))) $out->make_wrong_resp("Параметр 'filterEndDate' задан некорректно");
if($filterStartDate > $filterEndDate && !empty($fin->filterStartDate)) $out->make_wrong_resp("Параметр 'filterEndDate' задан некорректно");
$filterEndDate = $filterEndDate->format('Y-m-d');

//--------------------------------Получение текущей даты и времени
$nowDate = date_create(null, new DateTimeZone("Europe/Moscow"))->format('Y-m-d');
$nowTime = date_create(null, new DateTimeZone("Europe/Moscow"))->format('H:i:s');

//--------------------------------Заполнение dataByPeriods
$managerShedule = [];
if (empty($in->managersId)) { //Если входной массив пустой, то получаем график текущего пользователя
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

    $stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
    $stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (4)');
    while($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($data['for_date'] == $nowDate) $isOnline = '1';
        $managerShedule[$user['id']]['dataByPeriods'][] = $data;
    }
    $stmt->closeCursor(); unset($stmt);
}
elseif(array_search('0', $in->managersId)){ //Если входной массив имеет 0, то получаем график всех
    //получение периодов
	$queryPeriods = "SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
		FROM `managers_job_periods`
		WHERE `managers_job_periods`.`for_date` >= :filter_start_date
		AND `managers_job_periods`.`for_date` <= :filter_end_date;";
    $paramsPeriods = [
		'filter_start_date' => $filterStartDate,
		'filter_end_date' => $filterEndDate,
    ];

    $stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
    $stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (6)');
    while($data = $stmt->fetch(PDO::FETCH_ASSOC)) $managerShedule[$data['manager_id']]['dataByPeriods'][] = $data;
    $stmt->closeCursor(); unset($stmt);
}
else{ //Если входной массив не имеет 0 и не пустой, то получаем график всех перечисленных сотрудников
    //получение периодов
    $queryPeriods = "SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
		FROM `managers_job_periods` 
		WHERE `managers_job_periods`.`manager_id`= :manager_id
		AND `managers_job_periods`.`for_date` >= :filter_start_date
		AND `managers_job_periods`.`for_date` <= :filter_end_date;";
    
    foreach($in->managersId as $id){
        $paramsPeriods = [
            'manager_id' => $id,
            'filter_start_date' => $filterStartDate,
            'filter_end_date' => $filterEndDate,
        ];
        $stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (7)');
        $stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (7)');
        while($data = $stmt->fetch(PDO::FETCH_ASSOC)) $managerShedule[$id]['dataByPeriods'][] = $data;
        $stmt->closeCursor(); unset($stmt);
    }

    
}
$stmt->closeCursor(); unset($stmt);

//--------------------------------Заполнение dataByDate
/*  - dataByDate - Массив словарей, где каждый словарь имеет следующие поля:
     *       - workTimeSum - общее время работы за дату
     *       - haveReport - есть ли отчет за дату
     */
$stmt = $pdo->prepare("SELECT `managers_job_reports`.`work_time`
		FROM `managers_job_reports` 
		WHERE `managers_job_reports`.`manager_id`= :manager_id
		AND `managers_job_reports`.`for_date` = :for_date;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');

$datesByManager = [];
foreach($managerShedule as $manager){
    foreach($manager['dataByPeriods'] as $data) if(!in_array($data['for_date'], $datesByManager)) $datesByManager[$manager] = $data['for_date'];
}
foreach($datesByManager as $id => $date){
    $stmt->execute([
        'id' => $id,
        'for_date' => $date
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (8)');
    while($data = $stmt->fetch(PDO::FETCH_ASSOC))$managersInfo[$user['id']]['isOnline'] = '1';
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
        'id' => $id
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (9)');
    if ($stmt->rowCount() > 0) $managersInfo[$user['id']]['isOnline'] = '1';
    $stmt->closeCursor(); unset($stmt);
}
//--------------------------------Формирование ответа
$out->success = '1';
$out->managerShedule = $managerShedule;
$out->managersInfo = (object) $managersInfo;
$out->make_resp('');