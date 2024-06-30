<?php

const APP_SECRET_KEY_INT = '';
const APP_SECRET_KEY_STRING = '';

// Основные классы для API
class API_root_class
{
    public $signature = '';

    function calc_signature($nasotku_token): string
    {
        // $class_vars=get_object_vars(__CLASS__);
        $class_vars = get_object_vars($this);

        foreach ($class_vars as $key => $value) {
            if (is_array($value) || is_object($value)) unset($class_vars[$key]);
        }

        unset($class_vars['signature']);
        ksort($class_vars);
        $concat = implode($class_vars);
        $concat .= APP_SECRET_KEY_INT . APP_SECRET_KEY_STRING . $nasotku_token;

        return hash('sha256', $concat);
    }

    //проверяет подпись в запросе
    function check_signature($nasotku_token): bool
    {
        return ($this->signature == $this->calc_signature($nasotku_token));
    }

    function make_signature($nasotku_token)
    {
        //if(!isset($nasotku_token)) exit('not set token in make_signature');
        $this->signature = $this->calc_signature($nasotku_token);
    }

    // Выдает ответ и завершает программу
    function make_resp($nasotku_token)
    {
        $this->make_signature($nasotku_token);
        echo $this->to_json();
        exit();
    }

    function make_wrong_resp($message, $suc = "0")
    {
        //Подпись здесь будет неверная
        global $user_vk_id, $device;

        $nasotku_token = "";
        $wrong_resp = new MainResponseClass();
        $wrong_resp->success = $suc;
        $wrong_resp->message = $message;

        if ($suc == "-1") {
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $addr = $_SERVER['REQUEST_URI'];
            $browser = str_replace(";", ".,", $_SERVER['HTTP_USER_AGENT']);
            $file_name = $_SERVER['DOCUMENT_ROOT'] . '/user_logs/app_unlogs.csv';
            $content = "РАЗЛОГИН " . $user_vk_id . "; " . $user_ip . "; " . date("d.m.Y") . "; " . date("H:i:s") . "; " . $addr . "; " . $browser . " device-" . $device . " Message: $message\n";
            file_put_contents($file_name, $content, FILE_APPEND);
        }

        unset($wrong_resp->signature);
        exit($wrong_resp->to_json());
    }

    // Распарсиваем json в текущий объект
    function from_json($json_in)
    {
        $data = json_decode($json_in, true);
        //echo "Данные ".$json_in;
        if (!isset($data['signature'])) $this->make_wrong_resp("haven't set signature");
        foreach ($data as $key => $value) {
            if (!isset($this->$key)) $this->make_wrong_resp("wrong object");
            $this->$key = $value;//$this->{$key}
        }
    }

    function to_json()
    {
        return json_encode($this, JSON_UNESCAPED_UNICODE);
    }

    // Переносим данные из другого объекта в этот. Метод перебирает текущий класс и вытаскивает из другого класса свойства, которые попадутся. Кроме signature.
    function from_another_class($another_class)
    {
        foreach ($this as $key => $value) {
            if (!isset($another_class->$key)) continue;
            if ($key == 'signature') continue;
            $this->$key = $another_class->$key;
        }
    }
}

//Основной класс запросов клиента
class MainRequestClass extends API_root_class
{
    public $device = '';
}


//Основной класс ответов клиенту
class MainResponseClass extends \API_root_class
{
    public $success = '';
    public $message = '';
}
