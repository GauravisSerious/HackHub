<?php
// Include configuration
require_once 'includes/config.php';

// Get team ID from URL
$team_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$team_id) {
    // Redirect to teams list if no ID provided
    $_SESSION['message'] = "No team ID provided.";
    $_SESSION['message_type'] = "error";
    header("Location: debug-team-members.php");
    exit;
}

// Verify team exists
$sql = "SELECT team_id FROM teams WHERE team_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $team_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    // Team doesn't exist
    $_SESSION['message'] = "Team with ID $team_id not found.";
    $_SESSION['message_type'] = "error";
    header("Location: debug-team-members.php");
    exit;
}

// Clear any cached team data in session
if (isset($_SESSION['team_data'])) {
    unset($_SESSION['team_data']);
}

// Clear any other cached data related to teams
if (isset($_SESSION['team_members'])) {
    unset($_SESSION['team_members']);
}

if (isset($_SESSION['team_invitations'])) {
    unset($_SESSION['team_invitations']);
}

// Set success message
$_SESSION['message'] = "Team data has been refreshed. Displaying most current information.";
$_SESSION['message_type'] = "success";

// Redirect to team details with parameters to force refresh
// Include timestamp to prevent cache
$timestamp = time();
header("Location: debug-team-members.php?id=$team_id&updated=true&nocache=$timestamp");
exit;
?> 