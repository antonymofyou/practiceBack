<?php //---Получение списка вопросов и ответов на них

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class HomeTasksCuratorGetResultP1 extends MainRequestClass {
    public $dzNum = ''; //Номер ДЗ
    public $userVkId = ''; //ИД ученика для получения работ ученика
}
$in = new HomeTasksCuratorGetResultP1();
$in->from_json(file_get_contents('php://input'));

//---Класс ответа
class HomeTasksCuratorGetResultP1Response extends MainResponseClass {
    /* Словарь со следующими полями
        - userName          - Имя ученика
        - userSurname       - Фамилия ученика
        - htUserStatusP1    - Статус задания
    */
    public $user = []; //Данные ученика и задания

    /* Массив словарей со следующими полями:
        - qNumber           - Номер вопроса
        - userBall          - Набрано баллов
        - realBall          - Максимально баллов
        - userAnswer        - Ответ ученика
        - qAnswer           - Правильный ответ
        - selfmade          - Дополнительное задание(0/1, по умолчанию 0)
        - uq_status         - Правильность ответа(1 - неверный, 2 - частично верный, 3 - верный)
    */
    public $questions = []; //Данные вопросов к заданию

    /* Массив со следующими полями:
        - questionsSum      - Итого вопросов
        - userBalls         - Итого набрано баллов
        - realBalls         - Всего максимально баллов
        - mistakes          - Итого неверных ответов
    */
    public $sums = []; //Итоговые значения
}
$out = new HomeTasksCuratorGetResultP1Response();

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

//---Валидация dzNum и userVkId
if (((string) (int) $in->dzNum) !== ((string) $in->dzNum) || (int) $in->dzNum <= 0) $out->make_wrong_resp("Параметр 'dzNum' задан неверно или отсутствует");
if (((string) (int) $in->userVkId) !== ((string) $in->userVkId) || (int) $in->userVkId <= 0) $out->make_wrong_resp("Параметр 'userVkId' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `users`.`user_name`, `users`.`user_surname`, `ht_user`.`ht_user_status_p1`
    FROM `users`
    LEFT JOIN `ht_user` ON `users`.`user_vk_id`=`ht_user`.`user_id` AND `ht_user`.`ht_number` = :dzNum
    WHERE `users`.`user_type` IN ('Частичный', 'Интенсив', 'Админ', 'Куратор') AND `user_vk_id` = :userVkId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'dzNum' => $in->dzNum,
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Пользователь с ID {$in->userVkId} не найден или не является учеником");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);


$stmt = $pdo->prepare("
    SELECT `ht_user_p1`.`user_ball`, `ht_user_p1`.`real_ball`, `ht_user_p1`.`q_number`, `ht_user_p1`.`user_answer`, `ht_user_p1`.`q_answer`, `questions`.`selfmade`
    FROM `ht_user_p1`
	LEFT JOIN `questions` ON `questions`.`q_id` = `ht_user_p1`.`q_id`
	WHERE `ht_user_p1`.`user_id` = :userVkId AND `ht_user_p1`.`ht_number` = :dzNum;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (2)");
$stmt->execute([
    'userVkId' => $in->userVkId,
    'dzNum' => $in->dzNum
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Домашняя работа №{$in->dzNum} для пользователя {$in->userVkId} не найдена");
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$sums = ['questionsSum' => count($tasks), 'userBalls' => 0, 'realBalls' => 0, 'mistakes' => 0];


foreach($tasks as $index => $task) {
    $sums['userBalls'] += (int) $task['user_ball'];
    $sums['realBalls'] += (int) $task['real_ball'];
    $tasks[$index]['uq_status'] = '3';

    if ($task['user_ball'] != $task['real_ball']) {

        $sums['mistakes']++;

        if((int) $task['user_ball'] > 0) {
            $tasks[$index]['uq_status'] = '2';
        }

        else {
            $tasks[$index]['uq_status'] = '1';
        }
    }
}

//---Формирование ответа
$out->user['userName'] = (string) $user['user_name'];
$out->user['userSurname'] = (string) $user['user_surname'];
$out->user['htUserStatusP1'] = (string) $user['ht_user_status_p1'];


foreach($tasks as $task) {
    $out->questions[] = [
        'qNumber' => (string) $task['q_number'],
        'userBall' => (string) $task['user_ball'],
        'realBall' => (string) $task['real_ball'],
        'userAnswer' => (string) $task['user_answer'],
        'qAnswer' => (string) $task['q_answer'],
        'selfmade' => (string) $task['selfmade'],
        'uqStatus' => (string) $task['uq_status']
    ];
}

$out->sums['questionsSum'] = (string) $sums['questionsSum'];
$out->sums['userBalls'] = (string) $sums['userBalls'];
$out->sums['realBalls'] = (string) $sums['realBalls'];
$out->sums['mistakes'] = (string) $sums['mistakes'];


$out->success = "1";
$out->make_resp('');

