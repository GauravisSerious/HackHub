<?php
// Fix script to remove emergency displays from team-details.php
require_once 'includes/config.php';
require_once 'includes/auth-new.php';

// Only allow admin to run this script
if (!isAdmin()) {
    die("This script can only be run by administrators.");
}

$target_file = __DIR__ . '/pages/team-details.php';

// Read the file content
$content = file_get_contents($target_file);

// Patterns to find and remove
$patterns = [
    // Emergency Static Display
    '/<!-- EMERGENCY STATIC DISPLAY -->.*?<div class="mt-4 emergency-display">.*?<\/div>/s',
    
    // Hardcoded Fallback displays
    '/<!-- ABSOLUTELY GUARANTEED HARDCODED DISPLAY -->.*?<\/div>\s*<\?php endif; \?>/s'
];

// Replace with empty string
$replacements = ['', ''];

// Apply the replacements
$new_content = preg_replace($patterns, $replacements, $content);

// Write back to file
if ($new_content !== $content && $new_content !== null) {
    // Create a backup first
    file_put_contents($target_file . '.bak', $content);
    
    // Write the cleaned content
    file_put_contents($target_file, $new_content);
    
    echo "<h1>Team Display Fixed</h1>";
    echo "<p>Successfully removed emergency displays and hardcoded fallbacks from team-details.php.</p>";
    echo "<p>A backup has been created as team-details.php.bak</p>";
    echo "<p><a href='pages/teams.php' class='btn btn-primary'>Go to Teams</a></p>";
} else {
    echo "<h1>No Changes Needed</h1>";
    echo "<p>The emergency displays and hardcoded fallbacks were not found or there was an error with the replacements.</p>";
    echo "<p><a href='pages/teams.php' class='btn btn-primary'>Go to Teams</a></p>";
}
?> 