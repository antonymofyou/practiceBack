<?
session_start();
require 'includes/autorise.inc.php';

if(!isset($_GET['ticket_id'])) {$ticket_id=0;} else {$ticket_id=(int)$_GET['ticket_id'];}

if($_SESSION['user_type']!='Куратор' && $_SESSION['user_type']!='Админ') exit('Нет доступа');

$query="SELECT `tickets`.*, CONCAT(`response`.`user_name`,' ', `response`.`user_surname`) as `response_name`, CONCAT(`maker`.`user_name`,' ', `maker`.`user_surname`) as `maker_name`  FROM `tickets` 
LEFT JOIN `users` AS `response` ON `response`.`user_vk_id`=`tickets`.`response_vk_id`
LEFT JOIN `users` AS `maker` ON `maker`.`user_vk_id`=`tickets`.`user_vk_id`
WHERE `ticket_id`='".$ticket_id."' ;";
$result = mysqli_query($link, $query) or die("Ошибка " . mysqli_error($link)); 
$row_ticket = mysqli_fetch_array($result, MYSQLI_ASSOC);

if($row_ticket['ticket_id']=='') exit('За тобой не числится такой заявки');

if($_SESSION['user_type']!='Админ'){
	if($row_ticket['user_vk_id']!=$_SESSION['user_id'] && $row_ticket['response_vk_id']!=$_SESSION['user_id']) exit('За тобой не числится такой заявки');
}

$query="SELECT `tickets_mess`.*, CONCAT(`users`.`user_name`,' ',`users`.`user_surname`) AS `who_sent` FROM `tickets_mess` 
	LEFT JOIN `users` ON `users`.`user_vk_id`=`tickets_mess`.`user_vk_id`
	WHERE `tickets_mess`.`ticket_id`='".$ticket_id."' ORDER BY `tickets_mess`.`comment_dtime` ASC;";
$result = mysqli_query($link, $query) or die("Ошибка " . mysqli_error($link)); 

$print_status_flag=0;
if($_SESSION['user_type']=='Админ' || $row_ticket['response_vk_id']==$_SESSION['user_id']) $print_status_flag=1;
?>

<!DOCTYPE html>
<html>
<head>
	<meta name = "viewport" content = "width=device-width, initial-scale=1">
	<link rel="stylesheet" href="<?echo main_css;?>" type="text/css">
	<link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
	
	<style>
		.subcontent_bar{
			width:90%;
			margin: 0 auto;
		}
		
		.white_block{
			background:white;
			padding: 10px 30px 10px;
			width:calc(100% - 60px);
			border-radius:10px;
			margin-top:20px;
		}
		
		.white_block h3{
			font-size:18px;
			margin-top:5px;
			margin-bottom:0px;
			color:#458cec;
		}
			
		select{
			padding:2px 6px;
			border-radius:10px;
		}	
		
		#messages_block{
			width:calc(100% - 20px); 
			padding:10px;
			max-height:300px; 
			min-height: 150px;
			overflow:auto;
			border:1px solid #b7c7dd;
			border-radius:10px;
			
		}
		
		.message{
			width:80%; 
			margin:5px 0;
			border-radius:10px;
			padding:10px;
			font-size:11px;
			color:black;
			word-wrap: break-word;
		}
		
		.who_send_mess{
			color:#67809d;
		}
		
		
		.for_mess_write{
			width:calc(100% - 20px); 
			padding:10px;
			max-width:500px; 
			min-height:50px; 
			margin-top:10px;
			border-radius:10px;
			border: 1px solid #f7e9f4;
			
			outline: none;
			-moz-appearance: none;
		}
		
		#messages_block::-webkit-scrollbar {
			//background-color:blue;
			//width: 12px;
		}
		
		
		@media (max-width: 500px){
			.subcontent_bar{
				width:100%;
			}
			.white_block{
				padding: 10px 15px 10px;
				width:calc(100% - 30px);
			}
			
		}
	</style>
	
	<?//если тёмная тема
		if($black_design==1) {
			echo "
				<style>
					.white_block{
						background:#1a2130;
					}
				</style>
			";
		}
	?>
</head>

<body> 

<?include 'includes/header.inc.php';?>

<div class="content">

<?include 'includes/sidebar.inc.php';?>
	
<div class="content_bar">
	<div class='subcontent_bar'>
		<div class='white_block'>
			<h3>Заявка №<?echo $ticket_id;?></h3>
			<?
				if($row_ticket['user_vk_id']==$_SESSION['user_id']) {$sozdatel='Я';}
				else {$sozdatel="<a href='https://vk.com/id".$row_ticket['user_vk_id']."' target='_blank'>".$row_ticket['maker_name']."</a>";}
				
				if($row_ticket['response_vk_id']==$_SESSION['user_id']) {$responsible='Я';}
				else {$responsible="<a href='https://vk.com/id".$row_ticket['response_vk_id']."' target='_blank'>".$row_ticket['response_name']."</a>";}
				
				
				switch($row_ticket['status']){
					case 0: $ticket_status='новая'; break;
					case 1: $ticket_status='в работе'; break;
					case 5: $ticket_status='завершена'; break;
					case 10: $ticket_status='архив'; break;
				}
				
				switch($row_ticket['ticket_type']){//1-огр вопросы вопросы, 2-финансы, договора,  5-Антон, 7-редактирование ученика, 10-Светлана Леонидовна
					case 1: $response='Орг. вопросы'; break;
					case 2: $response='Финансы, договора'; break;
					case 5: $response='Антон'; break;
					case 6: $response='Лиза Тюрина'; break;
					case 7: $response='Редактирование ученика'; break;
					case 10: $response='Светлана Леонидовна'; break;
				}
				
				/*switch($row_ticket['response_vk_id']){//1-общие вопросы, 5-Работа платформы, 10-Светлана Леонидовна
					case zest_id: $response='Общие вопросы'; break;
					case 5498698: $response='Работа платформы'; break;
					case 9790324: $response='Светлана Леонидовна'; break;
				}*/
			?>
			<strong>Создатель:</strong> <?echo $sozdatel;?><br>
			<strong>Ответственный:</strong> <?echo $responsible;?><br>
			<strong>Создана:</strong> <span style='font-size:10px;'><?echo $row_ticket['when_made'];?></span><br>
			<strong>Срок рассмотрения:</strong> <span style='font-size:10px;'><?echo $row_ticket['ticket_deadline'];?></span><br>
			<strong>Тип заявки:</strong> <?echo $response;?><br>
			<strong>Статус:</strong> <?echo $ticket_status;?><br>
			
			
			
		</div>
		
		<div class='white_block'>
			<h3 style='color:#458cec; margin-bottom:5px;'>
				<?	if($row_ticket['ticket_name']=='') {echo "Заявка без темы";}
					else{echo $row_ticket['ticket_name'];}
				?>
			</h3>
			<div id='messages_block'>
				<?
					$last_mess_id=0;
					while($row_mess = mysqli_fetch_array($result, MYSQLI_ASSOC)){
						
						if($row_mess['user_vk_id']==$_SESSION['user_id']){
							$who_made="<strong class='who_send_mess'>Я </strong>";
							$style_mess="float:right; background:#f7e9f4;";
						}
						else{
							if($row_mess['user_vk_id']==$row_ticket['user_vk_id']){
								$who_made="<strong class='who_send_mess'>".$row_mess['who_sent']."(созд.)</strong>";
								$style_mess="float:left; background:#f0f3f8;";
							}
							else if($row_mess['user_vk_id']==$row_ticket['response_vk_id']){
								$who_made="<strong class='who_send_mess'>".$row_mess['who_sent']."(отв.)</strong>";
								$style_mess="float:left; background:#f0f3f8;";
							}
							else{
								$who_made="<a href='https://vk.com/id".$row_mess['user_vk_id']."' target='_blank'>".$row_mess['who_sent']."</a>";
								$style_mess="float:left; background:#e3fcf3;";
							}
						}
						
						/*if($row_mess['user_vk_id']==$row_ticket['user_vk_id']){
							if($row_mess['user_vk_id']==$_SESSION['user_id']){
								$who_made="<strong class='who_send_mess'>Я </strong>";
							}
							else{
								$who_made="<strong class='who_send_mess'>создатель <strong>";
							}
							$style_mess="float:right; background:#f7e9f4;";
						}
						else{
							$who_made="<a href='https://vk.com/id".$row_mess['user_vk_id']."' target='_blank'>ссылка</a>";
							$style_mess="float:left; background:#f0f3f8;";
						}*/
						
						$print_text=$who_made."<span style='color:#67809d; font-size:8px;'>(".$row_mess['comment_dtime'].")</span><br>".nl2br($row_mess['comment']);
						echo "<div class='message' style='".$style_mess."'>".$print_text."</div>";
						$last_mess_id=$row_mess['mess_id'];
					}
				?>
			</div>
			<div id='new_mess_block' style='width:100%;'>
				<textarea class='for_mess_write' placeholder='Напиши здесь сообщение'></textarea>
				<br><br>
				<a href='#' id='make_message' class='button_my'>Отправить</a>
				<span id='dyn_place'></span>
				<br><br>
				<?if($print_status_flag==1) {
					$sel=[];
					for($i=0; $i<=10; $i++) {
						$sel[$i]='';
						if($i==$row_ticket['ticket_type']) $sel[$i]='selected';
					}
					$sel1=[];
					for($i=0; $i<=10; $i++) {
						$sel1[$i]='';
						if($i==$row_ticket['importance']) $sel1[$i]='selected';
					}
					$sel2=[];
					for($i=0; $i<=10; $i++) {
						$sel2[$i]='';
						if($i==$row_ticket['status']) $sel2[$i]='selected';
					}
					echo"<div id='change_data' style='margin-top:10px;'>
						<strong>Куда заявка:</strong>
						<select id='ticket_type'>";
					echo	"<option value='7' ".$sel[7].">Редактирование ученика</option>";
					echo 	"<option value='1' ".$sel[1].">Организационные вопросы</option>";
					echo 	"<option value='2' ".$sel[2].">Финансы, доровора</option>";
					echo	"<option value='5' ".$sel[5].">Антон</option>";
					echo	"<option value='6' ".$sel[6].">Лиза Тюрина</option>";
					echo	"<option value='10' ".$sel[10].">Светлана Леонидовна</option>";
					echo "</select>
						<br><br>
						<strong>Срочность:</strong>
						<select id='ticket_importance'>
							<option value='5' ".$sel1[5].">Обычная</option>
							<option value='10' ".$sel1[10].">Сверхсрочная</option>
						</select>
						<br><br>";
					echo "<strong>Статус:</strong>
						<select id='ticket_status'>
							<option value='0' ".$sel2[0].">Новая</option>
							<option value='1' ".$sel2[1].">В работе</option>
							<option value='5' ".$sel2[5].">Завершена</option>";
					//echo "<option value='10' ".$sel2[10].">Архив</option>";
					echo "</select><br><br>";
					echo "<strong>Срок рассмотрения:</strong>
						<input id='ticket_deadline' style='padding-left:10px;' value='".$row_ticket['ticket_deadline']."' type='date'>";					
					echo "</div>";
				}
				?>
			</div>
		</div>
				
		<div style='width:100%; height:20px;'></div>
	</div>	
</div>
</div>
<?include 'includes/footer.inc.php';?>

<script>
$('#messages_block').scrollTop($('#messages_block').prop('scrollHeight'));

ticket_id=<?echo $ticket_id;?>;
last_mess_id=<?echo $last_mess_id;?>;

<?
if($print_status_flag==1)
{
	echo "ticket_type=".$row_ticket['ticket_type'];
	echo "
	ticket_status=".$row_ticket['status'];
	echo "
	ticket_importance=".$row_ticket['importance'];
	echo "
	ticket_deadline='".$row_ticket['ticket_deadline']."'";
}
?>

function update_messages(){
	$.ajax({
		url: "ajax/tickets/update_messages.ajax.php",
		type: "POST",
		data: {
				"ticket_id": ticket_id,
				"last_mess_id": last_mess_id,
			},
			cache: false,
			success: function(response){
				var arr = response.split('%splitter%');
				last_mess_id=arr[0];
				$('#messages_block').append(arr[1]);
				if(arr[1]!='') $('#messages_block').scrollTop($('#messages_block').prop('scrollHeight'));
			}
    });	
}

$('#make_message').on('click', function(){
	event.preventDefault();
	$('#dyn_place').empty();
	ticket_flag=0;
	<?
		if($print_status_flag==1){
			echo "if(ticket_type!=$('#ticket_type').val() || ticket_importance!=$('#ticket_importance').val() || ticket_status!=$('#ticket_status').val() || ticket_deadline!=$('#ticket_deadline').val()) ticket_flag=1;";
		}
	?>
	if($('.for_mess_write').val()=='' && ticket_flag==0) {
		$('#dyn_place').append("<strong style='color:red;'>Введи сообщение</strong>");
		return;
	}
	message_text=$('.for_mess_write').val();
	
	$('#make_message').hide();
	$('#dyn_place').empty();
	$('#dyn_place').append("<img  style='width:30px;' src='/images/loading1.gif'>");
	
	$.ajax({
		url: "ajax/tickets/make_message.ajax.php",
		type: "POST",
		data: {
				<? if($print_status_flag==1) echo "'ticket_type': $('#ticket_type').val(),
				'ticket_importance': $('#ticket_importance').val(),
				'ticket_status': $('#ticket_status').val(),
				'ticket_deadline': $('#ticket_deadline').val(),";?>
				"ticket_id": ticket_id,
				"ticket_message": message_text,
			},
			cache: false,
			success: function(response){
				$('#make_message').show();
				$('#dyn_place').empty();
				if(response == 'OK'){
					update_messages();
					$('.for_mess_write').val('');
					$('#dyn_place').append("<strong style='color: green;'>OK</strong>");
					
					<?
						if($print_status_flag==1){
							echo "ticket_type=$('#ticket_type').val();
							ticket_importance=$('#ticket_importance').val();
							ticket_status=$('#ticket_status').val();
							ticket_deadline=$('#ticket_deadline').val();";
						}
					?>
				}else{
					$('#dyn_place').append("<strong style='color: red;'>сообщение не отправлено. ("+response+")</strong>");
				}
			}
    });
});

setInterval(update_messages,30000);
</script>

</body>
</html>