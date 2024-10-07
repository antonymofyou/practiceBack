<?php //---Таблица автоматических зачётов ученика

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class UserInfoGetUserAutoZachets extends MainRequestClass {
    public $userVkId = ''; // Идентификатор пользователя, чьи автозачёты нужно просмотреть
}
$in = new UserInfoGetUserAutoZachets();
$in->from_json(file_get_contents('php://input'));

//---Класс ответа
class UserInfoGetUserAutoZachetsResponse extends MainResponseClass {
    /* Массив словарей со следующими полями:
        - zaId              - Идентификатор зачёта
        - zaDateStart       - Дата начала зачёта
        - zaDeadline        - Дата конца зачёта
        - zaLessonNumbers   - ??
        - zuErrors          - Сколько совершено ошибок
        - zaMaxErrors       - Лимит ошибок
        - zuPopitka         - Сколько попыток использовано
        - zaMaxPopitok      - Лимит попыток
        - zuStatus          - Статус зачёта: 'Сдан' или 'Несдан'
        - obnul             - Возможность обнуления(0/1), 1 когда зачёт не сдан и достигнут лимит попыток
    */
    public $zachets = []; //Массив словарей по автоматическим зачётам
}
$out = new UserInfoGetUserAutoZachetsResponse();

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

//---Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if(!in_array($user_type, ['Админ', 'Куратор']) && !in_array($user_vk_id, user_info_lookers)) {
    $out->make_wrong_resp('Ошибка доступа');
}

//---Валидация $in->studentId
if (((string) (int) $in->userVkId) !== ((string) $in->userVkId) || (int) $in->userVkId <= 0) $out->make_wrong_resp("Параметр 'userVkId' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `user_curator`
    FROM `users`
    WHERE `user_vk_id` = :userVkId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Пользователь с ID {$in->userVkId} не найден или не является учеником");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//---Проверка пользователя(2)
//Можно просматривать зачёты только своих учеников, админ может просматривать всех
if($user_type != "Куратор" && $user['user_curator'] != $user_vk_id && !in_array($user_vk_id, user_info_lookers)) {
    $out->make_wrong_resp('Ошибка доступа');
}

$stmt = $pdo->prepare("
    SELECT `zachets_auto`.`za_id`, `zachets_auto`.`za_date_start`, `zachets_auto`.`za_deadline`, `zachets_auto`.`za_lesson_numbers`, `zachets_user`.`zu_errors`, `zachets_auto`.`za_max_errors`, `zachets_user`.`zu_popitka`, `zachets_auto`.`za_max_popitok`, `zachets_user`.`zu_status`
    FROM `zachets_auto`
    LEFT JOIN `zachet_user` ON `zachet_user`.`zachet_id` = `zachets_auto`.`za_id` AND `zachet_user`.`user_id` = :userVkId
    WHERE `za_showed` = 1
    ORDER BY `za_id` DESC;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (2)");
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (2)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Автозачёты не найдены");
$zachets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Определение возможности для обнуления
foreach ($zachets as $index => $zachet) {
    $zachets[$index]['obnul'] = '0';
    //
    if($zachet['zu_status'] == 'Несдан' && $zachet['zu_popitka'] == $zachet['za_max_popitok']) {
        $zachets[$index]['obnul'] = '1';
    }
}

foreach($zachets as $zachet) {
    $out->zachets[] = [
        'zaId' => (string) $zachet['za_id'],
        'zaDateStart' => (string) $zachet['zaDateStart'],
        'zaDeadline' => (string) $zachet['zaDeadline'],
        'zaLessonNumbers' => (string) $zachet['zaLessonNumbers'],
        'zuErrors' => (string) $zachet['zuErrors'],
        'zaMaxErrors' => (string) $zachet['zaMaxErrors'],
        'zuPopitka' => (string) $zachet['zuPopitka'],
        'zaMaxPopitok' => (string) $zachet['zaMaxPopitok'],
        'zuStatus' => (string) $zachet['zuStatus'],
        'obnul' => (string) $zachet['obnul']
    ];
}

$out->success = "1";
$out->make_resp('');