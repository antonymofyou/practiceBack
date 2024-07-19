<?php // Создание, обновление и удаление домашнего задания

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//класс запроса
class SetHometask extends MainRequestClass { 
    public $htNumber = ''; // ключ дз

    public $ht = []; /* словарь с данными для обновления

    htNumsP1
    htNumsP1Dop
    htNumsP2
    typeP1
    addOtherTasksP1
    addOtherTasksP2
    htStatus
    htDeadline
    htDeadlineTime
    htDeadlineCur
    htComment
    isProbnik
    timerSecondsP1
    timerSecondsP2

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
$in->from_json(file_get_contents('php://in'));

//класс ответа
class SetHometaskResponse extends MainRequestClass {
    public $createdHt = [];  /* Словарь созданной вакансии
    - htNumber - номер дз
    - createdAt - дата создания дз
    */

    public $getHt = []; /* Словарь существующей вакансии

    htNumsP1
    htNumsP1Dop
    htNumsP2
    typeP1
    addOtherTasksP1
    addOtherTasksP2
    htStatus
    htDeadline
    htDeadlineTime
    htDeadlineCur
    htComment
    isProbnik
    timerSecondsP1
    timerSecondsP2
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
switch($action)
{
    case "create":
        $out->success = '1';
        $out->htNumber = [
            'htNumber' => (string) $ht['htNumber']
        ];
    break;

    case "update":

    break;

    case "delete":
    break;

    case "get":

        //Валидация htNumber
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

        //Получение всех данных
        $stmt = $pdo->prepare("
            SELECT * FROM 'home_tasks' WHERE 'ht_number' = :htNumber
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса');
        $stmt->execute([
            'htNumber' => $in->htNumber,
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса');
        if($stmt->rowCount() == 0) $out->make_wrong_resp('Ошибка: данные не получены');
        $getHt = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); unset($stmt);
    break;

    default: $out->make_wrong_resp("Некорреткный 'action'");
    
}
$out->make_resp('');


