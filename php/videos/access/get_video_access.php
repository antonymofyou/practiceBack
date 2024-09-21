<?php //---Просмотр доступа пользователя к видео

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class VideosAccessGetVideoAccess extends MainRequestClass {
    public $userVkId = ''; // Идентификатор ВК пользователя, чьи права к видео нужно просмотреть
}
$in = new VideosAccessGetVideoAccess();
$in->from_json(file_get_contents('php://input'));

//---Класс ответа
class VideosAccessGetVideoAccessResponse extends MainResponseClass {
    /* Массив, содержащий словари, содержащие следующие поля:
        - videoId           - ИД видео
        - videoShownName    - Показываемое название видео
        - videoLessonNum    - Номер урока, к которому привязано видео
        - videoChapter      - Раздел, в который входит это видео
        - videoPublic       - Опубликовано ли видео
        - videoAccess       - Доступность видео указанному ученику, чей ID указан в запросе
        - videoViews        - Количество просмотров видео указанным учеником, чей ID указан в запросе
    */ 
    public $videos = []; //Массив с видео
    public $chapters = []; //Массив названий всех разделов полученных видео
}
$out = new VideosAccessGetVideoAccessResponse();

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
$stmt->closeCursor(); unset($stmt);

//---Проверка пользователя (2)
if($user_id == changer_user) { //Если пользователь имеет право изменять права, то проверяется изменяемый пользователь
    if(!in_array($user['user_type'], ['Частичный', 'Интенсив', 'Пакетник'])) $out->make_wrong_resp("Нельзя изменять права к видео пользователей, которые не являются учениками");
} elseif ($user_type != 'Админ') $out->make_wrong_resp("Ошибка доступа"); //Админы могут просматривать всех

//---Получение данных
$stmt = $pdo->prepare("
    SELECT `videos`.`video_id`, `videos`.`video_shown_name`, `videos`.`video_lesson_num`, `videos`.`video_chapter`, `videos`.`video_public`, `video_access`.`access`, `video_access`.`views` 
    FROM `videos`
    LEFT JOIN `video_access` ON `video_access`.`video_id` = `videos`.`video_id` AND `video_access`.`user_vk_id` = :userVkId
    ORDER BY `videos`.`video_lesson_num` DESC;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (2)");
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Данные не найдены");

$videos = [];
while ($video = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $videos[] = [
        'videoId' => (string) $video['video_id'],
        'videoShownName' => (string) $video['video_shown_name'],
        'videoLessonNum' => (string) $video['video_lesson_num'],
        'videoChapter' => (string) $video['video_chapter'],
        'videoPublic' => (string) $video['video_public'],
        'videoAccess' => (string) $video['access'],
        'videoViews' => (string) $video['views']
    ];
} $stmt->closeCursor(); unset($stmt);

//---Формирование списка разделов
$chapters = [];
foreach($videos as $index => $video) {
    $chapters[] = $video['videoChapter'];
} $chapters = array_unique($chapters);
sort($chapters);

//---Возврат данных
$out->videos = (object) $videos;
$out->chapters = (object) $chapters;

$out->success = "1";
$out->make_resp('');