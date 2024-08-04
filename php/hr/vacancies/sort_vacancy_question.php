<?php
/*
 * [Менеджер] Сортировка статуса вакансии
 */

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/root_classes.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/check_permission.inc.php';

// класс запроса
class VacanciesSortVacancyStatus extends MainRequestClass {
    public $vacancyId = ''; // ID вакансии, к которой относятся оба статуса
    public $statusName = ''; // название статуса, который нужно переместить
    public $direction = ''; // направление сортировки, на сколько нужно позиций нужно переместить вверх или вниз (положительное число - увеличивает значение сортировки, опуская статус вниз, отрицательное - уменьшает значение сортировки, поднимая статус вверх; в случае нуля будет ошибка) (сортировка статусов при выдаче происходит от меньшего значения к большему)
}
$in = new VacanciesSortVacancyStatus();
$in->from_json(file_get_contents('php://input'));

// класс ответа
$out = new MainResponseClass();

//--------------------------------Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_HR . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//--------------------------------Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/manager_check_user.inc.php';

//--------------------------------Валидация $in->direction
if (((string) (int) $in->direction) !== ((string) $in->direction) || (int) $in->direction == 0) $out->make_wrong_resp("Параметр 'direction' задан некорректно");

//--------------------------------Валидация $in->vacancyId
if (((string) (int) $in->vacancyId) !== ((string) $in->vacancyId) || (int) $in->vacancyId <= 0) $out->make_wrong_resp("Параметр 'vacancyId' задан некорректно или отсутствует");

$stmt = $pdo->prepare("
    SELECT `id`
    FROM `vacancies`
    WHERE `id` = :id;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

$stmt->execute(['id' => $in->vacancyId]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

if ($stmt->rowCount() == 0) $out->make_wrong_resp("Вакансия с ID {$in->vacancyId} не найдена");

$stmt->closeCursor();
unset($stmt);

//--------------------------------Проверка доступа
if ($user['type'] != 'Админ' && !checkManagerVacancyPermission($pdo, $out, $user['id'], 'VACANCY_PERMISSION', $in->vacancyId)) {
    $out->make_wrong_resp('Отсутствует доступ к этой вакансии');
}

//--------------------------------Валидация $in->statusName
if ($in->statusName == '' || !is_string($in->statusName)) $out->make_wrong_resp("Параметр 'statusName' задан некорректно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `sort`
    FROM `vacancies_otkliki_statuses`
    WHERE `vacancy_id` = :vacancy_id
        AND `status` = :status;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'vacancy_id' => $in->vacancyId,
    'status' => $in->statusName,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Статус '{$in->statusName}' у вакансии {$in->vacancyId} не найден");
$status = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//--------------------------------Проверяем, что статус не имеет максимального или минимального значения
$stmt = $pdo->prepare("
    SELECT MIN(`sort`) AS `min_sort`, MAX(`sort`) AS `max_sort`
    FROM `vacancies_otkliki_statuses`
    WHERE `vacancy_id` = :vacancy_id;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');
$stmt->execute(['vacancy_id' => $in->vacancyId]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');
$sort = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//--------------------------------Если сортировка уже максимальна/минимальная, то ничего не делаем и отдам успешный респонс
if (
    ($sort['min_sort'] == $status['sort'] && $in->direction < 0) // статус уже имеет минимальное значение сортировки, при этом клиент хочет его еще уменьшить
    ||
    ($sort['max_sort'] == $status['sort'] && $in->direction > 0) // cтатус уже имеет максимальное значение сортировки, при этом клиент хочет его еще увеличить
) {
    $out->success = '1';
    $out->make_resp('');
}

//--------------------------------Ограничение минимального и максимального значения sort (чтобы он не отличался больше чем на единицу)
$finalSort = $status['sort'] + $in->direction; // получаем значение финальной сортировки
if ($finalSort < $sort['min_sort']) { // если финальная сортировка будет меньше минимальной, то устанавливаем такой direction, чтобы значение сортировки не ушло ниже минимального порога
    $in->direction = -($status['sort'] - $sort['min_sort']); // результат делаем со знаком минус, т.к. в этом случае мы должны уменьшить sort
} elseif ($finalSort > $sort['max_sort']) { // если финальная сортировка будет больше максимальной, то устанавливаем такой direction, чтобы значение сортировки не ушло выше максимального порога
    $in->direction = $sort['max_sort'] - $status['sort']; // результат оставляем положительным, т.к. в этом случае мы должны увеличить sort
}
$finalSort = $status['sort'] + $in->direction;

/*
 * В запросе ниже объеденины два запроса на UPDATE.
 * Первый WHEN: обновление у текущего статуса поля sort на новое значение
 * Второй WHEN: обновление у других статусов поля sort на +1 или -1 в определенном диапазоне
 */
$query = ''; // запрос
if ($in->direction < 0) { // если нужно уменьшить значение sort (то есть поднять статус вверх), то делаем +1 всем sort, что находятся в диапазоне меньше sort текущего статуса и sort большим/равным конечному sort для текущего статуса
    $query = "
        UPDATE `vacancies_otkliki_statuses`
        SET `sort` = (
            CASE
                WHEN `status` = :status THEN :finish_status_sort
                WHEN `sort` < :current_status_sort AND `sort` >= :finish_status_sort THEN `sort` + 1
                ELSE `sort`
            END
        )
        WHERE `vacancy_id` = :vacancy_id;
    ";
} elseif ($in->direction > 0) { // если нужно увеличить значение sort (то есть опустить статус вниз), то делаем -1 всем sort, что находятся в диапазоне больше sort текущего статуса и sort меньшим/равным конечному sort для текущего статуса
    $query = "
        UPDATE `vacancies_otkliki_statuses`
        SET `sort` = (
            CASE
                WHEN `status` = :status THEN :finish_status_sort
                WHEN `sort` > :current_status_sort AND `sort` <= :finish_status_sort THEN `sort` - 1
                ELSE `sort`
            END
        )
        WHERE `vacancy_id` = :vacancy_id;
    ";
}

$stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
$stmt->execute([
    'current_status_sort' => $status['sort'], // текущее значение sort выбранного статуса для сортировки
    'finish_status_sort' => $finalSort, // конечное значение sort для выбранного статуса
    'vacancy_id' => $in->vacancyId,
    'status' => $in->statusName
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
$stmt->closeCursor(); unset($stmt);

//--------------------------------Ответ
$out->success = '1';
$out->make_resp('');
