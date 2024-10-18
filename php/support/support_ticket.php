<?php // Получение заявки по ID

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class SupportTicketDzSupportTicket extends MainRequestClass {

	public $ticketId = ''; // идентификатор заявки(по умол. 0)

}
$in = new SupportTicketDzSupportTicket();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class SupportTicketDzSupportTicketResponse extends MainResponseClass {
	/*
	* 	Словарь, который имеет следующие поля:
	*     - messMaker - создатель сообщения
	*     - comment - тело сообщения
	*     - createdAt  - дата и время создания сообщения
	*     - userRole - роль пользователя, создавшего сообщение 
	*     - userVkId - идентификатор пользователя, создавшего сообщение
	*/
	public $ticket = []; // данные заявки

	/*
	* 	Массив словарей, каждый словарь имеет следующие поля:
	*     - messMaker - создатель сообщения
	*     - comment - тело сообщения
	*     - createdAt  - дата и время создания сообщения
	*     - userRole - роль пользователя, создавшего сообщение 
	*     - userVkId - идентификатор пользователя, создавшего сообщение
	*/
	public $messages = [];// сообщения по заявке

	public $lastMessId = ''; // идентификатор последнего сообщения

	public $printStatusFlag = ''; // Флаг отображения дополнительной информации для администратора или куратора заявки
}

$out = new SupportTicketDzSupportTicketResponse();

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

//--------------------------------Валидация $in->ticketId
if(empty($in->ticketId)) $in->ticketId = '0';
if (((string) (int) $in->ticketId) !== ((string) $in->ticketId)) $out->make_wrong_resp("Параметр 'ticketId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("SELECT `tickets_dz`.`ticket_id`
    FROM `tickets_dz`
    WHERE `ticket_id` = :ticket_id
;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
    'ticket_id' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Заявка с ID {$in->ticketId} не найдена");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Получение заявки
$stmt = $pdo->prepare("SELECT `tickets`.`ticket_id`, `tickets`.`user_vk_id`, `tickets`.`response_vk_id`, 
	`tickets`.`status`, `tickets`.`ticket_type`, `tickets`.`when_made`, `tickets`.`ticket_deadline`, `tickets`.`ticket_name`, `tickets`.`importance`,
	CONCAT(`response`.`user_name`,' ', `response`.`user_surname`) as `response_name`, 
	CONCAT(`maker`.`user_name`,' ', `maker`.`user_surname`) as `maker_name` 
    FROM `tickets`
	LEFT JOIN `users` AS `response` ON `response`.`user_vk_id`=`tickets`.`response_vk_id`
	LEFT JOIN `users` AS `maker` ON `maker`.`user_vk_id`=`tickets`.`user_vk_id`
    WHERE `ticket_id` = :ticket_id
;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'ticket_id' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//--------------------------------Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!(in_array($user_type, ['Админ'])))
	if (!(in_array($user_vk_id, [$ticket['user_vk_id'], $ticket['response_vk_id']]))) $out->make_wrong_resp('Нет доступа');
$printStatusFlag=0;
if(in_array($user_type, ['Админ']) || in_array($user_vk_id, [$ticket['response_vk_id']])) $printStatusFlag=1;

//--------------------------------Получение сообщений заявки
$stmt = $pdo->prepare("SELECT `tickets_mess`.*, CONCAT(`users`.`user_name`,' ',`users`.`user_surname`) AS `who_sent` FROM `tickets_mess` 
	LEFT JOIN `users` ON `users`.`user_vk_id`=`tickets_mess`.`user_vk_id`
	WHERE `tickets_mess`.`ticket_id`= :ticket_id ORDER BY `tickets_mess`.`comment_dtime` ASC
;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
$messagesQuery = $stmt->execute([
	'ticket_id' => $in->ticketId,
	]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
$messages = [];
$userRole = '';
$lastMessId = '';

while($messagesQuery = $stmt->fetch(PDO::FETCH_ASSOC)){
	if($messagesQuery['user_vk_id']==$user_vk_id){
		$userRole="Вы";
	}
	else if($messagesQuery['user_vk_id']==$ticketDz['user_vk_id']){
		$userRole="Создатель";
	}
	else if($messagesQuery['user_vk_id']==dz_answerer){
		$userRole="Ответственный";
	}
	else{
		$userRole="Другая";
	}

	$messages[] =[
		"messMaker" => (string) $messagesQuery["who_sent"],
		"comment" => (string) $messagesQuery["comment"],
		"commentDtime" => (string) $messagesQuery["comment_dtime"],
		"userRole" => (string) $userRole,
		"userVkId" => (string) $messagesQuery["user_vk_id"],
	];
	$lastMessId = $messagesQuery['mess_id'];
}
$stmt->closeCursor(); unset($stmt);

//--------------------------------Формирование ответа
$out->success = '1';
$out->ticket = (object) $ticket;
$out->messages = $messages;
$out->lastMessId = (string) $lastMessId;
$out->printStatusFlag = (string) $printStatusFlag;
$out->make_resp('');