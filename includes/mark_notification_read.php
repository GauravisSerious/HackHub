<?php
// Include configuration file
require_once 'config.php';
// Include auth functions
require_once 'auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Redirect to login page with an error message
    $_SESSION['message'] = "You must log in to access this page";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if notification ID is provided
if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
    // Redirect to referrer or dashboard
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : BASE_URL . "/pages/dashboard.php";
    header("Location: " . $redirect);
    exit();
}

$notification_id = intval($_POST['notification_id']);
$user_id = $_SESSION['user_id'];

// Mark the notification as read, ensuring it belongs to the current user
$update_sql = "UPDATE user_notifications SET is_read = 1 
              WHERE id = ? AND user_id = ?";

if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($update_stmt, "ii", $notification_id, $user_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
}

// Redirect back to the previous page
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : BASE_URL . "/pages/dashboard.php";
header("Location: " . $redirect);
exit();
?> 