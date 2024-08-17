<?php //Получаем данные отчёта

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

class JobReportsGetReport extends MainRequestClass {
    public $reportId = ''; //Идентификатор отчёта для удаления или обновления
    public $managerId = ''; //Идентификатор сотрудника, отчёт которого надо создать
    public $forDate = ''; //Дата, за которую нужно создать отчёт
    public $report = ''; //Отчёт
    public $action = ''; //Тип действия: create - создать отчёт, delete - удалить отчёт, update - обновить отчёт
}
$in = new JobReportsGetReport();
$in->from_json(file_get_contents('php://input'));


class JobReportsGetReportResponse extends MainResponseClass {
    /* Словарь со следующими полями:
    - reportId          - Идентификатор отчёта
    - managerId   - Идентификатор сотрудника, которому принадлежит отчёт
    - forDate     - Дата, за которую написан отчёт
    - workTime    - Всего отработано времени за день отчёта
    - report      - Отчёт
    - createdAt   - Дата и время создания отчёта
    - updatedAt   - Дата и время последнего обновления отчёта
    */
    public $report = []; //Словарь созданного или обновлённого отчёта, в случае удаления возращается пустой словарь
}
$out = new JobReportsGetReportResponse();

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

//Проверка пользователя: Если передан id, то проверяем на админа, иначе считаем id авторизованного пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if(!empty($in->managerId)) {
    if (!in_array($user_type, ['Админ'])) $out->make_wrong_resp('Ошибка доступа'); 
} else $in->managerId = $user['id'];

$today = date("Y-m-d H:i:s"); //Узнаём текущее датувремя

//Валидация действия
if(!in_array($action, ['create', 'delete', 'update'])) $out->make_wrong_resp('Неверное действие');

if($action == 'delete') { //Удаляем отчёт
    
    //Валидируем id
    $stmt = $pdo->prepare("
    SELECT `id`
    FROM `managers_job_reports`
    WHERE `id` = :reportId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'reportId' => $in->reportId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Отчёт не найден");
        $stmt->closeCursor(); unset($stmt);

    //Удаляем отчёт
    $stmt = $pdo->prepare("
    DELETE FROM `managers_job_reports`
    WHERE `id` = :reportId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
    $stmt->execute([
        'reportId' => $in->reportId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
    $stmt->closeCursor(); unset($stmt);
}