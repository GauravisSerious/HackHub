<?php
// This is a temporary script to reset the admin password or create a new admin user
// DELETE THIS FILE after running it once for security reasons

// Include configuration file
require_once 'includes/config.php';

// Set admin credentials
$username = 'admin';
$email = 'admin@hackathon.com';
$password = 'admin123'; // This will be the new plain password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$firstName = 'Admin';
$lastName = 'User';
$userType = 'admin';

// Check if admin user exists
$checkQuery = "SELECT user_id FROM users WHERE username = ? OR email = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    // Admin user exists, update password
    $checkStmt->bind_result($user_id);
    $checkStmt->fetch();
    $checkStmt->close();
    
    $updateQuery = "UPDATE users SET password = ? WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $hashedPassword, $user_id);
    
    if ($updateStmt->execute()) {
        echo "<p>Admin password has been reset successfully.</p>";
        echo "<p>Username: $username</p>";
        echo "<p>Password: $password</p>";
    } else {
        echo "<p>Error updating admin password: " . $conn->error . "</p>";
    }
    
    $updateStmt->close();
} else {
    // Admin user doesn't exist, create a new one
    $insertQuery = "INSERT INTO users (username, email, password, first_name, last_name, user_type) VALUES (?, ?, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("ssssss", $username, $email, $hashedPassword, $firstName, $lastName, $userType);
    
    if ($insertStmt->execute()) {
        echo "<p>Admin user has been created successfully.</p>";
        echo "<p>Username: $username</p>";
        echo "<p>Password: $password</p>";
    } else {
        echo "<p>Error creating admin user: " . $conn->error . "</p>";
    }
    
    $insertStmt->close();
}

echo "<p><strong>IMPORTANT:</strong> Delete this file immediately for security reasons!</p>";
echo "<p><a href='" . BASE_URL . "/pages/login.php'>Go to Login Page</a></p>";
?> 