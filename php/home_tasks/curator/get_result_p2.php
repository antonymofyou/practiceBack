<?php //---Получение данных для страницы проверки ДЗ куратором по всей работе ученика или по номеру вопроса

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class HomeTasksCuratorGetResultP2 extends MainRequestClass {
    public $dzNum = ''; //Номер ДЗ
    public $onlyChecked = ''; //Показать ли только проверенные работы при поиске по номеру вопроса(0/1)(необязательно, по умолчанию 0)

    //Необходимо одно из: 
    public $type = ''; //ИД ученика для получения всей работы ученика, либо номер вопроса для получения всех ответов по вопросу. Формат: userVkId:IdПользователя или taskNum:номерЗадания
    
    public $curatorId = ''; //Если запрашивает админ, то он может указать поиск по работам учеников определённого куратора
}
$in = new HomeTasksCuratorGetResultP2();
$in->from_json(file_get_contents('php://input'));

//---Класс ответа
class HomeTasksCuratorGetResultP2Response extends MainResponseClass {
    /* Словарь со следующими полями:
        Поля вопроса:
        - q2Id                  - ИД вопроса
        - q2Question            - Текст вопроса
        - q2ObyazDz             - Обязательность задания(0/1)
        - isProbnik             - Является ли задание пробником(0/1)

        Поля ответа:
        - htUserId              - ИД задания ученику
        - htNumber              - Номер задания
        - qNumber               - Номер вопроса
        - qId                   - ИД вопроса
        - realBall              - Максимальное количество баллов за задание
        - userAnswer            - Ответ ученика
        - teacherJson           - JSON с информацией о тексте(??), содержит матрицу с полями: start_pos, finish_pos, color, zacherkn, cur_subcomment
        - isChecked             - Проверена ли работа(0/1)
        - htUserChecker         - ИД проверяющего 
        - checker               - Фамилия и имя проверяющего
        - htUserStatusP2        - Статус домашнего задания: Выполняется, Готово, Отклонен, Самопров, Проверено, Аппеляция
        - htUserTarifNum        - Тариф ученика(1: Демократичный, 2: Авторитарный, 3: Тоталитарный)
        - htUserApellStud       - Текст аппеляции ученика(Только если статус = Аппеляция)
        - htUserApellTeacher    - Комментарий учителя к аппеляции(Только если статус = Аппеляция)
    
        Поля пользователя:
        - userVkId              - ИД ВК отвечающего ученика
        - userName              - Имя ученика
        - userSurname           - Фамилия ученика
        - userTarifNum          - Тариф ученика(1: Демократичный, 2: Авторитарный, 3: Тоталитарный)
        - userGoalBall          - Желаемые баллы??
        - userCurator           - ИД куратора ученика
        - userCuratorDz         - ИД куратора ученика по ДЗ
    */
    public $result = []; //Массив словарей с данными по вопросам, ответам и пользователю, которому принадлежит ответ
}
$out = new HomeTasksCuratorGetResultP2Response();

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

//---Проверка пользователя ()
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if(!in_array($user_type, ['Админ', 'Куратор'])) {
    $out->make_wrong_resp('Ошибка доступа');
}


//---Валидация dzNum
if (((string) (int) $in->dzNum) !== ((string) $in->dzNum) || (int) $in->dzNum <= 0) $out->make_wrong_resp("Параметр 'dzNum' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `ht_number`, `is_probnik`
    FROM `home_tasks`
    WHERE `ht_number` = :dzNum;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'dzNum' => $in->dzNum
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Задание с таким номером не найдено");
$homeTask = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//---Валидация type
if(!is_string($in->type) || empty($in->type)) $out->make_wrong_resp("Параметр 'type' задан неверно или отсутствует (1)");
$type = explode(':', $in->type) or $out->make_wrong_resp("Параметр 'type' задан неверно или отсутствует (2)"); 
if (count($type) != 2) $out->make_wrong_resp("Параметр 'type' задан неверно или отсутствует (3)");
if (((string) (int) $type[1]) !== ((string) $type[1]) || (int) $type[1] <= 0) $out->make_wrong_resp("ID в параметре 'type' задано неверно или отсутствует");

//---Если type это userVkId
if ($type[0] == "userVkId") {
    $params = [];
    $params['dzNum'] = $in->dzNum;
    $params['userVkId'] = $type[1];

    //Добавление проверки на куратора, куратор может смотреть только своих учеников, админ может смотреть кого угодно
    if($user_type == 'Админ') {
        if(((string) (int) $in->curatorId) === ((string) $in->curatorId) && (int) $in->curatorId > 0) $curatorId = $in->curatorId;
    }
    else $curatorId = $user_id;
    
    if ($curatorId != '') { 
        $addCurator = " AND (`users`.`user_curator` = :curatorId OR `users`.`user_curator_dz` = :curatorId)";
        $params['curatorId'] = $curatorId;
    }
    else $addCurator = '';

    $stmt = $pdo->prepare("
        SELECT `users`.`user_vk_id`, `users`.`user_name`, `users`.`user_surname`, `users`.`user_tarif_num`, `users_add`.`user_goal_ball`, `users`.`user_curator`, `users`.`user_curator_dz`, 
        `ht_user`.`ht_user_id`, `ht_user`.`ht_number`, `ht_user`.`ht_user_checker`, `ht_user`.`ht_user_status_p2`, `questions2`.`q2_id`, `questions2`.`q2_question`, `questions2`.`q2_obyaz_dz`,
        `ht_user_p2`.`user_id`, `ht_user_p2`.`q_id`, `ht_user_p2`.`q_number`, `ht_user_p2`.`real_ball`, `ht_user_p2`.`user_answer`, `ht_user_p2`.`teacher_json`, `ht_user_p2`.`is_checked`,
        CONCAT(`checkers`.`user_surname`, ' ', `checkers`.`user_name`) AS `checker`
        FROM `users` 
        LEFT JOIN `users_add` ON `users_add`.`user_vk_id` = `users`.`user_vk_id`
        LEFT JOIN `ht_user` ON `users`.`user_vk_id`=`ht_user`.`user_id` AND `ht_user`.`ht_number` = :dzNum
        LEFT JOIN `users` AS `checkers` ON `checkers`.`user_vk_id`=`ht_user`.`ht_user_checker`
        LEFT JOIN `ht_user_p2` ON `ht_user_p2`.`user_id` = `users`.`user_vk_id` AND `ht_user_p2`.`ht_number`= :dzNum
        LEFT JOIN `questions2` ON `questions2`.`q2_id` = `ht_user_p2`.`q_id`
        WHERE `users`.`user_vk_id` = :userVkId $addCurator
        ORDER BY `ht_user`.`ht_user_status_p2` DESC;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (2)");
    $stmt->execute($params) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("По запросу ничего не найдено (1)");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); unset($stmt);
} 

//---Если type это taskNum
elseif ($type[0] == "taskNum") {
    $params = [];
    $params['dzNum'] = $in->dzNum;
    $params['taskNum'] = $type[1];
    
    if($in->onlyChecked === '1') {
        $onlyCheckedStatus = "('Готово', 'Проверен', 'Отклонен')";
        $params['onlyChecked'] = 1;
    } else {
        $onlyCheckedStatus = "('Готово', 'Отклонен')";
        $params['onlyChecked'] = 0;
    }

    //Добавление проверки на куратора
    if($user_type == 'Админ') {
        if(((string) (int) $in->curatorId) !== ((string) $in->curatorId) || (int) $in->curatorId <= 0) 
        $curatorId = $in->curatorId;
    }
    else $curatorId = $user_id;
    
    if ($curatorId != '') { 
        $addCurator = " AND (`users`.`user_curator` = :curatorId OR `users`.`user_curator_dz` = :curatorId)";
        $params['curatorId'] = $curatorId;
    }
    else $addCurator = '';

    $stmt = $pdo->prepare("
        SELECT `ht_user_p2`.`user_id`, `ht_user_p2`.`q_id`, `ht_user_p2`.`q_number`, `ht_user_p2`.`real_ball`, `ht_user_p2`.`user_answer`, `ht_user_p2`.`teacher_json`, `ht_user_p2`.`is_checked`,
        `users`.`user_vk_id`, `users`.`user_name`, `users`.`user_surname`, `users`.`user_tarif_num`, `users_add`.`user_goal_ball`, `users`.`user_curator`, `users`.`user_curator_dz`,
        `ht_user`.`ht_user_id`, `ht_user`.`ht_number`, `ht_user`.`ht_user_checker`, `ht_user`.`ht_user_status_p2`, `questions2`.`q2_id`, `questions2`.`q2_question`, `questions2`.`q2_obyaz_dz`,
        CONCAT(`checkers`.`user_surname`, ' ', `checkers`.`user_name`) AS `checker`
        FROM `ht_user_p2`
        LEFT JOIN `users` ON `users`.`user_vk_id` = `ht_user_p2`.`user_id`
	    LEFT JOIN `users_add` ON `users_add`.`user_vk_id` = `users`.`user_vk_id`
        LEFT JOIN `ht_user` ON `ht_user`.`user_id` = `ht_user_p2`.`user_id` AND `ht_user`.`ht_number` = `ht_user_p2`.`ht_number`
        LEFT JOIN `users` AS `checkers` ON `checkers`.`user_vk_id`=`ht_user`.`ht_user_checker`
        LEFT JOIN `questions2` ON `questions2`.`q2_id` = `ht_user_p2`.`q_id`
        WHERE `ht_user_p2`.`ht_number` = :dzNum AND `ht_user_p2`.`q_number` = :taskNum AND `users`.`user_tarif_num` IN (2,3) AND (`users`.`user_blocked` = 0 OR `users`.`user_blocked` IS NULL)
        AND `users`.`user_type` IN ('Частичный', 'Интенсив', 'Админ') AND `ht_user`.`ht_user_status_p2` IN $onlyCheckedStatus AND `ht_user_p2`.`is_checked` = :onlyChecked
        $addCurator
        ORDER BY `ht_user`.`ht_user_date_p2`, `ht_user`.`ht_user_time_p2`;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (3)");
    $stmt->execute($params) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("По запросу ничего не найдено (2)");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); unset($stmt);
} 

else $out->make_wrong_resp("Параметр 'type' задан неверно или отсутствует (4)");


//---Формирование ответа
foreach($result as $value) {
    $out->result[] = [
        'q2Id' => (string) $value['q2_id'],
        'q2Question' => (string) $value['q2_question'],
        'q2ObyazDz' => (string) $value['q2_obyaz_dz'],
        'isProbnik' => (string) $homeTask['is_probnik'],
        'htUserId' => (string) $value['ht_user_id'],
        'htNumber' => (string) $value['ht_number'],
        'qNumber' => (string) $value['q_number'],
        'qId' => (string) $value['q_id'],
        'realBall' => (string) $value['real_ball'],
        'userAnswer' => (string) $value['user_answer'],
        'teacherJson' => (string) $value['teacher_json'],
        'isChecked' => (string) $value['is_checked'],
        'htUserChecker' => (string) $value['ht_user_checker'],
        'checker' => (string) $value['checker'],
        'htUserStatusP2' => (string) $value['ht_user_status_P2'],
        'htUserTarifNum' => (string) $value['ht_user_tarif_num'],
        'htUserApellStud' => (string) $value['ht_user_apell_stud'],
        'htUserApellTeacher' => (string) $value['ht_user_apell_teacher'],
        'userVkId' => (string) $value['user_vk_id'],
        'userName' => (string) $value['user_name'],
        'userSurname' => (string) $value['user_surname'],
        'userTarifNum' => (string) $value['user_tarif_num'],
        'userGoalBall' => (string) $value['user_goal_ball'],
        'userCurator' => (string) $value['user_curator'],
        'userCuratorDz' => (string) $value['user_curator_dz']
    ];
}

$out->success = "1";
$out->make_resp('');