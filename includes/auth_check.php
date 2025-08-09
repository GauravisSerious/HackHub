<?php
// Include the configuration file
require_once 'config.php';

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    // Set a message to display on login page
    $_SESSION['message'] = "You must be logged in to access that page";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// If script execution reaches here, the user is logged in
// You can add additional authorization checks here if needed

// For example, check if user is an admin for admin-only pages
function checkAdminAccess() {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        $_SESSION['message'] = "You don't have permission to access that page";
        $_SESSION['message_type'] = "danger";
        header("Location: " . BASE_URL . "/pages/dashboard.php");
        exit();
    }
}

// Check if user is a judge for judge-only pages
function checkJudgeAccess() {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'judge') {
        $_SESSION['message'] = "You don't have permission to access that page";
        $_SESSION['message_type'] = "danger";
        header("Location: " . BASE_URL . "/pages/dashboard.php");
        exit();
    }
}
?> 