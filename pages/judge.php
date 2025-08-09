<?php
// Start output buffering to catch any unexpected output from includes
ob_start();

// Include configuration file
require_once '../includes/config.php';
// Include auth functions
require_once '../includes/auth-new.php';

// Capture any output from includes
$unexpected_output = ob_get_clean();

// If there's unexpected output (like BOM), log it but don't display it
if (!empty($unexpected_output)) {
    error_log("Unexpected output from includes: " . bin2hex($unexpected_output));
}

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to access this page";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if user is a judge
if (!isJudge()) {
    // Set message
    $_SESSION['message'] = "You don't have permission to access the judge panel";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all submitted projects that haven't been evaluated by this judge
$projects = array();
$sql = "SELECT p.*, t.team_name, u.first_name, u.last_name, u.username 
        FROM projects p 
        JOIN teams t ON p.team_id = t.team_id 
        JOIN users u ON t.created_by = u.user_id 
        WHERE p.status = 'submitted' 
        AND p.project_id NOT IN (
            SELECT project_id FROM evaluations WHERE judge_id = ?
        )
        ORDER BY p.submission_date DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $projects[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Get projects already evaluated by this judge
$evaluated_projects = array();
$sql = "SELECT p.*, t.team_name, u.first_name, u.last_name, u.username, e.total_score, e.evaluated_at 
        FROM projects p 
        JOIN teams t ON p.team_id = t.team_id 
        JOIN users u ON t.created_by = u.user_id 
        JOIN evaluations e ON p.project_id = e.project_id 
        WHERE e.judge_id = ? 
        ORDER BY e.evaluated_at DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $evaluated_projects[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Include header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Judge Panel</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Judge Panel</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Projects to Evaluate -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Projects to Evaluate</h5>
                </div>
                <div class="card-body">
                    <?php if (count($projects) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Team</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['title']); ?></td>
                                    <td><?php echo htmlspecialchars($project['team_name']); ?></td>
                                    <td><span class="time-ago" data-timestamp="<?php echo strtotime($project['submission_date']); ?>"><?php echo time_ago($project['submission_date']); ?></span></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        <a href="<?php echo BASE_URL; ?>/pages/evaluate-project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-success">Evaluate</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No projects are currently waiting for evaluation.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Evaluated Projects -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Your Evaluations</h5>
                </div>
                <div class="card-body">
                    <?php if (count($evaluated_projects) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Team</th>
                                    <th>Score</th>
                                    <th>Evaluated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evaluated_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['title']); ?></td>
                                    <td><?php echo htmlspecialchars($project['team_name']); ?></td>
                                    <td><?php echo number_format($project['total_score'], 1); ?>/40</td>
                                    <td><span class="time-ago" data-timestamp="<?php echo strtotime($project['evaluated_at']); ?>"><?php echo time_ago($project['evaluated_at']); ?></span></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        <a href="<?php echo BASE_URL; ?>/pages/evaluate-project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-secondary">Review</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> You haven't evaluated any projects yet.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?> 