<?php //---Получение отчёта

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class JobReportsGetReport extends MainRequestClass {
    public $managerId = ''; //Идентификатор сотрудника, отчёт которого надо получить
    public $forDate = ''; //Дата, за которую нужно получить отчёт
}
$in = new JobReportsGetReport();
$in->from_json(file_get_contents('php://input'));


class JobReportsGetReportResponse extends MainResponseClass {
    /* Словарь со следующими полями:
    - reportId    - Идентификатор отчёта
    - managerId   - Идентификатор сотрудника, которому принадлежит отчёт
    - forDate     - Дата, за которую написан отчёт
    - workTime    - Всего отработано времени за день отчёта
    - report      - Отчёт
    - createdAt   - Дата и время создания отчёта
    - updatedAt   - Дата и время последнего обновления отчёта
    */
    public $report = []; //Словарь возвращаемого отчёта
}
$out = new JobReportsGetReportResponse();

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

//---Проверка пользователя: Если передан id, то проверяем на админа, иначе считаем id авторизованного пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/manager_check_user.inc.php';
if(!empty($in->managerId)) {
    if (!in_array($user['type'], ['Админ'])) $out->make_wrong_resp('Ошибка доступа');
} else $in->managerId = $user['id'];

//---Запрос на получение данных для вывода
if (((string) (int) $in->managerId) !== ((string) $in->managerId) || (int) $in->managerId <= 0) $out->make_wrong_resp("Параметр 'managerId' задан неверно");
if (!is_string($in->forDate) || empty($in->forDate)) $out->make_wrong_resp("Параметр 'forDate' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `id`, `manager_id`, `for_date`, `work_time`, `report`, `created_at`, `updated_at`
    FROM `managers_job_reports`
    WHERE `manager_id` = :managerId AND `for_date` = :forDate;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса');
$stmt->execute([
    'managerId' => $in->managerId,
    'forDate' => $in->forDate
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса');
if($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Не найден отчёт за $in->forDate");
$report = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//---Формирование ответа
$out->report = [
    'reportId' => (string) $report['id'],
    'managerId' => (string) $report['manager_id'],
    'forDate' => (string) $report['for_date'],
    'workTime' => (string) $report['work_time'],
    'report' => (string) $report['report'],
    'createdAt' => (string) $report['created_at'],
    'updatedAt' => (string) $report['updated_at']
];

$out->success = "1";
$out->make_resp('');