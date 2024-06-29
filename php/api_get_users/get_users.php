<?php

// Подключение корневых классов и заголовков
require_once __DIR__ . '/includes/api_default.inc.php';
require_once __DIR__ . '/includes/vk_config.inc.php';

// Типы пользователей (whitelist)
$userTypes = ['student', 'leaver', 'packer', 'curator', 'admin'];

// Класс запроса
class GetUsersRequest extends MainRequestClass
{
    public $inUserTypes = []; // Типы искуемых пользователей (array)
    public $inSearchType = ''; // Наименование поля по которому производится поиск (vk, lastName, curator, type, isBlocked) (string)
    public $inSearchValue = ''; // Значение по которому производится поиск (string)
}

$in = new GetUsersRequest();
$in->from_json(file_get_contents('php://input'));


// Класс ответа
class GetUsersResponse extends MainResponseClass
{
    public $usersArray = []; // Список найденных пользователей (array)
}

$out = new GetUsersResponse();


// Составляем SQL для поиска
$whereSql = '';
switch ($in->inSearchType) {
    case 'vk': // Поиск по ВКонтакте (ID/короткая ссылка)
        if ($in->inSearchValue != (int)$in->inSearchValue) { // Если в поле VK был передан не VK ID
            $vkBotConfig = new ConfigBotVK();

            // Получаем короткую ссылку
            $userShortLink = str_replace(array('http://', 'https://', 'm.vk.com/', 'vk.com/'), '', $in->inSearchValue);

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
            $in->inSearchValue = $vkId;
        }

        $whereSql .= 'WHERE `users`.`user_vk_id` = :searchValue ';
        break;

    case 'lastName': // Поиск по фамилии
        $whereSql .= 'WHERE `users`.`user_surname` = :searchValue ';
        break;

    case 'curator': // Поиск по куратору
        $whereSql .= 'WHERE `users`.`curator` = :user_curator ';
        break;

    case 'type': // Поиск по типу
        $whereSql .= 'WHERE `users`.`user_type` = :searchValue ';
        break;

    case 'isBlocked': // Поиск по положительному состоянию блокировки
        $whereSql .= 'WHERE `users`.`user_blocked` = :searchValue ';
        break;

    default: // По умолчанию поиск не производится
        $out->make_wrong_resp('Неверное наименование поля.', 0);
}

// Собираем SQL для фильтрации типов пользователей
foreach ($in->inUserTypes as $key => $inUserType) {
    // Защита от SQL Injection через whitelist
    if (in_array($inUserType, $userTypes)) {
        if ($key == 0) {
            $whereSql .= "AND `users`.`user_type` = '$inUserType' ";
        } else {
            $whereSql .= "OR `users`.`user_type` = '$inUserType' ";
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
	$whereSql;";

$whereData = array('searchValue' => $in->inSearchValue);


// Выполняем запрос
$pdo = new Database();

if (!$pdo) {
    $out->make_wrong_resp('Произошла ошибка при соединении с СУБД.', 0);
}

try {
    $stmt = $pdo->pdo->prepare($sql);
    $stmt->execute($whereData);
    $out->usersArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    $out->make_wrong_resp('Произошла ошибка при выполнении запроса.', 0);
}

$out->make_resp('');
