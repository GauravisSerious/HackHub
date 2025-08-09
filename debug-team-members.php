<?php
// Include configuration file
require_once 'includes/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get team ID from URL if provided
$team_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Apply basic styling
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
    h1, h2, h3 { color: #333; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .action-btn { 
        display: inline-block; 
        margin: 5px;
        padding: 8px 15px;
        background-color: #4CAF50;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }
    .section { 
        border: 1px solid #ddd; 
        padding: 15px; 
        margin-bottom: 20px;
        border-radius: 5px;
    }
</style>";

echo "<h1>Team Debug Tool</h1>";

// If specific user is selected, show their team membership
if ($user_id) {
    echo "<div class='section'>";
    echo "<h2>User Team Membership</h2>";
    
    // Get user details
    $sql = "SELECT user_id, username, CONCAT(first_name, ' ', last_name) AS name, email FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        echo "<h3>User Information</h3>";
        echo "<p><strong>ID:</strong> {$user['user_id']}</p>";
        echo "<p><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</p>";
        echo "<p><strong>Name:</strong> " . htmlspecialchars($user['name']) . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
        
        // Get user's teams
        $sql = "SELECT t.team_id, t.team_name, tm.is_leader, tm.joined_at
                FROM teams t
                JOIN team_members tm ON t.team_id = tm.team_id
                WHERE tm.user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            echo "<h3>Teams</h3>";
            echo "<table>";
            echo "<tr><th>Team ID</th><th>Team Name</th><th>Role</th><th>Joined At</th><th>Actions</th></tr>";
            
            while ($team = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>{$team['team_id']}</td>";
                echo "<td>" . htmlspecialchars($team['team_name']) . "</td>";
                echo "<td>" . ($team['is_leader'] ? 'Leader' : 'Member') . "</td>";
                echo "<td>" . htmlspecialchars($team['joined_at']) . "</td>";
                echo "<td><a href='?id={$team['team_id']}' class='action-btn'>View Team</a></td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p class='warning'>This user is not a member of any team.</p>";
        }
        
        // Get pending invitations
        $sql = "SELECT t.team_id, t.team_name, tr.status, tr.created_at
                FROM team_requests tr
                JOIN teams t ON tr.team_id = t.team_id
                WHERE tr.user_id = ? AND tr.status = 'pending'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            echo "<h3>Pending Invitations</h3>";
            echo "<table>";
            echo "<tr><th>Team ID</th><th>Team Name</th><th>Status</th><th>Invited At</th><th>Actions</th></tr>";
            
            while ($invite = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>{$invite['team_id']}</td>";
                echo "<td>" . htmlspecialchars($invite['team_name']) . "</td>";
                echo "<td>" . htmlspecialchars($invite['status']) . "</td>";
                echo "<td>" . htmlspecialchars($invite['created_at']) . "</td>";
                echo "<td><a href='?id={$invite['team_id']}' class='action-btn'>View Team</a></td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p class='info'>This user has no pending team invitations.</p>";
        }
    } else {
        echo "<p class='error'>User with ID $user_id not found!</p>";
    }
    
    echo "<p><a href='debug-team-members.php' class='action-btn'>Back to Teams List</a></p>";
    echo "</div>";
    
    // Exit here if we're viewing a specific user
    exit;
}

// If no team is specified, show all teams
if (!$team_id) {
    echo "<div class='section'>";
    echo "<h2>Available Teams</h2>";
    $sql = "SELECT team_id, team_name, created_at, 
           (SELECT COUNT(*) FROM team_members WHERE team_id = teams.team_id) AS member_count
           FROM teams ORDER BY team_id";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Team Name</th><th>Created</th><th>Members</th><th>Actions</th></tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>{$row['team_id']}</td>";
            echo "<td>" . htmlspecialchars($row['team_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "<td>{$row['member_count']}</td>";
            echo "<td><a href='?id={$row['team_id']}' class='action-btn'>View Details</a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No teams found.</p>";
    }
    echo "</div>";
    
    // Show database table structure/count information
    echo "<div class='section'>";
    echo "<h2>Database Tables</h2>";
    
    // Check teams table
    $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM teams");
    $row = mysqli_fetch_assoc($result);
    echo "<h3>Teams Table</h3>";
    echo "<p>Total records: {$row['count']}</p>";
    
    // Check team_members table
    $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM team_members");
    $row = mysqli_fetch_assoc($result);
    echo "<h3>Team Members Table</h3>";
    echo "<p>Total records: {$row['count']}</p>";
    
    // Check team_requests table
    $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM team_requests");
    $row = mysqli_fetch_assoc($result);
    echo "<h3>Team Requests Table</h3>";
    echo "<p>Total records: {$row['count']}</p>";
    
    // Check users table
    $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM users");
    $row = mysqli_fetch_assoc($result);
    echo "<h3>Users Table</h3>";
    echo "<p>Total records: {$row['count']}</p>";
    echo "<p><a href='list-users.php' class='action-btn'>List All Users</a></p>";
    
    echo "</div>";
    exit;
}

// If we get here, we're showing a specific team
echo "<div class='section'>";
echo "<h2>Team Details</h2>";

// Get team info
$sql = "SELECT * FROM teams WHERE team_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $team_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $team = mysqli_fetch_assoc($result);
    echo "<h3>" . htmlspecialchars($team['team_name']) . " (ID: {$team['team_id']})</h3>";
    echo "<p><strong>Created:</strong> " . htmlspecialchars($team['created_at']) . "</p>";
    echo "<p><strong>Updated:</strong> " . htmlspecialchars($team['updated_at']) . "</p>";
    
    // Add refresh link
    echo "<p><a href='refresh-team-details.php?id={$team['team_id']}' class='action-btn'>Refresh Team Data</a> ";
    echo "<a href='list-users.php?team_id={$team['team_id']}' class='action-btn'>Add Team Member</a></p>";
    
    // Get team members
    $sql = "SELECT tm.user_id, tm.is_leader, tm.joined_at, 
            CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, u.username
            FROM team_members tm
            JOIN users u ON tm.user_id = u.user_id
            WHERE tm.team_id = ?
            ORDER BY tm.is_leader DESC, tm.joined_at ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    echo "<h3>Team Members</h3>";
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<table>";
        echo "<tr><th>User ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Joined At</th></tr>";
        
        while ($member = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>{$member['user_id']}</td>";
            echo "<td>" . htmlspecialchars($member['name']) . "</td>";
            echo "<td>" . htmlspecialchars($member['username']) . "</td>";
            echo "<td>" . htmlspecialchars($member['email']) . "</td>";
            echo "<td>" . ($member['is_leader'] ? '<strong>Leader</strong>' : 'Member') . "</td>";
            echo "<td>" . htmlspecialchars($member['joined_at']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='warning'>No team members found!</p>";
    }
    
    // Get pending invitations
    $sql = "SELECT tr.id AS request_id, tr.user_id, tr.created_at, tr.status,
            CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, u.username
            FROM team_requests tr
            JOIN users u ON tr.user_id = u.user_id
            WHERE tr.team_id = ? AND tr.status = 'pending'
            ORDER BY tr.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    echo "<h3>Pending Invitations</h3>";
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<table>";
        echo "<tr><th>Request ID</th><th>User ID</th><th>Name</th><th>Username</th><th>Email</th><th>Created At</th></tr>";
        
        while ($invitation = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>{$invitation['request_id']}</td>";
            echo "<td>{$invitation['user_id']}</td>";
            echo "<td>" . htmlspecialchars($invitation['name']) . "</td>";
            echo "<td>" . htmlspecialchars($invitation['username']) . "</td>";
            echo "<td>" . htmlspecialchars($invitation['email']) . "</td>";
            echo "<td>" . htmlspecialchars($invitation['created_at']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='info'>No pending invitations.</p>";
    }
    
    // Get all invitations (history)
    $sql = "SELECT tr.id AS request_id, tr.user_id, tr.created_at, tr.status,
            CONCAT(u.first_name, ' ', u.last_name) AS name, u.email
            FROM team_requests tr
            JOIN users u ON tr.user_id = u.user_id
            WHERE tr.team_id = ?
            ORDER BY tr.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    echo "<h3>Invitation History</h3>";
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<table>";
        echo "<tr><th>Request ID</th><th>User ID</th><th>Name</th><th>Email</th><th>Status</th><th>Created At</th></tr>";
        
        while ($invitation = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>{$invitation['request_id']}</td>";
            echo "<td>{$invitation['user_id']}</td>";
            echo "<td>" . htmlspecialchars($invitation['name']) . "</td>";
            echo "<td>" . htmlspecialchars($invitation['email']) . "</td>";
            echo "<td>" . htmlspecialchars($invitation['status']) . "</td>";
            echo "<td>" . htmlspecialchars($invitation['created_at']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='warning'>No invitation history found!</p>";
    }
} else {
    echo "<p class='error'>Team with ID $team_id not found!</p>";
}

echo "<p><a href='debug-team-members.php' class='action-btn'>Back to Teams List</a></p>";
echo "</div>";
?> 