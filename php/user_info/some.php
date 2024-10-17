<?php // Получение данных вопроса для редактирования (ч.1)
header('Content-Type: application/json; charset=utf-8');
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/config.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/app/api/includes/root_classes.inc.php';
// класс запроса
class TrainP1AdminGetQuestion extends MainRequestClass {
    public $questionId = ''; // идентификатор вопроса ч.1, данные которого нужно получить
}
$in = new TrainP1AdminGetQuestion();
$in->from_json('
{
    "device": "",
    "questionId": "5",
    "signature": ""
}
');

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
    $pdo = new PDO("mysql:host=" . $host . ";dbname=" . $database . ";charset=" . $db_charset, $user, $password, $ssl_flag === $ssl_flag ? [
        PDO::MYSQL_ATTR_SSL_CA => null,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ] : [PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
} catch (PDOException $exception) {
    $out->make_wrong_resp('Нет соединения с базой данных');
}

//--------------------------------Проверка пользователя
//require $_SERVER['DOCUMENT_ROOT'] . '\practiceBack\php\includes\check_user.inc.php';


$zachets = [
    ['numberOfZachet'=> 1,'date'=>'2134' ],
];
foreach($zachets as  $zachet){
$out->question[] = ['numberOfZachet'=> $zachet['numberOfZachet'],'date'=>$zachet['date'] ];


}
//--------------------------------Ответ
$out->success = '1';
$out->question = [
    'qId' => (string) $question['q_id'],
    'qQuestion' => (string) $question['q_question'],
    'qEgeNum' => (string) $question['q_task_number'],
    'qChapter' => (string) $question['q_chapter'],
    'isPublished' => (string) $question['q_public'],
    'isSelfmade' => (string) $question['selfmade'],
];

$out->make_resp('');