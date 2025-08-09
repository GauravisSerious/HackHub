<?php
// This is a temporary debug file to check session data
// DELETE THIS FILE after debugging is complete

// Include configuration file
require_once 'includes/config.php';

// Security check - only show sensitive data to logged in users
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in - session data not available";
    die();
}

// Display session data
echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Add the username to session if it's missing
if (!isset($_SESSION['username']) && isset($_SESSION['user_id'])) {
    // Get username from database
    $userQuery = "SELECT username FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $_SESSION['username'] = $row['username'];
        echo "<h3>Username added to session: {$row['username']}</h3>";
    }
    
    $stmt->close();
}

echo "<p><a href='" . BASE_URL . "/index.php'>Return to homepage</a></p>";
echo "<p><strong>IMPORTANT:</strong> Delete this file after debugging!</p>";
?> 