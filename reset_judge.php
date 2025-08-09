<?php
// This is a temporary script to create a judge account
// DELETE THIS FILE after running it once for security reasons

// Include configuration file
require_once 'includes/config.php';

// Set judge credentials
$username = 'judge';
$email = 'judge@hackathon.com';
$password = 'judge123'; // This will be the plain password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$firstName = 'Judge';
$lastName = 'User';
$userType = 'judge';

// Check if judge user exists
$checkQuery = "SELECT user_id FROM users WHERE username = ? OR email = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    // Judge user exists, update password
    $checkStmt->bind_result($user_id);
    $checkStmt->fetch();
    $checkStmt->close();
    
    $updateQuery = "UPDATE users SET password = ? WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $hashedPassword, $user_id);
    
    if ($updateStmt->execute()) {
        echo "<p>Judge password has been reset successfully.</p>";
        echo "<p>Username: $username</p>";
        echo "<p>Password: $password</p>";
    } else {
        echo "<p>Error updating judge password: " . $conn->error . "</p>";
    }
    
    $updateStmt->close();
} else {
    // Judge user doesn't exist, create a new one
    $insertQuery = "INSERT INTO users (username, email, password, first_name, last_name, user_type) VALUES (?, ?, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("ssssss", $username, $email, $hashedPassword, $firstName, $lastName, $userType);
    
    if ($insertStmt->execute()) {
        echo "<p>Judge user has been created successfully.</p>";
        echo "<p>Username: $username</p>";
        echo "<p>Password: $password</p>";
    } else {
        echo "<p>Error creating judge user: " . $conn->error . "</p>";
    }
    
    $insertStmt->close();
}

echo "<p><strong>IMPORTANT:</strong> Delete this file immediately for security reasons!</p>";
echo "<p><a href='" . BASE_URL . "/pages/login.php'>Go to Login Page</a></p>";
?> 