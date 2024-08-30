<?php //---Создание, удаление периодов работы

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class JobSetPeriod extends MainRequestClass {
    public $action = ''; // Действие, которое нужно сделать (доступные значения: 'create' - создать период; 'delete' - удалить период)
    public $dayId = ''; // ID дня, для которого нужно создать/удалить период работы
    public $periodId = ''; // ID периода (при $action == 'delete') (период должен относится к указанному $dayId. Если период с указанным ID относится к другому дню с другим dayId, то будет ошибка)
    public $periodStart = ''; // Время начало периода работы, на которую нужно создать создать или на которую нужно обновить отчет (при $action == 'create')
    public $periodEnd = ''; // Время конца периода работы, на которую нужно создать создать или на которую нужно обновить отчет (при $action == 'create')
    // период работы periodStart и periodEnd не должны пересекаться с другими периодами
}
$in = new JobSetPeriod();
$in->from_json(file_get_contents('php://input'));

//---Класс ответа
class JobSetPeriodResponse extends MainResponseClass {
    /* Словарь, где ключ это dayId (ID дня), а значение это словарь, который имеет имеет следующие поля:
     *  - periodId - id периода
     *  - periodStart - начало рабочего периода
     *  - periodEnd - конец рабочего периода
     */
    public $periodsTimes = []; // словарь с обновленными периодами после создания/удаления (сортировка по periodStart) (может быть пустым словарем, если периодов нет)
}
$out = new JobSetPeriodResponse();

//---Подключение к БД
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_HR . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/manager_check_user.inc.php';

//Валидация действия
if(!in_array($in->action, ['create', 'delete'])) $out->make_wrong_resp('Неверное действие');

//---Удаление периода работы
elseif($in->action == "delete") {
    
    //Валидация dayId
    if (((string) (int) $in->dayId) !== ((string) $in->dayId) || (int) $in->dayId <= 0) $out->make_wrong_resp("Параметр 'dayId' задан неверно или отсутствует");
    $stmt = $pdo->prepare("
        SELECT `manager_id`
        FROM `managers_job_days`
        WHERE `id` = :dayId;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
    $stmt->execute([
        'dayId' => $in->dayId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Указанный день не существует");
    $day = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); unset($stmt);

    //Проверка соответствия пользователя дня и текущего пользователя
    if ($day['manager_id'] != $user['id']) $out->make_wrong_resp('Нельзя удалить период другого сотрудника');

    //Валидация periodId
    if (((string) (int) $in->periodId) !== ((string) $in->periodId) || (int) $in->periodId <= 0) $out->make_wrong_resp("Параметр 'periodId' задан неверно или отсутствует");
    $stmt = $pdo->prepare("
        SELECT `id`
        FROM `managers_job_time_periods`
        WHERE `id` = :periodId AND `day_id` = :dayId;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (2)");
    $stmt->execute([
        'periodId' => $in->periodId,
        'dayId' => $in->dayId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Этот период для указанного дня не существует");
    $stmt->closeCursor(); unset($stmt);

    //Удаление периода
    $stmt = $pdo->prepare("
        DELETE FROM `managers_job_time_periods`
        WHERE `id` = :periodId AND `day_id` = :dayId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
    $stmt->execute([
        'periodId' => $in->periodId,
        'dayId' => $in->dayId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
    $stmt->closeCursor(); unset($stmt);
}

//---Создание периода работы
elseif($in->action == "create") {
    
    //Валидация dayId
    if (((string) (int) $in->dayId) !== ((string) $in->dayId) || (int) $in->dayId <= 0) $out->make_wrong_resp("Параметр 'dayId' задан неверно или отсутствует");
    $stmt = $pdo->prepare("
        SELECT `manager_id`
        FROM `managers_job_days`
        WHERE `id` = :dayId;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (4)");
    $stmt->execute([
        'dayId' => $in->dayId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Указанный день не существует");
    $day = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); unset($stmt);

    //Проверка соответствия пользователя дня и текущего пользователя
    if ($day['manager_id'] != $user['id']) $out->make_wrong_resp('Нельзя создать период другого сотрудника');

    //Валидация periodStart
    if (empty($in->periodStart)) $out->make_wrong_resp("Параметр 'periodStart' не задан");
    $startTime = date_create_from_format("H:i", $in->periodStart) or $out->make_wrong_resp("Параметр 'periodStart' задан неверно (1)"); //Проверка на формат
    if($startTime->format("H:i") != $in->periodStart && $startTime->format("G:i") != $in->periodStart) $out->make_wrong_resp("Параметр 'periodStart' задан неверно (2)"); //Проверка на верность даты, выдаёт ошибку если, например, установлен час 25 или минута 61, но не будет ошибки, если отсутствует ведущий ноль у указанного часа

    //Валидация periodEnd
    if (empty($in->periodEnd)) $out->make_wrong_resp("Параметр 'periodEnd' не задан");
    $endTime = date_create_from_format("H:i", $in->periodEnd) or $out->make_wrong_resp("Параметр 'periodEnd' задан неверно (1)");
    if($endTime->format("H:i") != $in->periodEnd && $endTime->format("G:i") != $in->periodEnd) $out->make_wrong_resp("Параметр 'periodEnd' задан неверно (2)"); 

    //Начало периода должно быть раньше, чем его конец
    if ($startTime->getTimestamp() >= $endTime->getTimestamp()) $out->make_wrong_resp("Ошибка: Конец периода задан раньше, чем его начало или они заданы в одно и то же время");

    //Создаваемый период не должен пересекаться с другими периодами на этот день, получение периодов из БД
    $stmt = $pdo->prepare("
        SELECT `period_start` AS `start`, `period_end` AS `end`
        FROM `managers_job_time_periods`
        WHERE `day_id` = :dayId;
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (5)");
    $stmt->execute([
        'dayId' => $in->dayId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
    $periods = [];
    while($period = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $periods[] = [
            'start' => strtotime($period['start']),
            'end' => strtotime($period['end'])
        ];
    }
    $stmt->closeCursor(); unset($stmt);

    if(!empty($periods)) { //Пропускаем, если периодов ещё нет
        //Проверка периодов. Сравнивается метка времени начала одного из периодов из БД с началом и концом создаваемого периода, потом начало создаваемого периода с началом и концом взятого из БД периода, и так с каждым периодом из БД.
        //Таким образом, все пересекающиеся периоды вызовут ошибку
        $createStart = $startTime->getTimestamp();
        $createEnd = $endTime->getTimestamp();
        foreach ($periods as $period) {
            if ($period['start'] >= $createStart && $period['start'] <= $createEnd || $createStart >= $period['start'] && $createStart <= $period['end']) $out->make_wrong_resp("Ошибка: Создаваемый период пересекается с существующими");
        }
    }

    //Создание периода
    $stmt = $pdo->prepare("
        INSERT INTO `managers_job_time_periods`
        (`id`, `day_id`, `period_start`, `period_end`)
        VALUES (NULL, :dayId, :periodStart, :periodEnd)
    ") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (6)");
    $stmt->execute([
        'dayId' => $in->dayId,
        'periodStart' => $in->periodStart,
        'periodEnd' => $in->periodEnd
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');
    $stmt->closeCursor(); unset($stmt);
    // //Получение последнего созданного ID
    // $periodId = $pdo->lastInsertId();
    // if(!$periodId) $out->make_wrong_resp('Произошла ошибка при создании периода');
    // $in->periodId = $pdo->lastInsertId();
}

//---Формирование ответа
//Получение данных о существующих периодах после создания/удаления
$stmt = $pdo->prepare("
    SELECT `id`, `day_id`, `period_start`, `period_end`
    FROM `managers_job_time_periods`
    WHERE `day_id` = :dayId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (7)");
$stmt->execute([
    'dayId' => $in->dayId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (7)');
$periods = [];
    while($period = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $periods[$period['day_id']][] = [
                'periodId' => (string) $period['id'],
                'periodStart' => (string) $period['period_start'],
                'periodEnd' => (string) $period['period_end']
        ];
    }
$stmt->closeCursor(); unset($stmt);


$out->periodsTimes = $periods;
$out->success = "1";
$out->make_resp('');