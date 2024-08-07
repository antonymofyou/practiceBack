<?php // Получение должников куратора

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
$in = new MainRequestClass();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class KurGetKurDebtorsResp extends MainResponseClass
{
    /*
     * Массив словарей должников, где каждый словарь имеет следующие поля:
     *
     * userVkId    - ВК ID должника
     * userName    - Имя должника
     * userSurname - Фамилия должника
     * dolgovPart1 - Долги в первой части
     * dolgovPart2 - Долги во второй части
     * dolgovAZach - Долги в авто зачётах
     */
    public $debtors = [];
}

$out = new KurGetKurDebtorsResp();

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

// Дополнительный фрагмент запроса, если текущий пользователь - это куратор
$add_query = $user_type == 'Куратор' ? "AND `us`.`user_curator` = :userVkId" : '';

// Получение должников
// Подготовка запроса для получения должников данного куратора
$stmt = $pdo->prepare("
        SELECT `us`.`user_vk_id`,`us`.`user_surname`, `us`.`user_name`,
            (
                SELECT `dolgov_p1` FROM 
                    (SELECT `users`.`user_vk_id`, COUNT(*) AS `dolgov_p1` FROM `users` JOIN `home_tasks`
                        LEFT JOIN `ht_user` ON `ht_user`.`user_id` = `users`.`user_vk_id` AND `ht_user`.`ht_number` = `home_tasks`.`ht_number`
                        WHERE `home_tasks`.`ht_deadline` < CURDATE()
                            AND `home_tasks`.`ht_deadline` >= DATE_ADD(`users`.`user_start_course_date`, Interval 3 DAY)
                            AND `home_tasks`.`ht_number` != 0 
                            AND (`ht_user`.`ht_user_status_p1` = 'Выполняется' OR `ht_user`.`ht_user_status_p1` IS NULL)
                        GROUP BY `users`.`user_vk_id` 
                        HAVING `dolgov_p1` > 0
                    ) AS `students1`	
                WHERE `students1`.`user_vk_id` = `us`.`user_vk_id`
            ) AS `dolgov_part1`,
                
            (
                SELECT `dolgov_p2` FROM 
                    (SELECT `users`.`user_vk_id`, COUNT(*) AS `dolgov_p2` FROM `users` JOIN `home_tasks`
                        LEFT JOIN `ht_user` ON `ht_user`.`user_id` = `users`.`user_vk_id` AND `ht_user`.`ht_number` = `home_tasks`.`ht_number`
                        WHERE `home_tasks`.`ht_deadline` < CURDATE()
                            AND `home_tasks`.`ht_deadline` >= DATE_ADD(`users`.`user_start_course_date`, Interval 3 DAY)
                            AND `home_tasks`.`ht_number` != 0
                            AND (`ht_user`.`ht_user_status_p2` = 'Выполняется' OR `ht_user`.`ht_user_status_p2` IS NULL)
                        GROUP BY `users`.`user_vk_id` 
                        HAVING `dolgov_p2` > 0
                    ) AS `students2`	
                WHERE `students2`.`user_vk_id` = `us`.`user_vk_id`
            ) AS `dolgov_part2`,
            
            (
                SELECT `dolgov_a_z` FROM 
                    (
                        SELECT `users`.`user_vk_id`, COUNT(*) AS `dolgov_a_z` FROM `users` JOIN `zachets_auto`
                            LEFT JOIN `zachet_user` ON `zachet_user`.`zachet_id` = `zachets_auto`.`za_id` AND `zachet_user`.`user_id` = `users`.`user_vk_id`
                        WHERE `zachets_auto`.`za_deadline` < CURDATE()
                            AND `zachets_auto`.`za_deadline` >= DATE_ADD(`users`.`user_start_course_date`, Interval 45 DAY)
                            AND (`zachet_user`.`zu_status` IS NULL OR `zachet_user`.`zu_status` != 'Сдан')
                        GROUP BY `users`.`user_vk_id` 
                        HAVING `dolgov_a_z` > 0
                    ) AS `students3`	
                WHERE `students3`.`user_vk_id` = `us`.`user_vk_id`
            ) AS `dolgov_a_zach`
            
        FROM `users` AS `us`
        WHERE `us`.`user_blocked` = 0 AND `us`.`user_type` IN ('Частичный', 'Интенсив')
        ".$add_query."
        HAVING `dolgov_part1` > 0 OR `dolgov_part2` > 0 OR `dolgov_a_zach` > 0
        ORDER BY (IFNULL(`dolgov_part1`, 0) + IFNULL(`dolgov_part2`, 0) + IFNULL(`dolgov_a_zach`, 0)) DESC
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

// Выполнение запроса для получения должников данного куратора
$stmt->execute(['userVkId' => $user_vk_id])
or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

$debtors = [];
while ($debtor = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $debtors[] = [
        'userVkId' => (string)$debtor['user_vk_id'],
        'userName' => (string)$debtor['user_name'],
        'userSurname' => (string)$debtor['user_surname'],
        'dolgovPart1' => (string)$debtor['dolgov_part1'],
        'dolgovPart2' => (string)$debtor['dolgov_part2'],
        'dolgovAZach' => (string)$debtor['dolgov_a_zach'],
    ];
}

$stmt->closeCursor();
unset($stmt);

// Ответ
$out->debtors = $debtors;
$out->success = '1';
$out->make_resp('');
