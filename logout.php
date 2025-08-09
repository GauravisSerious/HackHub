<?php
// Include configuration file
require_once 'includes/config.php';

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a new session for the message
session_start();
$_SESSION['message'] = 'You have been successfully logged out!';
$_SESSION['message_type'] = 'success';

// Redirect to the login page
$redirect_url = BASE_URL . "/pages/login.php";
header("Location: " . $redirect_url);
exit();
?> 