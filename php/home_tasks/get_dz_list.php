<?php // Получение домашних заданий

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// класс запроса
class HometasksGetHometask extends MainRequestClass {

}


// класс ответа
class HometasksGetHometaskResponse extends MainResponseClass {

    /*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - is_probnik
     *     - ht_number 
     *     - type_p1 
     *     - ht_nums_P1 
     *     - ht_nums_P2 
     *     - ht_deadline 
     *     - ht_deadline_time 
     *     - ht_status
     */
    public $notNumber200Tasks = []; // задания всех номеров, но без 200-х

    /*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - is_probnik
     *     - ht_number 
     *     - type_p1 
     *     - ht_nums_P1 
     *     - ht_nums_P2 
     *     - ht_deadline 
     *     - ht_deadline_time 
     *     - ht_status
     */
    public $number200Tasks = []; // задания с номером 200 

    /*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - cc_check_date
     *     - cc_check_time
     *     - ht_status
     *     - ht_number
     *     - ht_num
     */
    public $crossCheks = []; // массив словарей с перекрестной проверкой
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
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.php';
if (!($user_type == 'Админ' || $user_type == 'Куратор') )
{
	header("Location: /");
	exit();
} else{

    header('Content-Type: application/json; charset=utf-8');
}
//--------------------------------Получение домашних заданий не с номером 200
$stmt = $pdo->prepare("
        SELECT *, DATE_FORMAT(`ht_deadline`,'%d.%m.%Y') as `ht_deadline`
        FROM `home_tasks`
        WHERE `ht_number` != :ht_number
        ORDER BY `home_tasks`.`ht_deadline` DESC"
        ) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$ht_number = 200;
$stmt->bindParam(':ht_number', $ht_number, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено в home_tasks");
$not_number_200_tasks = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);
$not_number_200_tasks = [
    'is_probnik' => (string) $not_number_200_tasks['is_probnik'],
    'ht_number' => (string) $not_number_200_tasks['ht_number'],
    'type_p1' => (string) $not_number_200_tasks['type_p1'],
    'ht_nums_P1' => (string) $not_number_200_tasks['ht_nums_P1'],
    'ht_nums_P2' => (string) $not_number_200_tasks['ht_nums_P2'],
    'ht_deadline' => (string) $not_number_200_tasks['ht_deadline'],
    'ht_deadline_time' => (string) $not_number_200_tasks['ht_deadline_time'],
    'ht_status' => (string) $not_number_200_tasks['ht_status'],
];

//--------------------------------Получение домашних заданий с номером 200
$stmt = $pdo->prepare("
        SELECT *, DATE_FORMAT(`ht_deadline`,'%d.%m.%Y') as `ht_deadline`
        FROM `home_tasks`
        WHERE `ht_number` = :ht_number
        ORDER BY `home_tasks`.`ht_deadline` DESC"
        ) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$ht_number = 200;
$stmt->bindParam(':ht_number', $ht_number, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено в home_tasks");
$number_200_tasks = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);
$number_200_tasks = [
    'is_probnik' => (string) $number_200_tasks['is_probnik'],
    'ht_number' => (string) $number_200_tasks['ht_number'],
    'type_p1' => (string) $number_200_tasks['type_p1'],
    'ht_nums_P1' => (string) $number_200_tasks['ht_nums_P1'],
    'ht_nums_P2' => (string) $number_200_tasks['ht_nums_P2'],
    'ht_deadline' => (string) $number_200_tasks['ht_deadline'],
    'ht_deadline_time' => (string) $number_200_tasks['ht_deadline_time'],
    'ht_status' => (string) $number_200_tasks['ht_status'],
];

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

$stmt->bindParam(':user_id', $user_vk_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено");
$crosscheks = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);
$crosscheks = [
    'cc_check_date' => (string) $crosscheks['cc_check_date'],
    'cc_check_time' => (string) $crosscheks['cc_check_time'],
    'ht_status' => (string) $crosscheks['ht_status'],
    'ht_number' => (string) $crosscheks['ht_number'],
    'ht_num' => (string) $crosscheks['ht_num'],
];

//--------------------------------Формируем ответ
$out->success = '1';
$out->not_number_200_tasks = (object) $not_number_200_tasks;
$out->number_200_tasks = (object) $number_200_tasks;
$out->crosscheks = (object) $crosscheks;
$out->make_resp('');















session_start();
require 'includes/autorise.inc.php';

if (!($_SESSION['user_type'] == 'Админ' || $_SESSION['user_type'] == 'Куратор')) {
    header("Location: /");
    exit();
}

header('Content-Type: application/json');

$link = mysqli_connect($host, $user, $password, $database) or die(json_encode(["error" => mysqli_error($link)]));

$tasks = [];

// ДЗ200 
$query = "SELECT *, DATE_FORMAT(`ht_deadline`,'%d.%m.%Y') as `ht_deadline`, `ht_deadline_time` FROM `home_tasks` WHERE `ht_number`=200 ORDER BY `home_tasks`.`ht_deadline` DESC;";
$result = mysqli_query($link, $query) or die(json_encode(["error" => mysqli_error($link)]));

while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
    $tasks[] = $row;
}

$response = [
    "tasks" => $tasks,
    "cross_checks" => []
];

// Формируем выдачу для перекрестной проверки
$query = "
SELECT `cross_check`.*, `ht_user`.`ht_user_checker`, `ht_user`.`ht_number` FROM `cross_check` 
INNER JOIN `ht_user` 
ON `cross_check`.`curator_vk_id`=`ht_user`.`ht_user_checker` 
AND `cross_check`.`ht_num`=`ht_user`.`ht_number`
WHERE `cross_check`.`checker_id`='".$_SESSION['user_id']."'
AND (`cross_check`.`ht_status`=0 OR `cross_check`.`ht_status` IS NULL)
GROUP BY `ht_num`, `checker_id`
;";

$result_check = mysqli_query($link, $query) or die(json_encode(["error" => mysqli_error($link)]));

while ($row_check = mysqli_fetch_array($result_check, MYSQLI_ASSOC)) {
    $response["cross_checks"][] = $row_check;
}

echo json_encode($response);
?>

