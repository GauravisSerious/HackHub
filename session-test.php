<?php
// Basic session test
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Starting session test...\n";

// Check for headers
if (headers_sent($file, $line)) {
    echo "Headers already sent in $file on line $line\n";
} else {
    echo "No headers have been sent yet\n";
    
    // Try to start a session
    session_start();
    echo "Session started successfully\n";
}

echo "Test complete\n";
?> 