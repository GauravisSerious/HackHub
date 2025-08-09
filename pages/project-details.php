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
    $_SESSION['message'] = "You must log in to view project details";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if project ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Set message
    $_SESSION['message'] = "Invalid project selection";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$project_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get project details
$sql = "SELECT p.*, t.team_name, t.team_id 
        FROM projects p 
        JOIN teams t ON p.team_id = t.team_id 
        WHERE p.project_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $project = mysqli_fetch_assoc($result);
        } else {
            // Set message
            $_SESSION['message'] = "Project not found";
            $_SESSION['message_type'] = "warning";
            
            // Redirect to dashboard
            header("Location: " . BASE_URL . "/pages/dashboard.php");
            exit();
        }
    } else {
        // Set message
        $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
        $_SESSION['message_type'] = "danger";
        
        // Redirect to dashboard
        header("Location: " . BASE_URL . "/pages/dashboard.php");
        exit();
    }
    
    mysqli_stmt_close($stmt);
}

// Check if user is team member
$isTeamMember = false;
$isTeamLeader = false;
$is_team_leader = false;  // Add this variable for backward compatibility

$sql = "SELECT is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $project['team_id'], $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $isTeamMember = true;
            mysqli_stmt_bind_result($stmt, $leader_status);
            mysqli_stmt_fetch($stmt);
            $isTeamLeader = $leader_status == 1;
            $is_team_leader = $isTeamLeader;  // Set both variables to the same value
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Check access permission
if (!$isTeamMember && !isAdmin() && !isJudge()) {
    // For non-team projects, only show if status is approved
    if ($project['status'] != 'approved') {
        // Set message
        $_SESSION['message'] = "You don't have permission to view this project";
        $_SESSION['message_type'] = "warning";
        
        // Redirect to dashboard
        header("Location: " . BASE_URL . "/pages/dashboard.php");
        exit();
    }
}

// Get team members
$team_members = array();
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.username, tm.is_leader 
        FROM users u 
        JOIN team_members tm ON u.user_id = tm.user_id 
        WHERE tm.team_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project['team_id']);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $team_members[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Get project files
$project_files = array();
$sql = "SELECT pf.*, u.first_name, u.last_name 
        FROM project_files pf 
        JOIN users u ON pf.uploaded_by = u.user_id 
        WHERE pf.project_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $project_files[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Get evaluations (for admins, judges, and team members of approved projects)
$evaluations = array();
if (isAdmin() || isJudge() || ($isTeamMember && $project['status'] == 'approved')) {
    $sql = "SELECT e.*, u.first_name, u.last_name 
            FROM evaluations e 
            JOIN users u ON e.judge_id = u.user_id 
            WHERE e.project_id = ?";
            
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $evaluations[] = $row;
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Check if judge has already evaluated this project
$hasEvaluated = false;
if (isJudge()) {
    $sql = "SELECT evaluation_id FROM evaluations WHERE project_id = ? AND judge_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $project_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $hasEvaluated = true;
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Get average scores
$average_scores = array(
    'innovation' => 0,
    'implementation' => 0,
    'impact' => 0,
    'presentation' => 0,
    'total' => 0
);

if (count($evaluations) > 0) {
    $innovation_sum = $implementation_sum = $impact_sum = $presentation_sum = $total_sum = 0;
    
    foreach ($evaluations as $eval) {
        $innovation_sum += $eval['innovation_score'];
        $implementation_sum += $eval['implementation_score'];
        $impact_sum += $eval['impact_score'];
        $presentation_sum += $eval['presentation_score'];
        $total_sum += $eval['total_score'];
    }
    
    $count = count($evaluations);
    $average_scores['innovation'] = round($innovation_sum / $count, 1);
    $average_scores['implementation'] = round($implementation_sum / $count, 1);
    $average_scores['impact'] = round($impact_sum / $count, 1);
    $average_scores['presentation'] = round($presentation_sum / $count, 1);
    $average_scores['total'] = round($total_sum / $count, 1);
}

// Include header
require_once '../includes/header.php';
?>

<div class="container px-2">
    <div class="row mb-2">
        <div class="col-md-12">
            <h1 class="fs-3 mb-1"><?php echo $project['title']; ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb py-0 mb-1">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $project['team_id']; ?>"><?php echo $project['team_name']; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $project['title']; ?></li>
                </ol>
            </nav>
            <?php if (isAdmin()): ?>
            <div class="alert alert-info py-1 mb-2">
                <strong>Debug Info:</strong> Project ID: <?php echo $project_id; ?>, Status: <?php echo $project['status']; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row g-2">
        <div class="col-md-8">
            <!-- Project Details -->
            <div class="card mb-2 project-details-header">
                <div class="card-body py-2">
                    <div class="project-status">
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
                        }
                        ?>
                    </div>
                    
                    <?php require_once '../includes/project-actions.php'; ?>
                    
                    <h2 class="mb-2"><?php echo $project['title']; ?></h2>
                    <p class="mb-2"><?php echo nl2br($project['description']); ?></p>
                    
                    <?php if (!empty($project['tech_stack'])): ?>
                    <div class="mb-2">
                        <h5>Technologies Used:</h5>
                        <div>
                            <?php
                            $technologies = explode(',', $project['tech_stack']);
                            foreach ($technologies as $tech) {
                                echo '<span class="tech-tag">' . trim($tech) . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <?php if (!empty($project['github_link'])): ?>
                        <div class="col-md-6 mb-2">
                            <strong><i class="fab fa-github me-2"></i> GitHub Repository:</strong>
                            <a href="<?php echo $project['github_link']; ?>" target="_blank" class="ms-2"><?php echo $project['github_link']; ?></a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($project['demo_link'])): ?>
                        <div class="col-md-6 mb-2">
                            <strong><i class="fas fa-external-link-alt me-2"></i> Demo URL:</strong>
                            <a href="<?php echo $project['demo_link']; ?>" target="_blank" class="ms-2"><?php echo $project['demo_link']; ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($project['submission_date']): ?>
                    <div class="mt-2">
                        <small class="d-block text-muted">Submitted: <span class="time-ago" data-timestamp="<?php echo strtotime($project['submission_date']); ?>"><?php echo time_ago($project['submission_date']); ?></span></small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Project Status Section -->
            <div class="card mt-2">
                <div class="card-header bg-white py-2">
                    <h5 class="mb-0">Project Status</h5>
                </div>
                <div class="card-body py-2">
                    <p class="mb-2"><strong>Current Status:</strong>
                        <span class="badge bg-<?php 
                            switch ($project['status']) {
                                case 'draft': echo 'secondary'; break;
                                case 'submitted': echo 'warning'; break;
                                case 'rejected': echo 'danger'; break;
                                case 'approved': echo 'success'; break;
                                default: echo 'secondary';
                            }
                        ?>">
                            <?php echo ucfirst($project['status']); ?>
                        </span>
                    </p>
                    
                    <?php if (isAdmin() && $project['status'] == 'submitted'): ?>
                    <div class="mt-2 mb-2">
                        <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                            Approve
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            Reject
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add PDF functionality here -->
                    <?php 
                    // Query to get PDF files
                    $pdf_file = null;
                    $pdf_sql = "SELECT * FROM project_files WHERE project_id = ? AND file_type = 'pdf' ORDER BY uploaded_at DESC LIMIT 1";
                    if ($pdf_stmt = mysqli_prepare($conn, $pdf_sql)) {
                        mysqli_stmt_bind_param($pdf_stmt, "i", $project_id);
                        if (mysqli_stmt_execute($pdf_stmt)) {
                            $pdf_result = mysqli_stmt_get_result($pdf_stmt);
                            if (mysqli_num_rows($pdf_result) > 0) {
                                $pdf_file = mysqli_fetch_assoc($pdf_result);
                            }
                        }
                        mysqli_stmt_close($pdf_stmt);
                    }
                    
                    if ($pdf_file): 
                    ?>
                    <div class="mt-3 border-top pt-2">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-pdf text-danger me-2"></i>
                            <strong class="me-2">Project PDF:</strong>
                            <a href="<?php echo BASE_URL; ?>/<?php echo $pdf_file['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-download me-1"></i> View PDF
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Evaluations (for admins, judges, and team members of approved projects) -->
            <?php if ((isAdmin() || isJudge() || ($isTeamMember && $project['status'] == 'approved')) && count($evaluations) > 0): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Project Evaluations</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr class="bg-light">
                                    <th>Criteria</th>
                                    <th class="text-center">Average Score</th>
                                    <th class="text-center">Max Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Innovation & Creativity</td>
                                    <td class="text-center"><?php echo $average_scores['innovation']; ?>/10</td>
                                    <td class="text-center">10</td>
                                </tr>
                                <tr>
                                    <td>Technical Implementation</td>
                                    <td class="text-center"><?php echo $average_scores['implementation']; ?>/10</td>
                                    <td class="text-center">10</td>
                                </tr>
                                <tr>
                                    <td>Impact & Usefulness</td>
                                    <td class="text-center"><?php echo $average_scores['impact']; ?>/10</td>
                                    <td class="text-center">10</td>
                                </tr>
                                <tr>
                                    <td>Presentation & UI/UX</td>
                                    <td class="text-center"><?php echo $average_scores['presentation']; ?>/10</td>
                                    <td class="text-center">10</td>
                                </tr>
                                <tr class="table-primary">
                                    <td><strong>Total Score</strong></td>
                                    <td class="text-center"><strong><?php echo $average_scores['total']; ?>/40</strong></td>
                                    <td class="text-center">40</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (isAdmin() || isJudge()): ?>
                    <div class="mt-4">
                        <h6>Individual Evaluations</h6>
                        <div class="accordion" id="evaluationsAccordion">
                            <?php foreach ($evaluations as $index => $eval): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                        Judge: <?php echo $eval['first_name'] . ' ' . $eval['last_name']; ?> | Total Score: <?php echo $eval['total_score']; ?>/40
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#evaluationsAccordion">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Innovation & Creativity:</strong> <?php echo $eval['innovation_score']; ?>/10</p>
                                                <p><strong>Technical Implementation:</strong> <?php echo $eval['implementation_score']; ?>/10</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Impact & Usefulness:</strong> <?php echo $eval['impact_score']; ?>/10</p>
                                                <p><strong>Presentation & UI/UX:</strong> <?php echo $eval['presentation_score']; ?>/10</p>
                                            </div>
                                        </div>
                                        <?php if (!empty($eval['comments'])): ?>
                                        <div class="mt-2">
                                            <p><strong>Comments:</strong></p>
                                            <p><?php echo nl2br($eval['comments']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <!-- Project Status Section (moved to top) -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Admin's Feedback</h5>
                </div>
                <div class="card-body">
                    <?php if ($project['status'] == 'rejected' && !empty($project['admin_feedback'])): ?>
                    <!-- Show only feedback for rejected projects -->
                    <div class="card border-danger mb-2">
                        <div class="card-header bg-danger text-white p-1 small fw-bold">Feedback (Rejection Reason)</div>
                        <div class="card-body p-2">
                            <?php echo nl2br(htmlspecialchars($project['admin_feedback'])); ?>
                        </div>
                    </div>
                    
                    <?php
                    // Show delete button if user is team leader
                    if ($is_team_leader): ?>
                    <div class="mt-2 border-top pt-2">
                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteProject('<?php echo $project_id; ?>', '<?php echo htmlspecialchars($project['title']); ?>')">
                            <i class="fas fa-trash me-1"></i> Delete Project
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <p class="mb-2"><strong>Current Status:</strong>
                        <span class="badge bg-<?php 
                            switch ($project['status']) {
                                case 'draft': echo 'secondary'; break;
                                case 'submitted': echo 'warning'; break;
                                case 'rejected': echo 'danger'; break;
                                case 'approved': echo 'success'; break;
                                default: echo 'secondary';
                            }
                        ?>">
                            <?php echo ucfirst($project['status']); ?>
                        </span>
                    </p>
                    
                    <?php if (isAdmin() && $project['status'] == 'submitted'): ?>
                    <div class="mt-3 mb-3">
                        <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                            Approve
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            Reject
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Status Timeline -->
                    <div class="mt-2 status-timeline">
                        <div class="status-item d-flex align-items-center mb-1">
                            <div class="status-icon bg-success text-white">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="ms-2">
                                <div>Created</div>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($project['created_at'])); ?></small>
                            </div>
                        </div>
                        
                        <div class="status-line"></div>
                        <div class="status-item d-flex align-items-center mb-1">
                            <div class="status-icon bg-<?php echo ($project['status'] == 'draft') ? 'secondary' : 'success'; ?> text-white">
                                <?php if ($project['status'] == 'draft'): ?>
                                <i class="fas fa-clock"></i>
                                <?php else: ?>
                                <i class="fas fa-check"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ms-2">
                                <div>Submission</div>
                                <?php if ($project['status'] != 'draft'): ?>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($project['submission_date'])); ?></small>
                                <?php else: ?>
                                <small class="text-muted">Pending</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="status-line"></div>
                        <div class="status-item d-flex align-items-center mb-1">
                            <div class="status-icon bg-<?php echo ($project['status'] == 'approved') ? 'success' : (($project['status'] == 'rejected') ? 'danger' : 'secondary'); ?> text-white">
                                <?php if ($project['status'] == 'approved'): ?>
                                <i class="fas fa-check"></i>
                                <?php elseif ($project['status'] == 'rejected'): ?>
                                <i class="fas fa-times"></i>
                                <?php else: ?>
                                <i class="fas fa-clock"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ms-2">
                                <div>Review</div>
                                <?php if ($project['status'] == 'draft'): ?>
                                <small class="text-muted">Waiting for submission</small>
                                <?php elseif ($project['status'] == 'submitted'): ?>
                                <small class="text-muted">Under review</small>
                                <?php elseif ($project['status'] == 'rejected'): ?>
                                <small class="text-muted">Needs revision</small>
                                <?php elseif ($project['status'] == 'approved'): ?>
                                <small class="text-muted">Approved</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="status-line"></div>
                        <div class="status-item d-flex align-items-center">
                            <div class="status-icon bg-<?php echo ($project['status'] == 'approved' && !empty($evaluations)) ? 'warning' : 'secondary'; ?> text-white">
                                <?php if ($project['status'] == 'approved' && !empty($evaluations)): ?>
                                <i class="fas fa-star"></i>
                                <?php else: ?>
                                <i class="fas fa-clock"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ms-2">
                                <div>Evaluation</div>
                                <?php if ($project['status'] != 'approved'): ?>
                                <small class="text-muted">Waiting for approval</small>
                                <?php elseif (empty($evaluations)): ?>
                                <small class="text-muted">Pending evaluation</small>
                                <?php else: ?>
                                <small class="text-muted">Evaluation complete</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // Check if user is team leader of this project
                    $is_team_leader = false;
                    if (isLoggedIn()) {
                        $user_id = $_SESSION['user_id'];
                        
                        $leader_sql = "SELECT tm.* 
                                      FROM team_members tm
                                      JOIN projects p ON tm.team_id = p.team_id
                                      WHERE p.project_id = ? AND tm.user_id = ? AND tm.is_leader = 1";
                        
                        if ($leader_stmt = mysqli_prepare($conn, $leader_sql)) {
                            mysqli_stmt_bind_param($leader_stmt, "ii", $project_id, $user_id);
                            
                            if (mysqli_stmt_execute($leader_stmt)) {
                                mysqli_stmt_store_result($leader_stmt);
                                
                                if (mysqli_stmt_num_rows($leader_stmt) > 0) {
                                    $is_team_leader = true;
                                }
                            }
                            
                            mysqli_stmt_close($leader_stmt);
                        }
                    }
                    
                    // Show delete button if user is team leader
                    if ($is_team_leader): ?>
                    <div class="mt-2 border-top pt-2">
                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteProject('<?php echo $project_id; ?>', '<?php echo htmlspecialchars($project['title']); ?>')">
                            <i class="fas fa-trash me-1"></i> Delete Project
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions Card (moved to bottom) -->
            <div class="card mb-4 mt-2">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <?php if ($isTeamMember): ?>
                        <?php if ($project['status'] == 'draft'): ?>
                        <div class="d-grid gap-2">
                            <a href="<?php echo BASE_URL; ?>/pages/edit-project.php?id=<?php echo $project_id; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i> Edit Project
                            </a>
                            <a href="<?php echo BASE_URL; ?>/pages/submit-project.php?id=<?php echo $project_id; ?>" class="btn btn-success">
                                <i class="fas fa-paper-plane me-2"></i> Submit for Review
                            </a>
                        </div>
                        <?php elseif ($project['status'] == 'rejected'): ?>
                        <div class="d-grid">
                            <a href="<?php echo BASE_URL; ?>/pages/edit-project.php?id=<?php echo $project_id; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i> Edit and Resubmit
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <?php if ($project['status'] == 'submitted'): ?>
                            <i class="fas fa-info-circle me-2"></i> Your project is currently under review. You'll be notified when it's evaluated.
                            <?php elseif ($project['status'] == 'approved'): ?>
                            <i class="fas fa-check-circle me-2"></i> Your project has been approved and is being evaluated by judges.
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php elseif (isAdmin()): ?>
                        <?php if ($project['status'] == 'submitted' || $project['status'] == 'draft'): ?>
                        <div class="d-grid gap-2">
                            <form action="<?php echo BASE_URL; ?>/pages/project_actions.php" method="POST">
                                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-check-circle me-2"></i> Approve Project
                                </button>
                            </form>
                            <a href="#" class="btn btn-danger reject-project-btn" data-project-id="<?php echo $project_id; ?>">
                                <i class="fas fa-times-circle me-2"></i> Reject Project
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="d-grid">
                            <a href="<?php echo BASE_URL; ?>/pages/admin.php" class="btn btn-primary">
                                <i class="fas fa-cog me-2"></i> Admin Dashboard
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php elseif (isJudge() && $project['status'] == 'approved'): ?>
                        <div class="d-grid">
                            <?php if (!$hasEvaluated): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/evaluate-project.php?id=<?php echo $project_id; ?>" class="btn btn-primary">
                                <i class="fas fa-star me-2"></i> Evaluate Project
                            </a>
                            <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/pages/evaluate-project.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-edit me-2"></i> Edit Evaluation
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add the single Rejection Modal at the bottom of the page, before the footer include -->
<!-- Rejection Modal - Single instance for all projects -->
<div class="modal fade" id="rejectProjectModal" tabindex="-1" aria-labelledby="rejectProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectProjectModalLabel">Reject Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo BASE_URL; ?>/pages/project_actions.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="project_id" id="rejectProjectId">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Project Modal -->
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

<?php
// Include footer
require_once '../includes/footer.php';
?>

<script>
// Single unified handler for all project functionality
document.addEventListener('DOMContentLoaded', function() {
    // Clear any existing event listeners first to prevent duplicates
    const rejectButtons = document.querySelectorAll('.reject-project-btn');
    rejectButtons.forEach(function(button) {
        // Clone the button to remove all event listeners
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
    });
    
    // Re-attach event listeners to all reject buttons
    document.querySelectorAll('.reject-project-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Stop event propagation
            
            // Close any existing open modals first
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            });
            
            // Set the project ID and show the modal
            const projectId = this.getAttribute('data-project-id');
            document.getElementById('rejectProjectId').value = projectId;
            
            // Use a timeout to ensure the modal opens after any others close
            setTimeout(function() {
                const rejectModal = new bootstrap.Modal(document.getElementById('rejectProjectModal'));
                rejectModal.show();
            }, 100);
        });
    });
    
    // Handle delete project functionality
    window.confirmDeleteProject = function(projectId, projectTitle) {
        // Close any open modals first
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
        
        // Set values and show delete modal
        document.getElementById('projectIdToDelete').value = projectId;
        document.getElementById('projectTitleToDelete').textContent = projectTitle;
        
        setTimeout(function() {
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteProjectModal'));
            deleteModal.show();
        }, 100);
    };
});
</script>

<style>
/* Status timeline styling */
.status-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.status-line {
    width: 2px;
    height: 20px;
    background-color: #dee2e6;
    margin-left: 13px;
    margin-bottom: 5px;
}

/* Card styling */
.card {
    border-radius: 0.25rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #dee2e6;
    margin-bottom: 0.5rem;
}

.card-header {
    border-bottom: 1px solid #dee2e6;
    padding: 0.5rem 1rem;
}

.card-footer {
    border-top: 1px solid #dee2e6;
    padding: 0.5rem 1rem;
}

/* Table styling */
.table th {
    font-weight: 500;
    color: #6c757d;
    border-top: none;
    padding: 0.5rem;
}

.table td {
    padding: 0.5rem;
}

/* Badge styling */
.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

/* Main container styles */
.container {
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}

.row {
    margin-bottom: 0.5rem;
}

.mb-4 {
    margin-bottom: 0.5rem !important;
}

.py-3 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

.card-body {
    padding: 0.5rem;
}

.table > :not(caption) > * > * {
    padding: 0.25rem 0.5rem;
}

.list-group-item {
    padding: 0.25rem 0.5rem;
}

/* Project details specific */
.project-details-header .card-body {
    padding: 0.5rem;
}

.tech-tag {
    margin-bottom: 0.25rem;
}

.card.border-danger {
    margin-bottom: 0.5rem;
    max-height: fit-content;
}

.card.border-danger .card-body {
    padding: 0.5rem;
    min-height: auto;
    height: auto;
}

.card.border-danger .card-header {
    padding: 0.35rem 0.75rem;
}

.card.border-danger p {
    margin-bottom: 0;
}

.feedback-card {
    max-height: fit-content;
    height: auto !important;
}
.feedback-card .card-body {
    height: auto !important;
    min-height: 0 !important;
    padding: 0.35rem 0.75rem;
}
</style> 