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
    typeP1 TEXT - Тип вопросов: Вопросыизурона или КаквЕГЭ
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
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/manager_check_user.inc.php';
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
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1.1)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1.1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Домашнее задание с номером {$in->htNumber} не найдено");
        $stmt->closeCursor(); unset($stmt);

    //Удаляем задание по номеру
    $stmt = $pdo->prepare("
    DELETE FROM `home_tasks` WHERE `ht_number` = :htNumber
    ") or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1.2)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1.2)');
    $stmt->closeCursor(); unset($stmt);

    //Удаляем перекрёстную проверку этого задания
    $stmt = $pdo->prepare("
    DELETE FROM `cross_check` WHERE `ht_num` = :htNumber
    ") or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1.3)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1.3)');
    $stmt->closeCursor(); unset($stmt);

    $out->message = "Задание $in->htNumber удалено";
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
            ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2.1)');
            $stmt->execute([
                'htNumber' => $in->htNumber
            ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2.1)');
            if ($stmt->rowCount() != 0) $out->make_wrong_resp("Ошибка: Домашнее задание с номером {$in->htNumber} уже существует");
            $stmt->closeCursor(); unset($stmt);
    } else $in->htNumber = null; //иначе в запрос передаётся null, чтобы создать задание с новым номером

    $stmt = $pdo->prepare("
    INSERT INTO `home_tasks` SET `ht_number` = :htNumber 
    
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2.2)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2.2)');
    $stmt->closeCursor(); unset($stmt);

    $in->htNumber = $pdo->lastInsertId(); if(!$in->htNumber) $out->make_wrong_resp('Произошла ошибка при создании задания');

    //Формируем таблицу перекрёстной проверки на созданное ДЗ
    //Формируем список кураторов, формируем перекрестные только тем, у кого есть ученики. Эксперты тут автоматом выпадут.
    $stmt = $pdo->prepare("
    SELECT `curators.user_surname`, `curators.user_vk_id`,
    COUNT(`users.user_vk_id`) AS `num_students` 
    FROM `users` AS `curators` LEFT JOIN `users` ON `users.user_curator`=`curators.user_vk_id` AND (`users.user_blocked` IS NULL OR `users.user_blocked`=0) AND `users.user_type` IN ('Частичный','Интенсив')
    WHERE `curators.user_type`='Куратор' AND (`curators.user_blocked` IS NULL OR `curators.user_blocked`=0)
    GROUP BY `curators.user_vk_id`
    HAVING `num_students`>0
    ORDER BY RAND()
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2.3)');
    $stmt->execute() or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2.3)');
    $curators[] = $stmt->fetch(PDO::FETCH_ASSOC);

    $crossCheck = []; //Словарь с данными о перекрёстной проверке
    
    //через array_rand выбирает проверяющего и куратора из полученного списка для перекрёстной проверки задания
    $crossCheck['ht_num'] = $in->htNumber;
    $crossCheck['curator_vk_id'] = $curators[array_rand($curators)]['user_vk_id'];
    $crossCheck['checker_id'] =  $curators[array_rand($curators)]['user_vk_id'];
    
    //Добавляем созданную проверку в базу данных
    $values = [];
    foreach ($crossCheck as $key => $value) { 
        $values[] = "`$key` = $value";
    }

    $values = join(', ', $values);

    $stmt = $pdo->prepare("INSERT INTO `cross_check` SET $values") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2.4)');
    $stmt->execute() or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2.4)');
    $stmt->closeCursor(); unset($stmt);    
}
    

if($in->action == "update"){ //Тут начинается действие Update

    //Валидация $in->htNumber
    if (((string) (int) $in->htNumber) !== ((string) $in->htNumber) || (int) $in->htNumber <= 0) $out->make_wrong_resp("Номер задания задан некорректно или отсутствует");
    $stmt = $pdo->prepare("
        SELECT `ht_number`
        FROM `home_tasks`
        WHERE `ht_number` = :htNumber
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3.1)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3.1)');
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
        if (mb_strlen($htUpdate['typeP1']) > 50) $out->make_wrong_resp("Поле 'typeP1' задано некорректно (1)");
        if (!in_array($in->htUpdate['typeP1'], ["Как в ЕГЭ", "Вопросы из урока"])) $out->make_wrong_resp("Поле 'typeP1' задано некорректно (2)");
        $changes['type_p1'] = "'" . $in->htUpdate['typeP1'] . "'"; //Закрываем в кавычки для передачи в бд, а то выскакивает ошибка о некорректном поле
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
        if (mb_strlen($htUpdate['htStatus']) > 50) $out->make_wrong_resp("Поле 'htStatus' задано некорректно (1)");
        if (!in_array($in->htUpdate['htStatus'], ["Новое", "Выполнение", "Проверка", "Завершено"])) $out->make_wrong_resp("Поле 'htStatus' задано некорректно (2)");
        $changes['ht_status'] = "'" . $in->htUpdate['htStatus'] . "'"; //Закрываем в кавычки
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
        if (!is_string($in->htUpdate['htComment']) || (mb_strlen($htUpdate['htComment'] > 256))) $out->make_wrong_resp("Поле 'htComment' задано некорректно");
        $changes['ht_comment'] = "'" . $in->htUpdate['htComment'] . "'"; //Закрываем в кавычки
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
    foreach ($changes as $key => $value) { 
        $values[] = "`$key` = $value";
    }
    $values = join(', ', $values);

    $stmt = $pdo->prepare("UPDATE `home_tasks` SET $values WHERE `ht_number` = :htNumber") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3.2)');
    $stmt->execute([
        'htNumber' => $in->htNumber
    ]) or $out->make_wrong_resp("Ошибка базы данных: выполнение запроса (3.2)");
    $stmt->closeCursor(); unset($stmt);  
}
        



//Получение всех данных о задании из таблицы home_tasks
$stmt = $pdo->prepare("
    SELECT `ht_nums_p1` AS `htNumsP1`,
    `ht_nums_p1_dop` AS `htNumsP1Dop`,
    `ht_nums_p2` AS `htNumsP2`,
    `type_p1` AS `typeP1`,
    `add_other_tasks_p1` AS `addOtherTasksP1`,
    `add_other_tasks_p2` AS `addOtherTasksP2`,
    `ht_status` AS `htStatus`,
    `ht_deadline` AS `htDeadline`,
    `ht_deadline_time` AS `htDeadlineTime`,
    DATE_FORMAT(`ht_deadline_cur`, '%Y-%m-%dT%H:%i') AS `htDeadlineCur`,
    `ht_comment` AS `htComment`,
    `is_probnik` AS `isProbnik`,
    `timer_seconds_p1` AS `timerSecondsP1`,
    `timer_seconds_p2` AS `timerSecondsP2`
    FROM `home_tasks` 
    WHERE `ht_number` = :htNumber
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4.2)');
$stmt->execute([
    'htNumber' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4.2)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$homeTask = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

$questions = []; // Создаём словарь с данными о количестве вопросов

//Получаем данные о количестве вопросов первой части из таблицы questions
$stmt = $pdo->prepare("
    SELECT COUNT(1) AS `numsP1`
    FROM `questions`
    WHERE `q_lesson_num` = :htNumber 
    AND `q_public` = 1 
    AND `selfmade` = 0
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(4.3)');
$stmt->execute([
    'htNumber' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(4.3)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$questions += $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Получаем данные о количестве вопросов из второй части из таблицы questions2
$stmt = $pdo->prepare("
    SELECT COUNT(1) AS `numsP2`
    FROM `questions2`
    WHERE `q2_lesson_num` = :htNumber 
    AND `q2_public` = 1 
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(4.4)');
$stmt->execute([
    'htNumber' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(4.4)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$questions += $stmt->fetch(PDO::FETCH_ASSOC); //Добавляем полученные данные в $questions
$stmt->closeCursor(); unset($stmt);

//Получаем данные о количестве дополнительных вопросов к первой части из таблицы questions
$stmt = $pdo->prepare(" 
    SELECT COUNT(1) AS `numsP1Dop`
    FROM `questions`
    WHERE `q_lesson_num` = :htNumber 
    AND `q_public` = 1 
    AND `selfmade` = 1
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(4.5)');
$stmt->execute([
    'htNumber' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(4.5)');
if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
$questions += $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Цикл формирует ответ словарём $homeTask из всех полученных данных
foreach($homeTask as $key => $value) 
{
    //$key = lcfirst(str_replace('_', '', ucwords($key, '_'))); //Превращаем snake_case в camelCase

    $out->homeTask += [
        $key => $value
    ];
};

//Дополнительно добавляем в ответ количество вопросов
foreach($questions as $key => $value) 
{
    $out->questions += [
        $key => $value
    ];
}

$out->success = "1";
$out->make_resp('');