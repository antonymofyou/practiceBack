<?php // [Менеджер] Обновление данных работника

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// класс запроса
class WorkersUpdateWorker extends MainRequestClass {
    public $workerId = ''; // идентификатор работника, которого нужно обновить (обяз.)

    /*
     * Словарь, который имеет следующие поля:
     *     - name - новое название вакансии (не обяз.)
     *     - description - новое описание вакансии (не обяз.)
     *     - published - новый статус публикации вакансии (0/1) (не обяз.)
     */
    public $worker = []; // данные работника, которые нужно обновить
}

$in = new WorkersUpdateWorker();
$in->from_json(file_get_contents('php://input'));

// класс ответа
$out = new MainResponseClass();
