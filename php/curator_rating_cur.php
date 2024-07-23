<?php // Получение списка рейтингов

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class HomeTaskDzProverkaList extends MainRequestClass {
	public $curatorVkId = ''; // идентификатор куратора
	
}
$in = new HomeTaskDzProverkaList();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class HomeTaskDzProverkaListResponse extends MainResponseClass {
	/*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - curatorVkId - идентификатор куратора
     *     - userVkId - идентификатор пользователя
     *     - whenMade - дата и время создания
     *     - oc1 - рейтинг пунктуальности
     *     - oc2 - рейтинг понятности
     *     - oc3 - рейтинг качества проверки ДЗ
     *     - oc4 - рейтинг качества ответов на вопросы
     *     - oc5 - рейтинг отношения куратора
     *     - oc6 - рейтинг Инициативности
     *     - comm1 - комментарий к рейтингу пунктуальности
     *     - comm2 - комментарий к рейтингу понятности
     *     - comm3 - комментарий к рейтингу качества проверки ДЗ
     *     - comm4 - комментарий к рейтингу качества ответов на вопросы
     *     - comm5 - комментарий к рейтингу отношения куратора
     *     - comm6 - комментарий к рейтингу Инициативности
     *     - curator - имя и фамилия куратора
	 * 
     */
    public $curatorsRating = []; // массив словарей с данными по характеристикам
}

$out = new HomeTaskDzProverkaListResponse();

//--------------------------------Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//--------------------------------Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
$curatorVkId = '';
if (in_array($user_type, ['Админ']) && !empty($in->curatorVkId)) $curatorVkId = $in->curatorVkId;
else $curatorVkId = $user_vk_id;

//--------------------------------Получение списка рейтингов
if (in_array($user_type, ['Админ']) && empty($in->curatorVkId)) {
	$query = "SELECT CONCAT(`curators`.`user_surname` ,  ' ', `curators`.`user_name` ) AS  `curator`, 
		`users`.`user_surname`, `users`.`user_name`,
		`curators_rating`.`curator_vk_id`, `curators_rating`.`user_vk_id`, `curators_rating`.`when_made`, 
		`curators_rating`.`oc1`, `curators_rating`.`comm1`,
		`curators_rating`.`oc2`, `curators_rating`.`comm2`,
		`curators_rating`.`oc3`, `curators_rating`.`comm3`,
		`curators_rating`.`oc4`, `curators_rating`.`comm4`,
		`curators_rating`.`oc5`, `curators_rating`.`comm5`,
		`curators_rating`.`oc6`, `curators_rating`.`comm6`
		FROM `curators_rating`
		LEFT JOIN `users` ON `users`.`user_vk_id`=`curators_rating`.`user_vk_id`
		LEFT JOIN `users` as `curators` ON `curators`.`user_vk_id`=`users`.`user_curator`
		WHERE `users`.`user_blocked`!=1
		ORDER BY `curators_rating`.`when_made` DESC;";
	$params = [];
}
else{
	$query = "SELECT CONCAT(`curators`.`user_surname` ,  ' ', `curators`.`user_name` ) AS  `curator`, 
		`users`.`user_surname`, `users`.`user_name`, 
		`curators_rating`.`curator_vk_id`, `curators_rating`.`user_vk_id`, `curators_rating`.`when_made`, 
		`curators_rating`.`oc1`, `curators_rating`.`comm1`,
		`curators_rating`.`oc2`, `curators_rating`.`comm2`,
		`curators_rating`.`oc3`, `curators_rating`.`comm3`,
		`curators_rating`.`oc4`, `curators_rating`.`comm4`,
		`curators_rating`.`oc5`, `curators_rating`.`comm5`,
		`curators_rating`.`oc6`, `curators_rating`.`comm6`
		FROM `curators_rating`
		LEFT JOIN `users` ON `users`.`user_vk_id`=`curators_rating`.`user_vk_id`
		LEFT JOIN `users` as `curators` ON `curators`.`user_vk_id`=`users`.`user_curator`
		WHERE `curators_rating`.`curator_vk_id`= :curator_id AND `users`.`user_blocked`!=1 AND `users`.`user_curator`= :curator_id
		ORDER BY `curators_rating`.`when_made` DESC;";
	$params = [
		'curator_id' => $curatorVkId,
	];
}
$stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute($params) or $out->make_wrong_reps('Ошибка базы данных: выполнение запроса (1)');
if($stmt->rowCount() == 0) $out->make_wrong_resp("Ни один рейтинг не был найден для пользователя/куратора [ID пользователя/куратора: {$curatorVkId}] ");

$curatorsRating = [];
while ($curatorRating = $stmt->fetch(PDO::FETCH_ASSOC)){
	$curatorsRating[] = [
		'curatorVkId' => (string) $curatorRating['curator_vk_id'],
		'userVkId' => (string) $curatorRating['user_vk_id'],
		'whenMade' => (string) $curatorRating['when_made'],
		'oc1' => (string) $curatorRating['oc1'],
		'oc2' => (string) $curatorRating['oc2'],
		'oc3' => (string) $curatorRating['oc3'],
		'oc4' => (string) $curatorRating['oc4'],
		'oc5' => (string) $curatorRating['oc5'],
		'oc6' => (string) $curatorRating['oc6'],
		'comm1' => (string) $curatorRating['comm1'],
		'comm2' => (string) $curatorRating['comm2'],
		'comm3' => (string) $curatorRating['comm3'],
		'comm4' => (string) $curatorRating['comm4'],
		'comm5' => (string) $curatorRating['comm5'],
		'comm6' => (string) $curatorRating['comm6'],
		'curator' => (string) $curatorRating['curator'],
	];
}
$stmt->closeCursor(); unset($stmt);

//--------------------------------Формирование ответа
$out->success = '1';
$out->curatorsRating = $curatorsRating;
$out->make_resp('');