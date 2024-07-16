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
     *     - isProbnik
     *     - htNumber 
     *     - typeP1
     *     - htNumsP1
     *     - htNumsP2 
     *     - htDeadline 
     *     - htDeadlineTime 
     *     - htStatus
     */
    public $tasks = []; // задания всех номеров, но без 200-х

    /*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - ccCheckDate
     *     - ccCheckTime
     *     - htStatus
     *     - htNumber
     *     - htNum
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
        SELECT 'isProbnik', 'htNumber', 'typeP1', 'htNumsP1', 'htNumsP2', 'htDeadline', 'htDeadlineTime', 'htStatus'
        FROM `homeTasks`
        WHERE `htNumber` != 200
        ORDER BY `htDeadline`"
        ) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$stmt->execute();

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено");
$notNumber200Tasks = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//--------------------------------Получение домашних заданий с номером 200
$stmt = $pdo->prepare("
        SELECT 'isProbnik', 'htNumber', 'typeP1', 'htNumsP1', 'htNumsP2', 'htDeadline', 'htDeadlineTime', 'htStatus'
        FROM `homeTasks`
        WHERE `htNumber`= 200
        ORDER BY `htDeadline`"
        ) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$stmt->execute();

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено");
$number200Tasks = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//--------------------------------Записываем полученные задачи в $tasks
$tasks = array_merge($notNumber200Tasks, $number200Tasks);

//--------------------------------Формируем выдачу для перекрестной проверки
$stmt = $pdo->prepare("
    SELECT `crossCheck`.`htNum`, `crossCheck`.`htStatus`, `crossCheck`.`ccCheckDate`, `crossCheck`.`ccCheckTime`,
    `htUser`.`htUserChecker`, `htUser`.`htNumber`
    FROM `crossCheck`
    LEFT JOIN `htUser` ON `crossCheck`.`curatorVkId` = `htUser`.`htUserChecker`
    AND `crossCheck`.`htNum` = `htUser`.`htNumber` AND `htUser`.`htUserStatus_p2` = 'Проверен'
    LEFT JOIN `homeTasks` ON `homeTasks`.`htNumber` = `crossCheck`.`htNum`
    WHERE `crossCheck`.`checkerId` = :user_id
    AND (`crossCheck`.`htStatus` = 0 OR `crossCheck`.`htStatus` IS NULL)
    AND DATEDIFF(CURDATE(), `homeTasks`.`htDeadline`) > -3
    GROUP BY `crossCheck`.`htNum`, `crossCheck`.`checkerId`"
    ) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$stmt->bindParam(':user_id', $user_vk_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) $out->make_wrong_resp("записей не найдено");
$crosscheks = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);
$crosscheks = [
    'ccCheckDate' => (string) $crosscheks['ccCheckDate'],
    'ccCheckTime' => (string) $crosscheks['ccCheckTime'],
    'htStatus' => (string) $crosscheks['htStatus'],
    'htNumber' => (string) $crosscheks['htNumber'],
    'htNum' => (string) $crosscheks['htNum'],
];

//--------------------------------Формируем ответ
$out->success = '1';
$out->tasks = (object) $tasks;
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

