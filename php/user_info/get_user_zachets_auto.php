<?php // "Получение автоматических зачётов пользователя"
header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class UserInfoGetUserZachetsAuto extends MainRequestClass {
     // идентификатор вопроса ч.1, данные которого нужно получить
}
$link=mysqli_init();
$in = new UserInfoGetUserZachetsAuto();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class UserInfoGetUserZachetsAutoResp extends MainResponseClass {
    /*
     * Словарь, который имеет следующие поля:
     *     - qId - идентификатор вопроса
     *     - qQuestion - json вопроса
     *     - qEgeNum - номер задания в ЕГЭ
     *     - qChapter - раздел
     *     - isPublished - опубликован ли (0/1)
     *     - isSelfmade - сделанный ли самостоятельно (0/1)
     */
    public $zachets = []; // данные зачета, взятые из дб  

}
$out = new UserInfoGetUserZachetsAutoResp();

//--------------------------------Подключение к базе данных

try {
    // Use PDO for the database connection
    $pdo = new PDO("mysql:host=localhost;port=3366;dbname=task;charset=utf8mb4", 'root', 'zj8qz1sd6ruhqq24xaxlwq');
} catch(PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

// Check user access 
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/check_user.inc.php';


//check if user exists
if ($in->userId){
$student_id = $in->userId;
} 
else{
exit('не установлен пользователь ');
}

// Check access
if ($user_type != 'Админ' && $user_type != 'Куратор') {
    $out->make_wrong_resp('отказ в доступе');
exit('отказ в доступе');
}



//get user_curator info 
$query = "SELECT `user_curator` FROM `users` WHERE `user_vk_id` = :user_vk_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_vk_id'=>$student_id]) or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)');
$fetchedQuery = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($user_type =='Куратор' && $fetchedQuery[0]['user_curator']  != $in->userId) exit('это не твой ученик');

$query="SELECT `zachets_auto`.*, `zachet_user`.`zu_status`, `zachet_user`.`zu_popitka`, `zachet_user`.`zu_errors`, `zachet_user`.`zu_errors`  FROM `zachets_auto` 
LEFT JOIN `zachet_user` ON `zachet_user`.`zachet_id`=`zachets_auto`.`za_id` AND `zachet_user`.`user_id`=:student_id
WHERE `za_showed`=1
ORDER BY `za_id` DESC;";

$stmt = $pdo->prepare($query) or $out->make_wrong_resp('Ошибка базы данных: подготовка запроса (1)');;
$stmt->execute([':student_id' => $student_id])  or $out->make_wrong_resp('Ошибка базы данных: выполнение запроса (1)') ;
$zachets = $stmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($zachets as $zachet) {
    $out->zachets[0] = [
        'zaId' => (string) $zachet['za_id'],
        'zaDateStart' => (string) $zachet['za_date_start'],
        'zaDeadline' => (string) $zachet['za_deadline'],
        'zaMaxTime' => (string) $zachet['za_max_time'],
        'zaMaxPopitok' => (string) $zachet['za_max_popitok'],
        'zaQuestions' => (string) $zachet['za_questions'],
        'zaMaxErrors' => (string) $zachet['za_max_errors'],
        'zaLessonNumbers' => (string) $zachet['za_lesson_numbers'],
        'zaShowed' => (string) $zachet['za_showed'],
        'zuStatus' => (string) $zachet['zu_status'],
        'zuPopitka' => (string) $zachet['zu_popitka'],
        // ...
    ];
}

    
$out->make_resp('');






