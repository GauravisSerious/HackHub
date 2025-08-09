<?php
// Include configuration file
require_once '../includes/config.php';
// Include auth functions
require_once '../includes/auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to submit a project";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if project ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Set message
    $_SESSION['message'] = "Invalid project selection";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$project_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get project details
$sql = "SELECT p.*, t.team_id 
        FROM projects p 
        JOIN teams t ON p.team_id = t.team_id 
        WHERE p.project_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $project = mysqli_fetch_assoc($result);
        } else {
            // Set message
            $_SESSION['message'] = "Project not found";
            $_SESSION['message_type'] = "warning";
            
            // Redirect to dashboard
            header("Location: " . BASE_URL . "/pages/dashboard.php");
            exit();
        }
    } else {
        // Set message
        $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
        $_SESSION['message_type'] = "danger";
        
        // Redirect to dashboard
        header("Location: " . BASE_URL . "/pages/dashboard.php");
        exit();
    }
    
    mysqli_stmt_close($stmt);
}

// Check if user is a team member
$isTeamMember = false;

$sql = "SELECT * FROM team_members WHERE team_id = ? AND user_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $project['team_id'], $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $isTeamMember = true;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Check access permission
if (!$isTeamMember) {
    // Set message
    $_SESSION['message'] = "You don't have permission to submit this project";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Check if project can be submitted (only draft or rejected projects)
if (!($project['status'] == 'draft' || $project['status'] == 'rejected')) {
    // Set message
    $_SESSION['message'] = "This project cannot be submitted at this time";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to project details
    header("Location: " . BASE_URL . "/pages/project-details.php?id=" . $project_id);
    exit();
}

// Update project status in database
$sql = "UPDATE projects 
        SET status = 'submitted', 
            submission_date = '2025-03-27 14:35:00', 
            updated_at = NOW() 
        WHERE project_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Set success message
        $_SESSION['message'] = "Project submitted successfully! It will now be reviewed by our team.";
        $_SESSION['message_type'] = "success";
    } else {
        // Set error message
        $_SESSION['message'] = "Error submitting project: " . mysqli_error($conn);
        $_SESSION['message_type'] = "danger";
    }
    
    mysqli_stmt_close($stmt);
} else {
    // Set error message
    $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
    $_SESSION['message_type'] = "danger";
}

// Redirect to project details
header("Location: " . BASE_URL . "/pages/project-details.php?id=" . $project_id);
exit();
?> 