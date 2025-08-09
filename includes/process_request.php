<?php
// Include configuration file
require_once 'config.php';
// Include auth functions
require_once 'auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to process requests";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if request ID and action are provided
if (!isset($_POST['request_id']) || !isset($_POST['action']) || empty($_POST['request_id']) || empty($_POST['action'])) {
    // Set message
    $_SESSION['message'] = "Invalid request";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$request_id = $_POST['request_id'];
$action = $_POST['action'];
$team_id = null; // Initialize team_id variable

// Get the team_id for this request first so we always have it for redirection
$team_id_query = "SELECT team_id FROM team_join_requests WHERE request_id = ?";
if ($team_id_stmt = mysqli_prepare($conn, $team_id_query)) {
    mysqli_stmt_bind_param($team_id_stmt, "i", $request_id);
    mysqli_stmt_execute($team_id_stmt);
    mysqli_stmt_store_result($team_id_stmt);
    
    if (mysqli_stmt_num_rows($team_id_stmt) > 0) {
        mysqli_stmt_bind_result($team_id_stmt, $team_id);
        mysqli_stmt_fetch($team_id_stmt);
    }
    
    mysqli_stmt_close($team_id_stmt);
}

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    // Set message
    $_SESSION['message'] = "Invalid action";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Get request details
$sql = "SELECT r.*, t.team_id, t.team_name, u.user_id, u.username 
        FROM team_join_requests r 
        JOIN teams t ON r.team_id = t.team_id 
        JOIN users u ON r.user_id = u.user_id 
        WHERE r.request_id = ? AND r.status = 'pending'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $request = mysqli_fetch_assoc($result);
            
            // Check if user is the team leader
            $isTeamLeader = false;
            
            $sql = "SELECT is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $request['team_id'], $_SESSION['user_id']);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        mysqli_stmt_bind_result($stmt, $is_leader);
                        mysqli_stmt_fetch($stmt);
                        $isTeamLeader = $is_leader == 1;
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
            
            // If user is not team leader or admin, redirect to dashboard
            if (!$isTeamLeader && !isAdmin()) {
                // Set message
                $_SESSION['message'] = "You don't have permission to process this request";
                $_SESSION['message_type'] = "warning";
                
                // Redirect to dashboard
                header("Location: " . BASE_URL . "/pages/dashboard.php");
                exit();
            }
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Update request status
                $sql = "UPDATE team_join_requests SET status = ? WHERE request_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "si", $action, $request_id);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to update request status");
                    }
                    
                    mysqli_stmt_close($stmt);
                }
                
                // If approved, add user to team
                if ($action == 'approve') {
                    // Check if user is already a member of another team
                    $sql = "SELECT team_id FROM team_members WHERE user_id = ?";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "i", $request['user_id']);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            mysqli_stmt_store_result($stmt);
                            
                            if (mysqli_stmt_num_rows($stmt) > 0) {
                                throw new Exception("User is already a member of another team");
                            }
                        }
                        
                        mysqli_stmt_close($stmt);
                    }
                    
                    // Add user to team
                    $sql = "INSERT INTO team_members (team_id, user_id, is_leader) VALUES (?, ?, 0)";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "ii", $request['team_id'], $request['user_id']);
                        
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Failed to add user to team");
                        }
                        
                        mysqli_stmt_close($stmt);
                    }
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Set success message
                $_SESSION['message'] = "Request " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully!";
                $_SESSION['message_type'] = "success";
                
            } catch (Exception $e) {
                // Rollback transaction
                mysqli_rollback($conn);
                
                // Set error message
                $_SESSION['message'] = "Error: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
            
        } else {
            // Set message
            $_SESSION['message'] = "Request not found";
            $_SESSION['message_type'] = "warning";
        }
    } else {
        // Set message
        $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
        $_SESSION['message_type'] = "danger";
    }
} else {
    // Set message
    $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
    $_SESSION['message_type'] = "danger";
}

// If we still don't have a team_id, redirect to dashboard
if ($team_id === null) {
    $_SESSION['message'] = "Couldn't identify the team. Please try again.";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Redirect to team details page
header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
exit();
?> 