<?php
// Fix script to add auth-new.php includes to all pages
require_once 'includes/config.php';

// Only allow admin to run this script
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die("This script can only be run by administrators.");
}

// List of directories to check
$directories = [
    'pages/',
    'includes/',
    './'  // Root directory
];

$modified_files = [];
$errors = [];

foreach ($directories as $directory) {
    $files = glob($directory . '*.php');
    
    foreach ($files as $file) {
        // Skip this file and the auth-new.php file itself
        if ($file === __FILE__ || $file === 'includes/auth-new.php') {
            continue;
        }
        
        // Read file contents
        $content = file_get_contents($file);
        
        // Check if the file calls isLoggedIn(), isAdmin(), etc., but doesn't include auth-new.php
        if ((strpos($content, 'isLoggedIn()') !== false || 
             strpos($content, 'isAdmin()') !== false || 
             strpos($content, 'isTeamLeader(') !== false ||
             strpos($content, 'isTeamMember(') !== false) && 
            strpos($content, 'auth-new.php') === false) {
            
            // Find the position after config.php include
            $pos = strpos($content, "require_once '../includes/config.php';");
            if ($pos === false) {
                $pos = strpos($content, "require_once 'includes/config.php';");
            }
            
            if ($pos !== false) {
                // Determine the auth-new.php path
                $auth_path = (strpos($file, 'pages/') === 0) ? 
                    "../includes/auth-new.php" : 
                    "includes/auth-new.php";
                
                // Find the end of the line with config.php
                $end_of_line = strpos($content, "\n", $pos);
                
                // Insert auth-new.php include after config.php include
                $new_content = substr($content, 0, $end_of_line + 1) . 
                               "// Include auth functions\n" .
                               "require_once '" . $auth_path . "';\n" .
                               substr($content, $end_of_line + 1);
                
                // Add output buffering for includes to catch any BOM issues
                $ob_start_pos = strpos($new_content, "<?php");
                if ($ob_start_pos !== false) {
                    $ob_code = "<?php\n// Start output buffering to catch any unexpected output from includes\nob_start();\n\n";
                    $new_content = $ob_code . substr($new_content, $ob_start_pos + 5);
                    
                    // Find position to add the ob_get_clean code (after requires)
                    $pos_after_requires = strpos($new_content, $auth_path);
                    if ($pos_after_requires !== false) {
                        $pos_after_requires = strpos($new_content, "\n", $pos_after_requires);
                        if ($pos_after_requires !== false) {
                            $ob_end_code = "\n\n// Capture any output from includes\n" .
                                           "\$unexpected_output = ob_get_clean();\n\n" .
                                           "// If there's unexpected output (like BOM), log it but don't display it\n" .
                                           "if (!empty(\$unexpected_output)) {\n" .
                                           "    error_log(\"Unexpected output from includes: \" . bin2hex(\$unexpected_output));\n" .
                                           "}\n";
                            $new_content = substr($new_content, 0, $pos_after_requires + 1) . 
                                           $ob_end_code . 
                                           substr($new_content, $pos_after_requires + 1);
                        }
                    }
                }
                
                // Write the modified content back to the file
                try {
                    file_put_contents($file, $new_content);
                    $modified_files[] = $file;
                } catch (Exception $e) {
                    $errors[] = "Failed to update file {$file}: " . $e->getMessage();
                }
            } else {
                $errors[] = "Could not find config.php include in {$file}";
            }
        }
    }
}

// Display results
echo "<h1>Auth Include Fix Results</h1>";

if (!empty($modified_files)) {
    echo "<h2>Modified Files:</h2>";
    echo "<ul>";
    foreach ($modified_files as $file) {
        echo "<li>{$file}</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h2>Errors:</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>{$error}</li>";
    }
    echo "</ul>";
}

if (empty($modified_files) && empty($errors)) {
    echo "<p>No files needed modification.</p>";
}
?> 