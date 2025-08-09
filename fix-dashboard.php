<?php
// Fix script for the dashboard.php team members display issue

// Include configuration file
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    die("Please login first");
}

echo "<h1>Dashboard Team Members Display Fix</h1>";

// Check if user is admin
if (!isAdmin()) {
    echo "<p style='color:red;'>WARNING: This script should be run by an administrator. Some operations may fail due to permission issues.</p>";
}

// Path to the dashboard file
$dashboard_file = __DIR__ . '/pages/dashboard.php';

if (!file_exists($dashboard_file)) {
    die("<p style='color:red;'>Error: dashboard.php file not found at: $dashboard_file</p>");
}

// Read dashboard file content
$dashboard_content = file_get_contents($dashboard_file);

// Step 1: Add CSS fix
echo "<h2>Step 1: Adding CSS Fix</h2>";

$css_fix = '
<!-- Fix for team members display issues -->
<style>
/* Fix for team members display */
.list-group-item {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}
.list-group-item .badge {
    display: inline-block !important; 
    visibility: visible !important;
}
</style>
';

// Check if the CSS fix is already there
if (strpos($dashboard_content, 'Fix for team members display') === false) {
    // Find the closing PHP tag before the footer
    $footer_include = "require_once '../includes/footer.php';";
    $fixed_content = str_replace($footer_include, $footer_include . "\n" . $css_fix, $dashboard_content);
    
    // Write the modified content back
    if (file_put_contents($dashboard_file, $fixed_content)) {
        echo "<p style='color:green;'>✓ Successfully added CSS fix to dashboard.php</p>";
        // Update content for next steps
        $dashboard_content = $fixed_content;
    } else {
        echo "<p style='color:red;'>✗ Error writing to dashboard.php. Please update the file manually with this CSS:</p>";
        echo "<pre>" . htmlspecialchars($css_fix) . "</pre>";
    }
} else {
    echo "<p style='color:blue;'>ℹ CSS fix already present in dashboard.php</p>";
}

// Step 2: Update team members display code
echo "<h2>Step 2: Updating Team Members Display Code</h2>";

// Identify the pattern to replace
$original_pattern = "echo '<li class=\"list-group-item d-flex justify-content-between align-items-center\">';
                                        echo htmlspecialchars(\$member['first_name'] . ' ' . \$member['last_name']) . 
                                             ' <span class=\"text-muted\">(@' . htmlspecialchars(\$member['username']) . ')</span>';
                                        if (\$member['is_leader']) {
                                            echo '<span class=\"badge bg-primary\">Team Leader</span>';
                                        }
                                        echo '</li>';";
        
$improved_code = "echo '<li class=\"list-group-item d-flex justify-content-between align-items-center\">';
                                        echo '<div>' . htmlspecialchars(\$member['first_name'] . ' ' . \$member['last_name']) . 
                                             ' <span class=\"text-muted\">(@' . htmlspecialchars(\$member['username']) . ')</span></div>';
                                        if (\$member['is_leader']) {
                                            echo '<span class=\"badge bg-primary\">Team Leader</span>';
                                        } else {
                                            echo '<span class=\"badge bg-secondary\">Member</span>';
                                        }
                                        echo '</li>';";

// Try to replace the code
$modified_content = str_replace($original_pattern, $improved_code, $dashboard_content);

// Check if the replacement worked
if ($modified_content !== $dashboard_content) {
    if (file_put_contents($dashboard_file, $modified_content)) {
        echo "<p style='color:green;'>✓ Successfully updated team member display code in dashboard.php</p>";
    } else {
        echo "<p style='color:red;'>✗ Error writing updated code to dashboard.php</p>";
        echo "<p>Please manually replace this code:</p>";
        echo "<pre>" . htmlspecialchars($original_pattern) . "</pre>";
        echo "<p>With this improved code:</p>";
        echo "<pre>" . htmlspecialchars($improved_code) . "</pre>";
    }
} else {
    echo "<p style='color:orange;'>⚠ Could not locate the exact team member display code in dashboard.php.</p>";
    echo "<p>Trying alternative approach...</p>";
    
    // Try a more targeted replacement approach
    $needle1 = 'echo htmlspecialchars($member[\'first_name\'] . \' \' . $member[\'last_name\'])';
    $needle2 = 'if ($member[\'is_leader\'])';
    $needle3 = '<span class="badge bg-primary">Team Leader</span>';
    
    // Find all instances of the three needles
    $pos1 = strpos($dashboard_content, $needle1);
    $pos2 = strpos($dashboard_content, $needle2);
    $pos3 = strpos($dashboard_content, $needle3);
    
    if ($pos1 !== false && $pos2 !== false && $pos3 !== false) {
        // Find the starting list item
        $start_li = strrpos(substr($dashboard_content, 0, $pos1), '<li class=');
        // Find the ending list item
        $end_li = strpos($dashboard_content, '</li>', $pos3) + 5;
        
        if ($start_li !== false && $end_li !== false) {
            // Extract the whole pattern
            $full_pattern = substr($dashboard_content, $start_li, $end_li - $start_li);
            echo "<p>Found code to replace:</p>";
            echo "<pre>" . htmlspecialchars($full_pattern) . "</pre>";
            
            // Create new content
            $new_content = substr($dashboard_content, 0, $start_li);
            $new_content .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>' . htmlspecialchars($member[\'first_name\'] . \' \' . $member[\'last_name\']) . 
                                     \' <span class="text-muted">(@\' . htmlspecialchars($member[\'username\']) . \')</span></div>\';
                                if ($member[\'is_leader\']) {
                                    echo \'<span class="badge bg-primary">Team Leader</span>\';
                                } else {
                                    echo \'<span class="badge bg-secondary">Member</span>\';
                                }
                                echo \'</li>\';';
            $new_content .= substr($dashboard_content, $end_li);
            
            if (file_put_contents($dashboard_file, $new_content)) {
                echo "<p style='color:green;'>✓ Successfully updated team member display code using alternative approach</p>";
            } else {
                echo "<p style='color:red;'>✗ Error writing updated code using alternative approach</p>";
            }
        } else {
            echo "<p style='color:red;'>✗ Could not find complete list item pattern</p>";
        }
    } else {
        echo "<p style='color:red;'>✗ Could not find all necessary code patterns</p>";
    }
    
    echo "<p>Manual fix instructions:</p>";
    echo "<p>Please edit the dashboard.php file around line 345-355 and update the team member display code to:</p>";
    echo "<pre>" . htmlspecialchars($improved_code) . "</pre>";
}

echo "<h2>Fix Complete</h2>";
echo "<p>Please return to the <a href='pages/dashboard.php'>dashboard</a> to check if the issue is fixed.</p>";
echo "<p>If you still have issues, you can also try the original <a href='fix-team-members.php'>fix-team-members.php</a> script.</p>";
?> 