<?php

class Database
{
    private $host;
    private $database;
    private $user;
    private $password;
    private $charset;

    private $dsn;
    private $opt;
    public $pdo;

    public function __construct()
    {
        $this->host = "127.0.0.1";
        $this->database = "hr";
        $this->user = "root";
        $this->password = "";
        $this->charset = "utf8mb4";

        $this->dsn = "mysql:host=$this->host;dbname=$this->database;charset=$this->charset";
        $this->opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($this->dsn, $this->user, $this->password, $this->opt);
    }
}
