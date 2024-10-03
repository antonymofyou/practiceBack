<?php // Получение списка дз по ученику

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class UserInfoStinfHometasks extends MainRequestClass
{
    public $studentId = ''; // ID менеджера, по которому нужно вывести расписание
}

$in = new UserInfoStinfHometasks();

// класс ответа
class UserInfoStinfHometasksResponce extends MainResponseClass
{
    /*
     * Массив словарей, где каждый словарь содержит следующие поля:
     *  - htNum - номер задания
     *  - videoId - id видео
     *  - forOrder - приоритет упорядочивания
     *  - access - доступ к видео
     *  - prosmotrov - количество просмотров
     *  - htStatus - статус домашнего задания
     *  - htUserBallovP1 - количество баллов за задание 1
     *  - htUserMaxballovP1 - максимальное количество баллов за задание 1
     *  - htUserStatusP1 - статус выполнения задания 1
     *  - htUserDateP1 - дата задания 1
     *  - htUserBallovP2 - количество баллов за задание 2
     *  - htUserMaxballovP2 - максимальное количество баллов за задание 2
     *  - htUserStatusP2 - статус выполнения задания 2
     *  - htUserDateP2 - дата задания 2
     */
    public $homeTasks = [];
}

$out = new UserInfoStinfHometasksResponce();

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
if (!(in_array($user_type, ['Админ', 'Куратор']))) $out->make_wrong_resp('Нет доступа');

//--------------------------------Валидация $in->studentId
if (((string) (int) $in->studentId) !== ((string) $in->studentId) || (int) $in->studentId <= 0) $out->make_wrong_resp("Параметр 'studentId' задан некорректно или отсутствует");

$stmt = $pdo->prepare("
    SELECT `user_curator`, `user_type`
    FROM `users`
    WHERE `user_vk_id` = :studentId
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$stmt->execute([
    'studentId' => $in->studentId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

if ($stmt->rowCount() == 0) $out->make_wrong_resp("Студент с ID {$in->studentId} не найден");
$student = $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
$stmt->closeCursor(); unset($stmt);

//--------------------------------Является ли текущий пользователь куратором студента
if($user_type == 'Куратор' && $student['user_curator'] != $user_vk_id && !in_array($user_vk_id, user_info_lookers)) $out->make_wrong_resp("Это не твой ученик");

//--------------------------------Заполнение homeTasks
$homeTasks = [];

$stmt = $pdo->prepare("SELECT  `home_tasks`.`ht_number` as `ht_num`, IF(`home_tasks`.`ht_number` = '200', 1, 0) AS `for_order`,
    `video_access`.`access`, `video_access`.`views`, `home_tasks`.`ht_status` ,  `video_access`.`video_id`,
    `ht_user`.`ht_user_ballov_p1`, `ht_user`.`ht_user_maxballov_p1`, `ht_user`.`ht_user_status_p1`, `ht_user`.`ht_user_date_p1`, 
    `ht_user`.`ht_user_ballov_p2`, `ht_user`.`ht_user_maxballov_p2`, `ht_user`.`ht_user_status_p2`, `ht_user`.`ht_user_date_p2` 
    FROM  `home_tasks`
        LEFT JOIN `ht_user` on `home_tasks`.`ht_number`=`ht_user`.`ht_number` AND `ht_user`.`user_id` =  :student_id
        LEFT JOIN `videos` on `videos`.`video_lesson_num`=`home_tasks`.`ht_number`
        LEFT JOIN `video_access` ON `video_access`.`video_id`=`videos`.`video_id` AND `video_access`.`user_vk_id`= :student_id
    WHERE  `home_tasks`.`ht_status`
    IN ('Выполнение',  'Проверка',  'Завершено')
    ORDER BY  `for_order`, `home_tasks`.`ht_number` DESC;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');

$stmt->execute([
    'student_id' => $in->student_id
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');

while ($homeTasksRequest = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if((int)$homeTasksRequest['access'] == 1 || $student['user_type'] == "Куратор") $prosmotrov = $homeTasksRequest['views'];
    else $prosmotrov = 'недост.';

    if($student['user_type'] == 'Интенсив') {
        if($homeTasksRequest['ht_num'] >= 100 && $homeTasksRequest['ht_num'] < 200) $prosmotrov = $homeTasksRequest['views'];
            else continue;
    }
    $homeTasks[] = [
        'htNum' => (string)$homeTasksRequest['ht_num'],
        'videoId' => (string)$homeTasksRequest['video_id'],
        'forOrder' => (string)$homeTasksRequest['for_order'],
        'access' => (string)$homeTasksRequest['access'],
        'views' => (string)$prosmotrov,
        'htStatus' => (string)$homeTasksRequest['ht_status'],
        'htUserBallovP1' => (string)$homeTasksRequest['ht_user_ballov_p1'],
        'htUserMaxballovP1' => (string)$homeTasksRequest['ht_user_maxballov_p1'],
        'htUserStatusP1' => (string)$homeTasksRequest['ht_user_status_p1'],
        'htUserDateP1' => (string)$homeTasksRequest['ht_user_date_p1'],
        'htUserBallovP2' => (string)$homeTasksRequest['ht_user_ballov_p2'],
        'htUserMaxballovP2' => (string)$homeTasksRequest['ht_user_maxballov_p2'],
        'htUserStatusP2' => (string)$homeTasksRequest['ht_user_status_p2'],
        'htUserDateP2' => (string)$homeTasksRequest['ht_user_date_p2']
    ];
}
$stmt->closeCursor();
unset($stmt);

//--------------------------------Формирование ответа
$out->success = '1';
$out->homeTasks = $homeTasks;
$out->make_resp('');