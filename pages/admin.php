<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is an admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['warning'] = "You don't have permission to access the admin area.";
    header("Location: dashboard.php");
    exit();
}

// Handle actions if any
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'approve_user':
            if (isset($_POST['user_id'])) {
                $userId = $_POST['user_id'];
                $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
                $stmt->bind_param("i", $userId);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "User approved successfully.";
                } else {
                    $_SESSION['error'] = "Failed to approve user: " . $conn->error;
                }
                $stmt->close();
            }
            break;
            
        case 'reject_user':
            if (isset($_POST['user_id'])) {
                $userId = $_POST['user_id'];
                $stmt = $conn->prepare("UPDATE users SET is_approved = 0 WHERE id = ?");
                $stmt->bind_param("i", $userId);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "User rejected successfully.";
                } else {
                    $_SESSION['error'] = "Failed to reject user: " . $conn->error;
                }
                $stmt->close();
            }
            break;
            
        case 'delete_user':
            if (isset($_POST['user_id'])) {
                $userId = $_POST['user_id'];
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "User deleted successfully.";
                } else {
                    $_SESSION['error'] = "Failed to delete user: " . $conn->error;
                }
                $stmt->close();
            }
            break;
            
        case 'delete_team':
            if (isset($_POST['team_id'])) {
                $teamId = $_POST['team_id'];
                
                // Start transaction
                $conn->begin_transaction();
                try {
                    // Delete team members associations
                    $stmt = $conn->prepare("DELETE FROM team_members WHERE team_id = ?");
                    $stmt->bind_param("i", $teamId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Delete team join requests
                    $stmt = $conn->prepare("DELETE FROM team_requests WHERE team_id = ?");
                    $stmt->bind_param("i", $teamId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Delete projects associated with the team
                    $stmt = $conn->prepare("DELETE FROM projects WHERE team_id = ?");
                    $stmt->bind_param("i", $teamId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Finally delete the team
                    $stmt = $conn->prepare("DELETE FROM teams WHERE id = ?");
                    $stmt->bind_param("i", $teamId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $conn->commit();
                    $_SESSION['success'] = "Team and associated data deleted successfully.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Failed to delete team: " . $e->getMessage();
                }
            }
            break;
            
        case 'update_hackathon':
            if (isset($_POST['hackathon_name']) && isset($_POST['registration_deadline']) && 
                isset($_POST['submission_deadline'])) {
                
                $name = $_POST['hackathon_name'];
                $regDeadline = $_POST['registration_deadline'];
                $subDeadline = $_POST['submission_deadline'];
                $description = $_POST['hackathon_description'] ?? '';
                
                // Check if hackathon settings exist
                $check = $conn->query("SELECT COUNT(*) as count FROM hackathon_settings");
                $row = $check->fetch_assoc();
                
                if ($row['count'] > 0) {
                    // Update existing settings
                    $stmt = $conn->prepare("UPDATE hackathon_settings SET name = ?, description = ?, registration_deadline = ?, submission_deadline = ?");
                    $stmt->bind_param("ssss", $name, $description, $regDeadline, $subDeadline);
                } else {
                    // Insert new settings
                    $stmt = $conn->prepare("INSERT INTO hackathon_settings (name, description, registration_deadline, submission_deadline) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $description, $regDeadline, $subDeadline);
                }
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Hackathon settings updated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to update hackathon settings: " . $conn->error;
                }
                $stmt->close();
            }
            break;
    }
    
    // Redirect to refresh the page and prevent form resubmission
    header("Location: admin.php");
    exit();
}

// Get hackathon settings
$hackathonSettings = null;
$settingsQuery = $conn->query("SELECT * FROM hackathon_settings LIMIT 1");
if ($settingsQuery->num_rows > 0) {
    $hackathonSettings = $settingsQuery->fetch_assoc();
}

// Get users pending approval
$pendingUsersQuery = $conn->query("SELECT * FROM users WHERE is_approved = 0 ORDER BY created_at DESC");
$pendingUsers = [];
while ($user = $pendingUsersQuery->fetch_assoc()) {
    $pendingUsers[] = $user;
}

// Get all users for management
$usersQuery = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$allUsers = [];
while ($user = $usersQuery->fetch_assoc()) {
    $allUsers[] = $user;
}

// Get all teams
$teamsQuery = $conn->query("SELECT t.*, COUNT(tm.user_id) as member_count 
                           FROM teams t 
                           LEFT JOIN team_members tm ON t.id = tm.team_id 
                           GROUP BY t.id 
                           ORDER BY t.created_at DESC");
$allTeams = [];
while ($team = $teamsQuery->fetch_assoc()) {
    $allTeams[] = $team;
}

// Get all projects
$projectsQuery = $conn->query("SELECT p.*, t.name as team_name 
                              FROM projects p 
                              JOIN teams t ON p.team_id = t.id 
                              ORDER BY p.created_at DESC");
$allProjects = [];
while ($project = $projectsQuery->fetch_assoc()) {
    $allProjects[] = $project;
}

$title = "Admin Panel";
include '../includes/header.php';
?>

<div class="container mt-4">
    <h1>Admin Panel</h1>
    
    <?php include '../includes/messages.php'; ?>
    
    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="dashboard-tab" data-toggle="tab" href="#dashboard" role="tab">Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="users-tab" data-toggle="tab" href="#users" role="tab">Users</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="teams-tab" data-toggle="tab" href="#teams" role="tab">Teams</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="projects-tab" data-toggle="tab" href="#projects" role="tab">Projects</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="settings-tab" data-toggle="tab" href="#settings" role="tab">Hackathon Settings</a>
        </li>
    </ul>
    
    <div class="tab-content p-3 border border-top-0 rounded-bottom" id="adminTabContent">
        <!-- Dashboard Tab -->
        <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Users</h5>
                            <h2><?php echo count($allUsers); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Teams</h5>
                            <h2><?php echo count($allTeams); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Projects</h5>
                            <h2><?php echo count($allProjects); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h5 class="card-title">Pending Approvals</h5>
                            <h2><?php echo count($pendingUsers); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Approvals Section -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Pending User Approvals</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingUsers)): ?>
                        <p class="text-muted">No pending approvals.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Registered On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="approve_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                </form>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="reject_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Users Tab -->
        <div class="tab-pane fade" id="users" role="tabpanel">
            <h3>User Management</h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td>
                                    <?php if ($user['is_approved'] == 1): ?>
                                        <span class="badge badge-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['role'] !== 'admin' || $_SESSION['user_id'] != $user['id']): ?>
                                        <?php if ($user['is_approved'] == 0): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="approve_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="reject_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-warning">Revoke</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Teams Tab -->
        <div class="tab-pane fade" id="teams" role="tabpanel">
            <h3>Team Management</h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Team Name</th>
                            <th>Members</th>
                            <th>Description</th>
                            <th>Created On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allTeams as $team): ?>
                            <tr>
                                <td><?php echo $team['id']; ?></td>
                                <td><?php echo htmlspecialchars($team['name']); ?></td>
                                <td><?php echo $team['member_count']; ?></td>
                                <td><?php echo htmlspecialchars(substr($team['description'], 0, 50)) . (strlen($team['description']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($team['created_at'])); ?></td>
                                <td>
                                    <a href="team-details.php?id=<?php echo $team['id']; ?>" class="btn btn-sm btn-info">View</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this team? This will also remove all associated projects and team members. This action cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_team">
                                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Projects Tab -->
        <div class="tab-pane fade" id="projects" role="tabpanel">
            <h3>Project Management</h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Project Name</th>
                            <th>Team</th>
                            <th>Status</th>
                            <th>Submitted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allProjects as $project): ?>
                            <tr>
                                <td><?php echo $project['id']; ?></td>
                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                <td><?php echo htmlspecialchars($project['team_name']); ?></td>
                                <td>
                                    <?php
                                    switch ($project['status']) {
                                        case 'draft':
                                            echo '<span class="badge badge-secondary">Draft</span>';
                                            break;
                                        case 'submitted':
                                            echo '<span class="badge badge-primary">Submitted</span>';
                                            break;
                                        case 'evaluated':
                                            echo '<span class="badge badge-success">Evaluated</span>';
                                            break;
                                        default:
                                            echo '<span class="badge badge-light">Unknown</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($project['created_at'])); ?></td>
                                <td>
                                    <a href="project-details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info">View</a>
                                    <?php if ($project['status'] === 'submitted'): ?>
                                        <a href="evaluate-project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">Evaluate</a>
                                    <?php elseif ($project['status'] === 'evaluated'): ?>
                                        <a href="evaluate-project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-secondary">Re-evaluate</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Hackathon Settings Tab -->
        <div class="tab-pane fade" id="settings" role="tabpanel">
            <h3>Hackathon Settings</h3>
            <form method="post" class="mb-4">
                <input type="hidden" name="action" value="update_hackathon">
                
                <div class="form-group">
                    <label for="hackathon_name">Hackathon Name</label>
                    <input type="text" class="form-control" id="hackathon_name" name="hackathon_name" required
                           value="<?php echo $hackathonSettings ? htmlspecialchars($hackathonSettings['name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="hackathon_description">Description</label>
                    <textarea class="form-control" id="hackathon_description" name="hackathon_description" rows="4"><?php echo $hackathonSettings ? htmlspecialchars($hackathonSettings['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="registration_deadline">Registration Deadline</label>
                    <input type="datetime-local" class="form-control" id="registration_deadline" name="registration_deadline" required
                           value="<?php echo $hackathonSettings ? date('Y-m-d\TH:i', strtotime($hackathonSettings['registration_deadline'])) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="submission_deadline">Submission Deadline</label>
                    <input type="datetime-local" class="form-control" id="submission_deadline" name="submission_deadline" required
                           value="<?php echo $hackathonSettings ? date('Y-m-d\TH:i', strtotime($hackathonSettings['submission_deadline'])) : ''; ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
</div>

<script>
// Activate the correct tab based on hash in URL
$(document).ready(function() {
    if (window.location.hash) {
        $('#adminTabs a[href="' + window.location.hash + '"]').tab('show');
    }
    
    // Change hash when tabs are clicked
    $('#adminTabs a').on('click', function(e) {
        window.location.hash = $(this).attr('href');
    });
});
</script>

<?php include '../includes/footer.php'; ?> 