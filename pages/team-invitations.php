<?php
// Include configuration file
require_once '../includes/config.php';
// Include auth functions
require_once '../includes/auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to view team invitations";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle invitation response
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    
    // Validate request belongs to user
    $sql = "SELECT team_id FROM team_requests WHERE id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $request_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $team_id = $row['team_id'];
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                if ($action === 'accept') {
                    // First check if user is already in another team
                    $check_sql = "SELECT COUNT(*) as team_count FROM team_members WHERE user_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $check_row = mysqli_fetch_assoc($check_result);
                    
                    if ($check_row['team_count'] > 0) {
                        throw new Exception("You are already a member of another team");
                    }
                    
                    // Add user to team members
                    $add_sql = "INSERT INTO team_members (team_id, user_id, is_leader) VALUES (?, ?, 0)";
                    $add_stmt = mysqli_prepare($conn, $add_sql);
                    mysqli_stmt_bind_param($add_stmt, "ii", $team_id, $user_id);
                    mysqli_stmt_execute($add_stmt);
                }
                
                // Update request status
                $status = ($action === 'accept') ? 'approved' : 'rejected';
                $update_sql = "UPDATE team_requests SET status = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $status, $request_id);
                mysqli_stmt_execute($update_stmt);
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Set success message
                $action_text = ($action === 'accept') ? 'accepted' : 'declined';
                $_SESSION['message'] = "Team invitation $action_text successfully";
                $_SESSION['message_type'] = "success";
                
                // If user accepted the invitation, redirect to team details
                if ($action === 'accept') {
                    header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
                    exit();
                }
                
            } catch (Exception $e) {
                // Rollback transaction
                mysqli_rollback($conn);
                
                // Set error message
                $_SESSION['message'] = "Error: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
        } else {
            // Invalid request
            $_SESSION['message'] = "Invalid invitation request";
            $_SESSION['message_type'] = "danger";
        }
        
        // Redirect back to invitations page
        header("Location: " . BASE_URL . "/pages/team-invitations.php");
        exit();
    }
}

// Get pending team invitations
$invitations = [];
$sql = "SELECT tr.*, t.team_name, t.team_description, 
        CONCAT(u.first_name, ' ', u.last_name) as leader_name 
        FROM team_requests tr 
        JOIN teams t ON tr.team_id = t.team_id 
        JOIN team_members tm ON t.team_id = tm.team_id AND tm.is_leader = 1 
        JOIN users u ON tm.user_id = u.user_id 
        WHERE tr.user_id = ? AND tr.status = 'pending'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $invitations[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Get past team invitations
$past_invitations = [];
$sql = "SELECT tr.*, t.team_name, tr.status, tr.updated_at 
        FROM team_requests tr 
        JOIN teams t ON tr.team_id = t.team_id
        WHERE tr.user_id = ? AND tr.status != 'pending' 
        ORDER BY tr.updated_at DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $past_invitations[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Include header
require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Team Invitations</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Team Invitations</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Pending Invitations -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Pending Invitations</h5>
                </div>
                <div class="card-body">
                    <?php if (count($invitations) > 0): ?>
                        <?php foreach ($invitations as $invitation): ?>
                            <div class="card mb-3 border-info">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($invitation['team_name']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($invitation['team_description']); ?></p>
                                    <p class="mb-2"><strong>Team Leader:</strong> <?php echo htmlspecialchars($invitation['leader_name']); ?></p>
                                    <p class="mb-3"><small class="text-muted">Invited on: <?php echo date('F j, Y', strtotime($invitation['created_at'])); ?></small></p>
                                    
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?php echo $invitation['id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-success me-2">Accept Invitation</button>
                                    </form>
                                    
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?php echo $invitation['id']; ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <button type="submit" class="btn btn-outline-danger">Decline</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> You have no pending team invitations.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Past Invitations -->
            <?php if (count($past_invitations) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Past Invitations</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Team Name</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($past_invitations as $invitation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($invitation['team_name']); ?></td>
                                    <td>
                                        <?php if ($invitation['status'] == 'approved'): ?>
                                            <span class="badge bg-success">Accepted</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Declined</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($invitation['updated_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <!-- Info Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">About Team Invitations</h5>
                </div>
                <div class="card-body">
                    <p>When someone adds you as a team member, you'll receive an invitation here.</p>
                    <p>What happens when you accept an invitation:</p>
                    <ul>
                        <li>You'll become a member of the team</li>
                        <li>You'll be able to view and contribute to team projects</li>
                        <li>You can only be a member of one team at a time</li>
                    </ul>
                </div>
            </div>
            
            <!-- Actions Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_URL; ?>/pages/create-team.php" class="btn btn-primary">
                            <i class="fas fa-users me-2"></i> Create Your Own Team
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/teams.php" class="btn btn-outline-secondary">
                            <i class="fas fa-search me-2"></i> Browse Teams
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?> 