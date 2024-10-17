<?php // Получение данных вопроса для редактирования (ч.1)
header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';


// класс запроса
class TrainP1AdminGetQuestion extends MainRequestClass {
    public $questionId = ''; // идентификатор вопроса ч.1, данные которого нужно получить
}
$link=mysqli_init();

$in = new TrainP1AdminGetQuestion();
$in->from_json(file_get_contents('php://input'));

// класс ответа
class TrainP1AdminGetQuestionResp extends MainResponseClass {
    /*
     * Словарь, который имеет следующие поля:
     *     - qId - идентификатор вопроса
     *     - qQuestion - json вопроса
     *     - qEgeNum - номер задания в ЕГЭ
     *     - qChapter - раздел
     *     - isPublished - опубликован ли (0/1)
     *     - isSelfmade - сделанный ли самостоятельно (0/1)
     */
    public $question = []; // данные вопроса

    public $chapters = []; // доступные разделы для вопроса (массив строк)
}
$out = new TrainP1AdminGetQuestionResp();

//--------------------------------Подключение к базе данных

try {
    // Use PDO for the database connection
    $pdo = new PDO("mysql:host=localhost;port=3366;dbname=task;charset=utf8mb4", 'root', 'zj8qz1sd6ruhqq24xaxlwq');
} catch(PDOException $e) {
    echo json_encode(["error" => "Connection failed: " . $e->getMessage()]);
    exit;
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
LEFT JOIN `zachet_user` ON `zachet_user`.`zachet_id`=`zachets_auto`.`za_id` AND `zachet_user`.`user_id`='".$student_id."'
WHERE `za_showed`=1
ORDER BY `za_id` DESC;";




$stmt = $pdo->prepare($query);
$stmt->execute();

$fetchedQuery = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Now extract the user_curator or handle potential empty results




$out->zachets =  $fetchedQuery;
$out->make_resp('');

