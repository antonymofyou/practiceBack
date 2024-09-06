<?php //---Создание сообщения

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class SupportTicketDzMakeMessage extends MainRequestClass {
    public $ticketId = ''; //ИД заявки, по которой надо отправить сообщение
    public $message = ''; //Текст сообщения (необяз.)

    public $ticketType = ''; // тип заявки (необяз.)
	public $comment = ''; // тело сообщения(необяз.)
	public $importance = ''; // важность заявки (необяз., по умолчанию 5 - обычная)
	public $status = ''; // статус заявки (необяз., по умолчанию 0 - новая)
}
$in = new SupportTicketDzMakeMessage();

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
$stmt = $pdo->prepare("
    SELECT `ticket_id`, `task_number`, `type`, `status`, `importance`
    FROM `tickets_dz`
    WHERE `ticket_id` = :ticketId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'ticketId' => $in->ticketId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Вопрос с ID {$in->ticketId} не найден");
$stmt->closeCursor(); unset($stmt);

//---Валидация $in->ticketType
if(isset($in->ticketType)) {
    if (!in_array($in->ticketType, ['1', '2', '5', '6', '7', '10']) && !empty($in->ticketType)) $out->make_wrong_resp("Параметр 'ticketType' задан некорректно");
}
//---Валидация $in->status
if(isset($in->status)) {
    if (!in_array($in->status, ['0', '1', '5', '10']) && !empty($in->status)) $out->make_wrong_resp("Параметр 'status' задан некорректно");
    else $in->importance = '0';
}
//---Валидация $in->importance
if(isset($in->importance)) {
    if (!in_array($in->importance, ['5', '10']) && !empty($in->importance)) $out->make_wrong_resp("Параметр 'importance' задан некорректно");
    else $in->importance = '5';
}
//---Валидация $in->comment
if(isset($in->comment)) {
    if (!is_string($in->comment)) $out->make_wrong_resp("Параметр 'comment' задан некорректно");
}




//---Проверка пользователя (2)

