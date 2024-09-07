<?php //---Создание сообщения

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class SupportTicketDzMakeMessage extends MainRequestClass {
    public $ticketId = ''; // ИД заявки, по которой надо отправить сообщение
    public $message = ''; // текст сообщения (необяз.)
    public $taskNumber = ''; // номер задания (необяз.)
    public $ticketType = ''; // тип заявки (необяз.)
	public $importance = ''; // важность заявки (необяз., по умолчанию 5 - обычная)
	public $status = ''; // статус заявки (необяз., по умолчанию 0 - новая)
}
$in = new SupportTicketDzMakeMessage();
$in->from_json(file_get_contents('php://input'));


//---Класс ответа
$out = new MainResponseClass();

//---Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//---Проверка пользователя (1)
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!(in_array($user_type, ['Админ', 'Куратор']))) $out->make_wrong_resp('Нет доступа');

//---Валидация $in->ticketId
//Получаем информацию по заявке
if (((string) (int) $in->ticketId) !== ((string) $in->ticketId) || (int) $in->ticketId <= 0) $out->make_wrong_resp("Параметр 'ticketId' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `ticket_id`, `task_number`, `type`, `status`, `importance`
    FROM `tickets_dz`
    WHERE `ticket_id` = :ticketId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'ticketId' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Вопрос с ID {$in->ticketId} не найден");
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$messageAdd = []; //Массив дополнений к сообщению

//---Валидация $in->taskNumber
if (!empty($in->taskNumber)) {
    if (((string) (int) $in->taskNumber) !== ((string) $in->taskNumber) || (int) $in->ticketId <= 0 ) $out->make_wrong_resp("Параметр 'taskNumber' задан некорректно");
    elseif ($ticket['task_number'] != $in->taskNumber) {
        if($ticket['task_number'] == null) $ticket['task_number'] = "Не задано";
        $messageAdd[] = "задание изменено с '{$ticket['task_number']}' на '{$in->taskNumber}'";
    }
} else $in->taskNumber = $ticket['task_number'];

//---Валидация $in->ticketType
if (!empty($in->ticketType)) {
    if (!in_array($in->ticketType, ['0', '1', '2', '5', '6', '7', '10'])) $out->make_wrong_resp("Параметр 'type' задан некорректно");
    elseif ($ticket['type'] != $in->ticketType) {

        //Словарь типов
        $typeToText = [
            null => 'Не задан', //В случае, если в заявке не было заданного типа
            '1' => 'Организационные вопросы',
            '2' => 'Финансы, договора',
            '5' => 'Антон',
            '6' => 'Лиза Тюрина',
            '7' => 'Редактирование ученика',
            '10' => 'Светлана Леонидовна',
        ];

        $messageAdd[] = "тип изменен с '{$typeToText[$ticket['type']]}' на '{$typeToText[$in->ticketType]}'";
    }
} else $in->ticketType = $ticket['type'];

//---Валидация $in->status
if (!empty($in->status) || $in->status === "0") {
    if (!in_array($in->status, ['0', '1', '5', '10'])) $out->make_wrong_resp("Параметр 'status' задан некорректно");
    elseif ($ticket['status'] != $in->status) {

        //Словарь статусов
        $statusToText = [
            null => 'Не задан',
            '0' => 'Новая',
            '1' => 'В работе',
            '5' => 'Завершена',
            '10' => 'Архив',
        ];

        $messageAdd[] = "статус заявки изменен с '{$statusToText[$ticket['status']]}' на '{$statusToText[$in->status]}'";
    }
} else $in->status = $ticket['status'];

//---Валидация $in->importance
if (!empty($in->importance)) {
    if (!in_array($in->importance, ['5', '10'])) $out->make_wrong_resp("Параметр 'importance' задан некорректно");
    elseif ($ticket['importance'] != $in->importance) {

        //Словарь срочности
        $importanceToText = [
            null => 'Не задана',
            '5' => 'Обычная',
            '10' => 'Сверхсрочная',
        ];

        $messageAdd[] = "срочность изменена с '{$importanceToText[$ticket['importance']]}' на '{$importanceToText[$in->importance]}'";
    }
} else $in->importance = $ticket['importance'];

//---Валидация $in->message
if (!is_string($in->message)) $out->make_wrong_resp("Параметр 'message' задан некорректно");

//---Обновление заявки в БД, если есть поля для обновления
if (!empty($messageAdd)) { 

    $in->message = trim($in->message . " (" . join(", ", $messageAdd)) . ")"; //Добавление $messageAdd в $message без лишней запятой и пробела, но в скобочках

    //Обновление заявки
    $stmt = $pdo->prepare("
        UPDATE `tickets_dz`
        SET `task_number` = :taskNumber, `type` = :type, `status` = :status, `importance` = :importance, `when_changed` = NOW()
        WHERE `ticket_id` = :ticketId;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (2)");
    $stmt->execute([
        'taskNumber' => $in->taskNumber,
        'type' => $in->ticketType,
        'status' => $in->status,
        'importance' => $in->importance,
        'ticketId' => $in->ticketId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    $stmt->closeCursor(); unset($stmt);

} elseif (empty($in->message)) $out->make_wrong_resp('Нет параметров для обновления заявки, нет сообщения'); //Выдаём ошибку если ничего не меняется, при этом отправляется пустое сообщение

//---Добавление сообщения в БД
$stmt = $pdo->prepare("
    INSERT INTO `tickets_mess_dz`
    (`ticket_id`, `user_vk_id`, `comment`, `comment_dtime`)
    VALUES (:ticketId, :userVkId, :message, NOW());
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (3)");
$stmt->execute([
    'ticketId' => $in->ticketId,
    'userVkId' => $user_id,
    'message' => $in->message
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
$stmt->closeCursor(); unset($stmt);

//---Удаление ??
$stmt = $pdo->prepare("
    DELETE FROM `ticket_dz_user`
    WHERE `ticket_id` = :ticketId;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (4)");
$stmt->execute([
    'ticketId' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
$stmt->closeCursor(); unset($stmt);

if ($user_id == dz_answerer) { //Если сообщение написал тот, кто отвечает на вопросы
    $botConfig = new ConfigBotVK;
    $request = array(
        'message' => 'Получен ответ на вопрос ДЗ, заявка №' . $in->ticketId, // . '. Ссылка https://насотку.рф/support_ticket_dz.php?in->ticketId=' . $in->ticketId,
        'user_id' => $ticket['user_vk_id'],
        'access_token' => $botConfig->gr_key,
        'v' => $botConfig->ver,
    );
    $params = http_build_query($request);
    file_get_contents('https://api.vk.com/method/messages.send?' . $params);
} 

elseif ($user_id == $ticket['user_vk_id']) { //Если сообщение написал тот, кто создавал заявку
    $botConfig = new ConfigBotVK;
    $request = array(
        'message' => 'Задан еще один вопрос ДЗ создателем заявки №' . $in->ticketId, // . '. Ссылка https://насотку.рф/support_ticket_dz.php?in->ticketId=' . $in->ticketId,
        'user_id' => dz_answerer,
        'access_token' => $botConfig->gr_key,
        'v' => $botConfig->ver,
    );
    $params = http_build_query($request);
    file_get_contents('https://api.vk.com/method/messages.send?' . $params);
} 

else { //Во всех остальных случаях
    $botConfig = new ConfigBotVK;
    $request = array(
        'message' => 'Задан еще один вопрос третьим куратором в заявке №' . $in->ticketId, // . '. Ссылка https://насотку.рф/support_ticket_dz.php?in->ticketId=' . $in->ticketId,
        'user_id' => dz_answerer,
        'access_token' => $botConfig->gr_key,
        'v' => $botConfig->ver,
    );
    $params = http_build_query($request);
    file_get_contents('https://api.vk.com/method/messages.send?' . $params);
}

$out->success = "1";
$out->make_resp('');

