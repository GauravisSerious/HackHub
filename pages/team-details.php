<?php
// Start output buffering to catch any unexpected output from includes
ob_start();

// Include configuration file
require_once '../includes/config.php';
// Include auth functions - avoid include_once which might have issues
require '../includes/auth-new.php';

// Capture any output from includes
$unexpected_output = ob_get_clean();

// If there's unexpected output (like BOM), log it but don't display it
if (!empty($unexpected_output)) {
    error_log("Unexpected output from includes: " . bin2hex($unexpected_output));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Check if team ID is provided
if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . '/pages/teams.php');
    exit();
}

$team_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Make sure team_id is set and is a valid integer before using it in a query
if (!isset($team_id) || empty($team_id) || !is_numeric($team_id)) {
    $_SESSION['message'] = "Invalid team ID";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "/pages/teams.php");
    exit();
}

$team_id = intval($team_id); // Ensure it's an integer

// ULTRA RELIABLE: Direct SQL query to get all team members
$direct_members = [];
$direct_sql = "SELECT tm.user_id, tm.is_leader, u.first_name, u.last_name 
               FROM team_members tm 
               JOIN users u ON tm.user_id = u.user_id 
               WHERE tm.team_id = $team_id 
               ORDER BY tm.is_leader DESC, u.first_name ASC";
$direct_result = mysqli_query($conn, $direct_sql);
if ($direct_result) {
    $direct_members_count = mysqli_num_rows($direct_result);
    error_log("DIRECT SQL FOUND $direct_members_count MEMBERS FOR TEAM $team_id");
    
    while ($member = mysqli_fetch_assoc($direct_result)) {
        $direct_members[] = [
            'user_id' => $member['user_id'],
            'first_name' => $member['first_name'],
            'last_name' => $member['last_name'],
            'is_leader' => $member['is_leader']
        ];
    }
}

// Get team details
$sql = "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name
        FROM teams t
        JOIN users u ON t.created_by = u.user_id
        WHERE t.team_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $team = mysqli_fetch_assoc($result);
        } else {
            // Set message
            $_SESSION['message'] = "Team not found";
            $_SESSION['message_type'] = "warning";
            
            // Redirect to teams page
            header("Location: " . BASE_URL . "/pages/teams.php");
            exit();
        }
    } else {
        // Set message
        $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
        $_SESSION['message_type'] = "danger";
        
        // Redirect to teams page
        header("Location: " . BASE_URL . "/pages/teams.php");
        exit();
    }
    
    mysqli_stmt_close($stmt);
} else {
    // Set message
    $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
    $_SESSION['message_type'] = "danger";
    
    // Redirect to teams page
    header("Location: " . BASE_URL . "/pages/teams.php");
    exit();
}

// Check if user is a member of this team
$isMember = false;
$isLeader = false;

$sql = "SELECT is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $isMember = true;
            mysqli_stmt_bind_result($stmt, $leader_status);
            mysqli_stmt_fetch($stmt);
            $isLeader = $leader_status == 1;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Check if the user has already requested to join this team
$hasRequestedToJoin = false;

// Try to query the team_join_requests table, but catch exceptions if it doesn't exist
try {
    $sql = "SELECT request_id FROM team_join_requests WHERE team_id = ? AND user_id = ? AND status = 'pending'";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $hasRequestedToJoin = true;
            }
        }
        
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    // Table likely doesn't exist yet, just continue without it
    $hasRequestedToJoin = false;
}

// Process join request if submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'join') {
    // Check if user is already a member of another team
    $sql = "SELECT team_id FROM team_members WHERE user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                // Set message
                $_SESSION['message'] = "You are already a member of another team";
                $_SESSION['message_type'] = "warning";
                
                // Redirect back to team details
                header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
                exit();
            }
        }
        
        mysqli_stmt_close($stmt);
    }
    
    // Try to submit join request
    try {
        // First, check if the table exists
        $tableExists = false;
        $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'team_join_requests'");
        if (mysqli_num_rows($checkTable) > 0) {
            $tableExists = true;
        }
        
        if ($tableExists) {
            // Submit join request if table exists
            $sql = "INSERT INTO team_join_requests (team_id, user_id, request_message, status) VALUES (?, ?, ?, 'pending')";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "iis", $team_id, $user_id, $request_message);
                
                $request_message = isset($_POST['message']) ? trim($_POST['message']) : "I would like to join your team.";
                
                if (mysqli_stmt_execute($stmt)) {
                    // Set success message
                    $_SESSION['message'] = "Join request sent successfully!";
                    $_SESSION['message_type'] = "success";
                    
                    // Redirect back to team details
                    header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
                    exit();
                } else {
                    // Set message
                    $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
                    $_SESSION['message_type'] = "danger";
                }
                
                mysqli_stmt_close($stmt);
            }
        } else {
            // If table doesn't exist, add user directly to the team (temporary workaround)
            $sql = "INSERT INTO team_members (team_id, user_id, is_leader) VALUES (?, ?, 0)";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Set success message
                    $_SESSION['message'] = "You have joined the team successfully!";
                    $_SESSION['message_type'] = "success";
                    
                    // Redirect back to team details
                    header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
                    exit();
                } else {
                    // Set message
                    $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
                    $_SESSION['message_type'] = "danger";
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    } catch (Exception $e) {
        // Set message
        $_SESSION['message'] = "Oops! Something went wrong. Please try again later. Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

// Process direct add user request (admin only) - DISABLED FOR ADMIN
if (false && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['direct_add_submit']) && isAdmin()) {
    $_SESSION['message'] = "This feature has been disabled for administrators";
            $_SESSION['message_type'] = "warning";
    
    // Redirect to self to refresh the page
    header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
    exit();
    
    // Original code starts below (never executed)
    $add_user_id = intval($_POST['direct_add_user_id']);
}

// Get team members
$team_members = array();
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.username, u.profile_pic, u.skills, tm.is_leader 
        FROM users u 
        JOIN team_members tm ON u.user_id = tm.user_id 
        WHERE tm.team_id = ?
        ORDER BY tm.is_leader DESC, u.first_name, u.last_name";

// Debug the raw team members data in the database
$debug_sql = "SELECT * FROM team_members WHERE team_id = $team_id";
$debug_result = mysqli_query($conn, $debug_sql);
error_log("Raw team_members table data for team_id $team_id:");
while ($row = mysqli_fetch_assoc($debug_result)) {
    error_log("  user_id: {$row['user_id']}, is_leader: {$row['is_leader']}");
}

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    
    // Log the query being executed
    error_log("Executing team members query for team_id: $team_id");
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $num_rows = mysqli_num_rows($result);
        
        // Log the number of results
        error_log("Found $num_rows team members for team_id: $team_id");
        
        while ($row = mysqli_fetch_assoc($result)) {
            $team_members[] = $row;
            // Log each member
            error_log("Team member: {$row['first_name']} {$row['last_name']} (user_id: {$row['user_id']}, is_leader: {$row['is_leader']})");
        }
    } else {
        error_log("Error executing team members query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_close($stmt);
} else {
    error_log("Error preparing team members query: " . mysqli_error($conn));
}

// When we get to the team members section, ensure we have reliable data
if (empty($team_members) && !empty($direct_members)) {
    error_log("EMERGENCY: team_members array is empty but direct query found members. Using direct data.");
    $team_members = $direct_members;
}

// Set flag to show debug information
$is_debug_user = in_array($user_id, [6, 7]) || 
                 (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Get pending join requests (only for team leader)
$join_requests = array();
if ($isLeader) {
    try {
        // First, check if the table exists
        $tableExists = false;
        $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'team_join_requests'");
        if (mysqli_num_rows($checkTable) > 0) {
            $tableExists = true;
        }
        
        if ($tableExists) {
            $sql = "SELECT r.request_id, r.user_id, r.request_message, r.created_at, u.first_name, u.last_name, u.username 
                    FROM team_join_requests r 
                    JOIN users u ON r.user_id = u.user_id 
                    WHERE r.team_id = ? AND r.status = 'pending'";
                    
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $team_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    
                    while ($row = mysqli_fetch_assoc($result)) {
                        $join_requests[] = $row;
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    } catch (Exception $e) {
        // If there's an error, just continue without join requests
        $join_requests = array();
    }
}

// Get team projects
$projects = array();
if ($isMember || isAdmin()) {
    $sql = "SELECT project_id, title, description, status, submission_date 
            FROM projects 
            WHERE team_id = ?";
            
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $team_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $projects[] = $row;
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Include header
$title = "Team Details: " . $team['team_name'];
require_once '../includes/header.php';
?>

<style>
    .team-details-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }
    
    .team-details-header {
        margin-bottom: 1.5rem;
    }
    
    .team-details-header h1 {
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #333;
    }
    
    .breadcrumb {
        background-color: transparent;
        padding: 0;
        margin-bottom: 1.5rem;
    }
    
    .team-details-card {
        background-color: #fff;
        border: 1px solid #495057;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        height: 100%;
    }
    
    .team-details-card-header {
        padding: 1rem;
        border-bottom: 1px solid #495057;
        font-weight: 600;
        background-color: #f8f9fa;
    }
    
    .team-details-card-body {
        padding: 1.25rem;
    }
    
    .info-row {
        margin-bottom: 1rem;
    }
    
    .info-row:last-child {
        margin-bottom: 0;
    }
    
    .info-label {
        font-weight: 500;
        color: #495057;
    }
    
    .member-table {
        width: 100%;
    }
    
    .member-table th, 
    .member-table td {
        padding: 0.75rem;
    }
    
    .member-table th {
        text-align: left;
        border-bottom: 1px solid #dee2e6;
    }
    
    .member-badge {
        font-size: 0.75rem;
        padding: 0.25em 0.6em;
        border-radius: 0.25rem;
        background-color: #4a6cf7;
        color: white;
        font-weight: normal;
    }
    
    .member-badge.member {
        background-color: #6c757d;
    }
</style>

<div class="team-details-container">
    <div class="team-details-header">
        <h1><?php echo htmlspecialchars($team['team_name']); ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/teams.php">Teams</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($team['team_name']); ?></li>
                </ol>
            </nav>
    </div>
    
    <div class="row">
            <!-- Team Details -->
        <div class="col-md-7">
            <div class="team-details-card">
                <div class="team-details-card-header">
                    Team Details
                </div>
                <div class="team-details-card-body">
                    <div class="info-row">
                        <?php echo nl2br(htmlspecialchars($team['team_description'])); ?>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Created by:</span> 
                        <?php echo htmlspecialchars($team['creator_name']); ?>
            </div>
            
                    <div class="info-row">
                        <span class="info-label">Created on:</span> 
                        <?php echo date('M d, Y', strtotime($team['created_at'])); ?>
            </div>
            
                    <?php if ($isMember && $isLeader): ?>
                    <div class="mt-4">
                        <a href="<?php echo BASE_URL; ?>/pages/edit-team.php?id=<?php echo $team_id; ?>" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Invite Members
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Members Summary -->
        <div class="col-md-5">
            <div class="team-details-card">
                <div class="team-details-card-header">
                    Members Summary (<?php echo count($direct_members); ?>)
                </div>
                <div class="team-details-card-body">
                    <table class="member-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                        <tbody>
                            <?php foreach ($direct_members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                <td>
                                                <?php if ($member['is_leader']): ?>
                                    <span class="member-badge">Leader</span>
                                                <?php else: ?>
                                    <span class="member-badge member">Member</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?> 