<?php // Получение списка общих и уникальных вопросов

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class JobGetShedule extends MainRequestClass {
	public $managersId = []; // ID менеджеров, по которым нужно вывести расписание, при наличии нулевого значения выводятся все расписания, доступно если пользователь - админ, 
                            //при пустом значении выводится расписание текущего пользователя
	public $filterStartDate = ''; // Дата начала за какой период нужно вывести расписание
	public $filterEndDate = ''; // Дата конца за какой период нужно вывести расписание
	
}
$in = new JobGetShedule();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class JobGetSheduleResponse extends MainResponseClass {
	/*
     * Массив словарей, где каждый словарь имеет ключ ID менеджера и имеет следующие поля:
     *     - dataByPeriods - Массив словарей, где каждый словарь имеет следующие поля:
     *       - managerId - идентификатор менеджера
     *       - forDate - на какую дату приходятся периоды
     *       - periodStart - начало рабочего периода
     *       - periodEnd - конец рабочего периода
     *       - createdAt - когда создан
     *       - updatedAt - когда изменен
	 *  - dataByDate - Массив словарей, где каждый словарь имеет следующие поля:
     *       - workTimeSum - общее время работы за дату
     *       - haveReport - есть ли отчет за дату
     *       - isOnline - работает ли в данный момент сотрудник
     */
    public $managerShedule = []; // массив словарей с общими вопросами
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

//--------------------------------Валидация $in->managersId
if (!empty($in->managersId)){
    foreach($in->managersId as $id)
    $stmt = $pdo->prepare("
        SELECT `id`
        FROM `managers`
        WHERE `id` = :id
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'id' => $id
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Сотрудник с id {$id} не найден");
    $stmt->closeCursor(); unset($stmt);
}

//--------------------------------Валидация $in->filterStartDate
if (!($filterStartDate = date_create($in->filterStartDate)) && !empty($in->filterStartDate)) $out->make_wrong_resp("Параметр 'filterStartDate' задан некорректно");

//--------------------------------Валидация $in->filterEndDate
if (!($filterEndDate = date_create($in->filterEndDate)) && !empty($in->filterEndDate)) $out->make_wrong_resp("Параметр 'filterEndDate' задан некорректно");
if($filterStartDate > $filterEndDate && !empty($fin->filterStartDate)) $out->make_wrong_resp("Параметр 'filterEndDate' задан некорректно");

//--------------------------------Получение расписания по сотрудникам
$periods = [];
if (empty($in->managersId)) {
    $queryPeriods = "SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
		FROM `managers_job_periods` 
		WHERE `managers_job_periods`.`manager_id`= :manager_id;";
	$paramsPeriods = [
		'manager_id' => $user['id'],
	];

    $stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    $stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (3)');
    while($data = $stmt->fetch(PDO::FETCH_ASSOC)) $periods[$data['manager_id']] = $data;
}
elseif(array_search('0', $in->managersId)){
	$queryPeriods = "SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
		FROM `managers_job_periods`;";
    $paramsPeriods = [];

    $stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    $stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (3)');
    while($data = $stmt->fetch(PDO::FETCH_ASSOC)) $periods[$data['manager_id']] = $data;
}
else{
    $queryPeriods = "SELECT `managers_job_periods`.`id`, `managers_job_periods`.`manager_id`, `managers_job_periods`.`for_date`, `managers_job_periods`.`period_start`, `managers_job_periods`.`period_end`
		FROM `managers_job_periods` 
		WHERE `managers_job_periods`.`manager_id`= :manager_id;";
    
    foreach($in->managersId as $id){
        $paramsPeriods = [
            'manager_id' => $id,
        ];
        $stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
        $stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (3)');
        $periods[$id] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    
}
$stmt->closeCursor(); unset($stmt);

//--------------------------------Формирование ответа
$out->success = '1';
$out->questionsList = $questionsList;
$out->questionsListUnique = $questionsListUnique;
$out->make_resp('');