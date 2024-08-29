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

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/manager_check_user.inc.php';

//Валидация действия
if(!in_array($in->action, ['create', 'delete', 'update'])) $out->make_wrong_resp('Неверное действие');

//---Удаление периода работы
elseif($in->action == "delete") {
    
    
}