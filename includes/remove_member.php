<?php
// Include configuration file
require_once 'config.php';
// Include auth functions
require_once 'auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to manage team members";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if member ID and team ID are provided
if (!isset($_POST['member_id']) || !isset($_POST['team_id']) || empty($_POST['member_id']) || empty($_POST['team_id'])) {
    // Set message
    $_SESSION['message'] = "Invalid request";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$member_id = intval($_POST['member_id']);
$team_id = intval($_POST['team_id']);
$current_user_id = $_SESSION['user_id'];

// First check if the member to remove is a team leader
$check_leader_sql = "SELECT is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
if ($check_stmt = mysqli_prepare($conn, $check_leader_sql)) {
    mysqli_stmt_bind_param($check_stmt, "ii", $team_id, $member_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        mysqli_stmt_bind_result($check_stmt, $is_leader);
        mysqli_stmt_fetch($check_stmt);
        
        if ($is_leader) {
            // Cannot remove a team leader
            $_SESSION['message'] = "Cannot remove the team leader. The leader must transfer leadership before leaving the team.";
            $_SESSION['message_type'] = "warning";
            header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
            exit();
        }
    }
    
    mysqli_stmt_close($check_stmt);
}

// Check if current user is team leader or admin
$is_authorized = false;

// Check for team leader
$leader_sql = "SELECT is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
if ($leader_stmt = mysqli_prepare($conn, $leader_sql)) {
    mysqli_stmt_bind_param($leader_stmt, "ii", $team_id, $current_user_id);
    mysqli_stmt_execute($leader_stmt);
    mysqli_stmt_store_result($leader_stmt);
    
    if (mysqli_stmt_num_rows($leader_stmt) > 0) {
        mysqli_stmt_bind_result($leader_stmt, $is_leader);
        mysqli_stmt_fetch($leader_stmt);
        $is_authorized = $is_leader;
    }
    
    mysqli_stmt_close($leader_stmt);
}

// If user is an admin or themselves (can remove themselves from a team)
if (isAdmin() || $current_user_id == $member_id) {
    $is_authorized = true;
}

if (!$is_authorized) {
    $_SESSION['message'] = "You don't have permission to remove members from this team";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
    exit();
}

// Remove the member
$delete_sql = "DELETE FROM team_members WHERE team_id = ? AND user_id = ?";
if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
    mysqli_stmt_bind_param($delete_stmt, "ii", $team_id, $member_id);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        // Get team and user details for the notification
        $team_name_sql = "SELECT team_name FROM teams WHERE team_id = ?";
        $team_stmt = mysqli_prepare($conn, $team_name_sql);
        mysqli_stmt_bind_param($team_stmt, "i", $team_id);
        mysqli_stmt_execute($team_stmt);
        mysqli_stmt_bind_result($team_stmt, $team_name);
        mysqli_stmt_fetch($team_stmt);
        mysqli_stmt_close($team_stmt);
        
        // Create a notification record for the removed user
        $removed_by = $current_user_id == $member_id ? "yourself" : "the team leader";
        $notification_text = "You have been removed from team '$team_name' by $removed_by.";
        
        // First check if the notifications table exists
        $check_table_sql = "SHOW TABLES LIKE 'user_notifications'";
        $table_result = mysqli_query($conn, $check_table_sql);
        
        if (mysqli_num_rows($table_result) == 0) {
            // Create the notifications table if it doesn't exist
            $create_table_sql = "CREATE TABLE user_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )";
            mysqli_query($conn, $create_table_sql);
        }
        
        // Insert notification
        $notification_sql = "INSERT INTO user_notifications (user_id, message) VALUES (?, ?)";
        $notification_stmt = mysqli_prepare($conn, $notification_sql);
        mysqli_stmt_bind_param($notification_stmt, "is", $member_id, $notification_text);
        mysqli_stmt_execute($notification_stmt);
        mysqli_stmt_close($notification_stmt);
        
        $_SESSION['message'] = "Team member removed successfully";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error removing team member: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    
    mysqli_stmt_close($delete_stmt);
}

// Redirect back to team details page
header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
exit();
?> 