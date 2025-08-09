<?php
// Include configuration file
require_once '../includes/config.php';
require_once '../includes/auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to delete projects";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if project ID is provided
if (!isset($_POST['project_id']) || empty($_POST['project_id'])) {
    // Set message
    $_SESSION['message'] = "Invalid request";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$project_id = $_POST['project_id'];
$user_id = $_SESSION['user_id'];

// Check if the user has permission to delete this project
// User must be either an admin or a team leader of the team that owns the project

// First, get the project and team details
$sql = "SELECT p.*, t.team_id FROM projects p
        JOIN teams t ON p.team_id = t.team_id
        WHERE p.project_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $project = mysqli_fetch_assoc($result);
            $team_id = $project['team_id'];
            
            // Check if user is admin or team leader
            $hasPermission = false;
            
            if (isAdmin()) {
                $hasPermission = true;
            } else {
                // Check if user is team leader
                $team_sql = "SELECT * FROM team_members 
                            WHERE team_id = ? AND user_id = ? AND is_leader = 1";
                
                if ($team_stmt = mysqli_prepare($conn, $team_sql)) {
                    mysqli_stmt_bind_param($team_stmt, "ii", $project['team_id'], $user_id);
                    
                    if (mysqli_stmt_execute($team_stmt)) {
                        mysqli_stmt_store_result($team_stmt);
                        
                        if (mysqli_stmt_num_rows($team_stmt) > 0) {
                            $hasPermission = true;
                        }
                    }
                    
                    mysqli_stmt_close($team_stmt);
                }
            }
            
            // If user has permission, delete the project
            if ($hasPermission) {
                // Store project title for message
                $project_title = $project['title'];
                
                // Clear any existing messages to prevent duplicates
                if (isset($_SESSION['message'])) unset($_SESSION['message']);
                if (isset($_SESSION['message_type'])) unset($_SESSION['message_type']);
                if (isset($_SESSION['project_status_message'])) unset($_SESSION['project_status_message']);
                
                // Start transaction for data consistency
                mysqli_begin_transaction($conn);
                
                try {
                    // First delete related records
                    
                    // 1. Delete project files from database
                    $files_sql = "DELETE FROM project_files WHERE project_id = ?";
                    $files_stmt = mysqli_prepare($conn, $files_sql);
                    mysqli_stmt_bind_param($files_stmt, "i", $project_id);
                    mysqli_stmt_execute($files_stmt);
                    mysqli_stmt_close($files_stmt);
                    
                    // 2. Delete evaluations
                    $evals_sql = "DELETE FROM evaluations WHERE project_id = ?";
                    $evals_stmt = mysqli_prepare($conn, $evals_sql);
                    mysqli_stmt_bind_param($evals_stmt, "i", $project_id);
                    mysqli_stmt_execute($evals_stmt);
                    mysqli_stmt_close($evals_stmt);
                    
                    // 3. Finally delete the project itself
                    $delete_sql = "DELETE FROM projects WHERE project_id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_sql);
                    mysqli_stmt_bind_param($delete_stmt, "i", $project_id);
                    mysqli_stmt_execute($delete_stmt);
                    mysqli_stmt_close($delete_stmt);
                    
                    // Commit transaction
                    mysqli_commit($conn);
                    
                    // Set success message
                    $_SESSION['message'] = "Project \"" . $project_title . "\" has been deleted successfully!";
                    $_SESSION['message_type'] = "success";
                }
                catch (Exception $e) {
                    // Rollback transaction on failure
                    mysqli_rollback($conn);
                    
                    // Set error message
                    $_SESSION['message'] = "Error deleting project: " . $e->getMessage();
                    $_SESSION['message_type'] = "danger";
                }
            } else {
                // Set permission denied message
                $_SESSION['message'] = "You don't have permission to delete this project";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            // Set project not found message
            $_SESSION['message'] = "Project not found";
            $_SESSION['message_type'] = "warning";
        }
    } else {
        // Set error message
        $_SESSION['message'] = "Error retrieving project details: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    
    mysqli_stmt_close($stmt);
} else {
    // Set error message
    $_SESSION['message'] = "Oops! Something went wrong. Please try again later. " . mysqli_error($conn);
    $_SESSION['message_type'] = "danger";
}

// Redirect back to dashboard
header("Location: " . BASE_URL . "/pages/dashboard.php");
exit();
?> 