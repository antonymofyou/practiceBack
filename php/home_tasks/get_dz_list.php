<?php
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

