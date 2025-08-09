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
    $_SESSION['message'] = "You must log in to view the leaderboard";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Get project evaluations with top scores
$top_projects = [];
$sql = "SELECT 
            p.project_id, 
            p.title, 
            t.team_name,
            AVG(e.total_score) as avg_score,
            COUNT(DISTINCT e.judge_id) as judges_count
        FROM 
            projects p
        JOIN 
            teams t ON p.team_id = t.team_id
        JOIN 
            evaluations e ON p.project_id = e.project_id
        WHERE 
            p.status = 'approved'
        GROUP BY 
            p.project_id, p.title, t.team_name
        ORDER BY 
            avg_score DESC
        LIMIT 5";

if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $top_projects[] = $row;
        }
    }
}

// Set page title
$title = "Leaderboard";
// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mb-3"><i class="fas fa-trophy text-warning me-2"></i>Project Leaderboard</h1>
            <p class="lead text-muted">Top 5 projects based on judge evaluations</p>
        </div>
    </div>
    
    <?php if (count($top_projects) > 0): ?>
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="py-3 ps-4">Rank</th>
                            <th class="py-3">Project</th>
                            <th class="py-3">Team</th>
                            <th class="py-3">Average Score</th>
                            <th class="py-3">Judges</th>
                            <th class="py-3 pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_projects as $index => $project): ?>
                        <tr class="<?php echo ($index === 0) ? 'table-warning' : ''; ?>">
                            <td class="ps-4">
                                <?php if ($index === 0): ?>
                                <span class="badge bg-warning rounded-circle p-2"><i class="fas fa-crown"></i></span>
                                <?php else: ?>
                                <span class="fw-bold"><?php echo $index + 1; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project['project_id']; ?>" class="fw-medium text-decoration-none">
                                    <?php echo htmlspecialchars($project['title']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($project['team_name']); ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo min(($project['avg_score'] / 40) * 100, 100); ?>%" 
                                             aria-valuenow="<?php echo $project['avg_score']; ?>" 
                                             aria-valuemin="0" aria-valuemax="40"></div>
                                    </div>
                                    <span class="fw-bold"><?php echo number_format($project['avg_score'], 1); ?></span>
                                </div>
                            </td>
                            <td><?php echo $project['judges_count']; ?></td>
                            <td class="pe-4 text-end">
                                <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-primary rounded-pill">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-light py-3">
            <h5 class="mb-0">Scoring Information</h5>
        </div>
        <div class="card-body">
            <p class="mb-3">Projects are evaluated by judges based on the following criteria:</p>
            <div class="row">
                <div class="col-md-6">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Innovation
                            <span class="badge bg-info rounded-pill">10 points</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Technical Implementation
                            <span class="badge bg-info rounded-pill">10 points</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Practicality
                            <span class="badge bg-info rounded-pill">10 points</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Presentation
                            <span class="badge bg-info rounded-pill">10 points</span>
                        </li>
                    </ul>
                </div>
            </div>
            <p class="mt-3 mb-0 small text-muted">
                The maximum possible score for a project is 40 points. The leaderboard displays the average score across all judges who evaluated each project.
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No evaluated projects found. Check back later when judges have evaluated submissions.
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?> 