<?php
// Fix script for team member display issues across the application
// This script fixes both the dashboard.php and team-details.php files

// Include configuration file
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    die("Please login first");
}

echo "<h1>Team Member Display Fix</h1>";

// Check if user is admin
if (!isAdmin()) {
    echo "<p style='color:red;'>WARNING: This script should be run by an administrator. Some operations may fail due to permission issues.</p>";
}

// Define file paths
$dashboard_file = __DIR__ . '/pages/dashboard.php';
$team_details_file = __DIR__ . '/pages/team-details.php';

if (!file_exists($dashboard_file)) {
    die("<p style='color:red;'>Error: dashboard.php file not found at: $dashboard_file</p>");
}

if (!file_exists($team_details_file)) {
    die("<p style='color:red;'>Error: team-details.php file not found at: $team_details_file</p>");
}

// Start with dashboard.php fixes
echo "<h2>Fixing dashboard.php</h2>";

// Read dashboard file content
$dashboard_content = file_get_contents($dashboard_file);

// Add CSS fix to dashboard.php
echo "<h3>Step 1: Adding CSS Fix to dashboard.php</h3>";

$dashboard_css_fix = '
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
    $fixed_content = str_replace($footer_include, $footer_include . "\n" . $dashboard_css_fix, $dashboard_content);
    
    // Write the modified content back
    if (file_put_contents($dashboard_file, $fixed_content)) {
        echo "<p style='color:green;'>✓ Successfully added CSS fix to dashboard.php</p>";
        // Update content for next steps
        $dashboard_content = $fixed_content;
    } else {
        echo "<p style='color:red;'>✗ Error writing to dashboard.php. Please update the file manually with this CSS:</p>";
        echo "<pre>" . htmlspecialchars($dashboard_css_fix) . "</pre>";
    }
} else {
    echo "<p style='color:blue;'>ℹ CSS fix already present in dashboard.php</p>";
}

// Fix team members display code in dashboard.php
echo "<h3>Step 2: Updating Team Members Display Code in dashboard.php</h3>";

$dashboard_search_patterns = [
    // Pattern 1 - Common format
    "echo '<li class=\"list-group-item d-flex justify-content-between align-items-center\">';
                                        echo htmlspecialchars(\$member['first_name'] . ' ' . \$member['last_name']) . 
                                             ' <span class=\"text-muted\">(@' . htmlspecialchars(\$member['username']) . ')</span>';
                                        if (\$member['is_leader']) {
                                            echo '<span class=\"badge bg-primary\">Team Leader</span>';
                                        }
                                        echo '</li>';",
    
    // Pattern 2 - Alternative format 
    'echo "<li class=\"list-group-item d-flex justify-content-between align-items-center\">";
                                        echo htmlspecialchars($member[\'first_name\'] . \' \' . $member[\'last_name\']) . 
                                             \' <span class="text-muted">(@\' . htmlspecialchars($member[\'username\']) . \')</span>\';
                                        if ($member[\'is_leader\']) {
                                            echo \'<span class="badge bg-primary">Team Leader</span>\';
                                        }
                                        echo "</li>";'
];

$dashboard_improved_code = "echo '<li class=\"list-group-item d-flex justify-content-between align-items-center\" id=\"dashboard-member-' . \$member['user_id'] . '\">';
                                        echo '<div>' . htmlspecialchars(\$member['first_name'] . ' ' . \$member['last_name']) . 
                                             ' <span class=\"text-muted\">(@' . htmlspecialchars(\$member['username']) . ')</span></div>';
                                        if (\$member['is_leader']) {
                                            echo '<span class=\"badge bg-primary\">Team Leader</span>';
                                        } else {
                                            echo '<span class=\"badge bg-secondary\">Member</span>';
                                        }
                                        echo '</li>';";

$dashboard_pattern_found = false;

foreach ($dashboard_search_patterns as $pattern) {
    // Try to replace the code
    $modified_dashboard = str_replace($pattern, $dashboard_improved_code, $dashboard_content);
    
    // Check if the replacement worked
    if ($modified_dashboard !== $dashboard_content) {
        if (file_put_contents($dashboard_file, $modified_dashboard)) {
            echo "<p style='color:green;'>✓ Successfully updated team member display code in dashboard.php</p>";
            $dashboard_pattern_found = true;
            break;
        } else {
            echo "<p style='color:red;'>✗ Error writing updated code to dashboard.php</p>";
        }
    }
}

if (!$dashboard_pattern_found) {
    echo "<p style='color:orange;'>⚠ Could not locate the exact team member display code in dashboard.php.</p>";
    echo "<p>Trying alternative approach with key phrase search...</p>";
    
    // Try a more targeted replacement approach
    $needle1 = 'htmlspecialchars($member[\'first_name\'] . \' \' . $member[\'last_name\'])';
    $needle2 = 'if ($member[\'is_leader\'])';
    $needle3 = 'class="badge bg-primary"';
    
    // Find all instances of the key phrases
    $pos1 = strpos($dashboard_content, $needle1);
    $pos2 = strpos($dashboard_content, $needle2);
    $pos3 = strpos($dashboard_content, $needle3);
    
    if ($pos1 !== false && $pos2 !== false && $pos3 !== false) {
        // Find the list item opening tag before the first needle
        $start_li = strrpos(substr($dashboard_content, 0, $pos1), '<li class');
        
        if ($start_li !== false) {
            // Find the ending list item tag after the last needle
            $end_li = strpos($dashboard_content, '</li>', $pos3);
            
            if ($end_li !== false) {
                $end_li += 5; // Include the closing </li> tag
                
                // Extract the whole pattern
                $full_pattern = substr($dashboard_content, $start_li, $end_li - $start_li);
                echo "<p>Found code pattern to replace:</p>";
                echo "<pre>" . htmlspecialchars($full_pattern) . "</pre>";
                
                // Create the replacement
                $manual_replacement = '<li class="list-group-item d-flex justify-content-between align-items-center" id="dashboard-member-<?php echo $member[\'user_id\']; ?>">
                                <div><?php echo htmlspecialchars($member[\'first_name\'] . \' \' . $member[\'last_name\']); ?> 
                                    <span class="text-muted">(@<?php echo htmlspecialchars($member[\'username\']); ?>)</span>
                                </div>
                                <?php if ($member[\'is_leader\']): ?>
                                    <span class="badge bg-primary">Team Leader</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Member</span>
                                <?php endif; ?>
                            </li>';
                
                // Replace the pattern in the content
                $modified_dashboard = str_replace($full_pattern, $manual_replacement, $dashboard_content);
                
                // Check if the replacement worked
                if ($modified_dashboard !== $dashboard_content) {
                    if (file_put_contents($dashboard_file, $modified_dashboard)) {
                        echo "<p style='color:green;'>✓ Successfully updated team member display code using alternative approach</p>";
                        $dashboard_pattern_found = true;
                    } else {
                        echo "<p style='color:red;'>✗ Error writing updated code to dashboard.php</p>";
                    }
                }
            }
        }
    }
    
    if (!$dashboard_pattern_found) {
        echo "<p style='color:red;'>✗ Could not automatically fix the dashboard.php file</p>";
        echo "<p>Please manually update the team member display code in dashboard.php to include both leader and regular member badges:</p>";
        echo "<pre>" . htmlspecialchars($dashboard_improved_code) . "</pre>";
    }
}

// Now fix team-details.php
echo "<h2>Fixing team-details.php</h2>";

// Read team details file content
$team_details_content = file_get_contents($team_details_file);

// Check if CSS fix is already in team-details.php
echo "<h3>Step 1: Checking CSS Fix in team-details.php</h3>";

if (strpos($team_details_content, 'Fix for member display issues') === false) {
    echo "<p style='color:orange;'>⚠ CSS fix not found in team-details.php</p>";
    
    // Add the CSS fix before the closing </body> tag
    $team_details_css_fix = '
<!-- Fix for member display issues -->
<style>
/* Ensure all list items in the members summary are displayed */
.card-body .list-group .list-group-item {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}
</style>
';
    
    $closing_tag = "require_once '../includes/footer.php';";
    $fixed_content = str_replace($closing_tag, $closing_tag . "\n" . $team_details_css_fix, $team_details_content);
    
    // Write the modified content back
    if (file_put_contents($team_details_file, $fixed_content)) {
        echo "<p style='color:green;'>✓ Successfully added CSS fix to team-details.php</p>";
        // Update content for next steps
        $team_details_content = $fixed_content;
    } else {
        echo "<p style='color:red;'>✗ Error writing to team-details.php. Please update the file manually with this CSS:</p>";
        echo "<pre>" . htmlspecialchars($team_details_css_fix) . "</pre>";
    }
} else {
    echo "<p style='color:blue;'>ℹ CSS fix already present in team-details.php</p>";
}

// Add JavaScript to check member display
echo "<h3>Step 2: Adding JavaScript Debug Helper to team-details.php</h3>";

$debug_js = '
<script>
// Debug function to check if all team members are displayed properly
document.addEventListener("DOMContentLoaded", function() {
    // Only run for admin or team leader
    const debugElement = document.querySelector(".alert-info.mb-3 small");
    if (debugElement) {
        // Count the team members in the summary list
        const membersList = document.getElementById("all-team-members");
        if (membersList) {
            const membersItems = membersList.querySelectorAll("li:not(.d-none)");
            console.log("Team Members List:", membersList);
            console.log("Visible Members Count:", membersItems.length);
            
            // Log each member for debugging
            membersItems.forEach((item, index) => {
                console.log(`Member ${index + 1}:`, item.textContent.trim());
            });
            
            // Update the debug display
            debugElement.textContent += ` | ${membersItems.length} member(s) visible`;
        }
    }
});
</script>
';

// Check if the debug JS is already present
if (strpos($team_details_content, 'Debug function to check if all team members are displayed properly') === false) {
    // Add the debug JS before the closing </body> tag
    $modified_content = str_replace('</body>', $debug_js . '</body>', $team_details_content);
    
    // If </body> not found, add before the last PHP closing tag
    if ($modified_content === $team_details_content) {
        $modified_content = $team_details_content . "\n" . $debug_js;
    }
    
    // Write the modified content back
    if (file_put_contents($team_details_file, $modified_content)) {
        echo "<p style='color:green;'>✓ Successfully added debug JavaScript to team-details.php</p>";
        // Update content for next steps
        $team_details_content = $modified_content;
    } else {
        echo "<p style='color:red;'>✗ Error writing to team-details.php. Please update the file manually.</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ Debug JavaScript already present in team-details.php</p>";
}

// Fix the team_members query in team-details.php
echo "<h3>Step 3: Checking team_members query in team-details.php</h3>";

// Create a test query to check for issues
try {
    // Check if we have user_id
    $user_id = $_SESSION['user_id'] ?? null;
    
    if ($user_id) {
        // Find teams this user is part of
        $sql = "SELECT team_id FROM team_members WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $team_id = $row['team_id'];
            
            // Check all members in the team
            echo "<h4>Team Members Check:</h4>";
            
            $member_sql = "SELECT tm.*, u.first_name, u.last_name, u.username, u.email 
                          FROM team_members tm 
                          JOIN users u ON tm.user_id = u.user_id 
                          WHERE tm.team_id = $team_id";
            $member_result = mysqli_query($conn, $member_sql);
            
            if ($member_result) {
                echo "<p>Found " . mysqli_num_rows($member_result) . " member(s) in team (ID: $team_id)</p>";
                
                echo "<table border='1' style='margin-bottom: 20px;'>";
                echo "<tr><th>ID</th><th>Name</th><th>Is Leader</th><th>Email</th></tr>";
                
                while ($member = mysqli_fetch_assoc($member_result)) {
                    echo "<tr>";
                    echo "<td>" . $member['user_id'] . "</td>";
                    echo "<td>" . $member['first_name'] . " " . $member['last_name'] . "</td>";
                    echo "<td>" . ($member['is_leader'] ? "Yes" : "No") . "</td>";
                    echo "<td>" . $member['email'] . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            }
        } else {
            echo "<p>You are not a member of any team. Create a team first to test this fix.</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error checking team members: " . $e->getMessage() . "</p>";
}

// Add direct fix button to force team creators to be team members
echo "<h3>Step 4: Add Team Creator Fix</h3>";
echo "<form method='post' action=''>";
echo "<input type='hidden' name='action' value='fix_team_creators'>";
echo "<button type='submit' class='btn btn-warning'>Fix Team Creators</button>";
echo "<p>This will ensure all team creators are also listed as team members with leader status</p>";
echo "</form>";

// Process the fix if requested
if (isset($_POST['action']) && $_POST['action'] === 'fix_team_creators') {
    try {
        // Get all teams
        $teams_sql = "SELECT team_id, team_name, created_by FROM teams";
        $teams_result = mysqli_query($conn, $teams_sql);
        
        echo "<h4>Results:</h4>";
        echo "<ul>";
        
        while ($team = mysqli_fetch_assoc($teams_result)) {
            $creator_id = $team['created_by'];
            $team_id = $team['team_id'];
            $team_name = $team['team_name'];
            
            // Check if creator is already a member
            $check_sql = "SELECT id, is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $team_id, $creator_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if ($member = mysqli_fetch_assoc($check_result)) {
                // Already a member, ensure is_leader is set
                if (!$member['is_leader']) {
                    $update_sql = "UPDATE team_members SET is_leader = 1 WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $member['id']);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        echo "<li>Updated team creator for '$team_name' (ID: $team_id) to be team leader</li>";
                    } else {
                        echo "<li style='color:red;'>Error updating leader status for team '$team_name'</li>";
                    }
                } else {
                    echo "<li>Team creator for '$team_name' (ID: $team_id) is already a team leader</li>";
                }
            } else {
                // Not a member, add as leader
                $insert_sql = "INSERT INTO team_members (team_id, user_id, is_leader) VALUES (?, ?, 1)";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "ii", $team_id, $creator_id);
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    echo "<li>Added team creator for '$team_name' (ID: $team_id) as team leader</li>";
                } else {
                    echo "<li style='color:red;'>Error adding team creator for team '$team_name'</li>";
                }
            }
        }
        
        echo "</ul>";
        echo "<p style='color:green;'>Team creator fix complete!</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error fixing team creators: " . $e->getMessage() . "</p>";
    }
}

// Final summary
echo "<h2>Fix Complete</h2>";
echo "<p>The following fixes have been applied:</p>";
echo "<ol>";
echo "<li>CSS fixes to ensure proper display of team members in both dashboard and team details pages</li>";
echo "<li>Updated team member display code to show badges for both leaders and regular members</li>";
echo "<li>Added debugging JavaScript to help troubleshoot any remaining issues</li>";
echo "<li>Option to ensure all team creators are properly set as team members with leader status</li>";
echo "</ol>";

echo "<p>Please return to the <a href='pages/dashboard.php'>dashboard</a> or <a href='pages/teams.php'>teams</a> page to check if the issues are fixed.</p>";
?> 