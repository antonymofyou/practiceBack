<?php // Создание заявки в тех. поддержку

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/add_task_sending_to_vk.inc.php';

// Класс запроса
class SupportMakeSupportTicket extends MainRequestClass
{
    /* Словарь заявки, который имеет следующие поля:
     * type          - Тип заявки
     * name          - Название заявки
     * importance    - Важность заявки:
     *                 5 - Обычная,
     *                 10 - Сверхсрочная
     */
    public $ticket = [];
    public $ticket_text = ''; // Текст заявки
}

$in = new SupportMakeSupportTicket();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class SupportMakeSupportTicketResp extends MainResponseClass
{
    public $id = ''; // ID созданной заявки
}

$out = new SupportMakeSupportTicketResp();

// Валидация поля importance (5 - обычная, 10 - сверхсрочная)
in_array($in->ticket['importance'], ['5', '10']) or $out->make_wrong_resp("Поле importance задано некорректно {$in->ticket['importance']}");

/* Типы заявок:
 * 1 - Организационные вопросы,
 * 2 - Финансы, договора,
 * 5 - Антон,
 * 6 - Лиза Тюрина
 * 7 - Редактирование ученика,
 * 10 - Светлана Леонидовна
 */
$responder_id_for_type = [
    '1' => org_answerer,
    '2' => money_answerer,
    '5' => anton_id,
    '6' => main_managers[0],
    '7' => changer_user,
    '10' => sveta_id,
];

// VkID ответчика заявки по типу заявки
$responder_vk_id = $responder_id_for_type[$in->ticket['type']] or $out->make_wrong_resp("Поле type задано некорректно {$in->ticket['type']}");

// Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp("Нет соединения с базой данных");
}

// Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
in_array($user_type, ['Куратор', 'Админ']) or $out->make_wrong_resp("Ошибка доступа");

// Создание подключения к БД для ВК бота
$mysqli = mysqli_init();
$mysqli->real_connect($host, $user, $password, $database, NULL, NULL, $ssl_flag) or $out->make_wrong_resp("can\'t connect DB");

/* Статусы заявок:
 * 0 - Новая,
 * 1 - В работе,
 * 5 - Завершена,
 * 10 - Архив
 */

// Подготовка запроса для создания заявки
$stmt = $pdo->prepare("
        INSERT INTO `tickets` (`ticket_name`, `ticket_type`, `user_vk_id`, `response_vk_id`, `status`, `importance`, `ticket_side`, `when_made`, `when_changed`) 
        VALUES (:name, :type, :userVkId, :responderVkId, 0, :importance, 0, NOW(), NOW())
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

// Выполнение запроса для создания заявки
$stmt->execute([
    'name' => $in->ticket['name'],
    'type' => $in->ticket['type'],
    'importance' => $in->ticket['importance'],
    'userVkId' => $user_vk_id,
    'responderVkId' => $responder_vk_id,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

// Получение ID созданной заявки
$out->id = $pdo->lastInsertId();
if (!$out->id) $out->make_wrong_resp("Произошла ошибка при создании заявки");

// Подготовка запроса для создания сообщения заявки
$stmt = $pdo->prepare("
        INSERT INTO `tickets_mess` (`ticket_id`, `user_vk_id`, `comment`, `comment_dtime`) 
        VALUES(:id, :userVkId, :comment, NOW())
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');

// Выполнение запроса для создания сообщения заявки
$stmt->execute([
    'id' => $out->id,
    'userVkId' => $user_vk_id,
    'comment' => $in->ticket_text,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');

// Отправка заявок через ВК бота
$message = $in->ticket['importance'] > 5 ? 'Тебе назначена СРОЧНАЯ заявка №' . $out->id : 'Тебе назначена заявка №' . $out->id;
addTaskSendingToVk($mysqli, [$responder_vk_id], $message);

// Ответ
$out->success = "1";
$out->make_resp("");
