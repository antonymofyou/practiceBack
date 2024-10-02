<?php // Сохранение проверки, окончание проверки, возврат на доработку, отклонение работы ученика

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';

// ------------------------- Класс запроса -------------------------
class HomeTaskCuratorReview extends MainRequestClass
{
    public $action = ''; // Действие с заданием (saveChecking / endChecking / workOnMistakes / reject), обязательное поле
    public $dzNum = ''; // Номер дз, обязательное поле
    public $studentId = ''; // ВК ID ученика, обязательное поле
    public $jsonData = ''; // Данные в json, обязательное поле
    /*
     * Обычный возврат на доработку или возврат "на работу над ошибками"
     * (по умолчанию задаем как 0, если такого поля нет),
     * необязательное поле (необходимо для returnForRevision)
     * */
    public $toMistakeWork = '';
}

$in = new HomeTaskCuratorReview();
$in->from_json(file_get_contents('php://input'));

// ------------------------- Класс ответа -------------------------
$out = new MainResponseClass();

// ------------------------- Валидация -------------------------
// Валидация $in->action
if (!in_array($in->action, ['saveChecking', 'endChecking', 'workOnMistakes', 'reject']))
    $out->make_wrong_resp('Параметр {action} задан некорректно или отсутствует');

// Валидация и приведение $in->dzNum к числу
(((string)(int)$in->dzNum) !== ((string)$in->dzNum) || (int)$in->dzNum < 0)
    ? $out->make_wrong_resp('Параметр {dzNum} задан некорректно или отсутствует')
    : $in->dzNum = (int)$in->dzNum;

// Валидация и приведение $in->userId к числу
(((string)(int)$in->studentId) !== ((string)$in->studentId) || (int)$in->studentId < 0)
    ? $out->make_wrong_resp('Параметр {studentId} задан некорректно или отсутствует')
    : $in->studentId = (int)$in->studentId;

// Валидация, установка значения если оно отсутствует, приведение $in->toMistakeWork к числу
$in->toMistakeWork = (int)($in->toMistakeWork ?? 0);
if (!in_array($in->toMistakeWork, [0, 1])) $out->make_wrong_resp('Параметр {toMistakeWork} задан некорректно');

// ------------------------- Десериализация JSON -------------------------
$curAnswer = json_decode($in->jsonData, true);

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

// ------------------------- Проверка доступа -------------------------
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Куратор', 'Админ'])) $out->make_wrong_resp('Нет прав');

// Проверка доступа запросившего человека к редактированию ДЗ
$accessCheckMessage = AccessCheck($user_type, $user_vk_id, $in, $pdo);
if ($accessCheckMessage != '') $out->make_wrong_resp($accessCheckMessage);

// ------------------------- Проверка соответствия количества заданий -------------------------
$checkingNumberOfTasksMessage = CheckingNumberOfTasks($curAnswer, $in, $pdo);
if ($checkingNumberOfTasksMessage != '') $out->make_wrong_resp($checkingNumberOfTasksMessage);

// ------------------------- Сохранение проверки -------------------------
if ($in->action == 'saveChecking') {
    // ------------------------- Получение количества заданий -------------------------
    // Подготовка запроса на получение количества заданий
    $stmt = $pdo->prepare("
        SELECT `q_nums_p2`
        FROM `ht_user` 
        WHERE `user_id` = :student_id
        AND `ht_number` = :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса сохранения проверки (1)');

    // Выполнение запроса на получение количества заданий
    $stmt->execute([
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса сохранения проверки (1)');

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // ------------------------- Обновление ДЗ -------------------------
    // Создание запроса на обновление ДЗ
    $queryIns = "INSERT INTO `ht_user_p2` (`user_id`, `ht_number`, `q_number`, `teacher_comment`, `teacher_json`, `user_ball`) VALUES ";
    $htUserBallovP2 = 0;

    for ($i = 1; $i <= $row['q_nums_p2']; $i++) {
        // Преобразование данных для текущей итерации
        $teacherComment = $curAnswer[$i]['cur_comment'];
        $userBall = $curAnswer[$i]['ballov'] === '' ? null : (int)$curAnswer[$i]['ballov'];
        $teacherJson = json_encode($curAnswer[$i]['add_comments'], JSON_UNESCAPED_UNICODE);

        // Конкатенация запроса
        $queryIns .= "('" . $in->studentId . "', '" . $in->dzNum . "', '" . $i . "', '" . $teacherComment . "', '" . $teacherJson . "', " . $userBall . "),";

        // Суммирование баллов
        $htUserBallovP2 += (int)$curAnswer[$i]['ballov'];
    }

    // Удаление последней запятой
    $queryIns = substr($queryIns, 0, -1);
    $queryIns .= " ON DUPLICATE KEY UPDATE `teacher_comment`=VALUES(`teacher_comment`), `teacher_json`=VALUES(`teacher_json`), `user_ball`=VALUES(`user_ball`);";

    // Выполнение запроса на обновление ДЗ
    $stmt = $pdo->query($queryIns) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса сохранения проверки (2)');

    // ------------------------- Обновление баллов ученика -------------------------
    // Подготовка запроса на обновление баллов ученика
    $stmt = $pdo->prepare("
        UPDATE `ht_user` 
        SET `ht_user_ballov_p2` = :ht_user_ballov_p2 
        WHERE `user_id` = :student_id 
        AND `ht_number` = :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса сохранения проверки (3)');

    // Выполнение запроса на обновление баллов ученика
    $stmt->execute([
        'ht_user_ballov_p2' => $htUserBallovP2,
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса сохранения проверки (3)');

    $stmt->closeCursor();
    unset($stmt);

    // ------------------------- Сохранение запроса в логи -------------------------
    $file_name = $_SERVER['DOCUMENT_ROOT'] . '/user_logs/' . $user_vk_id . '.csv';
    $content = $queryIns . "\n";
    file_put_contents($file_name, $content, FILE_APPEND);
}

// ------------------------- Конец проверки -------------------------
if ($in->action == 'endChecking') {
    // ------------------------- Получение количества заданий -------------------------
    // Подготовка запроса на получение количества заданий
    $stmt = $pdo->prepare("
        SELECT `q_nums_p2`
        FROM `ht_user` 
        WHERE `user_id` = :student_id
        AND `ht_number` = :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (1)');

    // Выполнение запроса на получение количества заданий
    $stmt->execute([
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (1)');

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // ------------------------- Обновление ДЗ -------------------------
    // Создание запроса на обновление ДЗ
    $queryIns = "INSERT INTO `ht_user_p2` (`user_id`, `ht_number`, `q_number`, `teacher_comment`, `teacher_json`, `user_ball`) VALUES ";
    $htUserBallovP2 = 0;

    for ($i = 1; $i <= $row['q_nums_p2']; $i++) {
        // Преобразование данных для текущей итерации
        $teacherComment = $curAnswer[$i]['cur_comment'];
        $userBall = $curAnswer[$i]['ballov'] === '' ? null : (int)$curAnswer[$i]['ballov'];
        $teacherJson = json_encode($curAnswer[$i]['add_comments'], JSON_UNESCAPED_UNICODE);

        // Конкатенация запроса
        $queryIns .= "('" . $in->studentId . "', '" . $in->dzNum . "', '" . $i . "', '" . $teacherComment . "', '" . $teacherJson . "', " . $userBall . "),";

        // Суммирование баллов
        $htUserBallovP2 += (int)$curAnswer[$i]['ballov'];
    }

    // Удаление последней запятой
    $queryIns = substr($queryIns, 0, -1);
    $queryIns .= " ON DUPLICATE KEY UPDATE `teacher_comment`=VALUES(`teacher_comment`), `teacher_json`=VALUES(`teacher_json`), `user_ball`=VALUES(`user_ball`);";

    // Выполнение запроса на обновление ДЗ
    $stmt = $pdo->query($queryIns) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (2)');

    // ------------------------- Получение тарифа ученика -------------------------
    // Подготовка запроса на получение тарифа ученика
    $stmt = $pdo->prepare("
        SELECT `users`.`user_curator`, 
               `users`.`user_curator_dz`, 
               `users`.`user_tarif_num` 
        FROM `users` 
        WHERE `user_vk_id`= :student_id;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (3)');

    // Выполнение запроса на получение тарифа ученика
    $stmt->execute(['student_id' => $in->studentId])
    or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (3)');

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $userTarifNum = $row['user_tarif_num'];

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
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (4)');

    // Выполнение запроса на обновление ДЗ, включая тариф
    $stmt->execute([
        'user_checker' => $user_vk_id,
        'user_tarif_num' => $userTarifNum,
        'ht_user_ballov_p2' => $htUserBallovP2,
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (4)');

    // ------------------------- Если работа над ошибками не обновляем тариф -------------------------
    // Подготовка запроса на обновление ДЗ, не включая тариф
    $stmt = $pdo->prepare("
        UPDATE `ht_user` 
        SET `ht_user_checker_mistwork` = :user_checker,
            `ht_user_check_date_mistwork` = CURDATE(), 
            `ht_user_status_p2` = 'Проверен', 
            `ht_user_ballov_p2` = :ht_user_ballov_p2 
		WHERE `user_id` = :student_id 
		AND `ht_number` = :dz_num 
		AND `mistake_work_p2` > 0;
	") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (5)');

    // Выполнение запроса на обновление ДЗ, не включая тариф
    $stmt->execute([
        'user_checker' => $user_vk_id,
        'ht_user_ballov_p2' => $htUserBallovP2,
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (5)');

    // ------------------------- Создание подключения к БД для ВК бота и обновления рейтинга ученика -------------------------
    $mysqli = mysqli_init();
    $mysqli->real_connect($host, $user, $password, $database, NULL, NULL, $ssl_flag)
    or $out->make_wrong_resp("can\'t connect DB");

    // ------------------------- ВК бот -------------------------
    require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/add_task_sending_to_vk.inc.php';

    // Отправка информации о возврате на доработку ученику через ВК бота
    $message = "Вторая часть твоего ДЗ №" . $in->dzNum . " проверена💙 Результат на платформе https://насотку.рф/dz2_student.php?dz_num=" . $in->dzNum;
    addTaskSendingToVk($mysqli, [$in->studentId], $message);

    // ------------------------- Обновление рейтинга ученика при проверке куратором -------------------------
    if ($userTarifNum == '2' || $userTarifNum == '3')
        update_student_rating($mysqli, $in->studentId, $htUserBallovP2, 'Д2_пр', 'ДЗ №' . $in->dzNum); // (vk_id, баллов, тип, коммент)

    // ------------------------- Сохранение запроса в логи -------------------------
    $query = "
        UPDATE `ht_user` 
        SET `ht_user_checker_mistwork` = '" . $user_vk_id . "', 
            `ht_user_check_date_mistwork` = CURDATE(), 
            `ht_user_status_p2` = 'Проверен', 
            `ht_user_ballov_p2` = '" . $htUserBallovP2 . "' 
		WHERE `user_id` = '" . $in->studentId . "' 
		AND `ht_number` = '" . $in->dzNum . "' 
		AND `mistake_work_p2` > 0;
    ";
    $file_name = $_SERVER['DOCUMENT_ROOT'] . '/user_logs/' . $user_vk_id . '.csv';
    $content = $query . "\n" . $queryIns . "\n";
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
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (6)');

    // Выполнение запроса на получение айди проверяющего
    $stmt->execute([
        'curator_vk_id' => $user_vk_id,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (6)');

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['checker_id'] != '') {
        // Отправка информации о возврате на доработку ученику через ВК бота
        $message = "Появилась перекрёстная по ДЗ №" . $in->dzNum;
        addTaskSendingToVk($mysqli, [$row['checker_id']], $message);

        // Подготовка запроса на обновление уведомлений бота о перекрестной проверке
        $stmt = $pdo->prepare("
            UPDATE `cross_check` 
            SET `bot_notify` = 1 
            WHERE `curator_vk_id` = :curator_vk_id 
            AND `ht_num` = :dz_num;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса окончания проверки (7)');

        // Выполнение запроса на обновление уведомлений бота о перекрестной проверке
        $stmt->execute([
            'curator_vk_id' => $user_vk_id,
            'dz_num' => $in->dzNum,
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса окончания проверки (7)');
    }

    $stmt->closeCursor();
    unset($stmt);
}

// ------------------------- Возврат на работу над ошибками -------------------------
if ($in->action == 'workOnMistakes') {
    // ------------------------- Проверка тарифа с работой над ошибками -------------------------
    // Проверка наличия у ученика тарифа с работой над ошибками, если куратор хочет вернуть работу в состояние работы над ошибками
    if ($in->toMistakeWork == 1) {
        // Подготовка запроса для проверки наличия у ученика тарифа с работой над ошибками
        $stmt = $pdo->prepare("
            SELECT `users`.`with_mistake_work` 
            FROM `users` 
            WHERE `user_vk_id`=:student_id;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса возврата на работу над ошибками (1)');

        // Выполнение запроса для проверки наличия у ученика тарифа с работой над ошибками
        $stmt->execute(['student_id' => $in->studentId])
        or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса возврата на работу над ошибками (1)');

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row['with_mistake_work'] == 0)
            $out->make_wrong_resp("У ученика с ID $in->studentId тариф без работы над ошибками, поэтому вернуть работу в состояние работы над ошибками нельзя");
    }

    // ------------------------- Получение количества заданий -------------------------
    // Подготовка запроса на получение количества заданий
    $stmt = $pdo->prepare("
        SELECT `q_nums_p2`
        FROM `ht_user` 
        WHERE `user_id` = :student_id
        AND `ht_number` = :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса возврата на работу над ошибками (2)');

    // Выполнение запроса на получение количества заданий
    $stmt->execute([
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса возврата на работу над ошибками (2)');

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // ------------------------- Обновление ДЗ -------------------------
    // Подготовка запроса на обновление ДЗ
    $stmt = $pdo->prepare("
        INSERT INTO `ht_user_p2` (`user_id`, `ht_number`, `q_number`, `teacher_comment`, `teacher_json`, `user_ball`, `is_checked`) 
        VALUES (:student_id, :dz_num, :q_number, :teacher_comment, :teacher_json, :user_ball, '0')
        ON DUPLICATE KEY UPDATE `teacher_comment`=VALUES(`teacher_comment`), 
                                `teacher_json`=VALUES(`teacher_json`), 
                                `user_ball`=VALUES(`user_ball`), 
                                `user_old_answer`=`user_answer`;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса возврата на работу над ошибками (3)');
    $htUserBallovP2 = 0;

    for ($i = 1; $i <= $row['q_nums_p2']; $i++) {
        // Преобразование данных для текущей итерации
        $teacherComment = $curAnswer[$i]['cur_comment'];
        $userBall = $curAnswer[$i]['ballov'] === '' ? null : (int)$curAnswer[$i]['ballov'];
        $teacherJson = json_encode($curAnswer[$i]['add_comments'], JSON_UNESCAPED_UNICODE);

        // Суммирование баллов
        $htUserBallovP2 += (int)$curAnswer[$i]['ballov'];

        // Выполнение запроса на обновление ДЗ
        $stmt->execute([
            'student_id' => $in->studentId,
            'dz_num' => $in->dzNum,
            'q_number' => $i,
            'teacher_comment' => $teacherComment,
            'teacher_json' => $teacherJson,
            'user_ball' => $userBall,
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса возврата на работу над ошибками (3)');
    }

    // Помимо обновления статуса и баллов, обновляем также колонку mistake_work_p2, увеличивая ее на единицу,
    // если $to_mistake_work == 1. В ином случае оставляем поле таким же
    $stmt = $pdo->prepare("
        UPDATE `ht_user` 
        SET `ht_user_status_p2`= 'Выполняется', 
            `ht_user_ballov_p2`= :ht_user_ballov_p2, 
            `mistake_work_p2` = :to_mistake_work 
        WHERE `user_id`= :student_id 
        AND `ht_number`= :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса возврата на работу над ошибками (4)');

    $stmt->execute([
        'ht_user_ballov_p2' => $htUserBallovP2,
        'to_mistake_work' => $in->toMistakeWork == 1 ? '`mistake_work_p2` + 1' : '`mistake_work_p2`',
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса возврата на работу над ошибками (4)');

    $stmt->closeCursor();
    unset($stmt);

    // ------------------------- Создание подключения к БД для ВК бота и обновления рейтинга ученика -------------------------
    $mysqli = mysqli_init();
    $mysqli->real_connect($host, $user, $password, $database, NULL, NULL, $ssl_flag)
    or $out->make_wrong_resp('can\'t connect DB');

    // ------------------------- ВК бот -------------------------
    require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/add_task_sending_to_vk.inc.php';

    // Отправка информации о возврате на доработку ученику через ВК бота
    $message = sprintf("Вторая часть твоего ДЗ №%s отправлена на доработку. Тебе необходимо доделать ДЗ и снова отправить его на проверку💚 Ссылка на ДЗ https://насотку.рф/dz2_student.php?dz_num=%s", $in->dzNum, $in->dzNum);
    addTaskSendingToVk($mysqli, [$in->studentId], $message);

    // ------------------------- Обновление рейтинга ученика (минус 50 баллов за возврат дз на доработку) -------------------------
    // Обновление рейтинга происходит только в том случае, если это не возврат на работу над ошибками
    if ($in->toMistakeWork != 1) {
        require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/update_student_rating.inc.php';
        update_student_rating($mysqli, $in->studentId, -50, 'Д2_во', 'ДЗ №' . $in->dzNum); // (vk_id, баллы, тип, коммент)
    }
}

// ------------------------- Отклонение -------------------------
if ($in->action == 'reject') {
    // ------------------------- Получение количества заданий -------------------------
    // Подготовка запроса на получение количества заданий
    $stmt = $pdo->prepare("
        SELECT `q_nums_p2`
        FROM `ht_user` 
        WHERE `user_id` = :student_id
        AND `ht_number` = :dz_num;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса отклонения дз (1)');

    // Выполнение запроса на получение количества заданий
    $stmt->execute([
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса отклонения дз (1)');

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // ------------------------- Обновление ДЗ -------------------------
    // Подготовка запроса на обновление ДЗ
    $stmt = $pdo->prepare("
        INSERT INTO `ht_user_p2` (`user_id`, `ht_number`, `q_number`, `teacher_comment`, `teacher_json`, `user_ball`) 
        VALUES (:student_id, :dz_num, :q_number, :teacher_comment, :teacher_json, :user_ball)
        ON DUPLICATE KEY UPDATE `teacher_comment`=VALUES(`teacher_comment`), 
                                `teacher_json`=VALUES(`teacher_json`), 
                                `user_ball`=VALUES(`user_ball`);
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса отклонения дз (2)');

    $htUserBallovP2 = 0;
    for ($i = 1; $i <= $row['q_nums_p2']; $i++) {
        // Преобразование данных для текущей итерации
        $teacherComment = $curAnswer[$i]['cur_comment'];
        $userBall = $curAnswer[$i]['ballov'] === '' ? null : (int)$curAnswer[$i]['ballov'];
        $teacherJson = json_encode($curAnswer[$i]['add_comments'], JSON_UNESCAPED_UNICODE);

        // Суммирование баллов
        $htUserBallovP2 += (int)$curAnswer[$i]['ballov'];

        // Выполнение запроса на обновление ДЗ
        $stmt->execute([
            'student_id' => $in->studentId,
            'dz_num' => $in->dzNum,
            'q_number' => $i,
            'teacher_comment' => $teacherComment,
            'teacher_json' => $teacherJson,
            'user_ball' => $userBall,
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса отклонения дз (2)');
    }

    // ------------------------- Обновление статуса и баллов -------------------------
    // Подготовка запроса на обновление статуса и баллов
    $stmt = $pdo->prepare("
        UPDATE `ht_user` 
        SET `ht_user_status_p2`= 'Отклонен', 
            `ht_user_ballov_p2`= :ht_user_ballov_p2 
        WHERE `user_id`= :student_id AND `ht_number`= :dz_num;
	") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса отклонения дз (3)');

    // Выполнение запроса на обновление статуса и баллов
    $stmt->execute([
        'ht_user_ballov_p2' => $htUserBallovP2,
        'student_id' => $in->studentId,
        'dz_num' => $in->dzNum,
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса отклонения дз (3)');

    $stmt->closeCursor();
    unset($stmt);

    // ------------------------- ВК бот -------------------------
    require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/add_task_sending_to_vk.inc.php';

    // Создание подключения к БД для ВК бота
    $mysqli = mysqli_init();
    $mysqli->real_connect($host, $user, $password, $database, NULL, NULL, $ssl_flag) or $out->make_wrong_resp("can\'t connect DB");

    // Отправка информации об отклонении дз ученику через ВК бота
    $message = sprintf("Вторая часть твоего ДЗ №%d отклонена. Если не знаешь, почему - обратись к куратору. Ты можешь самостоятельно проверить ответы на платформе https://насотку.рф/dz2_student.php?dz_num=%d", $in->dzNum, $in->dzNum);
    addTaskSendingToVk($mysqli, [$in->studentId], $message);
}

// ------------------------- Ответ -------------------------
$out->success = '1';
$out->make_resp('');

// ------------------------- Функции для проверки запроса -------------------------
/**
 * Функция выполняющая проверку доступа пользователя к методу
 * @param string $userType Тип пользователя (куратор / админ)
 * @param int $userVkId ВК айди пользователя (куратора)
 * @param HomeTaskCuratorReview $in Класс запроса, используются поля action, studentId
 * @param PDO $pdo PDO объект для запросов к БД
 * @return string Строка ошибки если пуста, то ошибки нет и доступ разрешен
 */
function AccessCheck(string $userType, int $userVkId, HomeTaskCuratorReview $in, PDO $pdo): string
{
    $message = '';
    if ($userType != 'Админ') {
        // Подготовка запроса для проверки пользователя
        $stmt = $pdo->prepare("
            SELECT `users`.`user_curator`, `users`.`user_curator_dz`
            FROM `users` 
            WHERE `user_vk_id`= :student_id;
        ") or $message = 'Ошибка базы данных: подготовка запроса для проверки пользователя';

        // Выполнение запроса для проверки пользователя
        $stmt->execute(['student_id' => $in->studentId])
        or $message = 'Ошибка базы данных: выполнение запроса для проверки пользователя';

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Проверка условий доступа
        if ($in->action == 'workOnMistakes') {
            if ($row['user_curator'] != $userVkId && $row['user_curator_dz'] != $userVkId)
                $message = 'Нет доступа';
        } else {
            if ($row['user_curator_dz'] != '' && $row['user_curator_dz'] != '0' && $row['user_curator_dz'] != $userVkId)
                $message = 'Нет доступа';
        }

        if (($row['user_curator_dz'] == '' || $row['user_curator_dz'] == '0') && $row['user_curator'] != $userVkId)
            $message = 'Нет доступа';

        $stmt->closeCursor();
        unset($stmt);
    }

    return $message;
}

/**
 * Функция выполняющая проверку соответствия количества заданий
 * @param mixed $curAnswer Десериализованный JSON запроса
 * @param HomeTaskCuratorReview $in Класс запроса, используются поля studentId, dzNum
 * @param PDO $pdo PDO объект для запросов к БД
 * @return string Строка ошибки если пуста, то ошибки нет и доступ разрешен
 */
function CheckingNumberOfTasks($curAnswer, HomeTaskCuratorReview $in, PDO $pdo): string
{
    $message = '';
    // Подготовка запроса для проверки количества заданий
    $stmt = $pdo->prepare("
        SELECT `q_nums_p2`
        FROM `ht_user` 
        WHERE `user_id`=:student_id AND `ht_number`=:dz_num;
    ") or $message = 'Ошибка базы данных: подготовка запроса для проверки количества заданий';

    // Выполнение запроса для проверки количества заданий
    $stmt->execute(['student_id' => $in->studentId, 'dz_num' => $in->dzNum])
    or $message = 'Ошибка базы данных: выполнение запроса для проверки количества заданий';

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['q_nums_p2'] != count($curAnswer)) $message = 'Не соответствует количество вопросов';

    $stmt->closeCursor();
    unset($stmt);
    return $message;
}

