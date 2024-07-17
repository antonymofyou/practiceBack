<?php

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса для получения информации о домашнем задании
class HomeTaskCheck extends MainRequestClass {
	public $htNumber = ''; // идентификатор домашнего задания, которое нужно отобразить
	
}
$in = new HomeTasksGetById();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class HomeTasksCheckResponse extends MainResponseClass {
	/*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - userName - имя пользователя
     *     - userSurname - фамилия пользователя
     *     - userCurator - идентификатор куратора
     *     - userVkId - идентификатор пользователя
     *     - userBlocked - заблокирован ли пользователь
     *     - userTarifNum - 
     *     - curator - фамилия и имя куратора 
     *     - htUserDateP1 - Время выполнений первой части задания
     *     - htUserTimeP1 - Время выполнений первой части задания
     *     - htUserDateP2 - Время выполнений второй части задания
     *     - htUserTimeP2 - Время выполнений второй части задания
     *     - htUserMaxballovP1 - Максимальное кол-во баллов для первой части задания
     *     - htUserMaxballovP2 - Максимальное кол-во баллов для второй части задания
     *     - htUserBallovP1 - Полученное кол-во баллов для первой части задания
     *     - htUserBallovP2 - Полученное кол-во баллов для второй части задания
     *     - htUserStatusP1 - статус выполнения первой части задания
     *     - htUserStatusP2 - статус выполнения второй части задания
	 * 
     */
    public $users = []; // массив словарей с данными пользователей, которые привязаны к этому заданию(если пользователь - куратор, то выводятся пользователи только этого куратора и авторитарных)

    /*
     * Словарь, который имеет следующие поля:
     *     - deadlineDate - дата окончания задания
     *     - deadlineTime - время окончания задания
     *     - deadlineDateTime - дата и время окончания задания
     *     - isProbnik - является ли задание пробником
     */
    public $homeTask = []; // данные об окончании задания и является ли задание пробником

/*
     * Словарь, который имеет следующие поля:
     *     - htNum - номер задания
     *     - htStatus - статус проверки задания
     *     - ccCheckDate - дата проверки задания
     *     - ccCheckTime - время проверки задания
     *     - htUserChecker - проверяющий пользователь
     *     - htNumber - идентификатор задания
     */
    public $crossCheck = []; // данные об окончании задания и является ли задание пробником
}

$out = new HomeTasksCheckResponse();

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
require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/manager_check_user.inc.php';
if (!(in_array($user_type, ['Админ', 'Куратор']))) $out->make_wrong_resp('Нет доступа');

//--------------------------------Валидация $in->htNumber
if (((string) (int) $in->htNumber) !== ((string) $in->htNumber) || (int) $in->vacancyId <= 0) $out->make_wrong_resp("Параметр 'vacancyId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `id`
    FROM `home_tasks`
    WHERE `id` = :id
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса');
$stmt->execute([
    'id' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Домашнее задание с ID {$in->htNumber} не найдена");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Получение списка пользователей
$for_curator='';
if ($user_type == 'Куратор') {
	$for_curator="AND (`users`.`user_curator`='".$user_id."' OR `users`.`user_curator_dz`='".$user_id."')  ";//Добавляем выдачу только учеников куратора, и авторитарных
}
$stmn->prepare("SELECT `users`.`user_name`, `users`.`user_surname`, `users`.`user_curator`, `users`.`user_vk_id`, `users`.`user_blocked`, `users`.`user_tarif_num`, CONCAT(  `curators`.`user_surname` ,  ' ', `curators`.`user_name` ) AS  `curator`, 
IF(`ht_user`.`ht_user_status_p2` IN('Готово','Самопров'),0,1) as `status_gotovo`,
`ht_user_date_p1`,
`ht_user`.`ht_user_time_p1`,
`ht_user_date_p2`,
`ht_user`.`ht_user_time_p2`,
`ht_user`.`ht_user_maxballov_p1`,
`ht_user`.`ht_user_maxballov_p2`,
`ht_user`.`ht_user_ballov_p1`,
`ht_user`.`ht_user_ballov_p2`,
`ht_user`.`ht_user_status_p1`,
`ht_user`.`ht_user_status_p2`
FROM `users`
LEFT JOIN  `users` AS  `curators` ON  (`users`.`user_curator` =  `curators`.`user_vk_id`  AND (`users`.`user_curator_dz` IS NULL OR `users`.`user_curator_dz`=0)) OR `users`.`user_curator_dz`=`curators`.`user_vk_id`
LEFT JOIN `ht_user` ON `users`.`user_vk_id`=`ht_user`.`user_id` AND `ht_user`.`ht_number`='".$htNumber."'
WHERE `users`.`user_type` IN ('Частичный','Интенсив','Админ', 'Куратор') ".$for_curator."
AND (`users`.`user_blocked`='0' OR  `users`.`user_blocked` IS NULL)
ORDER BY `curator` DESC,`users`.`user_tarif_num` DESC, `status_gotovo`, `ht_user`.`ht_user_status_p2` ASC, `ht_user`.`ht_user_date_p2`, `ht_user`.`ht_user_time_p2`, `ht_user`.`ht_user_status_p1` ASC, `users`.`user_blocked`, `users`.`user_surname`
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmn->execute() or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (1)');
if($stmn->rowCount() == 0) $out->make_wrong_resp("Ни один пользователь не был найден для задания [ID задания: {$in->htNumber}]");

$users = [];
while ($user = $stmn->fetch(PDO::FETCH_ASSOC)){
	$users[] = [
		'userName' => (string) $user['user_name'],
		'userSurname' => (string) $user['user_surname'],
		'userCurator' => (string) $user['user_curator'],
		'userVkId' => (string) $user['user_vk_id'],
		'userBlocked' => (string) $user['user_blocked'],
		'userTarifNum' => (string) $user['user_tarif_num'],
		'curator' => (string) $user['curator'],
		'htUserDateP1' => (string) $user['ht_user_date_p1'],
		'htUserTimeP1' => (string) $user['ht_user_time_p1'],
		'htUserDateP2' => (string) $user['ht_user_date_p2'],
		'htUserTimeP2' => (string) $user['ht_user_time_p2'],
		'htUserMaxballovP1' => (string) $user['ht_user_maxballov_p1'],
		'htUserMaxballovP2' => (string) $user['ht_user_maxballov_p2'],
		'htUserBallovP1' => (string) $user['ht_user_ballov_p1'],
		'htUserBallovP2' => (string) $user['ht_user_ballov_p2'],
		'htUserStatusP1' => (string) $user['ht_user_status_p1'],
		'htUserStatusP2' => (string) $user['ht_user_status_p2'],
	];
}
$stmt->closeCursor(); unset($stmt);
//--------------------------------Получение срока выполнения задания
$stmn = $pdo->prepare("SELECT `ht_deadline`, `ht_deadline_time`, `ht_deadline_cur`, `is_probnik`  
	FROM `home_tasks` 
	WHERE `ht_number`= :ht_number
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmn->execute(['ht_number' => $in->htNumber]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
$homeTask = $stmn->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$homeTask = [
	'htDeadline' => $homeTask['ht_deadline'],
	'htDeadlineTime' => $homeTask['ht_deadline_time'],
	'htDeadlineCur' => $homeTask['ht_deadline_cur'],
	'isProbnik' => $homeTask['is_probnik'],
];

//--------------------------------Получение перекрёстной проверки
$stmn = $pdo->prepare("SELECT `cross_check`.`ht_num`, `cross_check`.`ht_status`, `cross_check`.`cc_check_date`, `cross_check`.`cc_check_time`, 
			`ht_user`.`ht_user_checker`, `ht_user`.`ht_number`
		FROM `cross_check` 
		LEFT JOIN `ht_user` ON `cross_check`.`curator_vk_id`=`ht_user`.`ht_user_checker` 
			AND `cross_check`.`ht_num`=`ht_user`.`ht_number` AND `ht_user`.`ht_user_status_p2`='Проверен'
		WHERE `cross_check`.`checker_id`='".$user_vk_id."' AND `cross_check`.`ht_num`='".$htNumber."'
		LIMIT 1;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmn->execute(['ht_number' => $in->htNumber]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
$crossCheck = $stmn->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$crossCheck = [
	'htNum' => $crossCheck['ht_num'],
	'htStatus' => $crossCheck['ht_status'],
	'ccCheckDate' => $crossCheck['cc_check_date'],
	'ccCheckTime' => $crossCheck['cc_check_time'],
	'htUserChecker' => $crossCheck['ht_user_checker'],
	'htNumber' => $crossCheck['ht_number'],
];
//--------------------------------Формирование ответа
$out->success = '1';
$out->users = $users;
$out->homeTask = (object) $homeTask;
$out->crossCheck = (object) $crossCheck;
$out->make_resp('');