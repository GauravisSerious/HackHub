<?php
session_start();
require_once 'includes/config.php';

echo "<h1>Profile Image Debug</h1>";
echo "<p>Current profile image in session: " . ($_SESSION['profile_image'] ?? 'not set') . "</p>";

// Check if the profile image file exists
if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
    $filepath = $_SESSION['profile_image'];
    $absolute_path = __DIR__ . '/' . $filepath;
    
    echo "<p>Checking file path: " . $absolute_path . "</p>";
    
    if (file_exists($absolute_path)) {
        echo "<p style='color:green'>File exists!</p>";
    } else {
        echo "<p style='color:red'>File does not exist!</p>";
    }
    
    // Try alternative paths
    $alt_path = __DIR__ . '/uploads/profile_pics/' . basename($filepath);
    echo "<p>Checking alternative path: " . $alt_path . "</p>";
    if (file_exists($alt_path)) {
        echo "<p style='color:green'>File exists at alternative path!</p>";
    } else {
        echo "<p style='color:red'>File does not exist at alternative path!</p>";
    }
    
    // Display the image with different paths
    echo "<h2>Image Test</h2>";
    echo "<p>Using session path:</p>";
    echo "<img src='" . BASE_URL . "/" . $filepath . "' width='100' height='100'>";
    
    echo "<p>Using alternative path:</p>";
    echo "<img src='" . BASE_URL . "/uploads/profile_pics/" . basename($filepath) . "' width='100' height='100'>";
}
?> 