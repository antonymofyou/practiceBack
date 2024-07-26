<?php // Создание, обновление и удаление домашнего задания

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//класс запроса
class SetHometask extends MainRequestClass { 
    public $htNumber = ''; // ключ дз

    public $htUpdate = []; /* словарь с данными для обновления

    htNumsP1 INT - Количество вопросов из первой части
    htNumsP1Dop INT - Количество дополнительных вопросов из первой части
    htNumsP2 INT - Количество вопросов из второй части
    typeP1 TEXT - Тип вопросов: Вопросыизурона или КаквЕГЭ
    addOtherTasksP1 BOOLEAN - Добавить задания Ч1 из других уроков в случае нехватки
    addOtherTasksP2 BOOLEAN - Добавить задания Ч2 из других уроков в случае нехватки
    htStatus TEXT - Статус задания: Новое, Выполнение, Проверка или Завершено
    htDeadline DATE - День дедлайна
    htDeadlineTime TIME - Время дедлайна 
    htDeadlineCur DATETIME - Дедлайн проверки кураторов 
    htComment TEXT - 
    isProbnik BOOLEAN - Является ли пробником 
    timerSecondsP1 INT - Времени на Ч1 минут
    timerSecondsP2 INT - Времени на Ч2 минут

    */


    public $action = ''; // одно из: create, update или delete
    /* 
    create - создаёт незаполненное домашнее задание с номером и возвращает этот номер
    update - обновляет домашнее задание
    delete - удаляет домашнее задание
    */

}

$in = new SetHometask();
$in->from_json(file_get_contents('php://input'));




//класс ответа
class SetHometaskResponse extends MainResponseClass {

    public $homeTask = []; /* Словарь с данными дз

    htNumber INT PRIMARY KEY - Номер задания
    htNumsP1 INT - Количество вопросов из первой части
    htNumsP1Dop INT - Количество дополнительных вопросов из первой части
    htNumsP2 INT - Количество вопросов из второй части
    typeP1 TEXT - Тип вопросов: Вопросы из урока или Как в ЕГЭ
    addOtherTasksP1 BOOLEAN - Добавить задания Ч1 из других уроков в случае нехватки
    addOtherTasksP2 BOOLEAN - Добавить задания Ч2 из других уроков в случае нехватки
    htStatus TEXT - Статус задания: Новое, Выполнение, Проверка или Завершено
    htDeadline DATE - День дедлайна
    htDeadlineTime TIME - Время дедлайна 
    htDeadlineCur DATETIME - Дедлайн проверки кураторов 
    htComment TEXT 
    isProbnik BOOLEAN - Является ли пробником 
    timerSecondsP1 INT - Времени на Ч1 минут
    timerSecondsP2 INT - Времени на Ч2 минут

    */

    public $questions = []; /* Словарь с данными о количестве вопросов для этого задания

    numsP1 INT - Количество доступных вопросов 1 части
    numsP1Dop INT - Количество доступных дополнительных вопросов 1 части
    numsP2 INT - Количество доступных вопросов 2 части

    */

}
$out = new SetHometaskResponse();

//Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Админ', 'Куратор'])) $out->make_wrong_resp('Ошибка доступа');

//Формирование ответа $in->action. В зависимости от значения $in->action выбирается соответствующий алгоритм обработки
if($in->action != "delete" && $in->action != "update" && $in->action != "create") $out->make_wrong_resp('Неверное действие'); //Если action не задан, то выкидываем ошибку

if($in->action == "delete"){ //Тут удаляем строку по номеру

    //Валидация $in->htNumber
    if (((string) (int) $in->htNumber) !== ((string) $in->htNumber) || (int) $in->htNumber <= 0) $out->make_wrong_resp("Номер задания задан некорректно или отсутствует");
    $stmt = $pdo->prepare("
    SELECT `ht_number`
    FROM `home_tasks`
    WHERE `ht_number` = :htNumber
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Домашнее задание с номером {$in->htNumber} не найдено");
        $stmt->closeCursor(); unset($stmt);

    //Удаляем задание по номеру
    $stmt = $pdo->prepare("
    DELETE FROM `home_tasks` WHERE `ht_number` = :htNumber
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    $stmt->closeCursor(); unset($stmt);

    //Удаляем перекрёстную проверку этого задания
    $stmt = $pdo->prepare("
    DELETE FROM `cross_check` WHERE `ht_num` = :htNumber
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
    $stmt->closeCursor(); unset($stmt);

    $out->success = "1";
    $out->make_resp('');
}


if($in->action == "create") { //Тут делаем создание
        //Валидация htNumber, только если htNumber задан
    if($in->htNumber != '') {
        if (((string) (int) $in->htNumber) !== ((string) $in->htNumber) || (int) $in->htNumber <= 0) $out->make_wrong_resp("Номер задания задан некорректно");
            $stmt = $pdo->prepare("
                SELECT `ht_number`
                FROM `home_tasks`
                WHERE `ht_number` = :htNumber
            ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
            $stmt->execute([
                'htNumber' => $in->htNumber
            ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
            if ($stmt->rowCount() != 0) $out->make_wrong_resp("Ошибка: Домашнее задание с номером {$in->htNumber} уже существует");
            $stmt->closeCursor(); unset($stmt);
    } else $in->htNumber = null; //иначе в запрос передаётся null, чтобы создать задание с новым номером

    //Создаём массивы с данными для запроса INSERT со всеми полями и словарь с данными для этих полей
    $columns = ['ht_number', 'ht_nums_p1', 'ht_nums_p1_dop', 'ht_nums_p2', 'type_p1', 'add_other_tasks_p1', 'add_other_tasks_p2', 'ht_status', 'ht_deadline', 'ht_deadline_time', 'ht_deadline_cur', 'ht_comment', 'is_probnik', 'timer_seconds_p1', 'timer_seconds_p2'];
    $columns = "`" . join('`, `', $columns) . "`";

    $values = [':htNumber', ':htNumsP1', ':htNumsP1Dop', ':htNumsP2', ':typeP1', ':addOtherTasksP1', ':addOtherTasksP2', ':htStatus', ':htDeadline', ':htDeadlineTime', ':htDeadlineCur', ':htComment', ':isProbnik', ':timerSecondsP1', ':timerSecondsP2'];
    $values = join(', ', $values);

    $params = [
        'htNumber' => $in->htNumber,
        'htNumsP1' => null,
        'htNumsP1Dop' => null,
        'htNumsP2' => null,
        'typeP1' => null,
        'addOtherTasksP1' => null,
        'addOtherTasksP2' => null,
        'htStatus' => null,
        'htDeadline' => null,
        'htDeadlineTime' => null,
        'htDeadlineCur' => null,
        'htComment' => null,
        'isProbnik' => null,
        'timerSecondsP1' => null,
        'timerSecondsP2' => null
    ];

    $stmt = $pdo->prepare("INSERT INTO `home_tasks` ($columns) VALUES ($values)") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');
    $stmt->execute($params) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
    $stmt->closeCursor(); unset($stmt);

    $in->htNumber = $pdo->lastInsertId(); if(!$in->htNumber) $out->make_wrong_resp('Произошла ошибка при создании задания');

    //Формируем таблицу перекрёстной проверки на созданное ДЗ
    //Формируем список кураторов, формируем перекрестные только тем, у кого есть ученики. Эксперты тут автоматом выпадут.
    $stmt = $pdo->prepare("
    SELECT `curators`.`user_surname`, `curators`.`user_vk_id`,
    COUNT(`users`.`user_vk_id`) AS `num_students` 
    FROM `users` AS `curators` LEFT JOIN `users` ON `users`.`user_curator` = `curators`.`user_vk_id` AND (`users`.`user_blocked` IS NULL OR `users`.`user_blocked` = 0) AND `users`.`user_type` IN ('Частичный', 'Интенсив')
    WHERE `curators`.`user_type` = 'Куратор' AND (`curators`.`user_blocked` IS NULL OR `curators`.`user_blocked` = 0)
    GROUP BY `curators`.`user_vk_id`
    HAVING `num_students` > 0
    ORDER BY RAND()
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
    $stmt->execute() or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');
    $curators[] = $stmt->fetch(PDO::FETCH_ASSOC);

    $crossCheck = [ //Словарь с данными о перекрёстной проверке, через array_rand выбирает проверяющего и куратора из полученного списка для перекрёстной проверки задания
        'ht_num' => $in->htNumber,
        'curator_vk_id' => (string) $curators[array_rand($curators)]['user_vk_id'],
        'checker_id' => (string) $curators[array_rand($curators)]['user_vk_id']
    ]; 
    
    //Добавляем проверку в базу данных
    $stmt = $pdo->prepare("INSERT INTO `cross_check` (`ht_num`, `curator_vk_id`, `checker_id`) VALUES (:ht_num, :curator_vk_id, :checker_id)") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (7)');
    $stmt->execute($crossCheck) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (7)');
    $stmt->closeCursor(); unset($stmt);    
}
    

if($in->action == "update"){ //Тут начинается действие Update

    //Валидация $in->htNumber
    if (((string) (int) $in->htNumber) !== ((string) $in->htNumber) || (int) $in->htNumber <= 0) $out->make_wrong_resp("Номер задания задан некорректно или отсутствует");
    $stmt = $pdo->prepare("
        SELECT `ht_number`
        FROM `home_tasks`
        WHERE `ht_number` = :htNumber
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (8)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Домашнее задание с номером {$in->htNumber} не найдено");
    $stmt->closeCursor(); unset($stmt);

    $changes = []; //Словарь с валидированными изменениями

    //Валидация $in->htUpdate[], если поле указано, то валидируем и добавляем в список изменений
    //htNumsP1
    if (isset($in->htUpdate['htNumsP1'])) {
        if (((string) (int) $in->htUpdate['htNumsP1']) !== ((string) $in->htUpdate['htNumsP1']) || $in->htUpdate['htNumsP1'] < 0) $out->make_wrong_resp("Поле 'htNumsP1' задано некорректно");
        $changes['ht_nums_p1'] = $in->htUpdate['htNumsP1'];
    }

    //htNumsP1Dop
    if (isset($in->htUpdate['htNumsP1Dop'])) {
        if (((string) (int) $in->htUpdate['htNumsP1Dop']) !== ((string) $in->htUpdate['htNumsP1Dop']) || $in->htUpdate['htNumsP1Dop'] < 0) $out->make_wrong_resp("Поле 'htNumsP1Dop' задано некорректно");
        $changes['ht_nums_p1_dop'] = $in->htUpdate['htNumsP1Dop'];
    }

    //htNumsP2
    if (isset($in->htUpdate['htNumsP2'])) {
        if (((string) (int) $in->htUpdate['htNumsP2']) !== ((string) $in->htUpdate['htNumsP2']) || $in->htUpdate['htNumsP2'] < 0) $out->make_wrong_resp("Поле 'htNumsP2' задано некорректно");
        $changes['ht_nums_p2'] = $in->htUpdate['htNumsP2'];
    }

    //typeP1
    if (isset($in->htUpdate['typeP1'])) {
        if (!in_array($in->htUpdate['typeP1'], ["Как в ЕГЭ", "Вопросы из урока"])) $out->make_wrong_resp("Поле 'typeP1' задано некорректно");
        $changes['type_p1'] = $in->htUpdate['typeP1'];
    }

    //addOtherTasksP1
    if (isset($in->htUpdate['addOtherTasksP1'])) {
        if (((string) (int) $in->htUpdate['addOtherTasksP1']) !== ((string) $in->htUpdate['addOtherTasksP1'])) $out->make_wrong_resp("Поле 'addOtherTasksP1' задано некорректно (1)");
        if (!in_array($in->htUpdate['addOtherTasksP1'], [0, 1])) $out->make_wrong_resp("Поле 'addOtherTasksP1' задано некорректно (2)");

        $changes['add_other_tasks_p1'] = $in->htUpdate['addOtherTasksP1'];
    }

    //addOtherTasksP2
    if (isset($in->htUpdate['addOtherTasksP2'])) {
        if (((string) (int) $in->htUpdate['addOtherTasksP2']) !== ((string) $in->htUpdate['addOtherTasksP2'])) $out->make_wrong_resp("Поле 'addOtherTasksP2' задано некорректно (1)");
        if (!in_array($in->htUpdate['addOtherTasksP2'], [0, 1])) $out->make_wrong_resp("Поле 'addOtherTasksP2' задано некорректно (2)");
        $changes['add_other_tasks_p2'] = $in->htUpdate['addOtherTasksP2'];
    }

    //htStatus
    if (isset($in->htUpdate['htStatus'])) {
        if (!in_array($in->htUpdate['htStatus'], ["Новое", "Выполнение", "Проверка", "Завершено"])) $out->make_wrong_resp("Поле 'htStatus' задано некорректно");
        $changes['ht_status'] = $in->htUpdate['htStatus'];
    }

    //htDeadline
    if (isset($in->htUpdate['htDeadline'])) {
        if (!is_string($in->htUpdate['htDeadline'])) $out->make_wrong_resp("Поле 'htDeadline' задано некорректно");
        $changes['ht_deadline'] = $in->htUpdate['htDeadline'];
    }

    //htDeadlineTime
    if (isset($in->htUpdate['htDeadlineTime'])) {
        if (!is_string($in->htUpdate['htDeadlineTime'])) $out->make_wrong_resp("Поле 'htDeadlineTime' задано некорректно");
        $changes['ht_deadline_time'] = $in->htUpdate['htDeadlineTime'];
    }

    //htDeadlineCur
    if (isset($in->htUpdate['htDeadlineCur'])) {
        if (!is_string($in->htUpdate['htDeadlineCur'])) $out->make_wrong_resp("Поле 'htDeadlineCur' задано некорректно");
        $changes['ht_deadline_cur'] = $in->htUpdate['htDeadlineCur'];
    }

    //htComment
    if (isset($in->htUpdate['htComment'])) {
        if (!is_string($in->htUpdate['htComment'])) $out->make_wrong_resp("Поле 'htComment' задано некорректно");
        $changes['ht_comment'] = $in->htUpdate['htComment'];
    }

    //isProbnik
    if (isset($in->htUpdate['isProbnik'])) {
        if (!in_array($in->htUpdate['isProbnik'], [0, 1])) $out->make_wrong_resp("Поле 'isProbnik' задано некорректно");
        $changes['is_probnik'] = $in->htUpdate['isProbnik'];
    }

    //timerSecondsP1
    if (isset($in->htUpdate['timerSecondsP1'])) {
        if (((string) (int) $in->htUpdate['timerSecondsP1']) !== ((string) $in->htUpdate['timerSecondsP1']) || $in->htUpdate['timerSecondsP1'] < 0) $out->make_wrong_resp("Поле 'timerSecondsP1' задано некорректно");
        $changes['timer_seconds_p1'] = $in->htUpdate['timerSecondsP1'];
    }

    //timerSecondsP2
    if (isset($in->htUpdate['timerSecondsP2'])) {
        if (((string) (int) $in->htUpdate['timerSecondsP2']) !== ((string) $in->htUpdate['timerSecondsP2']) || $in->htUpdate['timerSecondsP2'] < 0) $out->make_wrong_resp("Поле 'htNumsP1' задано некорректно");
        $changes['timer_seconds_p2'] = $in->htUpdate['timerSecondsP2'];
    }

    // если ничего обновлять не нужно - то выводим ошибку
    if (empty($changes)) $out->make_wrong_resp('Ни для одного поля не было запрошено обновление');


    $values = [];
    $params = [];
    foreach ($changes as $key => $value) { 
        $values[] = "`$key` = :$key";
        $params[$key] = $value;
    }
    $values = join(', ', $values);
    $params['htNumber'] = $in->htNumber;

    $stmt = $pdo->prepare("UPDATE `home_tasks` SET $values WHERE `ht_number` = :htNumber") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (9)');
    $stmt->execute($params) or $out->make_wrong_resp("Ошибка базы данных: выполнение запроса (9)");
    $stmt->closeCursor(); unset($stmt);  
}


//Получение всех данных о задании из таблицы home_tasks
$stmt = $pdo->prepare("
    SELECT `ht_number`, `ht_nums_p1`, `ht_nums_p1_dop`, `ht_nums_p2`, `type_p1`, `add_other_tasks_p1`, `add_other_tasks_p2`, `ht_status`, `ht_deadline`, `ht_deadline_time`, DATE_FORMAT(`ht_deadline_cur`, '%Y-%m-%dT%H:%i') AS `ht_deadline_cur`, `ht_comment`, `is_probnik`, `timer_seconds_p1`, `timer_seconds_p2`
    FROM `home_tasks` 
    WHERE `ht_number` = :htNumber
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (10)');
$stmt->execute([
    'htNumber' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (10)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$homeTask = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Далее три запроса на количество вопросов каждой части
//Получаем данные о количестве вопросов первой части из таблицы questions
$stmt = $pdo->prepare("
    SELECT COUNT(1) AS `numsP1`
    FROM `questions`
    WHERE `q_lesson_num` = :htNumber 
    AND `q_public` = 1 
    AND `selfmade` = 0
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(11)');
$stmt->execute([
    'htNumber' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(11)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$numsP1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Получаем данные о количестве дополнительных вопросов к первой части из таблицы questions
$stmt = $pdo->prepare(" 
    SELECT COUNT(1) AS `numsP1Dop`
    FROM `questions`
    WHERE `q_lesson_num` = :htNumber 
    AND `q_public` = 1 
    AND `selfmade` = 1
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(12)');
$stmt->execute([
    'htNumber' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(12)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$numsP1Dop = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Получаем данные о количестве вопросов из второй части из таблицы questions2
$stmt = $pdo->prepare("
    SELECT COUNT(1) AS `numsP2`
    FROM `questions2`
    WHERE `q2_lesson_num` = :htNumber 
    AND `q2_public` = 1 
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(13)');
$stmt->execute([
    'htNumber' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(13)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$numsP2 = $stmt->fetch(PDO::FETCH_ASSOC); //Добавляем полученные данные в $questions
$stmt->closeCursor(); unset($stmt);

//Формируем ответ словарём $homeTask из всех полученных данных
$out->homeTask = [
    'htNumber' => (string) $homeTask['ht_number'],
    'htNumsP1' => (string) $homeTask['ht_nums_p1'],
    'htNumsP1Dop' => (string) $homeTask['ht_nums_p1_dop'],
    'htNumsP2' => (string) $homeTask['ht_nums_p2'],
    'typeP1' => (string) $homeTask['type_p1'],
    'addOtherTasksP1' => (string) $homeTask['add_other_tasks_p1'],
    'addOtherTasksP2' => (string) $homeTask['add_other_tasks_p2'],
    'htStatus' => (string) $homeTask['ht_status'],
    'htDeadline' => (string) $homeTask['ht_deadline'],
    'htDeadlineTime' => (string) $homeTask['ht_deadline_time'],
    'htDeadlineCur' => (string) $homeTask['ht_deadline_cur'],
    'htComment' => (string) $homeTask['ht_comment'],
    'isProbnik' => (string) $homeTask['is_probnik'],
    'timerSecondsP1' => (string) $homeTask['timer_seconds_p1'],
    'timerSecondsP2' => (string) $homeTask['timer_seconds_p2']
];

//Добавляем в ответ словарь с количеством вопросов
$out->questions = [
    'numsP1' => (string) $numsP1['numsP1'],
    'numsP1Dop' => (string) $numsP1Dop['numsP1Dop'],
    'numsP2' => (string) $numsP2['numsP2']
];

$out->success = "1";
$out->make_resp('');