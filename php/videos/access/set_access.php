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


//---Валидация $in->userVkId
if (((string) (int) $in->userVkId) !== ((string) $in->userVkId) || (int) $in->userVkId <= 0) $out->make_wrong_resp("Параметр 'userVkId' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `user_vk_id`, `user_type`
    FROM `users`
    WHERE `user_vk_id` = :userVkId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Пользователь с таким ID не найден");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//---Проверка пользователя (2)
if($user_id == changer_user) { //Если пользователь имеет право изменять права, то проверяется изменяемый пользователь
    if(!in_array($user['user_type'], ['Частичный', 'Интенсив', 'Пакетник'])) $out->make_wrong_resp("Нельзя изменять права к видео пользователяей, которые не являются учениками");
} elseif ($user_type != 'Админ') $out->make_wrong_resp("Ошибка доступа"); //Админы могут менять всех 


//---Валидация $in->videosAccess[...]['videoId'] 
$videoIDs = []; //Валидированные ID
$wheres = []; //Части условия поиска
foreach ($in->videosAccess as $index => $video) {
    if (((string) (int) $video['videoId']) !== ((string) $video['videoId']) || (int) $video['videoId'] <= 0) $out->make_wrong_resp("Параметр 'videoId' в 'videosAccess[{$index}]' задан неверно или отсутствует");
    $wheres[] = ":videoId$index";
    $videoID = "videoId$index";
    $videoIDs[$videoID] = $video['videoId'];
};

//Проверка на дубликаты ИД видео, выдаёт ошибку, если они присутствуют
$videoIDsDublicates = array_unique(array_diff_assoc($videoIDs, array_unique($videoIDs)));
if(!empty($videoIDsDublicates)) {
    $errorIDs = join(", ", $videoIDsDublicates);
    $out->make_wrong_resp("В массиве 'videosAccess' присутствуют дубликаты по ID {$errorIDs}"); 
}

//По полученным ID проводится перебор видео по БД
$whereClause = '`video_id` = ' . join(' OR `video_id` = ', $wheres); 

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
if(!empty($videosDiff)) { //Если есть значения, существующие в одном массиве, но не в другом, то выдаёт ошибку и эти ID
    $errorIDs = join(', ', $videosDiff);
    if (count($videosDiff) > 1) $out->make_wrong_resp("Видео с ID {$errorIDs} не найдены");
    else $out->make_wrong_resp("Видео с ID {$errorIDs} не найдено");
}
$stmt->closeCursor(); unset($stmt);


//---Валидация $in->videosAccess[...]['access']
foreach ($in->videosAccess as $index => $video) {
    if(!isset($video['access'])) $out->make_wrong_resp("Параметр 'access' в 'videosAccess[{$index}]' отсутствует");
    if(!in_array($video['access'], [0, 1]) || empty($video['access'])) $out->make_wrong_resp("Параметр 'access' в 'videosAccess[{$index}]' задан неверно");
};


//---Создание или обновление прав пользователя к видео
//Формирование множественного добавления
$params = [];
$values = [];
foreach($in->videosAccess as $index => $value) {
    $values[] = "(:videoId$index, :userVkId, :access$index)";
    $param = "videoId$index";
    $params[$param] = $value['videoId'];
    $param = "access$index";
    $params[$param] = $value['access'];
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