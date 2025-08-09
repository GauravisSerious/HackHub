<?php
// Include configuration file
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    die("Please login first");
}

echo "<h1>Team Members Fix</h1>";

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

echo "<p>Current user: $first_name $last_name ($username) - ID: $user_id</p>";

// Check if user has a team
$sql = "SELECT t.team_id, t.team_name, t.created_by FROM teams t WHERE t.created_by = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $team_id = $row['team_id'];
    $team_name = $row['team_name'];
    
    echo "<p>You created team: $team_name (ID: $team_id)</p>";
    
    // Check if user is already in team_members
    $sql = "SELECT id, is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($member = mysqli_fetch_assoc($result)) {
        echo "<p>You are already a member of this team (Member ID: {$member['id']})</p>";
        echo "<p>Leader status: " . ($member['is_leader'] ? "Yes" : "No") . "</p>";
        
        // Update to ensure user is leader
        if (!$member['is_leader']) {
            $sql = "UPDATE team_members SET is_leader = 1 WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $member['id']);
            
            if (mysqli_stmt_execute($stmt)) {
                echo "<p>Updated your status to team leader</p>";
            } else {
                echo "<p>Error updating leader status: " . mysqli_error($conn) . "</p>";
            }
        }
    } else {
        // Add user to team_members as leader
        $sql = "INSERT INTO team_members (team_id, user_id, is_leader) VALUES (?, ?, 1)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo "<p>Added you to your team as leader</p>";
        } else {
            echo "<p>Error adding to team: " . mysqli_error($conn) . "</p>";
        }
    }
    
    // Now let's check all members of the team
    $sql = "SELECT tm.id, tm.user_id, tm.is_leader, u.first_name, u.last_name, u.username
            FROM team_members tm
            JOIN users u ON tm.user_id = u.user_id
            WHERE tm.team_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    echo "<h2>Team Members:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Name</th><th>Username</th><th>Is Leader</th></tr>";
    
    while ($member = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $member['id'] . "</td>";
        echo "<td>" . $member['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($member['username']) . "</td>";
        echo "<td>" . ($member['is_leader'] ? "Yes" : "No") . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Add CSS fix for dashboard display
    echo "<h2>Adding CSS Fix:</h2>";
    
    $css_fix = '
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
</style>';

    // Add the CSS to dashboard
    $dashboard_file = __DIR__ . '/pages/dashboard.php';
    if (file_exists($dashboard_file)) {
        $dashboard_content = file_get_contents($dashboard_file);
        
        // Check if the CSS fix is already there
        if (strpos($dashboard_content, 'Fix for team members display') === false) {
            // Find the closing PHP tag before the footer
            $footer_include = "require_once '../includes/footer.php';";
            $fixed_content = str_replace($footer_include, $footer_include . "\n" . $css_fix, $dashboard_content);
            
            // Write the modified content back
            if (file_put_contents($dashboard_file, $fixed_content)) {
                echo "<p class='success'>Successfully added CSS fix to dashboard.php</p>";
            } else {
                echo "<p class='error'>Error writing to dashboard.php. Please update the file manually.</p>";
                echo "<pre>" . htmlspecialchars($css_fix) . "</pre>";
            }
        } else {
            echo "<p>CSS fix already present in dashboard.php</p>";
        }
    } else {
        echo "<p class='error'>Dashboard file not found at expected location. Please add this CSS manually:</p>";
        echo "<pre>" . htmlspecialchars($css_fix) . "</pre>";
    }
    
    // Also attempt to update the dashboard.php list-group-item code
    $has_updated_dashboard = false;
    
    if (file_exists($dashboard_file)) {
        $dashboard_content = file_get_contents($dashboard_file);
        
        // Replace the problematic team members code with improved version
        $original_code = "echo '<li class=\"list-group-item d-flex justify-content-between align-items-center\">';
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
        
        // Replace the code
        $modified_content = str_replace($original_code, $improved_code, $dashboard_content);
        
        // Check if we made a replacement
        if ($modified_content !== $dashboard_content) {
            if (file_put_contents($dashboard_file, $modified_content)) {
                $has_updated_dashboard = true;
                echo "<p class='success'>Successfully updated team member display code in dashboard.php</p>";
            } else {
                echo "<p class='error'>Error writing updated code to dashboard.php</p>";
            }
        } else {
            echo "<p>Could not locate the team member display code in dashboard.php. Manual update needed.</p>";
        }
    }
    
    if ($has_updated_dashboard) {
        echo "<p class='success'>All fixes have been applied. The team member display issue should be resolved!</p>";
    } else {
        echo "<p>Some manual fixes may still be needed. Please consider editing dashboard.php directly.</p>";
    }
    
    echo "<p>Go back to <a href='pages/dashboard.php'>dashboard</a> to see if the issue is fixed.</p>";
} else {
    echo "<p>You haven't created a team yet.</p>";
    
    // Check if user is a member of any team
    $sql = "SELECT tm.team_id, t.team_name, tm.is_leader 
            FROM team_members tm
            JOIN teams t ON tm.team_id = t.team_id
            WHERE tm.user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($member = mysqli_fetch_assoc($result)) {
        echo "<p>You are a member of team: " . htmlspecialchars($member['team_name']) . " (ID: {$member['team_id']})</p>";
        echo "<p>Leader status: " . ($member['is_leader'] ? "Yes" : "No") . "</p>";
        
        echo "<p>Go back to <a href='pages/dashboard.php'>dashboard</a> to see if the issue is fixed.</p>";
    } else {
        echo "<p>You are not a member of any team.</p>";
    }
}
?> 