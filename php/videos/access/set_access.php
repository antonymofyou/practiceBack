<?php //---Редактирование прав пользователей к видео

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class VideosAccessSetVideoAccess extends MainRequestClass {
    public $userVkId = ''; // Идентификатор ВК пользователя, которому нужно отредактировать права к видео
    /* Массив, содержащий словари со следующими полями:
        - videoID - ИД видео, к которому нужно отредактировать права
        - access - Заблокировать или разрешить доступ (0/1)
    */
    public $videosAccess = []; //Массив словарей с данными для редактирования доступа к видео, может содержать только одно видео
}
$in = new VideosAccessSetVideoAccess();
$in->from_json(file_get_contents('php://input'));


//---Класс ответа
$out = new MainResponseClass();

//---Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//---Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if($user_type != 'Админ') $out->make_wrong_resp("Ошибка доступа");

//---Валидация $in->userVkId
if (((string) (int) $in->userVkId) !== ((string) $in->userVkId) || (int) $in->userVkId <= 0) $out->make_wrong_resp("Параметр 'userVkId' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `users`.`user_vk_id`
    FROM `users`
    WHERE `user_vk_id` = :userVkId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Пользователь с таким ID не найден");
$stmt->closeCursor(); unset($stmt);


//---Валидация $in->videosAccess[...]['videoId'] 
$videoIDs = []; //Валидированные ID
$wheres = []; //Части условия поиска
$count = 0;
foreach ($in->videosAccess as $video) {
    if (((string) (int) $video['videoId']) !== ((string) $video['videoId']) || (int) $video['videoId'] <= 0) $out->make_wrong_resp("Параметр 'videoId' в 'videoAccess[{$count}]' задан неверно или отсутствует");
    $wheres[] = ":videoId$count";
    $videoID = "videoId$count";
    $videoIDs[$videoID] = $video['videoId'];
    $count++;
};

$whereClause = '`video_id` = ' . join(' OR `video_id` = ', $wheres); //По полученным ID проводится перебор видео по БД

$stmt = $pdo->prepare("
    SELECT `video_id`
    FROM `videos`
    WHERE $whereClause
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (2)");
$stmt->execute($videoIDs) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
$videos;
while($video = $stmt->fetch()) {
    $videos[] = $video['video_id'];
}
$videosDiff = array_diff($videoIDs, $videos); //Получение разности массивов между ID, полученными в запрос и ID, полученными из БД. БД не отправит столбцы с ID, которых нет
if(!empty($videosDiff)) { //Если есть значения, существующие в одном массиве, но не в другом, то выдаём ошибку и эти ID
    $errorIDs = join(', ', $videosDiff);
    if (count($videosDiff) > 1) $out->make_wrong_resp("Видео с ID {$errorIDs} не найдены");
    else $out->make_wrong_resp("Видео с ID {$errorIDs} не найдено");
}
$stmt->closeCursor(); unset($stmt);


//---Валидация $in->videosAccess[...]['access']
$count = 0;
foreach ($in->videosAccess as $video) {
   if(!in_array($video['access'], [0, 1])) $out->make_wrong_resp("Параметр 'access' в 'videoAccess[{$count}]' задан неверно или отсутствует");
   $count++;
};


//---Создание или обновление прав пользователя к видео
//Формирование множественного добавления
$params = [];
$values = [];
$count = 0;
foreach($in->videosAccess as $key => $value) {
    $values[] = "(:videoId$count, :userVkId, :access$count)";
    $param = "videoId$count";
    $params[$param] = $value['videoId'];
    $param = "access$count";
    $params[$param] = $value['access'];
    $count++;
}
$params['userVkId'] = $in->userVkId;
$values = join(', ', $values);

$stmt = $pdo->prepare("
    INSERT INTO `video_access`
    (`video_id`, `user_vk_id`, `access`)
    VALUES $values ON DUPLICATE KEY UPDATE `access` = VALUE(`access`); 
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (3)");
$stmt->execute($params) or $out->make_wrong_resp("Ошибка базы данных: выполнение запроса (3)");

$stmt->closeCursor(); unset($stmt);

$out->success = "1";
$out->make_resp('');