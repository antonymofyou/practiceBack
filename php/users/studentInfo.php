<?php //---Анкета ученика

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config_api.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';

//---Класс запроса
class UsersStudentInfo extends MainRequestClass {
    public $vkId = ''; //ВК ID ученика, данные которого нужно получить
}
$in = new UsersStudentInfo();
$in->from_json(file_get_contents('php://input'));

//---Класс ответа
class UsersStudentInfoResponse extends MainResponseClass {
    /* Словарь со следующими полями:
        - userVkId              - ВК ID ученика
        - userAvaLink           - Ссылка на ВК аватар ученика
        - bnBalance             - Текущий баланс ученика
        - userBlocked           - Заблокирован ли ученик
        - userName              - Имя ученика
        - userSurname           - Фамилия ученика
        - userOtch              - Отчество ученика
        - userRegion            - Идентификатор региона ученика
        - userBdate             - День рождения ученика
        - userClassNumber       - Класс ученика
        - userTel               - Телефон ученика
        - userEmail             - Электронная почта ученика
        - userTarifNum          - Тип тарифа ученика: Демократичный(1), Авторитарный(2) или Тоталитарный(3), если не задано, то без тарифа
        - userZachet            - Наличие зачёта(0, 1)
        - userTarif             - Тариф, руб/мес
        - userStartCourseDate   - Дата первой оплаты
        - userCurator           - ВК ИД куратора ученика
        - userCuratorDz         - ВК ИД куратора(ДЗ)
        - userCuratorZach       - ВК ИД куратора(Зачёт)
        - userGoalBall          - Баллы ученика по ЕГЭ
        - userGoalVuz           - Желаемый ВУЗ ученика
        - userGoalBudzhet       - Тип формы обучения: Неважно(0), Платка(1), Бюджет(2), если не задано, то неустановленная форма обучения
        - userOsobennosti       - Особенности ученика
        - userPayday            - День оплаты ученика, 1-31
    */
    public $studentInfo = []; //Словарь с данными ученика
}
$out = new UsersStudentInfoResponse();

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

if (((string) (int) $in->vkId) !== ((string) $in->vkId) || (int) $in->vkId <= 0) $out->make_wrong_resp("Параметр 'vkId' задан неверно или отсутствует");
$stmt = $pdo->prepare("
    SELECT `user_vk_id`, `user_ava_link`, `bn_balance`, `user_blocked`, `user_name`, `user_surname`, `user_otch`, `user_region`, `user_bdate`, `user_class_number`, `user_tel`, `user_email`, `user_tarif_num`, `user_zachet`, `user_tarif`, `user_start_course_date`, `user_curator`, `user_curator_dz`, `user_curator_zach`, `user_goal_ball`, `user_goal_vuz`, `user_goal_budzhet`, `user_osobennosti`, `user_payday`, `user_type`
    FROM `users` 
    LEFT JOIN `users_add` ON `users`.`user_vk_id` = `users_add`.`user_vk_id`
    LEFT JOIN `balance_now` ON `users`.`user_vk_id` = `balance_now`.`bn_user_id`
    WHERE `user_vk_id` = :vkId;
") or $out->make_wrong_resp("Ошибка базы данных: подготовка запроса (1)");
$stmt->execute([
    'vkId' => $in->vkId
]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
if ($stmt->rowCount() == 0) $out->make_wrong_resp("Ученик с таким ВК ID не найден");
$studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); unset($stmt);

//Проверка пользователя
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';
if($user_type != 'Админ') { //Админы могут смотреть кого-угодно
    if(!in_array($stundentInfo['user_type'], ['Частичный', 'Интенсив'])) $out->make_wrong_resp('Пользователь с таким ВК ID сейчас не является учеником');
    elseif($studentInfo['user_curator'] != $user_id) $out->make_wrong_resp('Можно смотреть данные только своих учеников'); //Некураторы неадмины не могут получать данные никаких учеников
}

$out->studentInfo = [
    'userVkId' => (string) $studentInfo['user_vk_id'],
    'userAvaLink' => (string) $studentInfo['user_ava_link'],
    'bnBalance' => (string) $studentInfo['bn_balance'],
    'userBlocked' => (string) $studentInfo['user_blocked'],
    'userName' => (string) $studentInfo['user_name'],
    'userSurname' => (string) $studentInfo['user_surname'],
    'userOtch' => (string) $studentInfo['user_otch'],
    'userRegion' => (string) $studentInfo['user_region'],
    'userBdate' => (string) $studentInfo['user_bdate'],
    'userClassNumber' => (string) $studentInfo['user_class_number'],
    'userTel' => (string) $studentInfo['user_tel'],
    'userEmail' => (string) $studentInfo['user_email'],
    'userTarifNum' => (string) $studentInfo['user_tarif_num'],
    'userZachet' => (string) $studentInfo['user_zachet'],
    'userTarif' => (string) $studentInfo['user_tarif'],
    'userStartCourseDate' => (string) $studentInfo['user_start_course_date'],
    'userCurator' => (string) $studentInfo['user_curator'],
    'userCuratorDz' => (string) $studentInfo['user_curator_dz'],
    'userCuratorZach' => (string) $studentInfo['user_curator_zach'],
    'userGoalBall' => (string) $studentInfo['user_goal_ball'],
    'userGoalVuz' => (string) $studentInfo['user_goal_vuz'],
    'userGoalBudzhet' => (string) $studentInfo['user_goal_budzhet'],
    'userOsobennosti' => (string) $studentInfo['user_osobennosti'],
    'userPayday' => (string) $studentInfo['user_payday']
];

$out->success = "1";
$out->make_resp('');