<?php // Сохранение проверки, окончание проверки, возврат на доработку, отклонение работы ученика

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/add_task_sending_to_vk.inc.php';

// ------------------------- Класс запроса -------------------------
class HomeTaskCuratorReview extends MainRequestClass
{
    /*
     * Действие с заданием, обязательное поле. Может иметь следующие значения:
     * saveChecking   - сохранение проверки
     * endChecking    - окончание проверки
     * reject         - отклонение дз
     * */
    public $action = '';
    public $dzNum = ''; // Номер дз, обязательное поле
    public $studentId = ''; // ВК ID ученика, обязательное поле
    public $jsonData = ''; // Данные в json, обязательное поле
}

$in = new HomeTaskCuratorReview();
$in->from_json(file_get_contents('php://input'));

// ------------------------- Класс ответа -------------------------
$out = new MainResponseClass();

// ------------------------- Подключение к БД -------------------------
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE_SOCEGE . ";charset=" . DB_CHARSET, DB_USER, DB_PASSWORD, DB_SSL_FLAG === MYSQLI_CLIENT_SSL ? [
        PDO::MYSQL_ATTR_SSL_CA => DB_SSL_CA,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

// Создание подключения к БД для ВК бота и обновления рейтинга ученика
$mysqli = mysqli_init();
$mysqli->real_connect($host, $user, $password, $database, NULL, NULL, $ssl_flag) or $out->make_wrong_resp("can\'t connect DB");

// ------------------------- Проверка доступа -------------------------
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Куратор', 'Админ'])) $out->make_wrong_resp('Нет прав');

// Проверка доступа запросившего человека к редактированию ДЗ
accessCheck($user_type, $user_vk_id, $in, $out, $pdo);

// ------------------------- Валидация -------------------------
// Валидация $in->action
if (!in_array($in->action, ['saveChecking', 'endChecking', 'workOnMistakes', 'reject'])) {
    $out->make_wrong_resp('Параметр {action} задан некорректно или отсутствует');
}

// Валидация и приведение $in->dzNum к числу
if (((string)(int)$in->dzNum) !== ((string)$in->dzNum) || (int)$in->dzNum < 0) {
    $out->make_wrong_resp('Параметр {dzNum} задан некорректно или отсутствует');
} else {
    $in->dzNum = (int)$in->dzNum;
}

// Валидация и приведение $in->userId к числу
if (((string)(int)$in->studentId) !== ((string)$in->studentId) || (int)$in->studentId < 0) {
    $out->make_wrong_resp('Параметр {studentId} задан некорректно или отсутствует');
} else {
    $in->studentId = (int)$in->studentId;
}

// Валидация и десериализация $in->jsonData
if (empty($in->jsonData)) {
    $out->make_wrong_resp('Параметр {jsonData} отсутствует');
}

$curAnswer = json_decode($in->jsonData, true);
if (!is_array($curAnswer)) {
    $out->make_wrong_resp('Параметр {jsonData} задан некорректно');
}

// ------------------------- Проверка соответствия количества заданий -------------------------
checkingNumberOfTasks($curAnswer, $in, $out, $pdo);

// ------------------------- Вызов функции соответствующей полю action -------------------------
switch ($in->action) {
    case 'saveChecking':
        saveChecking($user_vk_id, $curAnswer, $in, $out, $pdo);
        break;
    case 'endChecking':
        endChecking($user_vk_id, $curAnswer, $in, $out, $pdo, $mysqli);
        break;
    case 'reject':
        reject($curAnswer, $in, $out, $pdo, $mysqli);
        break;
}

// ------------------------- Ответ -------------------------
$out->success = '1';
$out->make_resp('');

// ------------------------- Функции для методов -------------------------

/**
 * Функция выполняющая проверку доступа пользователя к методу
 * @param string $userType Тип пользователя (куратор / админ)
 * @param int $userVkId ВК айди пользователя (куратора)
 * @param HomeTaskCuratorReview $in Класс запроса, используются поля action, studentId
 * @param MainResponseClass $out Класс ответа, используется для возврата ошибок
 * @param PDO $pdo PDO объект для запросов к БД
 * @return void
 */
function accessCheck(string $userType, int $userVkId, HomeTaskCuratorReview $in, MainResponseClass $out, PDO $pdo): void
{
    if ($userType != 'Админ') {
        // Подготовка запроса для проверки пользователя
        $stmt = $pdo->prepare("
            SELECT `users`.`user_curator`, `users`.`user_curator_dz`
            FROM `users` 
            WHERE `user_vk_id`= :student_id;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса для проверки пользователя');

        // Выполнение запроса для проверки пользователя
        $stmt->execute(['student_id' => $in->studentId])
        or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса для проверки пользователя');

        $stmt->rowCount() != 0 or $out->make_wrong_resp('Нет доступа (1)');

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Проверка условий доступа
        if ($user['user_curator_dz'] != '' && $user['user_curator_dz'] != '0' && $user['user_curator_dz'] != $userVkId)
            $out->make_wrong_resp('Нет доступа (2)');

        if (($user['user_curator_dz'] == '' || $user['user_curator_dz'] == '0') && $user['user_curator'] != $userVkId)
            $out->make_wrong_resp('Нет доступа (3)');

        $stmt->closeCursor();
        unset($stmt);
    }
}

/**
 * Функция выполняющая проверку соответствия количества вопросов
 * @param array $curAnswer Десериализованный JSON запроса в виде ассоциативного массива
 * @param HomeTaskCuratorReview $in Класс запроса, используются поля studentId, dzNum
 * @param MainResponseClass $out Класс ответа, используется для возврата ошибок
 * @param PDO $pdo PDO объект для запросов к БД
 * @return void
 */
function checkingNumberOfTasks(array $curAnswer, HomeTaskCuratorReview $in, MainResponseClass $out, PDO $pdo): void
{
    // Подготовка запроса для проверки количества вопросов
    $stmt = $pdo->prepare("
        SELECT `q_nums_p2`
        FROM `ht_user` 
        WHERE `user_id` = :student_id AND `ht_number` = :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса для проверки количества вопросов');

    // Выполнение запроса для проверки количества вопросов
    $stmt->execute(['student_id' => $in->studentId, 'dz_num' => $in->dzNum])
    or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса для проверки количества вопросов');

    $stmt->rowCount() != 0 or $out->make_wrong_resp('Количество вопросов по заданным параметрам отсутствует');

    $homeTask = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($homeTask['q_nums_p2'] != count($curAnswer)) $out->make_wrong_resp('Не соответствует количество вопросов');

    $stmt->closeCursor();
    unset($stmt);
}

/**
 * Функция выполняющая обновление ДЗ
 * @param int $qNumsP2 Количество вопросов в ДЗ
 * @param array $curAnswer Десериализованный JSON запроса в виде ассоциативного массива
 * @param HomeTaskCuratorReview $in Класс запроса, используются поля studentId, dzNum
 * @param MainResponseClass $out Класс ответа, используется для возврата ошибок
 * @param PDO $pdo PDO объект для запросов к БД
 * @return array Ассоциативный массив содержащий элементы:
 * query - выполненный запрос,
 * htUserBallovP2 - сумма баллов ученика
 */
function updateHomeTask(int $qNumsP2, array $curAnswer, HomeTaskCuratorReview $in, MainResponseClass $out, PDO $pdo): array
{
    // Создание запроса на обновление ДЗ
    $query = "INSERT INTO `ht_user_p2` (`user_id`, `ht_number`, `q_number`, `teacher_comment`, `teacher_json`, `user_ball`) VALUES ";
    $htUserBallovP2 = 0;

    for ($i = 1; $i <= $qNumsP2; $i++) {
        // Преобразование данных для текущей итерации
        $teacherComment = $curAnswer[$i]['cur_comment'];
        $userBall = $curAnswer[$i]['ballov'] === '' ? null : (int)$curAnswer[$i]['ballov'];
        $teacherJson = json_encode($curAnswer[$i]['add_comments'], JSON_UNESCAPED_UNICODE);

        // Конкатенация запроса
        $query .= "(" . $in->studentId . ", " . $in->dzNum . ", " . $i . ", " . $pdo->quote($teacherComment) . ", " . $pdo->quote($teacherJson) . ", " . $userBall . "),";

        // Суммирование баллов
        $htUserBallovP2 += (int)$curAnswer[$i]['ballov'];
    }

    // Удаление последней запятой
    $query = substr($query, 0, -1);
    $query .= " ON DUPLICATE KEY UPDATE `teacher_comment`=VALUES(`teacher_comment`), `teacher_json`=VALUES(`teacher_json`), `user_ball`=VALUES(`user_ball`);";

    // Выполнение запроса на обновление ДЗ
    $stmt = $pdo->query($query) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса на обновление ДЗ');

    $stmt->closeCursor();
    unset($stmt);

    return ['query' => $query, 'htUserBallovP2' => $htUserBallovP2];
}

/**
 * Функция возвращающая количество вопросов в ДЗ
 * @param HomeTaskCuratorReview $in Класс запроса, используются поля studentId, dzNum
 * @param MainResponseClass $out Класс ответа, используется для возврата ошибок
 * @param PDO $pdo PDO объект для запросов к БД
 * @return int Количество вопросов в ДЗ
 */
function getQuantityOfQuestions(HomeTaskCuratorReview $in, MainResponseClass $out, PDO $pdo): int
{
    // Подготовка запроса на получение количества вопросов
    $stmt = $pdo->prepare("
        SELECT `q_nums_p2`
        FROM `ht_user` 
        WHERE `user_id` = :student_id
        AND `ht_number` = :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса на получение количества вопросов');

    // Выполнение запроса на получение количества вопросов
    $stmt->execute([
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса на получение количества вопросов');

    $stmt->rowCount() != 0 or $out->make_wrong_resp('Количество вопросов по заданным параметрам отсутствует');

    $homeTask = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();
    unset($stmt);

    return (int)$homeTask['q_nums_p2'];
}

// ------------------------- Методы -------------------------
/**
 * Метод сохранения проверки
 * @param int $userVkId ВК айди пользователя (куратора)
 * @param array $curAnswer Десериализованный JSON запроса в виде ассоциативного массива
 * @param HomeTaskCuratorReview $in Класс запроса
 * @param MainResponseClass $out Класс ответа
 * @param PDO $pdo PDO объект для запросов к БД
 * @return void
 */
function saveChecking(int $userVkId, array $curAnswer, HomeTaskCuratorReview $in, MainResponseClass $out, PDO $pdo): void
{
    // Получение количества заданий
    $qNumsP2 = getQuantityOfQuestions($in, $out, $pdo);

    // Обновление ДЗ
    $homeTask = updateHomeTask($qNumsP2, $curAnswer, $in, $out, $pdo);
    $query = $homeTask['query'];
    $htUserBallovP2 = $homeTask['htUserBallovP2'];

    // ------------------------- Обновление баллов ученика -------------------------
    // Подготовка запроса на обновление баллов ученика
    $stmt = $pdo->prepare("
        UPDATE `ht_user` 
        SET `ht_user_ballov_p2` = :ht_user_ballov_p2 
        WHERE `user_id` = :student_id 
        AND `ht_number` = :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса сохранения проверки (1)');

    // Выполнение запроса на обновление баллов ученика
    $stmt->execute([
        'ht_user_ballov_p2' => $htUserBallovP2,
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса сохранения проверки (1)');

    $stmt->closeCursor();
    unset($stmt);

    // ------------------------- Сохранение запроса в логи -------------------------
    $file_name = $_SERVER['DOCUMENT_ROOT'] . '/user_logs/' . $userVkId . '.csv';
    $content = $query . "\n";
    file_put_contents($file_name, $content, FILE_APPEND);
}

/**
 * Метод окончания проверки
 * @param int $userVkId ВК айди пользователя (куратора)
 * @param array $curAnswer Десериализованный JSON запроса в виде ассоциативного массива
 * @param HomeTaskCuratorReview $in Класс запроса
 * @param MainResponseClass $out Класс ответа
 * @param PDO $pdo PDO объект для запросов к БД
 * @param mysqli $mysqli mysqli объект для запросов к БД
 * @return void
 */
function endChecking(int $userVkId, array $curAnswer, HomeTaskCuratorReview $in, MainResponseClass $out, PDO $pdo, mysqli $mysqli): void
{
    // Получение количества заданий
    $qNumsP2 = getQuantityOfQuestions($in, $out, $pdo);

    // Обновление ДЗ
    $homeTask = updateHomeTask($qNumsP2, $curAnswer, $in, $out, $pdo);
    $query = $homeTask['query'];
    $htUserBallovP2 = $homeTask['htUserBallovP2'];

    // ------------------------- Получение тарифа ученика -------------------------
    // Подготовка запроса на получение тарифа ученика
    $stmt = $pdo->prepare("
        SELECT `users`.`user_tarif_num` 
        FROM `users` 
        WHERE `user_vk_id`= :student_id;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (1)');

    // Выполнение запроса на получение тарифа ученика
    $stmt->execute(['student_id' => $in->studentId])
    or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (1)');

    $stmt->rowCount() != 0 or $out->make_wrong_resp('Тариф ученика с заданными параметрами отсутствует');

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userTarifNum = $user['user_tarif_num'];

    // ------------------------- Обновление ДЗ, если это не работа над ошибками -------------------------
    // Подготовка запроса на обновление ДЗ, включая тариф
    $stmt = $pdo->prepare("
        UPDATE `ht_user` 
        SET `ht_user_checker`= :user_checker, 
            `ht_user_tarif_num`= :user_tarif_num, 
            `ht_user_check_date` = CURDATE(), 
            `ht_user_status_p2` = 'Проверен', 
            `ht_user_ballov_p2`= :ht_user_ballov_p2  
        WHERE `user_id`= :student_id
        AND `ht_number`= :dz_num
        AND `mistake_work_p2` = 0;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (2)');

    // Выполнение запроса на обновление ДЗ, включая тариф
    $stmt->execute([
        'user_checker' => $userVkId,
        'user_tarif_num' => $userTarifNum,
        'ht_user_ballov_p2' => $htUserBallovP2,
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (2)');

    // ------------------------- Если работа над ошибками не обновляем тариф -------------------------
    $queryUpdate = "
        UPDATE `ht_user` 
        SET `ht_user_checker_mistwork` = '" . $userVkId . "', 
            `ht_user_check_date_mistwork` = CURDATE(), 
            `ht_user_status_p2` = 'Проверен', 
            `ht_user_ballov_p2` = '" . $htUserBallovP2 . "' 
        WHERE `user_id` = '" . $in->studentId . "' 
        AND `ht_number` = '" . $in->dzNum . "' 
        AND `mistake_work_p2` > 0;
    ";

    // Выполнение запроса на обновление ДЗ, не включая тариф
    $pdo->query($queryUpdate)
    or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (3)');

    // ------------------------- ВК бот -------------------------
    // Отправка информации о возврате на доработку ученику через ВК бота
    $message = "Вторая часть твоего ДЗ №" . $in->dzNum . " проверена💙 Результат на платформе https://насотку.рф/dz2_student.php?dz_num=" . $in->dzNum;
    addTaskSendingToVk($mysqli, [$in->studentId], $message);

    // ------------------------- Обновление рейтинга ученика при проверке куратором -------------------------
    if ($userTarifNum == '2' || $userTarifNum == '3')
        update_student_rating($mysqli, $in->studentId, $htUserBallovP2, 'Д2_пр', 'ДЗ №' . $in->dzNum); // (vk_id, баллов, тип, коммент)

    // ------------------------- Сохранение запроса в логи -------------------------
    $file_name = $_SERVER['DOCUMENT_ROOT'] . '/user_logs/' . $userVkId . '.csv';
    $content = $queryUpdate . "\n" . $query . "\n";
    file_put_contents($file_name, $content, FILE_APPEND);

    // ------------------------- Проверяем назначена ли перекрестная проверка и проверял ли ее куратор -------------------------
    // Подготовка запроса на получение айди проверяющего
    $stmt = $pdo->prepare("
        SELECT `checker_id` 
        FROM `cross_check` 
        WHERE `curator_vk_id`= :curator_vk_id
        AND `bot_notify` = 0 
        AND `cc_check_date` IS NULL 
        AND `ht_num`= :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (4)');

    // Выполнение запроса на получение айди проверяющего
    $stmt->execute([
        'curator_vk_id' => $userVkId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (4)');

    $crossCheck = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($crossCheck['checker_id'] != '') {
        // Отправка информации о возврате на доработку ученику через ВК бота
        $message = "Появилась перекрёстная по ДЗ №" . $in->dzNum;
        addTaskSendingToVk($mysqli, [$crossCheck['checker_id']], $message);

        // Подготовка запроса на обновление уведомлений бота о перекрестной проверке
        $stmt = $pdo->prepare("
            UPDATE `cross_check` 
            SET `bot_notify` = 1 
            WHERE `curator_vk_id` = :curator_vk_id 
            AND `ht_num` = :dz_num;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (5)');

        // Выполнение запроса на обновление уведомлений бота о перекрестной проверке
        $stmt->execute([
            'curator_vk_id' => $userVkId,
            'dz_num' => $in->dzNum,
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (5)');
    }

    $stmt->closeCursor();
    unset($stmt);
}

/**
 * Метод отклонения ДЗ
 * @param array $curAnswer Десериализованный JSON запроса в виде ассоциативного массива
 * @param HomeTaskCuratorReview $in Класс запроса
 * @param MainResponseClass $out Класс ответа
 * @param PDO $pdo PDO объект для запросов к БД
 * @param mysqli $mysqli mysqli объект для запросов к БД
 * @return void
 */
function reject(array $curAnswer, HomeTaskCuratorReview $in, MainResponseClass $out, PDO $pdo, mysqli $mysqli): void
{
    // Получение количества заданий
    $qNumsP2 = getQuantityOfQuestions($in, $out, $pdo);

    // Обновление ДЗ
    $homeTask = updateHomeTask($qNumsP2, $curAnswer, $in, $out, $pdo);
    $htUserBallovP2 = $homeTask['htUserBallovP2'];

    // ------------------------- Обновление статуса и баллов -------------------------
    // Подготовка запроса на обновление статуса и баллов
    $stmt = $pdo->prepare("
        UPDATE `ht_user` 
        SET `ht_user_status_p2`= 'Отклонен', 
            `ht_user_ballov_p2`= :ht_user_ballov_p2 
        WHERE `user_id`= :student_id AND `ht_number`= :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса отклонения дз (1)');

    // Выполнение запроса на обновление статуса и баллов
    $stmt->execute([
        'ht_user_ballov_p2' => $htUserBallovP2,
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса отклонения дз (1)');

    $stmt->closeCursor();
    unset($stmt);

    // ------------------------- ВК бот -------------------------
    $message = sprintf("Вторая часть твоего ДЗ №%d отклонена. Если не знаешь, почему - обратись к куратору. Ты можешь самостоятельно проверить ответы на платформе https://насотку.рф/dz2_student.php?dz_num=%d", $in->dzNum, $in->dzNum);
    // Отправка информации об отклонении дз ученику через ВК бота
    addTaskSendingToVk($mysqli, [$in->studentId], $message);
}
