<?php //---Обнуление зачёта

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class UserInfoGetUserAutoZachets extends MainRequestClass {
    public $userVkId = ''; // Идентификатор пользователя, чей зачёт надо обнулить
    public $zachId = ''; // Идентификатор зачёта для обнуления
}
$in = new UserInfoGetUserAutoZachets();
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
if(!in_array($user_type, ['Админ', 'Куратор'])) {
    $out->make_wrong_resp('Ошибка доступа');
}

//---Валидация $in->userVkId
if (((string) (int) $in->userVkId) !== ((string) $in->userVkId) || (int) $in->userVkId <= 0) $out->make_wrong_resp("Параметр 'userVkId' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `user_curator`
    FROM `users`
    WHERE `user_vk_id` = :userVkId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Пользователь с ID {$in->userVkId} не найден");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//---Проверка пользователя(2)
//Можно обнулять зачёты только своих учеников, админ может обнулять всех
if($user_type != "Куратор" && $user['user_curator'] != $user_vk_id) {
    $out->make_wrong_resp('Ошибка доступа');
}

//---Валидация $in->zachId
if (((string) (int) $in->zachId) !== ((string) $in->zachId) || (int) $in->zachId <= 0) $out->make_wrong_resp("Параметр 'zachId' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `zu_popitka`, `zu_status`
    FROM `zachet_user`
    WHERE `user_id` = :userVkId AND `zachet_id` = :zachId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (2)");
$stmt->execute([
    'userVkId' => $in->userVkId,
    'zachId' => $in->zachId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Зачёт с ID {$in->zachId} не найден");
$zachet = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//---Проверка на возможность обнуления
if($zachet['zu_popitka'] != 3 || $zachet['zu_status'] != 'Несдан') $out->make_wrong_resp("Есть ещё {$zachet['zu_popitka']} попыток или зачёт сдан");

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS `amount`
    FROM `questions`
    LEFT JOIN `user-question` ON `questions`.`q_id` = `user-question`.`q_id` AND `user-question`.`user_vk_id` = :userVkId
    WHERE `questions`.`q_public` = '1' AND (`user-question`.`uq_status` = '1' OR `user_question`.`uq_status` = '2');
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (3)");
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
$neproresh = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$neproresh = (int) $neproresh['amount'];
if($neproresh != 0) $out->make_wrong_resp("Остались нерешённые вопросы");

//---Добавление попытки ученику, то есть обнуление зачёта
$stmt = $pdo->prepare("
    UPDATE `zachet_user`
    SET `zu_popitka` = '1'
    WHERE `user_id` = :userVkId AND `zachet_id` = :zachId;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (4)");
$stmt->execute([
    'userVkId' => $in->userVkId,
    'zachId' => $in->zachId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
$stmt->closeCursor(); unset($stmt);


//---Создание CRM комментария
$comment = "Добавлена попыта пересдачи зачёта с ID {$in->zachId}";
$stmt = $pdo->prepare("
    INSERT INTO `crm_comments`
    (`user_vk_id`, `crm_comment`, `crm_editor`, `crm_date`, `crm_time`)
    VALUES (:userVkId, :comment, :editor, CURDATE(), CURTIME());
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (5)");
$stmt->execute([
    'userVkId' => $in->userVkId,
    'comment' => $comment,
    'editor' => $user_vk_id
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
$stmt->closeCursor(); unset($stmt);


//---Сообщение в ВК о добавлении попытки пересдачи
$user = [$in->userVkId]; //Массив
$message = "Добавлена попытка к автоматическому зачёту с номером {$in->zachId}";

$mysqli = mysqli_init();
$mysqli->real_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE_SOCEGE, NULL, NULL, DB_FLAGS) or $out->make_wrong_resp("Нет соединения с базой данных (2)");
addTaskSendingToVk($mysqli, $user, $message)[0]['success'] or $out->make_wrong_resp('Ошибка отправки рассылки в ВК');

$out->success = "1";
$out->make_resp('');