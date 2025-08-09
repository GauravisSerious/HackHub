<?php
// Test file to check for auth.php issues

// Display error messages
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Starting test...\n";

// Write file contents to temp file to check for BOM
$test_content = file_get_contents(__FILE__);
$test_hex = bin2hex(substr($test_content, 0, 10));
echo "First 10 bytes of test-auth.php (hex): $test_hex\n";

$auth_content = file_get_contents('includes/auth.php');
$auth_hex = bin2hex(substr($auth_content, 0, 10));
echo "First 10 bytes of auth.php (hex): $auth_hex\n";

$config_content = file_get_contents('includes/config.php');
$config_hex = bin2hex(substr($config_content, 0, 10));
echo "First 10 bytes of config.php (hex): $config_hex\n";

// Check for headers already sent
if (headers_sent($filename, $linenum)) {
    echo "Headers already sent in $filename on line $linenum\n";
}

// Try to include config and auth with output buffering
ob_start();
echo "Starting to include files...\n";

// Include config first
require_once 'includes/config.php';
echo "Config file loaded successfully\n";

// Now include auth.php
require_once 'includes/auth.php';
echo "Auth file loaded successfully\n";

// Output what we captured
$output = ob_get_clean();
echo "Output from includes: $output\n";

// Check if functions are defined correctly
if (function_exists('isLoggedIn') && function_exists('isAdmin') && function_exists('isTeamLeader')) {
    echo "All required auth functions are defined correctly.\n";
} else {
    echo "ERROR: Some auth functions are not defined correctly.\n";
}

echo "Test complete.\n";
?> 