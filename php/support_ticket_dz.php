<?php // Получение ифнормации по вопросу

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//класс запроса
class SupportTicketDz extends MainRequestClass {
	public $ticketId = ''; // идентификатор заявки
}
$in = new SupportTicketDz();
$in->from_json(file_get_contents('php://input'));

//класс ответа
class SupportTicketDzResponce extends MainResponseClass {
/*
* 	Словарь, который имеет следующие поля:
*     - ticketId - идентификатор заявки
*     - lessonNumber - номер урока
*     - taskNumber - номер задания
*     - type - тип заявки
*     - status - статус заявки
*     - questName - название вопроса
*     - userVkId - идентификатор пользователя, создавшего заявку
*     - importance - важность заявки
*     - whenMade - когда создана заявка
*     - whenChanged - когда отредактирована заявка
*     - responseName - имя и фамилия ответственного за заявку
*     - makerName - имя и фамилия создателя заявки
*/
	public $ticketDz = []; // словарь с данными заявки
/*
* 	Массив словарей, каждый словарь имеет следующие поля:
*     - messId - идентификатор сообщения
*     - ticketId - идентификатор заявки
*     - userVkId - идентификатор пользователя
*     - comment - сообщение
*     - commentDtime - дата и время отправки сообщения
*     - messMaker - имя и фамилия отправителя сообщения
*/
	public $ticketsMessDz = [];// массив словарей с данными сообщений по заявке
/*
* 	Словарь, который имеет следующие поля:
*     - ticketId - идентификатор заявки
*     - userVkId - идентификатор пользователя
*/
	public $ticketDzUser = [];// массив словарей с данными сообщений по заявке

	public $printStatusFlag = ''; // 
}
$out = new SupportTicketDzResponce();

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

//--------------------------------Валидация $in->ticketId
if (((string) (int) $in->ticketId) !== ((string) $in->ticketId) || (int) $in->ticketId <= 0) $out->make_wrong_resp("Параметр 'ticketId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `ticket_id`
    FROM `tickets_dz`
    WHERE `ticket_id` = :ticket_id
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса');
$stmt->execute([
    'ticket_id' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Заявка с id {$in->ticketId} не найден");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Получение заявки
$stmt = $pdo->prepare("SELECT `tickets_dz`.`ticket_id`, `tickets_dz`.`lesson_number`, `tickets_dz`.`task_number`, `tickets_dz`.`type`, `tickets_dz`.`status`, 
	`tickets_dz`.`quest_name`, `tickets_dz`.`user_vk_id`, `tickets_dz`.`importance`, `tickets_dz`.`when_made`, `tickets_dz`.`when_changed`, 
	CONCAT(`response`.`user_name`,' ', `response`.`user_surname`) as `response_name`, CONCAT(`maker`.`user_name`,' ', `maker`.`user_surname`) as `maker_name`  
	FROM `tickets_dz` 
	LEFT JOIN `users` AS `response` ON `response`.`user_vk_id`=:dz_answerer
	LEFT JOIN `users` AS `maker` ON `maker`.`user_vk_id`=`tickets_dz`.`user_vk_id`
	WHERE `ticket_id`=:ticket_id") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
	'dz_answerer' => dz_answerer,
	'ticket_id' => $in->ticketId,
]) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (1)');
if($stmt->rowCount() == 0) $out->make_wrong_resp("Ни одна заявка не была найдена [ID заявки: {$in->ticketId}] ");
$ticketDz = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$ticketDz = [
	'ticketId' => (string) $ticketDz['ticket_id'],
	'lessonNumber' => (string) $ticketDz['lesson_number'],
	'taskNumber' => (string) $ticketDz['task_number'],
	'type' => (string) $ticketDz['type'],
	'status' => (string) $ticketDz['status'],
	'questName' => (string) $ticketDz['quest_name'],
	'userVkId' => (string) $ticketDz['user_vk_id'],
	'importance' => (string) $ticketDz['importance'],
	'whenMade' => (string) $ticketDz['when_made'],
	'whenChanged' => (string) $ticketDz['when_changed'],
	'responseName' => (string) $ticketDz['response_name'],
	'makerName' => (string) $ticketDz['maker_name'],
];

//--------------------------------Получение сообщений задания
$stmt = $pdo->prepare("SELECT `tickets_mess_dz`.`mess_id`, `tickets_mess_dz`.`ticket_id`, `tickets_mess_dz`.`user_vk_id`, `tickets_mess_dz`.`comment`, 
	`tickets_mess_dz`.`comment_dtime`, CONCAT(`users`.`user_name`,' ',`users`.`user_surname`) AS `mess_maker` FROM `tickets_mess_dz` 
	LEFT JOIN `users` ON `users`.`user_vk_id`=`tickets_mess_dz`.`user_vk_id`
	WHERE `ticket_id`=:ticket_id ORDER BY `comment_dtime` ASC;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute(['ticket_id' => $in->ticketId,]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
$ticketsMessDz = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$ticketsMessDz = [
	'messId' => (string) $ticketsMessDz['mess_id'],
	'ticketId' => (string) $ticketsMessDz['ticket_id'],
	'userVkId' => (string) $ticketsMessDz['user_vk_id'],
	'comment' => (string) $ticketsMessDz['comment'],
	'commentDtime' => (string) $ticketsMessDz['comment_dtime'],
	'messMaker' => (string) $ticketsMessDz['mess_maker'],
];

//--------------------------------Вставка данных в ticket_dz_user
$stmt = $pdo->prepare("INSERT IGNORE INTO `ticket_dz_user` (`ticket_id`,`user_vk_id`) 
	VALUES (:ticket_id, :user_vk_id);
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
$stmt->execute([
	'ticket_id' => $in->ticketId,
	'user_vk_id' => $user_vk_id,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
$ticketDzUser = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$ticketDzUser = [
	'ticketId' => (string) $ticketDzUser['ticket_id'],
	'userVkId' => (string) $ticketDzUser['user_vk_id'],
];

//--------------------------------Присвоение флага
$printStatusFlag = "0";
if ($user_type == 'Админ' || $user_vk_id == dz_answerer) $printStatusFlag = "1";

//--------------------------------Формирование ответа
$out->success = '1';
$out->ticketDz = (object) $ticketDz;
$out->ticketsMessDz = $ticketsMessDz;
$out->ticketDzUser = (object) $ticketDzUser;
$out->printStatusFlag = $printStatusFlag;
$out->make_resp('');
