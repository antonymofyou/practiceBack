<?php // "Получение автоматических зачётов пользователя"
header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class UserInfoGetUserZachetsAuto extends MainRequestClass {
     // идентификатор вопроса ч.1, данные которого нужно получить
    public $userId = '';
}
$in = new UserInfoGetUserZachetsAuto();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class UserInfoGetUserZachetsAutoResp extends MainResponseClass {
    /*
     * Словарь, который имеет следующие поля:
     *     - qId - идентификатор вопроса
     *     - qQuestion - json вопроса
     *     - qEgeNum - номер задания в ЕГЭ
     *     - qChapter - раздел
     *     - isPublished - опубликован ли (0/1)
     *     - isSelfmade - сделанный ли самостоятельно (0/1)
     */
    public $zachets = []; // данные зачета, взятые из дб  

}
$out = new UserInfoGetUserZachetsAutoResp();

//--------------------------------Подключение к базе данных

try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
}  catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//--------------------------------Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';

//--------------------------------Валидация $in->userID
if (((string) (int) $in->userId) !== ((string) $in->userId) || (int) $in->userId <= 0) $out->make_wrong_resp("Параметр 'userId' задан некорректно или отсутствует");
$student_id = $in->userId;
    

//--------------------------------Проверка доступа
if ($user_type != 'Админ' && $user_type != 'Куратор') {
    $out->make_wrong_resp('отказ в доступе');
}

//получаем куратора

$stmt = $pdo->prepare(
"SELECT `user_curator` FROM `users` WHERE `user_vk_id` = :user_vk_id"    
);
$stmt->execute(['user_vk_id'=>$student_id]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

if ($stmt->rowCount() ==0){
    $out->make_wrong_resp('такого пользователя нету');
}
$userCurator = $stmt->fetch(PDO::FETCH_ASSOC);  
$stmt->closeCursor();
unset($stmt);

if ($user_type =='Куратор' && $fetchedQuery['user_curator']  != $in->userId) $out->make_wrong_resp('это не твой ученик');



$stmt = $pdo->prepare("
    SELECT `zachets_auto` .`za_id` , zachets_auto.`za_date_start` , zachets_auto.`za_deadline` , zachets_auto.`za_max_time` , zachets_auto.`za_max_popitok` , zachets_auto.`za_questions`, zachets_auto.`za_max_errors` , zachets_auto.`za_lesson_numbers` , zachets_auto.`za_showed`, zachet_user.`zu_status`,  zachet_user.`zu_popitka` 
    FROM `zachets_auto` 
    LEFT JOIN `zachet_user` ON `zachet_user`.`zachet_id`=`zachets_auto`.`za_id` AND `zachet_user`.`user_id` = :student_id 
    WHERE `za_showed` = 1 
    ORDER BY `za_id` DESC;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');;
$stmt->execute(['student_id' => $student_id])  or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("зачет ч.1 с ID {$in->userId} не найден");
$zachets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

foreach ($zachets as $zachet) {
    $out->zachets[] = [
        'zaId' => (string) $zachet['za_id'],
        'zaDateStart' => (string) $zachet['za_date_start'],
        'zaDeadline' => (string) $zachet['za_deadline'],
        'zaMaxTime' => (string) $zachet['za_max_time'],
        'zaMaxPopitok' => (string) $zachet['za_max_popitok'],
        'zaQuestions' => (string) $zachet['za_questions'],
        'zaMaxErrors' => (string) $zachet['za_max_errors'],
        'zaLessonNumbers' => (string) $zachet['za_lesson_numbers'],
        'zaShowed' => (string) $zachet['za_showed'],
        'zuStatus' => (string) $zachet['zu_status'],
        'zuPopitka' => (string) $zachet['zu_popitka'],
    ];
}
 
$out->make_resp('');






