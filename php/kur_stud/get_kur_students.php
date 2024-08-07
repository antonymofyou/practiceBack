<?php // Получение учеников данного куратора

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
$in = new MainRequestClass();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class KurGetKurStudentsResp extends MainResponseClass
{
    /*
     * Массив словарей студентов, где каждый словарь имеет следующие поля:
     *
     * userVkId            - ВК ID студента
     * userName            - Имя студента
     * userSurname         - Фамилия студента
     * userType            - Тип студента
     * userTarifNum        - Номер тарифа студента (1, 2, 3)
     * userZachet          - Зачтено ли?
     * userLink            - Ссылка на студента
     * userAvaLink         - Ссылка на аватарку
     * userBlocked         - Заблокирован ли студент?
     * blackDesign         - Установлена ли тёмная тема?
     * userGoalBall        - ?
     * regTimediff         - Временная разница от МСК (МСК + regTimediff)
     * bnBalance           - Баланс студента
     */
    public $students = [];
    public $showFindStudentField = '0'; // Показывать ли поле поиска ученика? (0/1)
}

$out = new KurGetKurStudentsResp();

// Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp("Нет соединения с базой данных");
}

// Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
in_array($user_type, ['Куратор', 'Админ']) or $out->make_wrong_resp("Ошибка доступа");

// Получение учеников
// Подготовка запроса для получения учеников данного куратора
$stmt = $pdo->prepare("
        SELECT `users`.`user_vk_id`,
               `users`.`user_name`,
               `users`.`user_surname`,
               `users`.`user_type`,
               `users`.`user_tarif_num`,
               `users`.`user_zachet`,
               `users`.`user_link`,
               `users`.`user_ava_link`,
               `users`.`user_blocked`,
               `users`.`black_design`,
               `users_add`.`user_goal_ball`,
               `regions`.`reg_timediff`,
               `balance_now`.`bn_balance`
        FROM `users`
        LEFT JOIN `users_add` ON `users_add`.`user_vk_id` = `users`.`user_vk_id`
        LEFT JOIN `balance_now` ON `users`.`user_vk_id` = `balance_now`.`bn_user_id`
        LEFT JOIN `regions` ON `users`.`user_region` = `regions`.`reg_number`
        WHERE `users`.`user_curator` = :userVkId
        AND (`users`.`user_blocked` IS NULL OR `users`.`user_blocked` = 0)
        AND `users`.`user_type` IN ('Частичный', 'Интенсив')
        ORDER BY IF(SIGN(`balance_now`.`bn_balance`) >= 0, 1, -1), `users`.`user_surname`
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

// Выполнение запроса для получения учеников данного куратора
$stmt->execute(['userVkId' => $user_vk_id])
or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

$students = [];
while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $students[] = [
        'userVkId' => (string)$student['user_vk_id'],
        'userName' => (string)$student['user_name'],
        'userSurname' => (string)$student['user_surname'],
        'userType' => (string)$student['user_type'],
        'userTarifNum' => (string)$student['user_tarif_num'],
        'userZachet' => (string)$student['user_zachet'],
        'userLink' => (string)$student['user_link'],
        'userAvaLink' => (string)$student['user_ava_link'],
        'userBlocked' => (string)$student['user_blocked'],
        'blackDesign' => (string)$student['black_design'],
        'userGoalBall' => (string)$student['user_goal_ball'],
        'regTimediff' => (string)$student['reg_timediff'],
        'bnBalance' => (string)$student['balance_now'],
    ];
}

$stmt->closeCursor();
unset($stmt);

// Ответ
$out->students = $students;
$out->showFindStudentField = $user_type == 'Админ' && in_array($user_vk_id, [changer_user] + user_info_lookers + main_managers) ? '1' : '0';
$out->success = '1';
$out->make_resp('');
