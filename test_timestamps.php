<?php
require_once 'includes/config.php';

echo "<h2>Time Display Diagnostic</h2>";

// Get current server time
echo "<p><strong>Current Server Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Query some projects with their submission dates
$sql = "SELECT project_id, title, submission_date FROM projects 
        WHERE submission_date IS NOT NULL 
        ORDER BY submission_date DESC";
$result = $conn->query($sql);

echo "<h3>Project Submission Times:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr>
        <th>ID</th>
        <th>Title</th>
        <th>Database Timestamp</th>
        <th>Unix Timestamp</th>
        <th>PHP time_ago</th>
        <th>Manual Calculation</th>
      </tr>";

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Get the submission date
        $submission_date = $row['submission_date'];
        
        // Convert to timestamp
        $timestamp = strtotime($submission_date);
        
        // Get time_ago from function
        $time_ago = time_ago($submission_date);
        
        // Manual time ago calculation for verification
        $now = time();
        $diff = $now - $timestamp;
        $manual_time_ago = "";
        
        if ($diff < 60) {
            $manual_time_ago = "Just now";
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            $manual_time_ago = "$minutes minute" . ($minutes > 1 ? "s" : "") . " ago";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            $manual_time_ago = "$hours hour" . ($hours > 1 ? "s" : "") . " ago";
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            $manual_time_ago = "$days day" . ($days > 1 ? "s" : "") . " ago";
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            $manual_time_ago = "$months month" . ($months > 1 ? "s" : "") . " ago";
        } else {
            $years = floor($diff / 31536000);
            $manual_time_ago = "$years year" . ($years > 1 ? "s" : "") . " ago";
        }
        
        echo "<tr>";
        echo "<td>" . $row['project_id'] . "</td>";
        echo "<td>" . $row['title'] . "</td>";
        echo "<td>" . $submission_date . "</td>";
        echo "<td>" . $timestamp . "</td>";
        echo "<td>" . $time_ago . "</td>";
        echo "<td>" . $manual_time_ago . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6'>No projects found</td></tr>";
}
echo "</table>";

// Update projects with incorrect timestamps
echo "<h3>Fix Timestamps:</h3>";

// Create test timestamps for demo (several minutes, hours, days ago)
$test_timestamps = array(
    array('label' => 'Just now', 'time' => date('Y-m-d H:i:s')),
    array('label' => '5 minutes ago', 'time' => date('Y-m-d H:i:s', time() - 300)),
    array('label' => '1 hour ago', 'time' => date('Y-m-d H:i:s', time() - 3600)),
    array('label' => '1 day ago', 'time' => date('Y-m-d H:i:s', time() - 86400)),
    array('label' => '2 days ago', 'time' => date('Y-m-d H:i:s', time() - 172800)),
);

// Display the test javascript component
echo "<div id='test-display' style='margin-top: 20px; padding: 15px; border: 1px solid #ccc;'>";
echo "<h4>Live Time Update Test</h4>";

foreach ($test_timestamps as $index => $test) {
    $timestamp = strtotime($test['time']);
    echo "<div style='margin: 10px 0;'>";
    echo "<strong>{$test['label']}:</strong> ";
    echo "<span id='test-time-{$index}' class='time-ago' data-timestamp='{$timestamp}'>";
    echo time_ago($test['time']);
    echo "</span>";
    echo "</div>";
}

echo "</div>";

// Update all projects with a fixed timestamp (optional - uncomment to use)
/*
echo "<h3>Update Project Timestamps (RUNS ONLY ONCE):</h3>";
$current_time = time();

// Update project 1 to just now
$time_just_now = date('Y-m-d H:i:s', $current_time);
$conn->query("UPDATE projects SET submission_date = '$time_just_now' WHERE project_id = 1");

// Update project 2 to 5 minutes ago
$time_5min = date('Y-m-d H:i:s', $current_time - 300);
$conn->query("UPDATE projects SET submission_date = '$time_5min' WHERE project_id = 2");

// Update project 3 to 1 hour ago
$time_1hr = date('Y-m-d H:i:s', $current_time - 3600);
$conn->query("UPDATE projects SET submission_date = '$time_1hr' WHERE project_id = 3");

// Update project 4 to 1 day ago
$time_1day = date('Y-m-d H:i:s', $current_time - 86400);
$conn->query("UPDATE projects SET submission_date = '$time_1day' WHERE project_id = 4");

// Update project 5 to 2 days ago
$time_2days = date('Y-m-d H:i:s', $current_time - 172800);
$conn->query("UPDATE projects SET submission_date = '$time_2days' WHERE project_id = 5");

echo "<p>Timestamps updated!</p>";
*/
?>

<!-- Dynamic Time Ago Script -->
<script>
// Convert PHP timestamps to dynamic relative time
function updateRelativeTimes() {
    const timeElements = document.querySelectorAll('.time-ago');
    
    timeElements.forEach(element => {
        const timestamp = parseInt(element.getAttribute('data-timestamp'));
        if (timestamp) {
            element.textContent = getRelativeTimeString(timestamp);
        }
    });
    
    // Update every 5 seconds for testing (normally would be 60 seconds)
    setTimeout(updateRelativeTimes, 5000);
}

// Calculate relative time string
function getRelativeTimeString(timestamp) {
    const now = Math.floor(Date.now() / 1000);
    const seconds = now - timestamp;
    
    if (seconds < 60) {
        return 'Just now';
    } else if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
    } else if (seconds < 86400) {
        const hours = Math.floor(seconds / 3600);
        return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
    } else if (seconds < 2592000) {
        const days = Math.floor(seconds / 86400);
        return days + ' day' + (days > 1 ? 's' : '') + ' ago';
    } else if (seconds < 31536000) {
        const months = Math.floor(seconds / 2592000);
        return months + ' month' + (months > 1 ? 's' : '') + ' ago';
    } else {
        const years = Math.floor(seconds / 31536000);
        return years + ' year' + (years > 1 ? 's' : '') + ' ago';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log("Time update script initialized");
    updateRelativeTimes();
    
    // Add debugging for testing
    const timeElements = document.querySelectorAll('.time-ago');
    timeElements.forEach(element => {
        const timestamp = parseInt(element.getAttribute('data-timestamp'));
        console.log("Element:", element, "Timestamp:", timestamp, "Current time:", Math.floor(Date.now() / 1000));
    });
});
</script> 