<?php // Получение обновленных сообщений

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class UpdateMessagesAjax extends MainRequestClass {
	public $ticketId = ''; // идентификатор заявки

	public $lastMessId = ''; // идентификатор последнего сообщения
	
}
$in = new UpdateMessagesAjax();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class UpdateMessagesAjaxResponse extends MainResponseClass {
	/*
	* 	Массив словарей, каждый словарь имеет следующие поля:
	*     - messMaker - создатель сообщения
	*     - comment - тело сообщения
	*     - createdAt  - дата и время создания сообщения
	*     - userRole - роль пользователя, создавшего сообщение 
	*     - userVkId - идентификатор пользователя, создавшего сообщение
	*/
	public $newMessages = [];// новые сообщения по заявке

	public $lastMessId = ''; // идентификатор последнего сообщения
}

$out = new UpdateMessagesAjaxResponse();

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
$stmt = $pdo->prepare("SELECT `ticket_id`
    FROM `tickets_dz`
    WHERE `ticket_id` = :ticket_id
;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса');
$stmt->execute([
    'ticket_id' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Заявка с ID {$in->ticketId} не найдена");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Валидация $in->lastMessId
if (((string) (int) $in->lastMessId) !== ((string) $in->lastMessId) || (int) $in->lastMessId <= 0) $out->make_wrong_resp("Параметр 'lastMessId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("SELECT `mess_id`
    FROM `tickets_mess_dz`
    WHERE `mess_id` = :last_mess_id
;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
    'last_mess_id' => $in->lastMessId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Сообщение с ID {$in->lastMessId} не найдена");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Получение заявки
$stmt = $pdo->prepare("SELECT `tickets_dz`.`user_vk_id`
    FROM `tickets_dz`
    WHERE `ticket_id` = :ticket_id
;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'ticket_id' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if($stmt->rowCount() == 0) $out->make_wrong_resp("Ни одна заявка не была найдена [ID заявки: {$in->ticketId}] ");
$ticketDz = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$ticketDz = [
	'userVkId' => (string) $ticketDz['user_vk_id'],
];

//--------------------------------Получение сообщений заявки
$stmt = $pdo->prepare("SELECT `tickets_mess_dz`.`mess_id`, `tickets_mess_dz`.`ticket_id`, `tickets_mess_dz`.`user_vk_id`, `tickets_mess_dz`.`comment`, 
	`tickets_mess_dz`.`comment_dtime`, CONCAT(`users`.`user_name`,' ',`users`.`user_surname`) AS `mess_maker` FROM `tickets_mess_dz` 
	LEFT JOIN `users` ON `users`.`user_vk_id`=`tickets_mess_dz`.`user_vk_id`
	WHERE `tickets_mess_dz`.`ticket_id`=:ticket_id AND `tickets_mess_dz`.`mess_id`>:last_mess_id ORDER BY `comment_dtime` ASC
;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
$newMessagesQuery =$stmt->execute([
	'ticket_id' => $in->ticketId,
	'last_mess_id' => $in->lastMessId,
	]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
$newMessages = [];
$userRole = '';
$lastMessId = '';

while($newMessagesQuery = $stmt->fetch(PDO::FETCH_ASSOC)){
	var_dump($newMessages);
	if($newMessagesQuery['user_vk_id']==$user_vk_id){
		$userRole="Вы";
	}
	else if($newMessagesQuery['user_vk_id']==$ticketDz['user_vk_id']){
		$userRole="Создатель";
	}
	else if($newMessagesQuery['user_vk_id']==dz_answerer){
		$userRole="Ответственный";
	}
	else{
		$userRole="Другая";
	}

	$newMessages[] =[
		"messMaker" => (string) $newMessagesQuery["mess_maker"],
		"comment" => (string) $newMessagesQuery["comment"],
		"commentDtime" => (string) $newMessagesQuery["comment_dtime"],
		"userRole" => (string) $userRole,
		"userVkId" => (string) $newMessagesQuery["user_vk_id"],
	];
	$lastMessId = $newMessagesQuery['mess_id'];
	var_dump($newMessages);
}
$stmt->closeCursor(); unset($stmt);

//--------------------------------Вставка данных в ticket_dz_user
$stmt = $pdo->prepare("INSERT IGNORE INTO `ticket_dz_user` (`ticket_id`,`user_vk_id`) 
	VALUES (:ticket_id, :user_vk_id)
;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
$stmt->execute([
	'ticket_id' => $in->ticketId,
	'user_vk_id' => $user_vk_id,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
$stmt->closeCursor(); unset($stmt);

//--------------------------------Формирование ответа
$out->success = '1';
$out->newMessages = $newMessages;
$out->lastMessId = (string) $lastMessId;
$out->make_resp('');