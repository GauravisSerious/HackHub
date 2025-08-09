<?php
// Include configuration file
require_once 'includes/config.php';

// Get team ID for adding users to
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

echo "<h1>User List</h1>";

if ($team_id) {
    // Get team info
    $team_query = "SELECT team_name FROM teams WHERE team_id = ?";
    $stmt = mysqli_prepare($conn, $team_query);
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $team = mysqli_fetch_assoc($result);
        echo "<h2>Adding users to team: " . htmlspecialchars($team['team_name']) . " (ID: $team_id)</h2>";
    } else {
        echo "<p style='color: red;'>Warning: Team with ID $team_id not found!</p>";
    }
}

// Get all users
$sql = "SELECT user_id, username, first_name, last_name, email, user_type 
        FROM users 
        ORDER BY user_id";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    echo "<style>
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .action-link { display: inline-block; margin: 0 5px; padding: 3px 8px; text-decoration: none; 
                      border-radius: 3px; color: white; }
        .add-link { background-color: green; }
        .view-link { background-color: blue; }
    </style>";
    
    echo "<table>";
    echo "<tr>
            <th>ID</th>
            <th>Username</th>
            <th>Name</th>
            <th>Email</th>
            <th>Type</th>
            <th>Actions</th>
          </tr>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_type']) . "</td>";
        echo "<td>";
        
        if ($team_id) {
            // Actions for adding to specific team
            echo "<a href='add-team-member.php?team_id=$team_id&user_id={$row['user_id']}&leader=0' class='action-link add-link'>Add as Member</a>";
            echo "<a href='add-team-member.php?team_id=$team_id&user_id={$row['user_id']}&leader=1' class='action-link add-link'>Add as Leader</a>";
        } else {
            // General actions
            echo "<a href='debug-team-members.php?user_id={$row['user_id']}' class='action-link view-link'>View Teams</a>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No users found.</p>";
}

if ($team_id) {
    echo "<p><a href='debug-team-members.php?id=$team_id'>Back to Team Details</a></p>";
} else {
    echo "<p><a href='debug-team-members.php'>Back to Team List</a></p>";
}
?> 