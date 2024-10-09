<?php // Пометка задания как проверенного и сохранение для него проверки

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// ------------------------- Класс запроса -------------------------
class HomeTaskCuratorChecked extends MainRequestClass
{
    public $dzNum = ''; // Номер дз, обязательное поле
    public $studentId = ''; // ВК ID ученика, обязательное поле
    public $taskNum = ''; // Номер задания, обязательное поле
    public $taskStatus = ''; // Статус задания, обязательное поле
    public $jsonData = ''; // Данные в json, необязательное поле
}

$in = new HomeTaskCuratorChecked();
$in->from_json(file_get_contents('php://input'));

// ------------------------- Класс ответа -------------------------
$out = new MainResponseClass();

// ------------------------- Подключение к БД -------------------------
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

// ------------------------- Проверка доступа -------------------------
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Куратор', 'Админ'])) $out->make_wrong_resp('Нет прав');

if ($user_type != 'Админ') {
    // Подготовка запроса для проверки пользователя
    $stmt = $pdo->prepare("
            SELECT `users`.`user_curator`, `users`.`user_curator_dz`
            FROM `users` 
            WHERE `user_vk_id`= :student_id;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса для проверки пользователя');

    // Выполнение запроса для проверки пользователя
    $stmt->execute(['student_id' => $in->studentId])
    or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса для проверки пользователя');

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Проверка условий доступа
    if ($user['user_curator_dz'] != '' && $user['user_curator_dz'] != '0' && $user['user_curator_dz'] != $user_vk_id)
        $out->make_wrong_resp('Нет доступа');

    if (($user['user_curator_dz'] == '' || $user['user_curator_dz'] == '0') && $user['user_curator'] != $user_vk_id)
        $out->make_wrong_resp('Нет доступа');

    $stmt->closeCursor();
    unset($stmt);
}

// ------------------------- Валидация -------------------------
// Валидация и приведение $in->dzNum к числу
if (((string)(int)$in->dzNum) !== ((string)$in->dzNum) || (int)$in->dzNum < 0) {
    $out->make_wrong_resp('Параметр {dzNum} задан некорректно или отсутствует');
} else {
    $in->dzNum = (int)$in->dzNum;
}

// Валидация и приведение $in->studentId к числу
if (((string)(int)$in->studentId) !== ((string)$in->studentId) || (int)$in->studentId < 0) {
    $out->make_wrong_resp('Параметр {studentId} задан некорректно или отсутствует');
} else {
    $in->studentId = (int)$in->studentId;
}

// Валидация и приведение $in->taskNum к числу
if (((string)(int)$in->taskNum) !== ((string)$in->taskNum) || (int)$in->taskNum < 0) {
    $out->make_wrong_resp('Параметр {taskNum} задан некорректно или отсутствует');
} else {
    $in->taskNum = (int)$in->taskNum;
}

// Валидация и приведение $in->taskStatus к числу
if (((string)(int)$in->taskStatus) !== ((string)$in->taskStatus) || (int)$in->taskStatus < 0) {
    $out->make_wrong_resp('Параметр {taskStatus} задан некорректно или отсутствует');
} else {
    $in->taskStatus = (int)$in->taskStatus;
}

// ------------------------- Десериализация JSON -------------------------
$curAnswer = json_decode($in->jsonData, true);

// ------------------------- Обновление ДЗ -------------------------
// Преобразование данных
$teacherComment = $pdo->quote($curAnswer['cur_comment']);
$userBall = $curAnswer['ballov'] === '' ? null : (int)$curAnswer['ballov'];
$teacherJson = $pdo->quote(json_encode($curAnswer['add_comments'], JSON_UNESCAPED_UNICODE));

// Подготовка запроса на обновление ДЗ
$query = "
    UPDATE `ht_user_p2` 
    SET `is_checked` = '" . $in->taskStatus . "', 
        `teacher_comment` = '" . $teacherComment . "', 
        `teacher_json` = '" . $teacherJson . "', 
        `user_ball` = '" . $userBall . "'
    WHERE `user_id` = '" . $in->studentId . "'
    AND `ht_number` = '" . $in->dzNum . "'
    AND `q_number` = '" . $in->taskNum . "';
";

// Выполнение запроса на обновление ДЗ
$stmt = $pdo->query($query) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса на обновление ДЗ');

// ------------------------- Обновление баллов ученика -------------------------
// Подготовка запроса на обновление баллов ученика
$stmt = $pdo->prepare("
    UPDATE `ht_user` 
    SET `ht_user_ballov_p2` = (
        SELECT SUM(`ht_user_p2`.`user_ball`) 
        FROM `ht_user_p2` 
        WHERE `ht_user_p2`.`user_id` = :student_id
        AND `ht_user_p2`.`ht_number` = :dz_num
    )
	WHERE `ht_user`.`user_id` = :student_id
	AND `ht_user`.`ht_number` = :dz_num 
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса на обновление баллов ученика');

// Выполнение запроса на обновление баллов ученика
$stmt->execute([
    'student_id' => $in->studentId,
    'dz_num' => $in->dzNum,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса на обновление баллов ученика');

// ------------------------- Сохранение запроса в логи -------------------------
$file_name = $_SERVER['DOCUMENT_ROOT'] . '/user_logs/' . $user_vk_id . '.csv';
$content = $query . "\n";
file_put_contents($file_name, $content, FILE_APPEND);

// ------------------------- Ответ -------------------------
$out->success = '1';
$out->make_resp('');
