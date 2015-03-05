<?php

/**
 * Description of Messenger
 * 
 *
 * @author Matias Thomsen
 * @since 02.07.2013 13:44:18
 */

class Messenger {
            
    function __construct() {
        if (session_id() == "") {
            session_start();
        }
        if (!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = array();
        }
        
    }
    
    public function setMessage($message, $type = 'error') {
        if ($type != 'error' && $type != 'warning' && $type != 'success') {
            throw new Exception("Messenger: invalid type");
        }
        if ($message == '') {
            throw new Exception("Messenger: message was no set");
        }
        $_SESSION['messages'][$type][] = $message;
    }
    
    public function getMessages() {
        $messages = $_SESSION['messages'];
        $this->clearMessages();
        return ($messages) ? $messages : false;
    }
    
    public function clearMessages() {
        $_SESSION['messages'] = array();
    }

}

?>
