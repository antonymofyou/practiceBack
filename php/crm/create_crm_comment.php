<?php // Вставка нового комментария к ученику в crm_comments

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// класс запроса
class CrmCreateComment extends MainRequestClass
{
    public $userVkId = ''; // ID пользователя, которому нужно добавить комментарий
    public $comment = ''; // Комментарий к пользователю

}

$in = new CrmCreateComment();
$in->from_json(file_get_contents('php://input'));

$out = new MainResponseClass();

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
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Админ', 'Куратор'])) $out->make_wrong_resp('Ошибка доступа');

//--------------------------------Валидация $in->userVkId
if (((string) (int) $in->userVkId) !== ((string) $in->userVkId) || (int) $in->userVkId <= 0) $out->make_wrong_resp("Параметр 'userVkId' задан некорректно или отсутствует");

$curatorId = ""; // Содержит куратора пользователя с id $in->userVkId

$stmt = $pdo->prepare("
    SELECT `users`.`user_curator`
    FROM `users`
    WHERE `users`.`user_vk_id` = :user_vk_id;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
    'user_vk_id' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

if ($stmt->rowCount() === 0) $out->make_wrong_resp("Не найдено ни одного пользователя с 'userVkId' = {$in->userVkId} ");

$studentData = $stmt->fetch(PDO::FETCH_ASSOC);

$curatorId = $studentData['user_curator'];

//--------------------------------Проверка, смотрит ли ученика тот куратор, который указан в БД
if ($user_type == "Куратор" && ($user_vk_id != $curatorId) && ($user_vk_id != changer_user) && !(in_array($user_type, main_managers))) $out->make_wrong_resp('Это не твой ученик');

//--------------------------------Валидация $in->comment
if (empty($in->comment)) $out->make_wrong_resp("Параметр 'comment' отсутствует");

//--------------------------------Вставка новой записи в crm_comment
$stmt = $pdo->prepare("
    INSERT INTO `crm_comments` (`user_vk_id`, `crm_comment`, `crm_date`, `crm_time`, `crm_editor`)
    VALUES (:user_vk_id, :crm_comment, CURDATE(), CURTIME(), :crm_editor);
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'user_vk_id' => $in->userVkId,
    'crm_comment' => $in->comment,
    'crm_editor' => $user_vk_id,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');

//--------------------------------Формирование ответа
$out->success = '1';
$out->make_resp('');