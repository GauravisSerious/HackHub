<?php
require_once 'includes/config.php';

echo "<h2>Updating Project Timestamps</h2>";

// Get all projects
$sql = "SELECT project_id, title FROM projects WHERE status = 'submitted' ORDER BY project_id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $current_time = time();
    $i = 0;
    
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Project ID</th><th>Title</th><th>New Timestamp</th><th>Relative Time</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        $project_id = $row['project_id'];
        $title = $row['title'];
        
        // Create different timestamps based on project index
        $time_offset = 0;
        switch($i % 5) {
            case 0: // Just now
                $time_offset = 0;
                $relative_time = "Just now";
                break;
            case 1: // 5 minutes ago
                $time_offset = 300; // 5 minutes
                $relative_time = "5 minutes ago";
                break;
            case 2: // 1 hour ago
                $time_offset = 3600; // 1 hour
                $relative_time = "1 hour ago";
                break;
            case 3: // 1 day ago
                $time_offset = 86400; // 1 day
                $relative_time = "1 day ago";
                break;
            case 4: // 2 days ago
                $time_offset = 172800; // 2 days
                $relative_time = "2 days ago";
                break;
        }
        
        $new_timestamp = date('Y-m-d H:i:s', $current_time - $time_offset);
        
        // Update the project with new timestamp
        $update_sql = "UPDATE projects SET submission_date = '$new_timestamp' WHERE project_id = $project_id";
        if ($conn->query($update_sql)) {
            echo "<tr>";
            echo "<td>{$project_id}</td>";
            echo "<td>{$title}</td>";
            echo "<td>{$new_timestamp}</td>";
            echo "<td>{$relative_time}</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td>{$project_id}</td>";
            echo "<td>{$title}</td>";
            echo "<td colspan='2'>Error: " . $conn->error . "</td>";
            echo "</tr>";
        }
        
        $i++;
    }
    
    echo "</table>";
    
    echo "<p>Timestamps updated successfully! <a href='test_timestamps.php'>Go to test page</a> to verify.</p>";
    
} else {
    echo "<p>No projects found to update.</p>";
}

// Add a message to clear browser cache
echo "<div style='margin-top: 20px; padding: 10px; background-color: #f8f9fa; border-left: 5px solid #007bff;'>";
echo "<strong>Important:</strong> You may need to clear your browser cache or do a hard refresh (Ctrl+F5) to see the updates on the project pages.";
echo "</div>";

// Provide links back to the main pages
echo "<div style='margin-top: 20px;'>";
echo "<a href='" . BASE_URL . "/pages/projects.php' class='btn btn-primary'>Go to Projects</a> ";
echo "<a href='" . BASE_URL . "/pages/dashboard.php' class='btn btn-secondary'>Go to Dashboard</a>";
echo "</div>";
?> 