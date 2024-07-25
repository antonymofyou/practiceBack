<?php // Создание вопроса

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class HomeTaskDzProverkaList extends MainRequestClass {
	public $lessonNum = ''; // номер урока
	public $taskNum = ''; // номер задания
	public $type = ''; // тип вопроса
	public $importance = ''; // важность вопроса
	public $name = ''; // название вопроса
	public $text = ''; // тело вопроса
}
$in = new HomeTaskDzProverkaList();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class HomeTaskDzProverkaListResponse extends MainResponseClass {
    public $ticketId = ''; // идентификатор заявки
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
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса');
$stmt->execute([
    'lesson_number' => $in->lessonNum
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Урок с номером {$in->lessonNum} не найден");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Валидация $in->taskNum
if (((string) (int) $in->taskNum) !== ((string) $in->taskNum) || (int) $in->taskNum < 0) $out->make_wrong_resp("Параметр 'taskNum' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `task_number`
    FROM `tickets_dz`
    WHERE `task_number` = :task_number
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
    'task_number' => $in->taskNum
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Задание с номером {$in->taskNum} не найдено");
$stmt->closeCursor(); unset($stmt);

//--------------------------------Валидация $in->type
if (!in_array($in->type, ['Общий', 'Уникальный'])) $out->make_wrong_resp("Параметр 'type' задан некорректно или отсутствует");

//--------------------------------Валидация $in->importance
if (!in_array($in->importance, ['5', '10'])) $out->make_wrong_resp("Параметр 'importance' задан некорректно или отсутствует");

//--------------------------------Валидация $in->name
if (empty($in->name)) $out->make_wrong_resp("Параметр 'name' задан некорректно или отсутствует");

//--------------------------------Валидация $in->text
if (empty($in->text)) $out->make_wrong_resp("Параметр 'text' задан некорректно или отсутствует");

//--------------------------------Вставка нового вопроса
$stmt = $pdo->prepare(
	"INSERT INTO `tickets_dz` (`lesson_number`, `task_number`,`type`,`status`,`quest_name`,`importance`, `user_vk_id`, `when_made`, `when_changed`)
	VALUES (:lesson_num, :task_num,:question_type, 'Открыт', :question_name, :question_importance, :user_vk_id, NOW(), NOW());"
) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
$stmt->execute([
	"lesson_num" => $in->lessonNum,
	"task_num" => $in->taskNum,
	"question_type" => $in->type,
	"question_name" => $in->name,
	"question_importance" => $in->importance,
	"user_vk_id" => $user_vk_id,
]) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (6)');


//--------------------------------Получение ticket_id созданного вопроса
$stmt = $pdo->prepare("SELECT LAST_INSERT_ID() AS ticket_id;") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
$stmt->execute([]) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (6)');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$ticketId = $result["ticket_id"];
$stmt->closeCursor(); unset($stmt);

//--------------------------------Вставка нового вопроса
$stmt = $pdo->prepare(
	"INSERT INTO `tickets_mess_dz` (`ticket_id`, `user_vk_id`, `comment`, `comment_dtime`) 
	VALUES(:ticket_id, :user_vk_id, :question_text, NOW());"
) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
$stmt->execute([
	"ticket_id" => $ticketId,
	"question_text" => $in->text,
	"user_vk_id" => $user_vk_id,
]) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (6)');


//--------------------------------Отправка создания важного вопроса
if($in->importance >= 0)
{
	$bot_config = new ConfigBotVK;
	$req_message= array( 
		'message'=>'Создан новый вопрос на платформе по ДЗ №'.$in->lessonNum.'. Важность-высокая! Ссылка https://насотку.рф/support_ticket_dz.php?ticket_id='.$ticketId,
		'user_id'=>dz_answerer,
		'access_token'=>$bot_config->gr_key,
		'v'=>$bot_config->ver,
		);	
	$get_params=http_build_query($req_message);
	file_get_contents('https://api.vk.com/method/messages.send?'.$get_params);
}

//--------------------------------Формирование ответа
$out->success = '1';
$out->ticketId = $ticketId;
$out->make_resp('');