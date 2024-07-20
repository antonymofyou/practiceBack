<?php // Создание, обновление и удаление домашнего задания

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/root_classes.inc.php';

//класс запроса
class SetHometask extends MainRequestClass { 
    public $htNumber = ''; // ключ дз

    public $ht = []; /* словарь с данными для обновления

    htNumsP1 INT Количество вопросов из первой части
    htNumsP1Dop INT Количество дополнительных вопросов из первой части
    htNumsP2 INT Количество вопросов из второй части
    typeP1 TEXT Тип вопросов: Вопросыизурона или КаквЕГЭ
    addOtherTasksP1 BOOLEAN Добавить задания Ч1 из других уроков в случае нехватки
    addOtherTasksP2 BOOLEAN Добавить задания Ч2 из других уроков в случае нехватки
    htStatus TEXT Статус задания: Новое, Выполнение, Проверка или Завершено
    htDeadline DATE День дедлайна
    htDeadlineTime TIME Время дедлайна 
    htDeadlineCur DATETIME Дедлайн проверки кураторов 
    htComment TEXT 
    isProbnik BOOLEAN Является ли пробником 
    timerSecondsP1 INT Времени на Ч1 минут
    timerSecondsP2 INT Времени на Ч2 минут

    */


    public $action = ''; // одно из: create, update, delete или get
    /* 
    create создаёт незаполненное домашнее задание с номером и возвращает этот номер
    update обновляет домашнее задание
    delete удаляет домашнее задание
    get возвращает данные домашнего задания и доступные задачи по номеру
    */

}

$in = new SetHometask();
$in->from_json(file_get_contents('php://input'));




//класс ответа
class SetHometaskResponse extends MainResponseClass {

    public $homeTask = []; /* Словарь с данными дз

    htNumsP1 INT Количество вопросов из первой части
    htNumsP1Dop INT Количество дополнительных вопросов из первой части
    htNumsP2 INT Количество вопросов из второй части
    typeP1 TEXT Тип вопросов: Вопросыизурона или КаквЕГЭ
    addOtherTasksP1 BOOLEAN Добавить задания Ч1 из других уроков в случае нехватки
    addOtherTasksP2 BOOLEAN Добавить задания Ч2 из других уроков в случае нехватки
    htStatus TEXT Статус задания: Новое, Выполнение, Проверка или Завершено
    htDeadline DATE День дедлайна
    htDeadlineTime TIME Время дедлайна 
    htDeadlineCur DATETIME Дедлайн проверки кураторов 
    htComment TEXT 
    isProbnik BOOLEAN Является ли пробником 
    timerSecondsP1 INT Времени на Ч1 минут
    timerSecondsP2 INT Времени на Ч2 минут

    */

    public $questions = []; /* Словарь с данными о количестве вопросов

    numsP1 INT Количество доступных вопросов 1 части
    numsP1Dop INT Количество доступных дополнительных вопросов 1 части
    numsP2 INT Количество доступных вопросов 2 части

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
//require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/manager_check_user.inc.php';

/*

//Массив key => value для UPDATE запроса
$updateHt = [];

//Валидация $in->htNumber
if (((string) (int) $in->htNumber) !== ((string) $in->htNumber) || (int) $in->htNumber <= 0) $out->make_wrong_resp("Параметр 'htNumber' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `ht_number`
    FROM `home_tasks`
    WHERE `ht_number` = :htNumber
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
$stmt->execute([
    'id' => $in->htNumber
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Домашнее задание {$in->htNumber} не найдено");
$stmt->closeCursor(); unset($stmt);

//Валидация данных для обновления $in->ht[]
if(isset($in->ht['htNumsP1'])) {
    if(!is_int($in->ht['htNumsP1'])) $out->make_wrong_resp("Поле htNumsP1 задано некорректно");
    $updateHt['ht_nums_P1'] = $in->ht['htNumsP1'];
}

*/







//Формирование ответа $action. В зависимости от значения $action выбирается соответствующий алгоритм обработки
switch($in->action)
{
    case "create":
       
        $stmt = $pdo->prepare("
        INSERT INTO home_tasks SET ht_number = NULL
        

        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса');
        $stmt->execute() or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (11)');
        $stmt->closeCursor(); unset($stmt);

        $in->htNumber = $pdo->lastInsertId(); if(!$in->htNumber) $out->make_wrong_resp('Произошла ошибка при создании задания');

        $out->message = "Создание или обновление задания"; //Сообщаем о назначении запроса в вывод
        //Код выполняется дальше
    case "update":
        

        
    case 'get':

        //Валидация htNumber
        if (((string) (int) $in->htNumber) !== ((string) $in->htNumber) || (int) $in->htNumber <= 0) $out->make_wrong_resp("Параметр 'htNumber' задан некорректно или отсутствует");
            $stmt = $pdo->prepare("
            SELECT ht_number
            FROM home_tasks
            WHERE ht_number = :htNumber
            ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
            $stmt->execute([
                'htNumber' => $in->htNumber
            ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
            if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Домашнее задание под номером {$in->htNumber} не найдено");
                $stmt->closeCursor(); unset($stmt);

        //Получение всех данных о задании из таблицы home_tasks
        $stmt = $pdo->prepare("
        SELECT *,
        DATE_FORMAT('ht_deadline_cur', '%Y-%m-%dT%H:%i') AS htDeadlineCur
        FROM home_tasks 
        WHERE ht_number = :htNumber
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
        $stmt->execute([
            'htNumber' => $in->htNumber
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
        if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
        $homeTask = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); unset($stmt);

        //Получаем данные о вопросах первой части из таблицы questions
        $stmt = $pdo->prepare("
        SELECT COUNT(1) as numsP1
        FROM questions
        WHERE q_lesson_num = :htNumber 
        AND q_public = 1 
        AND selfmade = 0
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(3)');
        $stmt->execute([
            'htNumber' => $in->htNumber
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(3)');
        //if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
        $questions = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); unset($stmt);

        //Получаем данные о вопросах из второй части из таблицы questions2
        $stmt = $pdo->prepare("
        SELECT COUNT(1) as numsP2
        FROM questions2
        WHERE q2_lesson_num = :htNumber 
        AND q2_public = 1 
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(3)');
        $stmt->execute([
            'htNumber' => $in->htNumber
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(3)');
        //if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
        $questions += $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); unset($stmt);

        //Дополнительные вопросы к первой части из таблицы questions
        $stmt = $pdo->prepare(" 
        SELECT COUNT(1) as numsP1Dop
        FROM questions
        WHERE q_lesson_num = :htNumber 
        AND q_public = 1 
        AND selfmade = 1
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса(3)');
        $stmt->execute([
            'htNumber' => $in->htNumber
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса(3)');
        //if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
        $questions += $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); unset($stmt);

        //Цикл формирует ответ словарём $homeTask из всех полученных данных
        foreach($homeTask as $key => $value) 
        {
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

    break;

    case "delete":
    
    break;

    default: $out->make_wrong_resp("Некорреткный 'action'");
    
}
$out->make_resp('');


