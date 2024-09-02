<?php // Получение комментариев по ученику

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// класс запроса
class CrmGetComment extends MainRequestClass
{
    public $userVkId = ''; // ID пользователя, которому нужно вывести комментарии
}

$in = new CrmGetComment();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class CrmGetCommentResponce extends MainResponseClass
{
    /*
     * Массив словарей, где каждый словарь содержит следующие поля:
     *  - comment - комментарий
     *  - date - дата создания в формате yyyy-mm-dd
     *  - time - время создания в формате hh-mm-ss
     *  - editorId - id создателя комментария
     *  - editorName - имя создателя комментария
     *  - editorSurname - фамилия создателя комментария
     */
    public $comments = [];
}

$out = new CrmGetCommentResponce();

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
if ($curatorId == null || empty($curatorId)) $out->make_wrong_resp("У ученика с 'userVkId' = {$in->userVkId} нет куратора");

//--------------------------------Проверка, смотрит ли ученика тот куратор, который указан в БД
if ($user_type == "Куратор" && ($user_vk_id == $curatorId) && ($user_vk_id != changer_user) && !(in_array($user_type, main_managers))) $out->make_wrong_resp('Это не твой ученик');

//--------------------------------Проверка, смотрит ли пользователь комментарии про себя
if (($user_type != "Админ") && ($user_vk_id == $in->userVkId)) $out->make_wrong_resp('Нельзя смотреть комментарии про себя');

//--------------------------------Получение  записей из crm_comment по пользователю
$stmt = $pdo->prepare("
    SELECT `crm_comments`.`crm_date`, `crm_comments`.`crm_time`, `crm_comments`.`crm_editor`, `crm_comments`.`crm_comment`,
           `users`.`user_name`, `users`.`user_surname`
    FROM `crm_comments`
    LEFT JOIN `users` on `crm_comments`.`crm_editor` = `users`.`user_vk_id`
    WHERE `crm_comments`.`user_vk_id` = :user_vk_id;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'user_vk_id' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $comments[] = [
        'comment' => (string) $row['crm_comment'],
        'date' => (string) $row['crm_date'],
        'time' => (string) $row['crm_time'],
        'editorId' => (string) $row['crm_editor'],
        'editorName' => (string) $row['user_name'],
        'editorSurname' => (string) $row['user_surname'],
    ];
}

//--------------------------------Формирование ответа
$out->success = '1';
$out->comments = $comments;
$out->make_resp('');