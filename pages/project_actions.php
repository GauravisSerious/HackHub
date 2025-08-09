<?php
// Include configuration file
require_once '../includes/config.php';
require_once '../includes/auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['message'] = "You must log in to process projects";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if user is admin or judge
if (!isAdmin() && !isJudge()) {
    $_SESSION['message'] = "You don't have permission to process projects";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Check if project ID and action are provided
if (!isset($_POST['project_id']) || !isset($_POST['action']) || empty($_POST['project_id']) || empty($_POST['action'])) {
    $_SESSION['message'] = "Invalid request";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$project_id = $_POST['project_id'];
$action = $_POST['action'];

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    $_SESSION['message'] = "Invalid action";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Check for rejection reason if action is reject
if ($action === 'reject' && (!isset($_POST['rejection_reason']) || empty($_POST['rejection_reason']))) {
    $_SESSION['message'] = "Please provide a reason for rejection";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "/pages/project-details.php?id=" . $project_id);
    exit();
}

// Get project details
$sql = "SELECT * FROM projects WHERE project_id = ? AND (status = 'submitted' OR status = 'draft')";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $project = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            // Update project status
            $update_sql = "UPDATE projects SET status = ?, admin_feedback = ?, updated_at = NOW()";
            
            // If we're approving a project that's still in draft status, also set the submission_date
            if ($action === 'approve' && $project['status'] === 'draft') {
                $update_sql .= ", submission_date = NOW()";
            }
            
            $update_sql .= " WHERE project_id = ?";
            
            if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                $admin_feedback = ($action === 'reject') ? $_POST['rejection_reason'] : null;
                $status = ($action === 'approve') ? 'approved' : 'rejected';
                
                mysqli_stmt_bind_param($update_stmt, "ssi", $status, $admin_feedback, $project_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    if (isset($_SESSION['message'])) unset($_SESSION['message']);
                    if (isset($_SESSION['message_type'])) unset($_SESSION['message_type']);
                    if (isset($_SESSION['project_status_message'])) unset($_SESSION['project_status_message']);
                    
                    $_SESSION['message'] = "Project " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error updating project status: " . mysqli_error($conn);
                    $_SESSION['message_type'] = "danger";
                }
                
                mysqli_stmt_close($update_stmt);
            }
        } else {
            mysqli_stmt_close($stmt);
            $_SESSION['message'] = "Project not found or already processed";
            $_SESSION['message_type'] = "warning";
        }
    } else {
        mysqli_stmt_close($stmt);
        $_SESSION['message'] = "Something went wrong. Please try again later.";
        $_SESSION['message_type'] = "danger";
    }
} else {
    $_SESSION['message'] = "Something went wrong. Please try again later.";
    $_SESSION['message_type'] = "danger";
}

// Redirect back to project details
header("Location: " . BASE_URL . "/pages/project-details.php?id=" . $project_id);
exit();
?> 