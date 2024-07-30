<?
//создание сообщения пользователем
session_start();
//require $_SERVER['DOCUMENT_ROOT'] . '/includes/autorise.inc.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.inc.php';

$link=mysqli_init();
mysqli_real_connect($link, $host, $user, $password, $database,NULL,NULL,$ssl_flag) or die ('Connect error (' . mysqli_connect_errno() . ')');


$ticket_id=(int)$_POST['ticket_id'];
$last_mess_id=(int)$_POST['last_mess_id'];

$query="SELECT * FROM `tickets` WHERE `ticket_id`='".$ticket_id."';";
$result=mysqli_query($link, $query) or die("Ошибка " . mysqli_error($link));
$row = mysqli_fetch_array($result, MYSQLI_ASSOC);

if($row['user_vk_id']!=$_SESSION['user_id'] && $row['response_vk_id']!=$_SESSION['user_id'] && $_SESSION['user_type']!='Админ') exit('не твоя заявка');

$query="SELECT `tickets_mess`.*, CONCAT(`users`.`user_name`,' ',`users`.`user_surname`) AS `who_sent` FROM `tickets_mess` 
	LEFT JOIN `users` ON `users`.`user_vk_id`=`tickets_mess`.`user_vk_id`
	WHERE `tickets_mess`.`ticket_id`='".$ticket_id."' AND `tickets_mess`.`mess_id`>".$last_mess_id." ORDER BY `tickets_mess`.`comment_dtime` ASC;";
$result = mysqli_query($link, $query) or die("Ошибка " . mysqli_error($link)); 

$new_messages='';
while($row_mess = mysqli_fetch_array($result, MYSQLI_ASSOC)){
	
	if($row_mess['user_vk_id']==$_SESSION['user_id']){
		$who_made="<strong class='who_send_mess'>Я </strong>";
		$style_mess="float:right; background:#f7e9f4;";
	}
	else{
		if($row_mess['user_vk_id']==$row['user_vk_id']){
			$who_made="<strong class='who_send_mess'>".$row_mess['who_sent']."(созд.)</strong>";
			$style_mess="float:left; background:#f0f3f8;";
		}
		else if($row_mess['user_vk_id']==$row['response_vk_id']){
			$who_made="<strong class='who_send_mess'>".$row_mess['who_sent']."(отв.)</strong>";
			$style_mess="float:left; background:#f0f3f8;";
		}
		else{
			$who_made="<a href='https://vk.com/id".$row_mess['user_vk_id']."' target='_blank'>".$row_mess['who_sent']."</a>";
			$style_mess="float:left; background:#e3fcf3;";
		}
	}
	
	$print_text=$who_made."<span style='color:#67809d; font-size:8px;'>(".$row_mess['comment_dtime'].")</span><br>".nl2br($row_mess['comment']);
	$new_messages.="<div class='message' style='".$style_mess."'>".$print_text."</div>";
	$last_mess_id=$row_mess['mess_id'];
}

echo $last_mess_id.'%splitter%'.$new_messages;
?>