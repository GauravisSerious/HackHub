<?php
// Include configuration file
require_once '../includes/config.php';

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
if (isJudge()) {
    // Redirect to judge.php if they're a judge
    header("Location: " . BASE_URL . "/pages/judge.php");
    exit();
}

// Check if user is admin
if (!isAdmin()) {
    // Set message
    $_SESSION['message'] = "You don't have permission to access this page";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Get all evaluated projects
$projects = array();
$sql = "SELECT p.project_id, p.title, t.team_name, 
               COUNT(e.evaluation_id) as evaluation_count, 
               AVG(e.total_score) as avg_score 
        FROM projects p 
        JOIN teams t ON p.team_id = t.team_id 
        LEFT JOIN evaluations e ON p.project_id = e.project_id 
        WHERE p.status = 'approved'
        GROUP BY p.project_id 
        ORDER BY avg_score DESC";

if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $projects[] = $row;
    }
}

// Include header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Project Evaluations</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Project Evaluations</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Project Rankings</h5>
                </div>
                <div class="card-body">
                    <?php if (count($projects) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Project</th>
                                    <th>Team</th>
                                    <th>Evaluations</th>
                                    <th>Avg. Score</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $index => $project): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($project['title']); ?></td>
                                    <td><?php echo htmlspecialchars($project['team_name']); ?></td>
                                    <td><?php echo $project['evaluation_count']; ?></td>
                                    <td>
                                        <?php 
                                        if ($project['evaluation_count'] > 0) {
                                            echo number_format($project['avg_score'], 1) . '/40';
                                        } else {
                                            echo '<span class="text-muted">No evaluations</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No projects have been approved for evaluation yet.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?> 