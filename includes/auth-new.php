<?php
// Prevent direct access to this file, except for logout.php
$script_name = basename($_SERVER['SCRIPT_NAME']);
if ($script_name !== 'logout.php' && !defined('BASE_URL')) {
    exit('No direct script access allowed');
}

/**
 * Authentication and Authorization Functions
 */

// Check if a user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Check if current user is an admin
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
    }
}

// Check if current user is a team leader of a specific team
if (!function_exists('isTeamLeader')) {
    function isTeamLeader($team_id = null) {
        global $conn;
        
        if (!isLoggedIn()) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        
        // If no team_id is provided, check if user is a leader in any team
        if ($team_id === null) {
            $sql = "SELECT COUNT(*) as count FROM team_members WHERE user_id = ? AND is_leader = 1";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    return $row['count'] > 0;
                }
                
                mysqli_stmt_close($stmt);
            }
            
            return false;
        }
        
        // Check if user is a leader in the specified team
        $sql = "SELECT COUNT(*) as count FROM team_members WHERE team_id = ? AND user_id = ? AND is_leader = 1";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                return $row['count'] > 0;
            }
            
            mysqli_stmt_close($stmt);
        }
        
        return false;
    }
}

// Check if user is a member of a team
if (!function_exists('isTeamMember')) {
    function isTeamMember($team_id = null) {
        global $conn;
        
        if (!isLoggedIn()) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        
        // If no team_id is provided, check if user is a member in any team
        if ($team_id === null) {
            $sql = "SELECT COUNT(*) as count FROM team_members WHERE user_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    return $row['count'] > 0;
                }
                
                mysqli_stmt_close($stmt);
            }
            
            return false;
        }
        
        // Check if user is a member in the specified team
        $sql = "SELECT COUNT(*) as count FROM team_members WHERE team_id = ? AND user_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                return $row['count'] > 0;
            }
            
            mysqli_stmt_close($stmt);
        }
        
        return false;
    }
}

// Require user to be logged in, redirect if not
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            $_SESSION['message'] = "You must be logged in to access that page";
            $_SESSION['message_type'] = "warning";
            header("Location: " . BASE_URL . "/pages/login.php");
            exit();
        }
    }
}

// Require admin access, redirect if not admin
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        requireLogin();
        
        if (!isAdmin()) {
            $_SESSION['message'] = "You don't have permission to access that page";
            $_SESSION['message_type'] = "danger";
            header("Location: " . BASE_URL . "/pages/dashboard.php");
            exit();
        }
    }
}

// Require team leader of specified team, redirect if not
if (!function_exists('requireTeamLeader')) {
    function requireTeamLeader($team_id) {
        requireLogin();
        
        if (!isAdmin() && !isTeamLeader($team_id)) {
            $_SESSION['message'] = "You must be the team leader to access that page";
            $_SESSION['message_type'] = "danger";
            header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
            exit();
        }
    }
}

// Get the user's current team ID or null if not in a team
if (!function_exists('getUserTeamId')) {
    function getUserTeamId() {
        global $conn;
        
        if (!isLoggedIn()) {
            return null;
        }
        
        $user_id = $_SESSION['user_id'];
        
        $sql = "SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                
                if ($row = mysqli_fetch_assoc($result)) {
                    return $row['team_id'];
                }
            }
            
            mysqli_stmt_close($stmt);
        }
        
        return null;
    }
}

// Update session data with fresh user information
if (!function_exists('updateSessionData')) {
    function updateSessionData() {
        global $conn;
        
        if (!isLoggedIn()) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        
        $sql = "SELECT username, first_name, last_name, user_type, profile_pic FROM users WHERE user_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                
                if ($row = mysqli_fetch_assoc($result)) {
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['first_name'] = $row['first_name'];
                    $_SESSION['last_name'] = $row['last_name'];
                    $_SESSION['user_type'] = $row['user_type'];
                    $_SESSION['profile_image'] = $row['profile_pic'];
                    return true;
                }
            }
            
            mysqli_stmt_close($stmt);
        }
        
        return false;
    }
} 