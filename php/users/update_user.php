<?php // Получаем данные пользователя

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class UsersUpdateUser extends MainRequestClass {
    public $userVkId = ''; // Идентификатор ВК пользователя

    /* Словарь со следующими полями:
        - userName - Имя пользователя
        - userSurname - Фамилия пользователя
        - userOtch - Отчество пользователя
        - userCurator - ВК ИД куратора пользователя
        - userCuratorDz - ВК ИД куратора(ДЗ)
        - userCuratorZach - ВК ИД куратора(Зачёт)
        - userBlocked - Заблокирован ли пользователь: 0: Нет, 1: Да
        - userBdate - День рождения пользователя
        - userTel - Телефон пользователя
        - userEmail - Электронная почта пользователя
        - userType - Тип пользователя: Пакетник, Частичный, Интенсив, Куратор, Админ, Выпускник или Демо
        - userTarif - Тариф, руб/мес
        - userTarifNum - Тип тарифа пользователя: Демократичный, Авторитарный или Тоталитарный
        - userZachet - Наличие зачёта: 0: Нет, 1: Да
        - userPayday - День оплаты пользователя, 1-31
        - userClassNumber - Класс пользователя
        - userStartCourseDate - Дата первой оплаты
        - userRegion - Идентификатор регион пользователя
    */
    public $userSet = []; // Словарь с информацией для обновления
}
$in = new UsersUpdateUser();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class UsersUpdateUserResponse extends MainResponseClass {

    /*  Словарь со следующими полями:
        - userVkId - ВК ID пользователя
        - userPromo - Реферальный промокод пользователя
        - userReferer - ID реферера, который пригласил этого пользователя
        - userAvaLink - Ссылка на ВК аватар пользователя
        - userName - Имя пользователя
        - userSurname - Фамилия пользователя
        - userOtch - Отчество пользователя
        - userCurator - ВК ИД куратора пользователя
        - userCuratorDz - ВК ИД куратора(ДЗ)
        - userCuratorZach - ВК ИД куратора(Зачёт)
        - userBlocked - Заблокирован ли пользователь
        - userBdate - День рождения пользователя
        - userTel - Телефон пользователя
        - userEmail - Электронная почта пользователя
        - userType - Тип пользователя: Пакетник, Частичный, Интенсив, Куратор, Админ, Выпускник или Демо
        - userTarif - Тариф, руб/мес
        - userTarifNum - Тип тарифа пользователя: 1: Демократичный, 2: Авторитарный или 3: Тоталитарный
        - userZachet - Наличие зачёта
        - userPayday - День оплаты пользователя, 1-31
        - userClassNumber - Класс пользователя
        - userStartCourseDate - Дата первой оплаты
        - userRegion - Идентификатор региона пользователя
     */
    public $user = []; //Словарь с информацией о пользователе
}
$out = new UsersUpdateUserResponse();

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

//Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if (!in_array($user_type, ['Админ'])) $out->make_wrong_resp('Ошибка доступа'); // Доступ только у админа

//Валидация $in->userVkId
if (((string) (int) $in->userVkId) !== ((string) $in->userVkId) || (int) $in->userVkId <= 0) $out->make_wrong_resp("Параметр 'userVkId' задан неверно или отсутствует");
    $stmt = $pdo->prepare("
        SELECT `user_vk_id`
        FROM `users`
        WHERE `user_vk_id` = :userVkId;
    ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');
    $stmt->execute([
        'userVkId' => $in->userVkId
    ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
    if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ошибка: Пользователь с ВК ID {$in->userVkId} не найден");
    $stmt->closeCursor(); unset($stmt);

//Валидация $in->userSet[...] Незаданные поля пропускаем, заданные валидируем и записываем в словарь
$userSet = [];

//userName
if (isset($in->userSet['userName'])) {
    if(!is_string($in->userSet['userName']) || mb_strlen($in->userSet['userName'] > 30)) $out->make_wrong_resp("Параметр обновления 'userName' задан неверно");
    $userSet['user_name'] = $in->userSet['userName'];
}
//userSurname
if (isset($in->userSet['userSurname'])) {
    if(!is_string($in->userSet['userSurname']) || mb_strlen($in->userSet['userSurname'] > 30)) $out->make_wrong_resp("Параметр обновления 'userSurname' задан неверно");
    $userSet['user_name'] = $in->userSet['userSurname'];
}
//userOtch
if (isset($in->userSet['userOtch'])) {
    if(!is_string($in->userSet['userOtch']) || mb_strlen($in->userSet['userOtch'] > 30)) $out->make_wrong_resp("Параметр обновления 'userOtch' задан неверно");
    $userSet['user_name'] = $in->userSet['userOtch'];
}
//userCurator
if (isset($in->userSet['userCurator'])) {
    if (((string) (int) $in->userSet['userCurator']) !== ((string) $in->userSet['userCurator']) || (int) $in->userSet['userCurator'] <= 0) $out->make_wrong_resp("Параметр обновления 'userCurator' задан неверно");
        $stmt = $pdo->prepare("
            SELECT `user_vk_id`, `user_type`
            FROM `users`
            WHERE `user_vk_id` = :userVkId;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (4)');
        $stmt->execute([
            'userVkId' => $in->userSet['userVkId']
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (4)');
        if ($stmt->rowCount() == 0 || $stmt->fetch(PDO::FETCH_ASSOC)['user_type'] != 'Куратор') $out->make_wrong_resp("Ошибка: Куратор с ВК ID {$in->userSet['userVkId']} не найден");
        $stmt->closeCursor(); unset($stmt);
    $userSet['user_curator'] = $in->userSet['userCurator'];
}
//userCuratorDz
if (isset($in->userSet['userCuratorDz'])) {
    if (((string) (int) $in->userSet['userCuratorDz']) !== ((string) $in->userSet['userCuratorDz']) || (int) $in->userSet['userCuratorDz'] <= 0) $out->make_wrong_resp("Параметр обновления 'userCuratorDz' задан неверно");
        $stmt = $pdo->prepare("
            SELECT `user_vk_id`, `user_type`
            FROM `users`
            WHERE `user_vk_id` = :userVkId;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (5)');
        $stmt->execute([
            'userVkId' => $in->userSet['userVkId']
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (5)');
        if ($stmt->rowCount() == 0 || $stmt->fetch(PDO::FETCH_ASSOC)['user_type'] != 'Куратор') $out->make_wrong_resp("Ошибка: Куратор с ВК ID {$in->userSet['userVkId']} не найден");
        $stmt->closeCursor(); unset($stmt);
    $userSet['user_curator_dz'] = $in->userSet['userCuratorDz'];
}
//userCuratorZach
if (isset($in->userSet['userCuratorZach'])) {
    if (((string) (int) $in->userSet['userCuratorZach']) !== ((string) $in->userSet['userCuratorZach']) || (int) $in->userSet['userCuratorZach'] <= 0) $out->make_wrong_resp("Параметр обновления 'userCuratorZach' задан неверно");
        $stmt = $pdo->prepare("
            SELECT `user_vk_id`, `user_type`
            FROM `users`
            WHERE `user_vk_id` = :userVkId;
        ") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (6)');
        $stmt->execute([
            'userVkId' => $in->userSet['userVkId']
        ]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (6)');
        if ($stmt->rowCount() == 0 || $stmt->fetch(PDO::FETCH_ASSOC)['user_type'] != 'Куратор') $out->make_wrong_resp("Ошибка: Куратор с ВК ID {$in->userSet['userVkId']} не найден");
        $stmt->closeCursor(); unset($stmt);
    $userSet['user_curator_zach'] = $in->userSet['userCuratorZach'];
}
//userBlocked
if (isset($in->userSet['userBlocked'])) {
    if (!in_array($in->userSet['userBlocked'], [0, 1])) $out->make_wrong_resp("Параметр обновления 'userBlocked' задан неверно");
    $userSet['user_blocked'] = $in->userSet['userBlocked'];
}
//userBdate
if (isset($in->userSet['userBdate'])) {
    if (!is_string($in->userSet['userBdate'])) $out->make_wrong_resp("Параметр обновления 'userBdate' задан неверно");
    $userSet['user_bdate'] = $in->userSet['userBdate'];
}
//userTel
if (isset($in->userSet['userTel'])) {
    if (!is_string($in->userSet['userTel']) || mb_strlen($in->userSet['userTel'] > 20)) $out->make_wrong_resp("Параметр обновления 'userTel' задан неверно");
    $userSet['user_tel'] = $in->userSet['userTel'];
}
//userEmail
if (isset($in->userSet['userEmail'])) {
    if (!is_string($in->userSet['userEmail']) || mb_strlen($in->userSet['userEmail'] > 70)) $out->make_wrong_resp("Параметр обновления 'userEmail' задан неверно");
    $userSet['user_email'] = $in->userSet['userEmail'];
}
//userType
if (isset($in->userSet['userType'])) {
    if (!in_array($in->userSet['userType'], ['Пакетник', 'Частичный', 'Интенсив', 'Куратор', 'Админ', 'Выпускник', 'Демо'])) $out->make_wrong_resp("Параметр обновления 'userType' задан неверно");
    $userSet['user_type'] = $in->userSet['userType'];
}
//userTarif
if (isset($in->userSet['userTarif'])) {
    if (((string) (int) $in->userSet['userTarif']) !== ((string) $in->userSet['userTarif']) || (int) $in->userSet['userTarif'] <= 0) $out->make_wrong_resp("Параметр обновления 'userTarif' задан неверно");
    $userSet['user_tarif'] = $in->userSet['userTarif'];
}
//userTarifNum
if (isset($in->userSet['userTarifNum'])) {
    if (!in_array($in->userSet['userTarifNum'], [0, 1, 2, 3])) $out->make_wrong_resp("Параметр обновления 'userTarifNum' задан неверно");
    $userSet['user_tarif_num'] = $in->userSet['userTarifNum'];
}
//userZachet
if (isset($in->userSet['userZachet'])) {
    if (!in_array($in->userSet['userZachet'], [0, 1])) $out->make_wrong_resp("Параметр обновления 'userZachet' задан неверно");
    $userSet['user_zachet'] = $in->userSet['userZachet'];
}
//userPayday
if (isset($in->userSet['userPayday'])) {
    if (((string) (int) $in->userSet['userPayday']) !== ((string) $in->userSet['userPayday']) || (int) $in->userSet['userPayday'] < 0 || (int) $in->userSet['userPayday'] > 31) $out->make_wrong_resp("Параметр обновления 'userPayday' задан неверно");
    $userSet['user_payday'] = $in->userSet['userPayday'];
}
//userClassNumber
if (isset($in->userSet['userClassNumber'])) {
    if (((string) (int) $in->userSet['userClassNumber']) !== ((string) $in->userSet['userClassNumber']) || (int) $in->userSet['userClassNumber'] <= 0) $out->make_wrong_resp("Параметр обновления 'userClassNumber' задан неверно");
    $userSet['user_class_number'] = $in->userSet['userClassNumber'];
}
//userStartCourseDate
if (isset($in->userSet['userStartCourseDate'])) {
    if (!is_string($in->userSet['userStartCourseDate'])) $out->make_wrong_resp("Параметр обновления 'userStartCourseDate' задан неверно");
    $userSet['user_start_course_date'] = $in->userSet['userStartCourseDate'];
}
//userRegion
if (isset($in->userSet['userRegion'])) {
    if (((string) (int) $in->userSet['userRegion']) !== ((string) $in->userSet['userRegion'])) $out->make_wrong_resp("Параметр обновления 'userRegion' задан неверно");
    $userSet['user_region'] = $in->userSet['userRegion'];
}

// если ничего обновлять не нужно - то выводим ошибку
if (empty($userSet)) $out->make_wrong_resp('Ни для одного поля не было запрошено обновление');

$values = [];
$params = [];
foreach ($userSet as $key => $value) { 
    $values[] = "`$key` = :$key";
    $params[$key] = $value;
}
$values = join(', ', $values);
$params['userVkId'] = $in->userVkId;

$stmt = $pdo->prepare("
    UPDATE `users` 
    SET $values 
    WHERE `user_vk_id` = :userVkId
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (7)');
$stmt->execute($params) or $out->make_wrong_resp("Ошибка базы данных: выполнение запроса (7)");
$stmt->closeCursor(); unset($stmt); 

//Заносим crm комментарий
$stmt = $pdo->prepare("
    INSERT INTO `crm_comments`
    (`user_vk_id`, `crm_comment`, `crm_editor`, `crm_date`, `crm_time`)
    VALUES (:userVkId, :crmComment, :editor, CURDATE(), CURTIME());
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (8)');
$stmt->execute([
    'userVkId' => $in->userVkId,
    'crmComment' => 'Поля изменены: ' . join(', ', array_keys($userSet)),
    'editor' => $user_vk_id //check_user.inc.php
]) or $out->make_wrong_resp('Ошибка базы данных: Выполнение запроса (8)');


//Возвращаем изменённые данные пользователя
//Получаем данные
$stmt = $pdo->prepare("
    SELECT `user_vk_id`, `user_referer`, `user_ava_link`, `user_name`, `user_surname`, `user_otch`, `user_curator`, `user_curator_dz`, `user_curator_zach`, `user_blocked`, `user_bdate`, `user_tel`, `user_email`, `user_type`, `user_tarif`, `user_tarif_num`, `user_zachet`, `user_payday`, `user_class_number`, `user_start_course_date`, `user_region`
    FROM `users`
    WHERE `user_vk_id` = :userVkId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (9)');
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: Выполнение запроса (9)');
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Преобразуем промокод в реферальный ID
//0-B 1-C 2-D 3-E 4-F 5-G 6-H 7-K 8-L 9-M
$letters = array('B', 'C', 'D', 'E', 'F', 'G', 'H', 'K', 'L', 'M');
$numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
$promocode = str_replace($numbers, $letters, (string) $user['user_vk_id']);

//Формируем ответ
$out->user = [
    'userVkId' => (string) $user['user_vk_id'],
    'userPromo' => (string) $promocode,
    'userReferer' => (string) $user['user_referer'],
    'userAvaLink' => (string) $user['user_ava_link'],
    'userName' => (string) $user['user_name'],
    'userSurname' => (string) $user['user_surname'],
    'userOtch' => (string) $user['user_otch'],
    'userCurator' => (string) $user['user_curator'],
    'userCuratorDz' => (string) $user['user_curator_dz'],
    'userCuratorZach' => (string) $user['user_curator_zach'],
    'userBlocked' => (string) $user['user_blocked'],
    'userBdate' => (string) $user['user_bdate'],
    'userTel' => (string) $user['user_tel'],
    'userEmail' => (string) $user['user_email'],
    'userType' => (string) $user['user_type'],
    'userTarif' => (string) $user['user_tarif'],
    'userTarifNum' => (string) $user['user_tarif_num'],
    'userZachet' => (string) $user['user_zachet'],
    'userPayday' => (string) $user['user_payday'],
    'userClassNumber' => (string) $user['user_class_number'],
    'userStartCourseDate' => (string) $user['user_start_course_date'],
    'userRegion' => (string) $user['user_region']
];

$out->success = "1";
$out->make_resp('');