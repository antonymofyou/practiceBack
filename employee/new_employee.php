<?php

/*
 * [Сотрудники] Добавление нового сотрудника
 */

header('Content-Type: application/json; charset=utf-8');

// Импортируем класс для работы с СУБД
require_once __DIR__ . '/../database.class.php';

// Получаем и декодируем в ассоциативный массив клиентский JSON ввод
$requestData = json_decode(file_get_contents('php://input'), true);

// Проверяем, все ли обязательные параметры были переданы
if (!isset(
    $requestData['firstName'],
    $requestData['lastName'],
    $requestData['middleName'],
    $requestData['type'],
    $requestData['vkId'])
) {
    die(json_encode(array(
        'state' => false,
        'code' => 'MISSING_REQUIRED_PARAMS',
        'humanReadable' => "Обязательные параметры не были переданы."
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}


// ... Проверка ключа доступа API (до реализации) ...
$accessGranted = true;

if (!$accessGranted) {
    die(json_encode(array(
        'state' => false,
        'code' => 'INVALID_API_KEY',
        'humanReadable' => "Ключ доступа API недействителен."
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}


// Добавление в таблицу нового сотрудника
$db = new Database();

$dbAnswer = $db->single_query('INSERT INTO `staff`
(`type`, `first_name`, `last_name`, `middle_name`, `vk_id`, `blocked`)
VALUES (:type, :first_name, :last_name, :middle_name, :vk_id, :blocked)',
    array(
        'type' => $requestData['type'],
        'first_name' => $requestData['firstName'],
        'last_name' => $requestData['lastName'],
        'middle_name' => $requestData['middleName'],
        'vk_id' => $requestData['vkId'],
        'blocked' => 0
    ));

$employeeId = $dbAnswer[1];


// Получение только что созданного сотрудника
$newEmployee = ($db->single_query('SELECT `type`, `first_name`, `last_name`, `middle_name`, `vk_id`, `blocked` FROM `staff` WHERE `id` = :employee_id',
    array(
        'employee_id' => $employeeId
    )))[0];


// Вывод ответа
echo json_encode(array(
    'state' => true,
    'code' => 'NEW_EMPLOYEE_CREATED',
    'humanReadable' => 'Новый сотрудник был успешно добавлен.',
    'employee' => array(
        'id' => $employeeId,
        'type' => $newEmployee['type'],
        'lastName' => $newEmployee['last_name'],
        'firstName' => $newEmployee['first_name'],
        'middleName' => $newEmployee['middle_name'],
        'vkId' => $newEmployee['vk_id'],
        'is_blocked' => $newEmployee['blocked']
    )
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
