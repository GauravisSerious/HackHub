<?php
// This file handles displaying flash messages to the user

// Check if there's a message in the session
if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    
    // Map message types to Bootstrap alert classes
    $alert_class = 'alert-info'; // Default
    
    switch ($message_type) {
        case 'success':
            $alert_class = 'alert-success';
            $icon = 'fa-check-circle';
            break;
        case 'warning':
            $alert_class = 'alert-warning';
            $icon = 'fa-exclamation-triangle';
            break;
        case 'danger':
        case 'error':
            $alert_class = 'alert-danger';
            $icon = 'fa-times-circle';
            break;
        case 'info':
        default:
            $alert_class = 'alert-info';
            $icon = 'fa-info-circle';
            break;
    }
    
    // Display the message
    echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
    echo '<i class="fas ' . $icon . '"></i> ' . $message;
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    
    // Clear the message from the session
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?> 