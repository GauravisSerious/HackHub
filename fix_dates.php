<?php
require_once 'includes/config.php';

echo "<h3>Fixing project submission dates</h3>";

// Use the specific date provided: March 27, 2025 at 2:35 PM
$current_date = '2025-03-27 14:35:00';

// Fix NULL or 1970 dates for submitted projects
$sql1 = "UPDATE projects 
        SET submission_date = '$current_date'
        WHERE (submission_date IS NULL 
        OR submission_date = '1970-01-01 00:00:00'
        OR YEAR(submission_date) > 2025)
        AND status = 'submitted'";

if ($conn->query($sql1)) {
    echo "<p>Successfully updated all project dates to March 27, 2025 at 2:35 PM.</p>";
} else {
    echo "<p>Error fixing dates: " . $conn->error . "</p>";
}

// List all projects after fixes
$sql2 = "SELECT project_id, title, status, submission_date FROM projects ORDER BY project_id";
$result = $conn->query($sql2);

if ($result->num_rows > 0) {
    echo "<h4>Current Projects:</h4>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Submission Date</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["project_id"] . "</td>";
        echo "<td>" . $row["title"] . "</td>";
        echo "<td>" . $row["status"] . "</td>";
        echo "<td>" . $row["submission_date"] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No projects found</p>";
}

$conn->close();
?> 