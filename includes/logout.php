<?php
// Include configuration file
require_once 'config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Start a new session for the message
session_start();
$_SESSION['message'] = 'You have been successfully logged out!';
$_SESSION['message_type'] = 'success';

// Redirect to the login page using absolute URL
$redirect_url = BASE_URL . "/pages/login.php";
header("Location: " . $redirect_url);
exit();
?> 