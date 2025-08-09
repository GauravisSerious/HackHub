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

// Get user data
$user_id = $_SESSION['user_id'];

// Check if user has a team
$hasTeam = false;
$teamId = 0;
$teamName = "";
$isTeamLeader = false;

$sql = "SELECT t.team_id, t.team_name, tm.is_leader 
        FROM teams t 
        JOIN team_members tm ON t.team_id = tm.team_id 
        WHERE tm.user_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $hasTeam = true;
            mysqli_stmt_bind_result($stmt, $teamId, $teamName, $isTeamLeader);
            mysqli_stmt_fetch($stmt);
        }
    }
    mysqli_stmt_close($stmt);
}

// Get projects for user's team
$hasProjects = false;
$projects = [];

if ($hasTeam) {
    $sql = "SELECT project_id, title, status, submission_date 
            FROM projects 
            WHERE team_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $teamId);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                $hasProjects = true;
                while ($row = mysqli_fetch_assoc($result)) {
                    $projects[] = $row;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Get all submitted projects by all teams (for judges only)
$allProjects = [];
if (isJudge()) {
    $sql = "SELECT p.project_id, p.title, t.team_name, p.status, p.submission_date 
            FROM projects p
            JOIN teams t ON p.team_id = t.team_id
            WHERE p.status = 'submitted' OR p.status = 'approved'";
    
    if ($result = mysqli_query($conn, $sql)) {
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $allProjects[] = $row;
            }
        }
    }
}

// Include header
require_once '../includes/header.php';
?>

<style>
    /* Dashboard Container Styling */
    .dashboard-container {
        max-width: 1100px;
        margin: 0 auto;
    }
    
    /* Card Styling */
    .dashboard-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        margin-bottom: 1.25rem;
        height: 100%;
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
    }
    
    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .dashboard-card-header {
        padding: 0.9rem 1.2rem;
        border-bottom: 1px solid rgba(0,0,0,.05);
        font-weight: 600;
        background-color: #f8f9fa;
        color: #2d3748;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .dashboard-card-body {
        padding: 1.2rem;
    }
    
    /* Dashboard Team Cards Styling */
    .dashboard-team-cards .dashboard-card-header {
        background-color: #fff;
        border: 1px solid #495057;
        border-bottom: none;
        padding: 0.75rem 1rem;
        font-weight: 500;
        border-radius: 8px 8px 0 0;
    }
    
    .dashboard-team-cards .team-card {
        background-color: #fff;
        border: 1px solid #495057;
        border-top: none;
        border-radius: 0 0 8px 8px;
        padding: 1rem;
        margin-bottom: 0;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    /* Projects Empty State Styling */
    .project-empty-state {
        text-align: center;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
    }
    
    .project-empty-state-icon {
        font-size: 3rem;
        color: #e2e8f0;
        margin-bottom: 1rem;
    }
    
    /* Empty State Styling */
    .empty-state {
        text-align: center;
        padding: 1.5rem;
    }
    
    .empty-state-icon {
        font-size: 3rem;
        color: #cbd5e0;
        margin-bottom: 1rem;
    }
    
    .empty-state-text {
        color: #718096;
        margin-bottom: 1rem;
    }
    
    /* Team Card Styling */
    .team-card {
        background-color: #f8f9fa;
        border-radius: 6px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    /* For the specific dashboard implementation */
    .dashboard-team-cards .team-card {
        border-left: 0;
        background-color: #fff;
        margin-bottom: 0;
        border-radius: 0 0 6px 6px;
    }
    
    .team-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .team-name {
        font-weight: 600;
        font-size: 1.1rem;
        color: #2d3748;
    }
    
    .team-role {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
        background-color: #4a6cf7;
        color: white;
    }
    
    .member-list {
        padding-left: 0;
        list-style-type: none;
        margin-bottom: 0;
    }
    
    .member-list li {
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(0,0,0,.05);
        display: flex;
        align-items: center;
    }
    
    .member-list li:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    
    .member-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background-color: #e2e8f0;
        font-size: 0.75rem;
        margin-right: 0.5rem;
    }
    
    .member-badge {
        margin-left: auto;
        font-size: 0.7rem;
        padding: 0.15rem 0.4rem;
        border-radius: 10px;
    }
    
    .leader-badge {
        background-color: #4a6cf7;
        color: white;
    }
    
    .member-badge {
        background-color: #e2e8f0;
        color: #4a5568;
    }
    
    /* Timeline Styling */
    .timeline {
        position: relative;
        padding-left: 2rem;
    }
    
    .timeline-item {
        position: relative;
        padding-bottom: 1.5rem;
        padding-left: 1rem;
    }
    
    .timeline-item:before {
        content: "";
        position: absolute;
        left: -1.25rem;
        top: 0.25rem;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: #e2e8f0;
        border: 2px solid #4a6cf7;
        z-index: 1;
    }
    
    .timeline-item:after {
        content: "";
        position: absolute;
        left: -1.2rem;
        top: 0.5rem;
        bottom: 0;
        width: 2px;
        background-color: #e2e8f0;
        z-index: 0;
    }
    
    .timeline-item:last-child:after {
        display: none;
    }
    
    .timeline-item.active:before {
        background-color: #4a6cf7;
    }
    
    /* Team Management Section */
    .team-management {
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    
    .team-management-header {
        background: linear-gradient(135deg, #4a6cf7, #2b3ddb);
        color: white;
        padding: 0.9rem 1.2rem;
        font-weight: 600;
    }
    
    .team-management-body {
        background-color: #f8f9fa;
        padding: 1.2rem;
    }
    
    .action-btn {
        display: flex;
        align-items: center;
        background-color: white;
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 6px;
        padding: 0.7rem 1rem;
        margin-bottom: 0.75rem;
        color: #4a5568;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .action-btn:hover {
        background-color: #f1f5f9;
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        text-decoration: none;
    }
    
    .action-btn i {
        margin-right: 0.5rem;
        color: #4a6cf7;
    }
    
    .action-btn:last-child {
        margin-bottom: 0;
    }
    
    .welcome-message {
        background: linear-gradient(135deg, #f6f8fd, #edf2ff);
        border-radius: 8px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.25rem;
        border-left: 4px solid #4a6cf7;
    }
    
    .project-empty-state .btn-primary {
        opacity: 1 !important;
        pointer-events: auto !important;
        cursor: pointer !important;
        background-color: #4a6cf7 !important;
        border-color: #4a6cf7 !important;
    }
    
    .project-empty-state .btn-primary:hover {
        background-color: #3a5ce5 !important;
        border-color: #3a5ce5 !important;
    }
    
    /* Ensure Create Project button is clickable */
    .create-project-btn {
        position: relative !important;
        z-index: 100 !important;
        display: inline-block !important;
        text-decoration: none !important;
    }

    /* Fix for team members display */
    .list-group-item {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
    }
    .list-group-item .badge {
        display: inline-block !important; 
        visibility: visible !important;
        margin-left: 8px !important;
    }

    /* Team Invitation Badge Styling */
    .action-btn {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .action-btn .badge {
        margin-left: 8px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Fix button clickability issues */
    .btn {
        position: relative !important;
        z-index: 10 !important;
        pointer-events: auto !important;
        cursor: pointer !important;
    }

    .view-project-btn, .create-project-btn {
        position: relative !important;
        z-index: 100 !important;
        pointer-events: auto !important;
        cursor: pointer !important;
        opacity: 1 !important;
    }

    /* Make sure table cells don't block button clicks */
    td {
        position: relative;
    }

    /* Stat Card Styling */
    .stat-card {
        border-radius: 12px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        padding: 2rem 1.5rem;
        text-align: center;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
        z-index: 1;
    }
    
    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.15);
    }
    
    .stat-card i {
        font-size: 2.75rem;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }
    
    .stat-card h3 {
        font-size: 2.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 2;
    }
    
    .stat-card p {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 0;
        font-weight: 500;
        position: relative;
        z-index: 2;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        width: 150%;
        height: 150%;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
        top: -25%;
        left: -25%;
        z-index: 1;
    }
    
    .stat-card-primary {
        background: linear-gradient(135deg, #4e73df 0%, #3260d7 100%);
        color: white;
    }
    
    .stat-card-success {
        background: linear-gradient(135deg, #1cc88a 0%, #13a673 100%);
        color: white;
    }
    
    .stat-card-info {
        background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
        color: white;
    }
    
    .stat-card-warning {
        background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
        color: white;
    }
    
    /* Welcome banner styling */
    .admin-welcome-banner {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    
    .admin-welcome-banner::before {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
        top: -100px;
        right: -100px;
        border-radius: 50%;
    }
    
    .admin-welcome-banner::after {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        bottom: -150px;
        left: -150px;
        border-radius: 50%;
    }
    
    /* Enhanced tooltips */
    .custom-tooltip {
        --bs-tooltip-bg: #4e73df;
        --bs-tooltip-color: white;
        font-size: 0.85rem;
    }
    
    /* Projects table styling */
    .projects-table thead th {
        font-weight: 600;
        color: #555;
        letter-spacing: 0.5px;
    }
    
    .projects-table tbody tr {
        transition: background-color 0.15s;
    }
    
    .projects-table tbody tr:hover {
        background-color: rgba(78, 115, 223, 0.05);
    }
    
    .projects-table .project-link {
        color: #4e73df;
        font-weight: 500;
        transition: color 0.15s;
    }
    
    .projects-table .project-link:hover {
        color: #224abe;
        text-decoration: none;
    }
</style>

<div class="container py-4 dashboard-container">
    <!-- Welcome Message -->
    <div class="welcome-message">
        <h4 class="mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h4>
        <p class="mb-0 text-muted">Manage your teams and projects from this dashboard.</p>
    </div>

    <!-- Single Project Delete Modal -->
    <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteProjectModalLabel">Confirm Project Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the project "<strong id="projectTitleToDelete"></strong>"?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. All project data will be permanently removed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="<?php echo BASE_URL; ?>/pages/project_delete.php" method="POST">
                        <input type="hidden" name="project_id" id="projectIdToDelete">
                        <button type="submit" class="btn btn-danger">Delete Project</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Dashboard</h1>
            <p class="text-muted">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>!</p>
        </div>
    </div>
    
    <?php if (isAdmin()): ?>
    <!-- Admin Dashboard -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card stat-card-primary rounded-4 shadow">
                <i class="fas fa-users"></i>
                <?php
                $sql = "SELECT COUNT(*) as count FROM users";
                $result = mysqli_query($conn, $sql);
                $row = mysqli_fetch_assoc($result);
                ?>
                <h3><?php echo $row['count']; ?></h3>
                <p>Total Users</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card stat-card-success rounded-4 shadow">
                <i class="fas fa-users-cog"></i>
                <?php
                $sql = "SELECT COUNT(*) as count FROM teams";
                $result = mysqli_query($conn, $sql);
                $row = mysqli_fetch_assoc($result);
                ?>
                <h3><?php echo $row['count']; ?></h3>
                <p>Total Teams</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card stat-card-info rounded-4 shadow">
                <i class="fas fa-code"></i>
                <?php
                $sql = "SELECT COUNT(*) as count FROM projects";
                $result = mysqli_query($conn, $sql);
                $row = mysqli_fetch_assoc($result);
                ?>
                <h3><?php echo $row['count']; ?></h3>
                <p>Total Projects</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stat-card stat-card-warning rounded-4 shadow">
                <i class="fas fa-clipboard-check"></i>
                <?php
                $sql = "SELECT COUNT(*) as count FROM projects WHERE status = 'submitted'";
                $result = mysqli_query($conn, $sql);
                $row = mysqli_fetch_assoc($result);
                ?>
                <h3><?php echo $row['count']; ?></h3>
                <p>Pending Reviews</p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 rounded-3 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom border-2 border-primary border-opacity-25">
                    <h5 class="mb-0 fw-bold text-primary">Projects Pending Approval</h5>
                    <a href="<?php echo BASE_URL; ?>/pages/projects.php?filter=submitted" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <i class="fas fa-list me-1"></i> View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 projects-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 ps-4">Project</th>
                                    <th class="py-3">Team</th>
                                    <th class="py-3">Submitted</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3 pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (isAdmin()) {
                                    $sql = "SELECT p.project_id, p.title, t.team_name, p.submission_date, p.status 
                                            FROM projects p
                                            JOIN teams t ON p.team_id = t.team_id
                                            WHERE p.status = 'submitted'
                                            ORDER BY p.submission_date DESC
                                            LIMIT 5";
                                    $result = mysqli_query($conn, $sql);
                                    
                                    if ($result && mysqli_num_rows($result) > 0) {
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo '<tr>';
                                            echo '<td class="ps-4"><a href="' . BASE_URL . '/pages/project-details.php?id=' . $row['project_id'] . '" class="project-link">' . htmlspecialchars($row['title']) . '</a></td>';
                                            echo '<td>' . htmlspecialchars($row['team_name']) . '</td>';
                                            echo '<td><span class="time-ago" data-timestamp="' . strtotime($row['submission_date']) . '">' . time_ago($row['submission_date']) . '</span></td>';
                                            echo '<td><span class="badge bg-warning text-white rounded-pill px-3 py-2">Pending</span></td>';
                                            echo '<td class="pe-4 text-end">';
                                            echo '<a href="' . BASE_URL . '/pages/project-details.php?id=' . $row['project_id'] . '" class="btn btn-sm btn-primary rounded-circle me-1" data-bs-toggle="tooltip" title="View Details"><i class="fas fa-eye"></i></a>';
                                            echo '<a href="' . BASE_URL . '/pages/review-project.php?id=' . $row['project_id'] . '" class="btn btn-sm btn-success rounded-circle" data-bs-toggle="tooltip" title="Review Project"><i class="fas fa-check"></i></a>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="text-center py-5">
                                            <div class="py-4">
                                                <i class="fas fa-clipboard-check text-muted mb-3" style="font-size: 3rem;"></i>
                                                <h5 class="fw-normal text-muted">No projects pending approval</h5>
                                            </div>
                                        </td></tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif (isJudge()): ?>
    <!-- Judge Dashboard -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Projects for Evaluation</h5>
                </div>
                <div class="card-body">
                    <?php if (count($allProjects) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Team</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                        <th>Evaluated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allProjects as $project): ?>
                                        <?php
                                        // Check if the judge has already evaluated this project
                                        $evaluated = false;
                                        $sql = "SELECT evaluation_id FROM evaluations WHERE project_id = ? AND judge_id = ?";
                                        if ($stmt = mysqli_prepare($conn, $sql)) {
                                            mysqli_stmt_bind_param($stmt, "ii", $project['project_id'], $user_id);
                                            if (mysqli_stmt_execute($stmt)) {
                                                mysqli_stmt_store_result($stmt);
                                                if (mysqli_stmt_num_rows($stmt) > 0) {
                                                    $evaluated = true;
                                                }
                                            }
                                            mysqli_stmt_close($stmt);
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $project['title']; ?></td>
                                            <td><?php echo $project['team_name']; ?></td>
                                            <td><span class="time-ago" data-timestamp="<?php echo strtotime($project['submission_date']); ?>"><?php echo time_ago($project['submission_date']); ?></span></td>
                                            <td>
                                                <?php if ($project['status'] == 'submitted'): ?>
                                                    <span class="badge bg-warning">Under Review</span>
                                                <?php elseif ($project['status'] == 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($evaluated): ?>
                                                    <span class="badge bg-success">Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                                <a href="<?php echo BASE_URL; ?>/pages/evaluate-project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm <?php echo $evaluated ? 'btn-secondary' : 'btn-success'; ?>">
                                                    <?php echo $evaluated ? 'Edit Evaluation' : 'Evaluate'; ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No projects available for evaluation yet.</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="<?php echo BASE_URL; ?>/pages/judge.php" class="btn btn-primary">View All Evaluations</a>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Participant Dashboard -->
    <div class="row mb-4">
        <!-- Main Dashboard Container -->
        <div class="col-md-12">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Your Hackathon Status</span>
                </div>
                <div class="dashboard-card-body p-3">
                    <!-- Team and Projects Cards Row -->
                    <div class="row dashboard-team-cards">
                        <!-- Team Information -->
                        <div class="col-md-6 mb-4 mb-md-0">
                            <div class="dashboard-card-header border bg-white rounded-top">
                                <span>Your Team</span>
                                <?php if ($hasTeam && $isTeamLeader): ?>
                                    <a href="<?php echo BASE_URL; ?>/pages/edit-team.php?id=<?php echo $teamId; ?>" class="btn btn-sm btn-outline-primary">Edit Team</a>
                                <?php endif; ?>
                            </div>
                            <div class="team-card border border-top-0 rounded-bottom">
                                <?php if ($hasTeam): ?>
                                    <?php 
                                    // Get team leader info
                                    $leaderName = "";
                                    $sql = "SELECT u.first_name, u.last_name FROM users u 
                                            JOIN team_members tm ON u.user_id = tm.user_id 
                                            WHERE tm.team_id = ? AND tm.is_leader = 1";
                                    
                                    if ($stmt = mysqli_prepare($conn, $sql)) {
                                        mysqli_stmt_bind_param($stmt, "i", $teamId);
                                        
                                        if (mysqli_stmt_execute($stmt)) {
                                            $result = mysqli_stmt_get_result($stmt);
                                            if ($row = mysqli_fetch_assoc($result)) {
                                                $leaderName = $row['first_name'] . ' ' . $row['last_name'];
                                            }
                                        }
                                        mysqli_stmt_close($stmt);
                                    }
                                    ?>
                                    <div class="team-header">
                                        <div class="team-name"><?php echo htmlspecialchars($teamName); ?></div>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($leaderName)): ?>
                                                <?php echo htmlspecialchars($leaderName); ?>
                                            <?php endif; ?>
                                            <?php if ($isTeamLeader): ?>
                                                <span class="team-role ms-2">Team Leader</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $teamId; ?>" class="btn btn-sm btn-primary">View Team Details</a>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h5>You're not part of any team yet</h5>
                                        <p class="empty-state-text">Create a new team or join an existing one</p>
                                        <div>
                                            <a href="<?php echo BASE_URL; ?>/pages/create-team.php" class="btn btn-primary me-2">Create Team</a>
                                            <a href="<?php echo BASE_URL; ?>/pages/teams.php" class="btn btn-outline-secondary">Browse Teams</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Projects Information -->
                        <div class="col-md-6">
                            <div class="dashboard-card-header border bg-white rounded-top">
                                <span>Your Projects</span>
                                <?php if ($hasTeam && $hasProjects): ?>
                                    <a href="<?php echo BASE_URL; ?>/pages/create-project.php" class="btn btn-sm btn-outline-primary">Add Project</a>
                                <?php endif; ?>
                            </div>
                            <div class="team-card border border-top-0 rounded-bottom">
                                <?php if ($hasTeam): ?>
                                    <?php if ($hasProjects): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Project</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($projects as $project): ?>
                                                        <tr>
                                                            <td><?php echo $project['title']; ?></td>
                                                            <td>
                                                                <?php
                                                                switch ($project['status']) {
                                                                    case 'draft':
                                                                        echo '<span class="badge bg-secondary">Draft</span>';
                                                                        break;
                                                                    case 'submitted':
                                                                        echo '<span class="badge bg-warning">Under Review</span>';
                                                                        break;
                                                                    case 'rejected':
                                                                        echo '<span class="badge bg-danger">Rejected</span>';
                                                                        break;
                                                                    case 'approved':
                                                                        echo '<span class="badge bg-success">Approved</span>';
                                                                        break;
                                                                    default:
                                                                        echo '<span class="badge bg-secondary">Unknown</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <button onclick="window.location.href='<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project['project_id']; ?>'" class="btn btn-sm btn-primary view-project-btn" data-project-id="<?php echo $project['project_id']; ?>">View</button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="project-empty-state">
                                            <div class="project-empty-state-icon">
                                                <i class="fas fa-code"></i>
                                            </div>
                                            <h5>No projects yet</h5>
                                            <p class="text-muted mb-3">Create a new project for your team</p>
                                            <button type="button" class="btn btn-primary create-project-btn" onclick="window.location.href='<?php echo BASE_URL; ?>/pages/create-project.php'">Create Project</button>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="project-empty-state">
                                        <div class="project-empty-state-icon">
                                            <i class="fas fa-code"></i>
                                        </div>
                                        <h5>No projects yet</h5>
                                        <p class="text-muted mb-3">You need to be part of a team first</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Timeline and Team Management Row -->
    <div class="row">
        <!-- Timeline -->
        <div class="col-md-8 mb-4">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Hackathon Timeline</span>
                </div>
                <div class="dashboard-card-body">
                    <div class="timeline">
                        <div class="timeline-item active">
                            <h5>Registration & Team Formation</h5>
                            <p class="text-muted mb-1">Create your account and form a team with other participants</p>
                            <small class="badge bg-primary">Current Phase</small>
                        </div>
                        <div class="timeline-item">
                            <h5>Admin Verification</h5>
                            <p class="text-muted mb-1">Teams are verified by admins</p>
                        </div>
                        <div class="timeline-item">
                            <h5>Project Submission</h5>
                            <p class="text-muted mb-1">Teams submit their projects for review</p>
                        </div>
                        <div class="timeline-item">
                            <h5>Judging & Evaluation</h5>
                            <p class="text-muted mb-1">Experts evaluate submitted projects</p>
                        </div>
                        <div class="timeline-item">
                            <h5>Winner Announcement</h5>
                            <p class="text-muted mb-1">Winners are announced and prizes are awarded</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Team Management -->
        <div class="col-md-4 mb-4">
            <div class="team-management">
                <div class="team-management-header">
                    <h5 class="mb-0">Team Management</h5>
                </div>
                <div class="team-management-body">
                    <?php
                    // Count pending team invitations
                    $invitation_count = 0;
                    $count_sql = "SELECT COUNT(*) as count FROM team_requests WHERE user_id = ? AND status = 'pending'";
                    if ($count_stmt = mysqli_prepare($conn, $count_sql)) {
                        mysqli_stmt_bind_param($count_stmt, "i", $user_id);
                        if (mysqli_stmt_execute($count_stmt)) {
                            $count_result = mysqli_stmt_get_result($count_stmt);
                            if ($count_row = mysqli_fetch_assoc($count_result)) {
                                $invitation_count = $count_row['count'];
                            }
                        }
                        mysqli_stmt_close($count_stmt);
                    }
                    ?>
                    <a href="<?php echo BASE_URL; ?>/pages/profile.php" class="action-btn">
                        <i class="fas fa-envelope"></i> View Team Invitations
                        <?php if ($invitation_count > 0): ?>
                        <span class="badge bg-danger"><?php echo $invitation_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/teams.php" class="action-btn">
                        <i class="fas fa-list"></i> View All Teams
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add JavaScript to handle the delete modal at the bottom before closing body tag -->
<script>
function confirmDelete(projectId, projectTitle) {
    document.getElementById('projectIdToDelete').value = projectId;
    document.getElementById('projectTitleToDelete').textContent = projectTitle;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteProjectModal'));
    deleteModal.show();
}
</script>

<!-- Add direct click handler to Create Project button -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fix for Create Project buttons
    var createProjectButtons = document.querySelectorAll('.create-project-btn');
    createProjectButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            window.location.href = '<?php echo BASE_URL; ?>/pages/create-project.php';
        });
    });
    
    // Fix for View Project buttons
    var viewProjectButtons = document.querySelectorAll('.view-project-btn');
    viewProjectButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            var projectId = this.getAttribute('data-project-id');
            if (projectId) {
                window.location.href = '<?php echo BASE_URL; ?>/pages/project-details.php?id=' + projectId;
            }
        });
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            customClass: 'custom-tooltip'
        });
    });
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?> 