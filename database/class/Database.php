<?php
       
include_once 'Messenger.php';

class Database extends Messenger{
    protected $db_host;
    protected $db_name;
    protected $db_user;
    protected $db_passwd;
    protected $db;
    protected $debug;
    
    function __construct($host, $username, $password, $database) {
        $this->db_host = $host;
        $this->db_user = $username;
        $this->db_passwd = $password;
        $this->db_name = $database;
        $this->debug = false;
        $this->connect();
    }
    
    public function connect() {
        if (!$this->db) {
            try {
                $this->db = new PDO('mysql:host='.$this->db_host.';dbname=' . $this->db_name, $this->db_user, $this->db_passwd);
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }catch (Exception $e) {
//                echo $e->getMessage();
                if ($e->getCode() == 99942) {
                    echo $e->getMessage();
                    die();
                } else {
                    echo "Database connection Failed";
                    die();
                }
            }
        }
        
        return $this->db;
    }
    
    public function getDb_user() {
        return $this->db_user;
    }

    public function getDb_passwd() {
        return $this->db_passwd;
    }
    
    public function getDb_name() {
        return $this->db_name;
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    public function setDebug($boolean) {
        $this->debug = $boolean;
    }



}
?>
