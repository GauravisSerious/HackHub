<?php
// Include configuration file
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    echo "Access denied";
    exit();
}

// Get table structure
echo "<h2>Teams Table Structure</h2>";
$result = mysqli_query($conn, "DESCRIBE teams");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error getting table structure: " . mysqli_error($conn);
}

// Get sample teams data
echo "<h2>Sample Teams Data</h2>";
$result = mysqli_query($conn, "SELECT * FROM teams LIMIT 5");
if ($result) {
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        echo "<table border='1'><tr>";
        foreach ($row as $key => $value) {
            echo "<th>" . htmlspecialchars($key) . "</th>";
        }
        echo "</tr>";
        
        // Reset pointer
        mysqli_data_seek($result, 0);
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No teams found";
    }
} else {
    echo "Error getting teams data: " . mysqli_error($conn);
}
?> 