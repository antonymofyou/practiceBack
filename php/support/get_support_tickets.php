<?php // Получение информации для кураторов

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';

// Класс запроса
$in = new MainRequestClass();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class SupportGetSupportTicketsResp extends MainResponseClass
{
    /*
     * 3 массива словарей заявок (текущие, отложенные и завершенные)
     * с одинаковой структурой, где каждый словарь имеет следующие поля:
     * id            - ID заявки
     * name          - Название заявки
     * type          - Типы заявок:
     *                 1 - Организационные вопросы,
     *                 2 - Финансы, договора,
     *                 5 - Антон,
     *                 6 - Лиза Тюрина
     *                 7 - Редактирование ученика,
     *                 10 - Светлана Леонидовна
     * status        - Статусы заявок:
     *                 0 - Новая,
     *                 1 - В работе,
     *                 5 - Завершена,
     *                 10 - Архив
     * deadline      - Дата дедлайна заявки
     * needAnswer    - Нужен ли ответ на заявку (влияет на отображение):
     *                 0 - Нет,
     *                 1 - Да
     */
    public $currentTickets = [];
    public $deferredTickets = [];
    public $completedTickets = [];
}

$out = new SupportGetSupportTicketsResp();

// Проверка пользователя
in_array($user_type, ['Куратор', 'Админ']) or $out->make_wrong_resp("Ошибка доступа");

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

// Получаем заявки для администратора
if ($user_type == 'Админ') {
    // Выполнение запроса для получения текущих заявок
    $stmt = $pdo->query("
        SELECT `ticket_id`, `ticket_name`, `ticket_type`, `status`, `ticket_deadline`, `ticket_side`, `user_vk_id`, `response_vk_id`
        FROM `tickets` 
        WHERE `status` NOT IN (5,10) 
        AND (`ticket_deadline` IS NULL OR `ticket_deadline`<=CURDATE())
		ORDER BY `status`, `ticket_side` DESC, `when_changed` DESC
    ") or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

    $currentTickets = fillTickets($stmt, $user_vk_id);

    // Выполнение запроса для получения отложенных заявок
    $stmt = $pdo->query("
        SELECT `ticket_id`, `ticket_name`, `ticket_type`, `status`, `ticket_deadline`, `ticket_side`, `user_vk_id`, `response_vk_id`
        FROM `tickets` 
        WHERE `status` NOT IN (5, 10)
		AND (`ticket_deadline`>CURDATE())
		ORDER BY `status`, `ticket_side` DESC, `when_changed` DESC
    ") or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');

    $deferredTickets = fillTickets($stmt, $user_vk_id);

    // Выполнение запроса для получения завершенных заявок
    $stmt = $pdo->query("
        SELECT `ticket_id`, `ticket_name`, `ticket_type`, `status`, `ticket_deadline`, `ticket_side`, `user_vk_id`, `response_vk_id`
        FROM `tickets` 
        WHERE `status` IN (5) 
		ORDER BY `ticket_id` DESC
		LIMIT 200
    ") or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');

    $completedTickets = fillTickets($stmt, $user_vk_id);

    $stmt->closeCursor();
    unset($stmt);

    $out->currentTickets = $currentTickets;
    $out->deferredTickets = $deferredTickets;
    $out->completedTickets = $completedTickets;
}

// Получаем заявки для куратора
if ($user_type == 'Куратор') {
    // Подготовка запроса для получения текущих заявок
    $stmt = $pdo->prepare("
        SELECT `ticket_id`, `ticket_name`, `ticket_type`, `status`, `ticket_deadline`, `ticket_side`, `user_vk_id`, `response_vk_id`
        FROM `tickets` 
        WHERE (`user_vk_id` = :userVkId OR `response_vk_id` = :userVkId) 
		AND `status` NOT IN (5,10)
		AND (`ticket_deadline` IS NULL OR `ticket_deadline`<=CURDATE())
		ORDER BY `status`, `ticket_side` DESC,  `when_changed` DESC
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');

    // Выполнение запроса для получения текущих заявок
    $stmt->execute(['userVkId' => $user_vk_id]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');

    $currentTickets = fillTickets($stmt, $user_vk_id);

    // Подготовка запроса для получения отложенных заявок
    $stmt = $pdo->prepare("
        SELECT `ticket_id`, `ticket_name`, `ticket_type`, `status`, `ticket_deadline`, `ticket_side`, `user_vk_id`, `response_vk_id`
        FROM `tickets` 
        WHERE (`user_vk_id` = :userVkId OR `response_vk_id` = :userVkId) 
		AND `status` NOT IN (5,10)
		AND (`ticket_deadline`>CURDATE())
		ORDER BY `status`, `ticket_side` DESC,  `when_changed` DESC
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');

    // Выполнение запроса для получения отложенных заявок
    $stmt->execute(['userVkId' => $user_vk_id]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');

    $deferredTickets = fillTickets($stmt, $user_vk_id);

    // Подготовка запроса для получения завершенных заявок
    $stmt = $pdo->prepare("
        SELECT `ticket_id`, `ticket_name`, `ticket_type`, `status`, `ticket_deadline`, `ticket_side`, `user_vk_id`, `response_vk_id`
        FROM `tickets` 
        WHERE (`user_vk_id` = :userVkId OR `response_vk_id` = :userVkId) 
		AND `status` IN (5)
		ORDER BY `ticket_id` DESC
		LIMIT 200
    ") or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');

    // Выполнение запроса для получения завершенных заявок
    $stmt->execute(['userVkId' => $user_vk_id]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');

    $completedTickets = fillTickets($stmt, $user_vk_id);

    $stmt->closeCursor();
    unset($stmt);

    $out->currentTickets = $currentTickets;
    $out->deferredTickets = $deferredTickets;
    $out->completedTickets = $completedTickets;
}

/**
 * Функция заполняет массив элементами полученными в запросе
 *
 * @param PDOStatement $stmt - Объект PDOStatement с выполненным запросом SELECT с колонками:
 * - ticket_id,
 * - ticket_name,
 * - ticket_type,
 * - status,
 * - ticket_deadline,
 * - ticket_side,
 * - user_vk_id,
 * - response_vk_id
 * @param int $user_vk_id - ВК ID пользователя ($user_vk_id из check_user.inc.php)
 * @return array
 */
function fillTickets(PDOStatement $stmt, int $user_vk_id): array
{
    $tickets = [];

    while ($ticket = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $isUserSide = $ticket['ticket_side'] == '1' && $ticket['user_vk_id'] == $user_vk_id; // Пользователь, смотрит свою заявку (создатель заявки)?
        $isAdminSide = $ticket['ticket_side'] == '0' && $ticket['response_vk_id'] == $user_vk_id; // Пользователь, смотрит свою заявку (тех. поддержка)?
        $sideMatches = $isUserSide || $isAdminSide; // Пользователь, смотрит свою заявку?
        $isStatusActual = $ticket['status'] < 2; // Статус заявки равен "новая" или "текущая"?
        $needAnswer = $sideMatches && $isStatusActual ? '1' : '0'; // Нужно отображать "требует ответа"?

        $tickets[] = [
            'id' => (string)$ticket['ticket_id'],
            'name' => (string)$ticket['ticket_name'],
            'type' => (string)$ticket['ticket_type'],
            'status' => (string)$ticket['status'],
            'deadline' => (string)$ticket['ticket_deadline'],
            'needAnswer' => $needAnswer,
        ];
    }

    return $tickets;
}

// Ответ
$out->success = '1';
$out->make_resp('');
