<?php // [Менеджер] Сортировка вопросов вакансии

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/root_classes.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/check_permission.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['API_DEV_PATH_HR'] ?? '') . '/app/api/includes/manager_check_user.inc.php';

// Класс запроса
class VacanciesSortVacancyQuestion extends MainRequestClass
{
    public $vacancyId = ''; // ID вакансии, к которой относятся вопросы
    public $questionId = ''; // ID вопроса, который нужно переместить
    /*
     * Направление сортировки, на сколько позиций нужно переместить вверх или вниз
     * (положительное число - увеличивает значение сортировки, опуская статус вниз,
     * отрицательное - уменьшает значение сортировки, поднимая вопрос вверх;
     * в случае нуля будет ошибка)
     * (сортировка вопросов при выдаче происходит от меньшего значения к большему)
     */
    public $direction = '';
}

$in = new VacanciesSortVacancyQuestion();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
$out = new MainResponseClass();

// Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_HR . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

// Проверка доступа
if ($user['type'] != 'Админ' && !checkManagerVacancyPermission($pdo, $out, $user['id'], 'VACANCY_PERMISSION', $in->vacancyId)) {
    $out->make_wrong_resp('Отсутствует доступ к этой вакансии');
}

// Валидация $in->vacancyId
if (((string)(int)$in->vacancyId) !== ((string)$in->vacancyId) || (int)$in->vacancyId <= 0)
    $out->make_wrong_resp("Параметр 'vacancyId' задан некорректно или отсутствует");

// Валидация $in->questionId
if (((string)(int)$in->questionId) !== ((string)$in->questionId) || (int)$in->questionId <= 0)
    $out->make_wrong_resp("Параметр 'questionId' задан некорректно или отсутствует");

// Валидация $in->direction
if (((string)(int)$in->direction) !== ((string)$in->direction) || (int)$in->direction == 0)
    $out->make_wrong_resp("Параметр 'direction' задан некорректно");

// Подготовка запроса для проверки наличия вакансии с ID = vacancyId
$stmt = $pdo->prepare("
    SELECT `id`
    FROM `vacancies`
    WHERE `id` = :vacancyId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');

// Выполнение запроса для проверки наличия вакансии с ID = vacancyId
$stmt->execute(['vacancyId' => $in->vacancyId]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');

// Проверка наличия вакансии
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Вакансия с ID {$in->vacancyId} не найдена");

$stmt->closeCursor();
unset($stmt);

// Подготовка запроса для получения вопроса
$stmt = $pdo->prepare("
    SELECT `sort`
    FROM `vacancy_questions`
    WHERE `vacancy_id` = :vacancyId
    AND `id` = :questionId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');

// Выполнение запроса для получения вопроса
$stmt->execute([
    'vacancyId' => $in->vacancyId,
    'questionId' => $in->questionId,
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');

// Проверка наличия вопроса
$stmt->rowCount() != 0 or $out->make_wrong_resp("Вопрос {$in->questionId} у вакансии {$in->vacancyId} не найден");

/*
 * Словарь, содержащий поле:
 * sort - Значение сортировки
 */
$question = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt->closeCursor();
unset($stmt);

// Подготовка запроса для проверки, того что сортировка не имеет максимального или минимального значения
$stmt = $pdo->prepare("
    SELECT MIN(`sort`) AS `min_sort`, MAX(`sort`) AS `max_sort`
    FROM `vacancy_questions`
    WHERE `vacancy_id` = :vacancyId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (3)');

// Выполнение запроса для проверки, того что сортировка не имеет максимального или минимального значения
$stmt->execute(['vacancyId' => $in->vacancyId]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (3)');

/*
 * Словарь, содержащий поля:
 * min_sort - Минимальное значение sort
 * max_sort - Максимальное значение sort
 */
$sort = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt->closeCursor();
unset($stmt);

// Проверяем, что сортировка не имеет максимального или минимального значения
$isMaxSort = ($sort['min_sort'] == $question['sort'] && $in->direction < 0); // Вопрос уже имеет минимальное значение сортировки, при этом клиент хочет его еще уменьшить
$isMinSort = ($sort['max_sort'] == $question['sort'] && $in->direction > 0); // Вопрос уже имеет максимальное значение сортировки, при этом клиент хочет его еще увеличить

// Если сортировка уже максимальна/минимальна - отдаем успешный ответ
if ($isMaxSort || $isMinSort) {
    $out->success = '1';
    $out->make_resp('');
}

// Ограничение минимального и максимального значения sort (чтобы он не отличался больше чем на единицу)
$finalSort = $question['sort'] + $in->direction; // Получаем значение финальной сортировки

// Если финальная сортировка будет меньше минимальной, то устанавливаем такой direction, чтобы значение сортировки не ушло ниже минимального порога
if ($finalSort < $sort['min_sort']) {
    $in->direction = -($question['sort'] - $sort['min_sort']); // Результат со знаком минус, т.к. в этом случае мы должны уменьшить sort
}

// Если финальная сортировка будет больше максимальной, то устанавливаем такой direction, чтобы значение сортировки не ушло выше максимального порога
if ($finalSort > $sort['max_sort']) {
    $in->direction = $sort['max_sort'] - $question['sort']; // Результат оставляем положительным, т.к. в этом случае мы должны увеличить sort
}

$finalSort = $question['sort'] + $in->direction; // Пересчитываем значение финальной сортировки после изменений в условиях (если они произошли)

/*
 * В запросе ниже объеденины два запроса на UPDATE.
 * Первый WHEN: обновление у текущего вопроса поля sort на новое значение
 * Второй WHEN: обновление у других вопросов поля sort на +1 или -1 в определенном диапазоне
 */
$query = ''; // Запрос

// Если нужно уменьшить значение sort, то есть поднять вопрос вверх, то делаем +1 всем sort, что находятся в диапазоне меньше sort текущего вопроса и sort большим/равным конечному sort для текущего вопроса
if ($in->direction < 0) {
    $query = "
        UPDATE `vacancy_questions`
        SET `sort` = (
            CASE
                WHEN `id` = :questionId THEN :finalSort
                WHEN `sort` < :currentSort AND `sort` >= :finalSort THEN `sort` + 1
                ELSE `sort`
            END
        )
        WHERE `vacancy_id` = :vacancyId;
    ";
}

// Если нужно увеличить значение sort, то есть опустить статус вниз, то делаем -1 всем sort, что находятся в диапазоне больше sort текущего вопроса и sort меньшим/равным конечному sort для текущего вопроса
if ($in->direction > 0) {
    $query = "
        UPDATE `vacancy_questions`
        SET `sort` = (
            CASE
                WHEN `id` = :questionId THEN :finalSort
                WHEN `sort` > :currentSort AND `sort` <= :finalSort THEN `sort` - 1
                ELSE `sort`
            END
        )
        WHERE `vacancy_id` = :vacancyId;
    ";
}

// Подготовка запроса для изменения значения сортировки
$stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');

// Выполнение запроса для изменения значения сортировки
$stmt->execute([
    'currentSort' => $question['sort'], // Текущее значение sort выбранного вопроса для сортировки
    'finalSort' => $finalSort, // Конечное значение sort для выбранного вопроса
    'vacancyId' => $in->vacancyId,
    'questionId' => $in->questionId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
$stmt->closeCursor();
unset($stmt);

// Ответ
$out->success = '1';
$out->make_resp('');
