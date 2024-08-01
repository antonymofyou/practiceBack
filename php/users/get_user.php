<?php // Получаем данные пользователя для редактирования

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

// Класс запроса
class UsersGetUser extends MainRequestClass {
    public $userVkId = ''; // Идентификатор ВК пользователя
}
$in = new UsersGetUser();
$in->from_json(file_get_contents('php://input'));

// Класс ответа
class UsersGetUserResponse  extends MainResponseClass {

    /*  Словарь со следующими полями:
     *  - userVkId - ВК ID пользователя
     *  - userPromo - Реферальный промокод пользователя
     *  - userReferer - ID реферера, который пригласил этого пользователя
     *  - userAvaLink - Ссылка на ВК аватар пользователя
     *  - userName - Имя пользователя
     *  - userSurname - Фамилия пользователя
     *  - userOtch - Отчество пользователя
     *  - userCurator - ВК ИД куратора пользователя
     *  - userCuratorDz - ВК ИД куратора(ДЗ)
     *  - userCuratorZach - ВК ИД куратора(Зачёт)
     *  - userBlocked - Заблокирован ли пользователь
     *  - userBdate - День рождения пользователя
     *  - userTel - Телефон пользователя
     *  - userEmail - Электронная почта пользователя
     *  - userType - Тип пользователя: Пакетник, Частичный, Интенсив, Куратор, Админ, Выпускник или Демо
     *  - userTarif - Тариф, руб/мес
     *  - userTarifNum - Тип тарифа пользователя: Демократичный, Авторитарный или Тоталитарный
     *  - userZachet - Наличие зачёта
     *  - userPayday - День оплаты пользователя, 1-31
     *  - userClassNumber - Класс пользователя
     *  - userStartCourseDate - Дата первой оплаты
     *  - userRegion - Идентификатор региона пользователя
     */
    public $user = []; //Словарь с информацией о пользователе
}
$out = new UsersGetUserResponse();


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

//Преобразуем промокод в реферальный ID
//0-B 1-C 2-D 3-E 4-F 5-G 6-H 7-K 8-L 9-M
$letters = array('B', 'C', 'D', 'E', 'F', 'G', 'H', 'K', 'L', 'M');
$numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
$promocode = str_replace($numbers, $letters, $in->userVkId);

//Получаем данные
$stmt = $pdo->prepare("
    SELECT `user_vk_id`, `user_referer`, `user_ava_link`, `user_name`, `user_surname`, `user_otch`, `user_curator`, `user_curator_dz`, `user_curator_zach`, `user_blocked`, `user_bdate`, `user_tel`, `user_email`, `user_type`, `user_tarif`, `user_tarif_num`, `user_zachet`, `user_payday`, `user_class_number`, `user_start_course_date`, `user_region`
    FROM `users`
    WHERE `user_vk_id` = :userVkId;
") or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (2)');
$stmt->execute([
    'userVkId' => $in->userVkId
]) or $out->make_wrong_resp('Ошибка базы данных: Выполнение запроса (2)');
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

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