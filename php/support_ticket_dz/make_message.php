<?php // Создание сообщения пользователем

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class SupportTicketDztMakeMessage extends MainRequestClass {
	public $ticketId = ''; // id заявки
	public $responceVkId = ''; // ответственный по заявке
	public $ticketType = ''; // тип заявки (не обяз.)
	public $comment = ''; // тело сообщения(не обяз.)
	public $importance = ''; // важность заявки (не обяз., по умолчанию 5 - обычная)
	public $status = ''; // статус заявки (не обяз., по умолчанию 0 - новая)
	public $ticketDeadline = ''; // срок рассмотрения заявки (не обяз., по умолчанию NULL)
}
$in = new SupportTicketDztMakeMessage();
$in->from_json(file_get_contents('php://input'));

// класс ответа
$out = new MainResponseClass();

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
if (((string) (int) $in->ticketId) !== ((string) $in->ticketId) || (int) $in->ticketId <= 0) $out->make_wrong_resp("Параметр 'ticketId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `tickets`.`ticket_id`, `tickets`.`ticket_type`, `tickets`.`user_vk_id`, `tickets`.`response_vk_id`, 
	`tickets`.`status`,`tickets`.`importance`, `tickets`.`ticket_side`,`tickets`.`ticket_deadline`   
    FROM `tickets`
    WHERE `ticket_id` = :ticket_id
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
    'ticket_id' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Заявка с ID {$in->ticketId} не найдена");
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//--------------------------------Валидация $in->responceVkId
if (((string) (int) $in->responceVkId) !== ((string) $in->responceVkId) || (int) $in->responceVkId <= 0) $out->make_wrong_resp("Параметр 'responceVkId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `users`.`user_vk_id`
	FROM `users`
    WHERE `user_vk_id` = :user_vk_id
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'user_vk_id' => $in->responceVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Пользователь с ID {$in->responceVkId} не найден");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!(in_array($user_type, ['Админ'])) && !(in_array($user_vk_id, [$ticket['response_vk_id'], $ticket['user_vk_id']]))) $out->make_wrong_resp('Нет доступа');

//--------------------------------Валидация $in->ticketType
if (!in_array($in->ticketType, ['1', '2', '5', '6', '7', '10']) && !empty($in->ticketType)) $out->make_wrong_resp("Параметр 'ticketType' задан некорректно");

//--------------------------------Валидация $in->status
if (!in_array($in->status, ['0', '1', '5', '10']) && !empty($in->status)) $out->make_wrong_resp("Параметр 'status' задан некорректно");
else $in->importance = '0';

//--------------------------------Валидация $in->importance
if (!in_array($in->importance, ['5', '10']) && !empty($in->importance)) $out->make_wrong_resp("Параметр 'importance' задан некорректно");
else $in->importance = '5';

//--------------------------------Валидация $in->comment
if (!is_string($in->comment)) $out->make_wrong_resp("Параметр 'comment' задан некорректно");

//--------------------------------Валидация $in->ticketDeadline
if (!is_string($in->ticketDeadline)) $out->make_wrong_resp("Параметр 'ticketDeadline' задан некорректно");
else $in->importance = 'NULL';

//--------------------------------Массив статусов
$statusToText = [
	'0' => 'Новая',
	'1' => 'В работе',
	'5' => 'Завершена',
	'10' => 'Архив',
];

//--------------------------------Массив типов
$typeToText = [
	'1' => 'Организационные вопросы',
	'2' => 'Финансы, договора',
	'5' => 'Антон',
	'6' => 'Лиза Тюрина',
	'7' => 'Редактирование ученика',
	'10' => 'Светлана Леонидовна',
];

//--------------------------------Массив ответственных за типы
$typetoResponse = [
	'1' => org_answerer,
	'2' => money_answerer,
	'5' => anton_id,
	'6' => main_managers[0],
	'7' => changer_user,
	'10' => sveta_id,
];

//--------------------------------Массив важностей
$importanceToText = [
	'5' => 'Обычная',
	'10' => 'Сверхсрочная',
];

//--------------------------------Проверка на изменение заявки
$ticketSide = 1;
$ticketSide0 = $ticket['ticket_side'];
$ticketMessage = "";
$flagChanged = 0;
$flagSendBot = 0;
$flagSendEnd = 0;

foreach($ticket as $key => $value){
	$keyToArray = explode("_", $key);
    $keyInCamelCase = (string) $keyToArray[0];
    for ($i=1; $i < count($keyToArray); $i++) { 
        $keyInCamelCase .= ucfirst($keyToArray[$i]);
    }

	if(isset($in->$keyInCamelCase)){
        if($in->$keyInCamelCase != $value && !empty($in->$keyInCamelCase)){
			switch($key){
				case "ticket_type":
					$ticketMessage = "Тип изменён с \'" . $typeToText[$value] . "\' на \'" . $in->ticketType . "\'";
					$in->$keyInCamelCase = $typetoResponse[$value];
					$flagChanged = 1;
					$ticketSide = 0;
					$flagSendBot = 1;
					break;

				case "importance":
					$ticketMessage = "Важность изменена с \'" . $importanceToText[$value] . "\' на \'" . $in->importance . "\'";
					$in->$keyInCamelCase = $value;
					$flagChanged = 1;
					break;

				case "status":
					$ticketMessage = "Статус изменён с \'" . $statusToText[$value] . "\' на \'" . $in->status . "\'";
					$in->$keyInCamelCase = $value;
					if($value == 5) $flagSendEnd = 1;
					$flagChanged = 1;
					break;

				case "ticket_deadline":
					$ticketMessage = "Срок рассмотрения изменён с \'" . $value . "\' на \'" . $in->ticketDeadline . "\'";
					$in->$keyInCamelCase = $value;
					$flagChanged = 1;
					$ticketSide = 0;
					break;
			}
        }
    }         
}
if($flagChanged == 1){$ticketMessage = substr($ticketMessage,0,-2);}

//--------------------------------Изменение заявки
$stmt = $pdo->prepare(
	"UPDATE `tickets` SET `importance`= :ticket_importance, `ticket_type`= :ticket_type, `ticket_deadline`= :ticket_deadline, `response_vk_id`= :response_vk_id, `status`= :ticket_status WHERE `ticket_id`= :ticket_id;"
) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
$stmt->execute([
	"ticket_importance" => $in->importance,
	"ticket_type" => $in->ticketType,
	"ticket_deadline" => $in->ticketDeadline,
	"response_vk_id" => $in->responceVkId,
	"ticket_status" => $in->status,
	"ticket_id" => $in->ticketId,
]) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (3)');

//--------------------------------Вставка нового сообщения
$stmt = $pdo->prepare(
	"INSERT INTO `tickets_mess` (`ticket_id`, `user_vk_id`, `comment`, `comment_dtime`) 
	VALUES (:ticket_id, :user_vk_id, :ticket_message, NOW());"
) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
$stmt->execute([
	"ticket_id" => $in->ticketId,
	"user_vk_id" => $user_vk_id,
	"ticket_message" => $in->comment,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');


//--------------------------------Обновление ticketSide
if($user_vk_id == $ticket['user_vk_id']) $ticketSide = 0;

$stmt = $pdo->prepare(
	"UPDATE `tickets` 
	SET `ticket_side`= :ticket_side, `when_changed`=NOW() WHERE `ticket_id`=:ticket_id;"
) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');
$stmt->execute([
	"ticket_side" => $ticketSide,
	"ticket_id" => $in->ticketId,
]) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (5)');
$stmt->closeCursor(); unset($stmt);

//--------------------------------Отправка в ВК уведомления о создании

if(empty($in->responseVkId)) $in->responseVkId = $ticket['response_vk_id'];

if($flagSendBot == 1){
	$bot_config = new ConfigBotVK;
	$req_message= array( 
		'message'=>'‼📝Тебе назначена ранее созданная заявка №' . $in->ticketId,
		'user_id'=>$in->responseVkId,
		'access_token'=>$bot_config->gr_key,
		'v'=>$bot_config->ver,
		);	
	$get_params=http_build_query($req_message);
	file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);
}
elseif($flagSendEnd == 1){
	$bot_config = new ConfigBotVK;
	$req_message= array( 
		'message'=>'📝Твоя заявка №' . $in->ticketId . ' завершена', // Ссылка https://насотку.рф/support_ticket.php?ticket_id='.$ticket_id
		'user_id'=>$ticket['user_vk_id'],
		'access_token'=>$bot_config->gr_key,
		'v'=>$bot_config->ver,
		);	
	$get_params=http_build_query($req_message);
	file_get_contents('https://api.vk.com/method/messages.send?'.$get_params);
}
elseif($ticketSide0 != $ticketSide && $ticketSide0 == 1){//отправляем создателю заявки, так как передали ему мяч
	$bot_config = new ConfigBotVK;
	$req_message= array( 
		'message'=>'📝Новый ответ по твоей заявке №'. $in->ticketId,
		'user_id'=>$ticket['user_vk_id'],
		'access_token'=>$bot_config->gr_key,
		'v'=>$bot_config->ver,
		);	
	$get_params=http_build_query($req_message);
	file_get_contents('https://api.vk.com/method/messages.send?'.$get_params);
}
elseif($ticketSide0 != $ticketSide && $ticketSide0 == 0){//отправляем отвественному, так как передали ему мяч
	$bot_config = new ConfigBotVK;
	$req_message= array( 
		'message'=>'📝Новое сообщение в твоей заявке №' . $in->ticket_id,
		'user_id'=>$in->responseVkId,
		'access_token'=>$bot_config->gr_key,
		'v'=>$bot_config->ver,
		);	
	$get_params=http_build_query($req_message);
	file_get_contents('https://api.vk.com/method/messages.send?'.$get_params);
}
//--------------------------------Формирование ответа
$out->success = '1';
$out->make_resp('');