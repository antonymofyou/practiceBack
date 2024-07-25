<?php // Получение списка общих и уникальных вопросов

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class HomeTaskDzProverkaList extends MainRequestClass {
	public $lessonNum = ''; // номер урока
	public $taskNum = ''; // номер задания
	
}
$in = new HomeTaskDzProverkaList();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class HomeTaskDzProverkaListResponse extends MainResponseClass {
	/*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - ticketId - идентификатор заявки
     *     - taskNumber - номер задания
     *     - status - статус вопроса
     *     - questName - название вопроса
     *     - comment - комментарий вопроса
     *     - flagWatched - прочитан ли вопрос
	 * 
     */
    public $questionsList = []; // массив словарей с общими вопросами

	/*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - ticketId - идентификатор заявки
     *     - taskNumber - номер задания
     *     - status - статус вопроса
     *     - questName - название вопроса
     *     - comment - комментарий вопроса
     *     - flagWatched - прочитан ли вопрос
	 * 
     */
    public $questionsListUnique = []; // массив словарей с уникальными вопросами
}

$out = new HomeTaskDzProverkaListResponse();

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
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!(in_array($user_type, ['Админ', 'Куратор']))) $out->make_wrong_resp('Нет доступа');

//--------------------------------Валидация $in->lessonNum
if (((string) (int) $in->lessonNum) !== ((string) $in->lessonNum) || (int) $in->lessonNum <= 0) $out->make_wrong_resp("Параметр 'lessonNum' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `lesson_number`
    FROM `tickets_dz`
    WHERE `lesson_number` = :lesson_number
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
    'lesson_number' => $in->lessonNum
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Урок с номером {$in->lessonNum} не найден");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Валидация $in->taskNum
if (((string) (int) $in->taskNum) !== ((string) $in->taskNum) || (int) $in->taskNum < 0) $out->make_wrong_resp("Параметр 'taskNum' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `task_number`
    FROM `tickets_dz`
    WHERE `task_number` = :task_number
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'task_number' => $in->taskNum
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Задание с номером {$in->taskNum} не найдено");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Получение общего списка вопросов
if ($in->taskNum != '0') {
	$query = "SELECT `tickets_dz`.`ticket_id`, `tickets_dz`.`task_number`, `tickets_dz`.`status`, `tickets_dz`.`quest_name`,
		`ticket_dz_user`.`user_vk_id` AS `flag_watched`, 
		`tickets_mess_dz`.`comment` 
		FROM `tickets_dz` 
		LEFT JOIN `ticket_dz_user` ON `ticket_dz_user`.`ticket_id`=`tickets_dz`.`ticket_id`  AND `ticket_dz_user`.`user_vk_id`= :user_vk_id
		LEFT JOIN `tickets_mess_dz` ON  `tickets_mess_dz`.`ticket_id`=`tickets_dz`.`ticket_id`
		WHERE `tickets_dz`.`lesson_number`= :lesson_num
		AND `tickets_dz`.`task_number`= :task_num 
		AND `tickets_dz`.`type`='Общий' 
		GROUP BY `ticket_id`
		ORDER BY `tickets_dz`.`when_changed` DESC;";
	$params = [
		'user_vk_id' => $user_vk_id,
		'lesson_num' => $in->lessonNum,
		'task_num' => $in->taskNum,
	];
}
else{
	$query = "SELECT `tickets_dz`.`ticket_id`, `tickets_dz`.`task_number`, `tickets_dz`.`status`, `tickets_dz`.`quest_name`,
		`ticket_dz_user`.`user_vk_id` AS `flag_watched`, 
		`tickets_mess_dz`.`comment` 
		FROM `tickets_dz` 
		LEFT JOIN `ticket_dz_user` ON `ticket_dz_user`.`ticket_id`=`tickets_dz`.`ticket_id`  AND `ticket_dz_user`.`user_vk_id`= :user_vk_id
		LEFT JOIN `tickets_mess_dz` ON  `tickets_mess_dz`.`ticket_id`=`tickets_dz`.`ticket_id`
		WHERE `tickets_dz`.`lesson_number`= :lesson_num
		AND `tickets_dz`.`type`='Общий' 
		GROUP BY `ticket_id`
		ORDER BY `tickets_dz`.`when_changed` DESC;";
	$params = [
		'user_vk_id' => $user_vk_id,
		'lesson_num' => $in->lessonNum,
	];
}

$stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');

$stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (3)');
$questionsList = [];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)){
	$questionsList[] = [
		'ticketId' => (string) $result['ticket_id'],
		'taskNumber' => (string) $result['task_number'],
		'status' => (string) $result['status'],
		'questName' => (string) $result['quest_name'],
		'comment' => (string) $result['comment'],
		'flagWatched' => (string) $result['flag_watched'],
	];
}
$stmt->closeCursor(); unset($stmt);

//--------------------------------Получение уникального списка вопросов
if ($in->taskNum != '0') {
	$query = "SELECT `tickets_dz`.`ticket_id`, `tickets_dz`.`task_number`, `tickets_dz`.`status`, `tickets_dz`.`quest_name`,
		`ticket_dz_user`.`user_vk_id` AS `flag_watched`, 
		`tickets_mess_dz`.`comment` 
		FROM `tickets_dz` 
		LEFT JOIN `ticket_dz_user` ON `ticket_dz_user`.`ticket_id`=`tickets_dz`.`ticket_id`  AND `ticket_dz_user`.`user_vk_id`= :user_vk_id
		LEFT JOIN `tickets_mess_dz` ON  `tickets_mess_dz`.`ticket_id`=`tickets_dz`.`ticket_id`
		WHERE `tickets_dz`.`lesson_number`= :lesson_num
		AND `tickets_dz`.`task_number`= :task_num 
		AND `tickets_dz`.`type`='Уникальный' 
		GROUP BY `ticket_id`
		ORDER BY `tickets_dz`.`when_changed` DESC;";
	$params = [
		'user_vk_id' => $user_vk_id,
		'lesson_num' => $in->lessonNum,
		'task_num' => $in->taskNum,
	];
}
else{
	$query = "SELECT `tickets_dz`.`ticket_id`, `tickets_dz`.`task_number`, `tickets_dz`.`status`, `tickets_dz`.`quest_name`,
		`ticket_dz_user`.`user_vk_id` AS `flag_watched`, 
		`tickets_mess_dz`.`comment` 
		FROM `tickets_dz` 
		LEFT JOIN `ticket_dz_user` ON `ticket_dz_user`.`ticket_id`=`tickets_dz`.`ticket_id`  AND `ticket_dz_user`.`user_vk_id`= :user_vk_id
		LEFT JOIN `tickets_mess_dz` ON  `tickets_mess_dz`.`ticket_id`=`tickets_dz`.`ticket_id`
		WHERE `tickets_dz`.`lesson_number`= :lesson_num
		AND `tickets_dz`.`type`='Уникальный' 
		GROUP BY `ticket_id`
		ORDER BY `tickets_dz`.`when_changed` DESC;";
	$params = [
		'user_vk_id' => $user_vk_id,
		'lesson_num' => $in->lessonNum,
	];
}
$stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
$stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (4)');

$questionsListUnique = [];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)){
	$questionsListUnique[] = [
		'ticketId' => (string) $result['ticket_id'],
		'taskNumber' => (string) $result['task_number'],
		'status' => (string) $result['status'],
		'questName' => (string) $result['quest_name'],
		'comment' => (string) $result['comment'],
		'flagWatched' => (string) $result['flag_watched'],
	];
}
$stmt->closeCursor(); unset($stmt);

//--------------------------------Формирование ответа
$out->success = '1';
$out->questionsList = $questionsList;
$out->questionsListUnique = $questionsListUnique;
$out->make_resp('');