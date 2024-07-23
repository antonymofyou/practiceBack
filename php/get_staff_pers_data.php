<?php // [Менеджер] Получение сотрудника по ID

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// класс запроса
class WorkersGetWorkerById extends MainRequestClass {
    public $workerId = ''; // ID работника, которого нужно получить
}
$in = new WorkersGetWorkerById();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class WorkersGetWorkerByIdResponse extends MainResponseClass {
    /*
     * Массив словарей, где каждый словарь имеет следующие поля:
     *     - id
     *     - vkId
     *     - type
     *     - firstName
     *     - lastName
     *     - middleName
     *     - blocked
     */
    public $workerPersonalData = []; // данные вакансии
}
$out = new WorkersGetWorkerByIdResponse();

