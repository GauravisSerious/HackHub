<?php
// Include configuration file
require_once 'includes/config.php';

echo "<h1>Team Members Check</h1>";

// Check team_members table
$sql = "SELECT tm.id, tm.team_id, tm.user_id, tm.is_leader, 
               t.team_name, 
               CONCAT(u.first_name, ' ', u.last_name) as member_name, 
               u.username
        FROM team_members tm
        JOIN teams t ON tm.team_id = t.team_id
        JOIN users u ON tm.user_id = u.user_id
        ORDER BY tm.team_id, tm.is_leader DESC";

$result = mysqli_query($conn, $sql);

if ($result) {
    $count = mysqli_num_rows($result);
    echo "<p>Total team members in database: " . $count . "</p>";
    
    if ($count > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>
                <th>ID</th>
                <th>Team ID</th>
                <th>Team Name</th>
                <th>User ID</th>
                <th>Member Name</th>
                <th>Username</th>
                <th>Is Leader</th>
              </tr>";
              
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['team_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['team_name']) . "</td>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['member_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td>" . ($row['is_leader'] ? "Yes" : "No") . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No team members found in the database.</p>";
    }
} else {
    echo "<p>Error: " . mysqli_error($conn) . "</p>";
}

// Show tables
echo "<h2>Database Tables</h2>";
$sql = "SHOW TABLES";
$result = mysqli_query($conn, $sql);

if ($result) {
    echo "<ul>";
    while ($row = mysqli_fetch_row($result)) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Error: " . mysqli_error($conn) . "</p>";
}

// Check specific team if team ID is provided
if (isset($_GET['team_id'])) {
    $team_id = (int)$_GET['team_id'];
    echo "<h2>Members for Team ID: " . $team_id . "</h2>";
    
    $sql = "SELECT tm.id, tm.user_id, tm.is_leader, 
                 CONCAT(u.first_name, ' ', u.last_name) as member_name, 
                 u.username
          FROM team_members tm
          JOIN users u ON tm.user_id = u.user_id
          WHERE tm.team_id = ?";
          
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $team_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $count = mysqli_num_rows($result);
            
            echo "<p>Team members found: " . $count . "</p>";
            
            if ($count > 0) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Member Name</th>
                        <th>Username</th>
                        <th>Is Leader</th>
                      </tr>";
                      
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['user_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['member_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                    echo "<td>" . ($row['is_leader'] ? "Yes" : "No") . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            }
        } else {
            echo "<p>Error executing query: " . mysqli_error($conn) . "</p>";
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo "<p>Error preparing query: " . mysqli_error($conn) . "</p>";
    }
}
?> 