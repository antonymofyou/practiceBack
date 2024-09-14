<?php //---Редактирование прав пользователей к видео

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class VideosAccessSetVideoAccess extends MainRequestClass {
    public $userVkId = ''; // Идентификатор ВК пользователя
    public 
}
$in = new UsersStudentInfo();
//$in->from_json(file_get_contents('php://input'));

//---Класс ответа
class VideosAccessSetVideoAccessResponse extends MainResponseClass {
    public $studentInfo = []; 
}
$out = new VideosAccessSetVideoAccessResponse();

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

//---Валидация
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




