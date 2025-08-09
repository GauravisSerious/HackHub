<?php
// Include configuration file
require_once 'includes/config.php';

// Get parameters
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$is_leader = isset($_GET['leader']) ? (intval($_GET['leader']) > 0 ? 1 : 0) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : null;

// Basic styling
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
    .container { max-width: 800px; margin: 0 auto; }
    .card { border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .btn { display: inline-block; padding: 10px 15px; text-decoration: none; background-color: #4CAF50; color: white; border-radius: 4px; }
    .btn-danger { background-color: #f44336; }
    .btn-warning { background-color: #ff9800; }
</style>";

echo "<div class='container'>";
echo "<h1>Add Team Member</h1>";

// Validate inputs
if (!$team_id || !$user_id) {
    echo "<div class='card error'>";
    echo "<p>Missing required parameters. Both team_id and user_id must be provided.</p>";
    echo "<p><a href='debug-team-members.php' class='btn'>Back to Teams</a></p>";
    echo "</div>";
    exit;
}

// Check if team exists
$team_query = "SELECT team_name FROM teams WHERE team_id = ?";
$stmt = mysqli_prepare($conn, $team_query);
mysqli_stmt_bind_param($stmt, "i", $team_id);
mysqli_stmt_execute($stmt);
$team_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($team_result) === 0) {
    echo "<div class='card error'>";
    echo "<p>Team with ID $team_id does not exist.</p>";
    echo "<p><a href='debug-team-members.php' class='btn'>Back to Teams</a></p>";
    echo "</div>";
    exit;
}

$team = mysqli_fetch_assoc($team_result);

// Check if user exists
$user_query = "SELECT CONCAT(first_name, ' ', last_name) AS name, email FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($user_result) === 0) {
    echo "<div class='card error'>";
    echo "<p>User with ID $user_id does not exist.</p>";
    echo "<p><a href='debug-team-members.php?id=$team_id' class='btn'>Back to Team</a></p>";
    echo "</div>";
    exit;
}

$user = mysqli_fetch_assoc($user_result);

// Check if already a member
$check_query = "SELECT is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
mysqli_stmt_execute($stmt);
$check_result = mysqli_stmt_get_result($stmt);
$already_member = mysqli_num_rows($check_result) > 0;
$current_role = $already_member ? mysqli_fetch_assoc($check_result)['is_leader'] : null;

// Display confirmation if no action specified
if (!$action) {
    echo "<div class='card'>";
    echo "<h2>Confirmation</h2>";
    echo "<p>You're about to add the following user to a team:</p>";
    echo "<p><strong>User:</strong> " . htmlspecialchars($user['name']) . " (ID: $user_id)</p>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
    echo "<p><strong>Team:</strong> " . htmlspecialchars($team['team_name']) . " (ID: $team_id)</p>";
    echo "<p><strong>Role:</strong> " . ($is_leader ? "Leader" : "Member") . "</p>";
    
    if ($already_member) {
        echo "<div class='warning'>";
        echo "<p>⚠️ This user is already a member of this team with role: " . ($current_role ? "Leader" : "Member") . "</p>";
        if ($current_role != $is_leader) {
            echo "<p>Their role will be changed to: " . ($is_leader ? "Leader" : "Member") . "</p>";
        } else {
            echo "<p>No changes will be made to their role.</p>";
        }
        echo "</div>";
    }
    
    echo "<p><a href='?team_id=$team_id&user_id=$user_id&leader=$is_leader&action=confirm' class='btn'>Confirm</a> ";
    echo "<a href='debug-team-members.php?id=$team_id' class='btn btn-danger'>Cancel</a></p>";
    echo "</div>";
    exit;
}

// Process the action
if ($action === 'confirm') {
    try {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        if ($already_member) {
            // Update role if different
            if ($current_role != $is_leader) {
                $update_query = "UPDATE team_members SET is_leader = ? WHERE team_id = ? AND user_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "iii", $is_leader, $team_id, $user_id);
                $result = mysqli_stmt_execute($stmt);
                
                if (!$result) {
                    throw new Exception("Failed to update member role: " . mysqli_error($conn));
                }
                
                $message = "User role updated successfully!";
            } else {
                $message = "No changes were made. User already has the specified role.";
            }
        } else {
            // Insert new team member
            $insert_query = "INSERT INTO team_members (team_id, user_id, is_leader, joined_at) VALUES (?, ?, ?, NOW())";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iii", $team_id, $user_id, $is_leader);
            $result = mysqli_stmt_execute($stmt);
            
            if (!$result) {
                throw new Exception("Failed to add team member: " . mysqli_error($conn));
            }
            
            $message = "User added to team successfully!";
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Show success
        echo "<div class='card success'>";
        echo "<h2>Success</h2>";
        echo "<p>$message</p>";
        echo "<p><a href='debug-team-members.php?id=$team_id' class='btn'>View Team</a> ";
        echo "<a href='list-users.php?team_id=$team_id' class='btn'>Add More Members</a></p>";
        echo "</div>";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        
        echo "<div class='card error'>";
        echo "<h2>Error</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "<p><a href='debug-team-members.php?id=$team_id' class='btn'>Back to Team</a></p>";
        echo "</div>";
    }
} else {
    echo "<div class='card error'>";
    echo "<p>Invalid action.</p>";
    echo "<p><a href='debug-team-members.php?id=$team_id' class='btn'>Back to Team</a></p>";
    echo "</div>";
}

echo "</div>";
?> 