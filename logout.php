<?php
require_once 'config.php';
if(isLoggedIn()){
    try{logAction($pdo,'LOGOUT','User logged out');}catch(Exception $e){}
}
session_destroy();
header('Location: login.php');
exit;
