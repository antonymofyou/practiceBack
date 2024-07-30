<?php // Получаем данные пользователя по списаниям и пополнениям

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class UsersGetUserTransactions extends MainRequestClass {
    public $userVkId = ''; // Идентификатор ВК пользователя
}
$in = new UsersGetUserTransactions();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class UsersGetUserTransactionsResponse  extends MainResponseClass {

    //Текущий баланс
    public $balanceNow = '';

    /* Массив словарей со следующими полями:
        - bsId - Идентификатор транзакции
        - bsOrderId - Идентификатор пополнения
        - bsComment - Комментарий к транзакции
        - bsType - Тип транзакции
        - bsChanger - ?
        - bsDate - Дата транзакции
        - bsTime - Время транзакции
        - bsValue - Сумма транзакции
    */
    public $transactions = [];
}
$out = new UsersGetUserTransactionsResponse();

//Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Админ'])) $out->make_wrong_resp('Ошибка доступа'); // Доступ только у админа

//Валидация $in->userVkId
if (((string) (int) $in->userVkId) !== ((string) $in->userVkId) || (int) $in->userVkId <= 0) $out->make_wrong_resp("Параметр 'userVkId' задан неверно или отсутствует");
    $stmt = $pdo->prepare("
        SELECT `user_vk_id`
        FROM `users`
        WHERE `user_vk_id` = :userVkId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'userVkId' => $in->userVkId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Пользователь с ВК ID {$in->userVkId} не найден");
    $stmt->closeCursor(); unset($stmt);

//Получаем данные о текущем балансе
$stmt = $pdo->prepare("
    SELECT `bn_balance`
    FROM `balance_now`
    WHERE `bn_user_id` = :userVkId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
$balanceNow = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Получаем массив данных о прошлых транзакциях
$stmt = $pdo->prepare("
    SELECT `bs_id`, `bs_order_id`, `bs_comment`, `bs_type`, `bs_value`, `bs_date`, `bs_time`, `bs_changer`
    FROM `balance_story`
    WHERE `bs_user_id` = :userVkId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
//Пополняем массив $out->transactions
while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $out->transactions[] = [
        'bsId' => (string) $item['bs_id'],
        'bsOrderId' => (string) $item['bs_order_id'],
        'bsCommentId' => (string) $item['bs_comment'],
        'bsType' => (string) $item['bs_type'],
        'bsValue' => (string) $item['bs_value'],
        'bsDate' => (string) $item['bs_date'],
        'bsTime' => (string) $item['bs_time'],
        'bsChanger' => (string) $item['bs_changer']
    ];
} $stmt->closeCursor(); unset($stmt);

$out->success = "1";
$out->make_resp('');