<?php

// Подключение корневых классов и заголовков
require_once __DIR__ . '/includes/api_default.inc.php';
require_once __DIR__ . '/includes/vk_config.inc.php';

// Типы пользователей (whitelist)
$userTypes = ['Учен', 'Учен(нов)', 'Выпуск', 'Пакет', 'Курат', 'Блокир', 'Админ', 'Демо'];

// Класс запроса
class GetUsersRequest extends MainRequestClass
{
    public $userTypes = []; // Типы искуемых пользователей (array)
    public $orderBy = ''; // Наименование поля по которому производится сортировка (lastName, curator, type, blocked) (string)
    public $vk = ''; // VK ID или VK Short link по которому производится поиск (string)
}

$in = new GetUsersRequest();
$in->from_json(file_get_contents('php://input'));


// Класс ответа
class GetUsersResponse extends MainResponseClass
{
    public $users = []; // Список найденных пользователей (array)
}

$out = new GetUsersResponse();


// Составляем SQL для поиска
$whereSql = '';
if ($in->vk != (int)$in->vk) { // Если в поле VK был передан не VK ID
    $vkBotConfig = new ConfigBotVK();

    // Получаем короткую ссылку
    $userShortLink = str_replace(array('http://', 'https://', 'm.vk.com/', 'vk.com/'), '', $in->vk);

    // Обращаемся и получаем информацию о пользователе
    $userInfo = @file_get_contents("https://api.vk.com/method/utils.resolveScreenName?screen_name=$userShortLink&v=$vkBotConfig->ver&access_token=$vkBotConfig->gr_key");

    if (!$userInfo) {
        $out->make_wrong_resp('Произошла ошибка при выполнении запроса VK API.', 0);
    }

    $userInfo = json_decode($userInfo, true);

    // Если пользователь во ВКонтакте не найден
    if (!isset($userInfo['response']['type']) || $userInfo['response']['type'] != 'user') {
        $out->make_wrong_resp('Пользователь не найден (VK API).', 0);
    }

    $vkId = $userInfo['response']['object_id'];

    // Переопределяем ввод VK ID, чтобы поиск выполнялся по нужному значению
    $in->vk = $vkId;
}

$whereSql .= 'WHERE `users`.`user_vk_id` = :searchValue';


// Составляем SQL для сортировки
$orderSql = '';
switch ($in->orderBy) {
    case 'lastName': // Указанная сортировка по фамилии
        $orderSql .= 'ORDER BY `users`.`user_surname` ';
        break;

    case 'curator': // Сортировка по куратору
        $orderSql .= 'ORDER BY `users`.`curator` ';
        break;

    case 'type': // Сортировка по типу
        $orderSql .= 'ORDER BY `users`.`user_type` ';
        break;

    case 'isBlocked': // Сортировка по положительному состоянию блокировки
        $orderSql .= 'ORDER BY `users`.`user_blocked` ';
        break;

    default: // По умолчанию сортировка по фамилии
        $orderSql .= 'ORDER BY `users`.`user_surname` ';
        break;
}


// Собираем SQL для фильтрации типов пользователей
foreach ($in->userTypes as $key => $inUserType) {
    // Защита от SQL Injection через whitelist
    if (in_array($inUserType, $userTypes)) {
        if ($key == 0) {
            $whereSql .= ' AND `users`.`user_type` = "' . $inUserType . '" ';
        } else {
            $whereSql .= 'OR `users`.`user_type` = "' . $inUserType . '"';
        }
    }
}


// Собираем финальный запрос
$sql = "
SELECT `users`.*, `users_add`.`user_goal_ball`, `regions`.`reg_timediff`,
IF(`users`.`user_blocked` is NULL, 0, `users`.`user_blocked`) as `user_blocked1`, DATE_FORMAT(`users`.`user_start_course_date`, '%d.%m.%Y') AS `user_start_course_date`,
CONCAT(`curators`.`user_surname`, ' ', `curators`.`user_name`) AS `curator`, `balance_now`.`bn_balance`, SIGN(`balance_now`.`bn_balance`) as `balance_sign`
	FROM  `users`
	LEFT JOIN `users_add` ON `users_add`.`user_vk_id`=`users`.`user_vk_id`
	LEFT JOIN `users` AS `curators` ON `users`.`user_curator` = `curators`.`user_vk_id` 
	LEFT JOIN `balance_now` ON `users`.`user_vk_id` = `balance_now`.`bn_user_id`
	LEFT JOIN `regions` ON `users`.`user_region`=`regions`.`reg_number`
	$whereSql
	$orderSql;";

$whereData = array('searchValue' => $in->vk);


// Выполняем запрос
try {
    $db = new Database();
} catch (Exception $ex) {
    $out->make_wrong_resp('Произошла ошибка при соединении с СУБД.', 0);
}

try {
    $stmt = $db->pdo->prepare($sql);
    $stmt->execute($whereData);
    $out->success = 1;
    $out->users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    echo $sql;
    $out->make_wrong_resp('Произошла ошибка при выполнении запроса.', 0);
}

$out->make_resp('');
