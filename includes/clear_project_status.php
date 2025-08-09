<?php
// Include configuration file
require_once 'config.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Clear project status message flags
    unset($_SESSION['project_status_message']);
    unset($_SESSION['project_id']);
    unset($_SESSION['project_title']);
    unset($_SESSION['project_status']);
    unset($_SESSION['rejection_reason']);
}

// Return success response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit();
?> 