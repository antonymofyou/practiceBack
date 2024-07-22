<?php // Получение домашних заданий

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// класс запроса
$in = new MainRequestClass();
$in->from_json(file_get_contents('php://input'));


// класс ответа
class HometasksGetHometaskResponse extends MainResponseClass {

    /*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - isProbnik
     *     - htNumber 
     *     - typeP1 
     *     - htNumsP1 
     *     - htNumsP2 
     *     - htDeadline 
     *     - htDeadlineTime 
     *     - htStatus
     */
    public $homeTasks = []; // задания 

    /*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - ccCheckDate
     *     - ccCheckTime
     *     - htStatus
     *     - htNumber
     *     - htNum
     */
    public $crossChecks = []; // массив словарей с перекрестной проверкой
}
$out = new HometasksGetHometaskResponse();

//--------------------------------Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_HR . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//--------------------------------Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Админ', 'Куратор'])) $out->make_wrong_resp('Ошибка доступа');

//--------------------------------Получение домашних заданий не с номером 200
$stmt = $pdo->prepare("
        SELECT `is_probnik`, `ht_number`, `type_p1`, `ht_nums_P1`, `ht_nums_P2`, `ht_deadline`, `ht_deadline_time`, `ht_status`
        FROM `home_tasks`
        WHERE `ht_number` != :ht_number
        ORDER BY `ht_deadline`"
        ) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$htNumber = 200;
$stmt->execute(['ht_number' => $htNumber]);

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено в home_tasks");
$notNumber200Tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);
foreach ($notNumber200Tasks as $row){
    $tasks = [
        'isProbnik' => (string) $row['is_probnik'],
        'htNumber' => (string) $row['ht_number'],
        'typeP1' => (string) $row['type_p1'],
        'htNumsP1' => (string) $row['ht_nums_P1'],
        'htNumsP2' => (string) $row['ht_nums_P2'],
        'htDeadline' => (string) $row['ht_deadline'],
        'htDeadlineTime' => (string) $row['ht_deadline_time'],
        'htStatus' => (string) $row['ht_status'],
    ];
    $out-> homeTasks[] = $tasks;
}

//--------------------------------Получение домашних заданий с номером 200
$stmt = $pdo->prepare("
        SELECT `is_probnik`, `ht_number`, `type_p1`, `ht_nums_P1`, `ht_nums_P2`, `ht_deadline`, `ht_deadline_time`, `ht_status`
        FROM `home_tasks`
        WHERE `ht_number`= :ht_number
        ORDER BY `ht_deadline`"
        ) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$htNumber = 200;
$stmt->execute(['ht_number' => $htNumber]);

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено в home_tasks");
$number200Tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);
foreach ($number200Tasks as $row){
    $tasks = [
        'isProbnik' => (string) $row['is_probnik'],
        'htNumber' => (string) $row['ht_number'],
        'typeP1' => (string) $row['type_p1'],
        'htNumsP1' => (string) $row['ht_nums_P1'],
        'htNumsP2' => (string) $row['ht_nums_P2'],
        'htDeadline' => (string) $row['ht_deadline'],
        'htDeadlineTime' => (string) $row['ht_deadline_time'],
        'htStatus' => (string) $row['ht_status'],
    ];
    $out-> homeTasks[] = $tasks;
}

//--------------------------------Формируем выдачу для перекрестной проверки
$stmt = $pdo->prepare("
    SELECT `cross_check`.`ht_num`, `cross_check`.`ht_status`, `cross_check`.`cc_check_date`, `cross_check`.`cc_check_time`,
    `ht_user`.`ht_user_checker`, `ht_user`.`ht_number`
    FROM `cross_check`
    LEFT JOIN `ht_user` ON `cross_check`.`curator_vk_id` = `ht_user`.`ht_user_checker`
    AND `cross_check`.`ht_num` = `ht_user`.`ht_number` AND `ht_user`.`ht_user_status_p2` = 'Проверен'
    LEFT JOIN `home_tasks` ON `home_tasks`.`ht_number` = `cross_check`.`ht_num`
    WHERE `cross_check`.`checker_id` = :user_id
    AND (`cross_check`.`ht_status` = 0 OR `cross_check`.`ht_status` IS NULL)
    AND DATEDIFF(CURDATE(), `home_tasks`.`ht_deadline`) > -3
    GROUP BY `cross_check`.`ht_num`, `cross_check`.`checker_id`"
    ) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$stmt->execute(['user_id' => $user_vk_id]);

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено");
$crossChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);
foreach ($crossChecks as $row){
    $checks = [
        'ccCheckDate' => (string) $row['cc_check_date'],
        'ccCheckTime' => (string) $row['cc_check_time'],
        'htStatus' => (string) $row['ht_status'],
        'htNumber' => (string) $row['ht_number'],
        'htNum' => (string) $row['ht_num'],
    ];
    $out-> crossChecks[] = $checks;
}

//--------------------------------Формируем ответ
$out->success = '1';
$out->make_resp('');