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
    $_SESSION['message'] = "You don't have permission to evaluate projects";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Check if project ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Set message
    $_SESSION['message'] = "No project specified for evaluation";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to judge panel
    header("Location: " . BASE_URL . "/pages/judge.php");
    exit();
}

$project_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get project details
$project = [];
$sql = "SELECT p.*, t.team_name, t.team_id 
        FROM projects p 
        JOIN teams t ON p.team_id = t.team_id 
        WHERE p.project_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $project = mysqli_fetch_assoc($result);
        } else {
            // Project not found
            $_SESSION['message'] = "Project not found";
            $_SESSION['message_type'] = "danger";
            
            // Redirect to judge panel
            header("Location: " . BASE_URL . "/pages/judge.php");
            exit();
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Check if project is in submitted status
if ($project['status'] !== 'submitted' && $project['status'] !== 'approved') {
    // Set message
    $_SESSION['message'] = "This project is not available for evaluation";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to judge panel
    header("Location: " . BASE_URL . "/pages/judge.php");
    exit();
}

// Check if this judge has already evaluated this project
$existing_evaluation = null;
$sql = "SELECT * FROM evaluations WHERE project_id = ? AND judge_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $project_id, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $existing_evaluation = mysqli_fetch_assoc($result);
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Process form submission for evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    // Validate form inputs
    $innovation_score = isset($_POST['innovation_score']) ? (int)$_POST['innovation_score'] : 0;
    $technical_score = isset($_POST['technical_score']) ? (int)$_POST['technical_score'] : 0;
    $practicality_score = isset($_POST['practicality_score']) ? (int)$_POST['practicality_score'] : 0;
    $presentation_score = isset($_POST['presentation_score']) ? (int)$_POST['presentation_score'] : 0;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    
    // Calculate total score
    $total_score = $innovation_score + $technical_score + $practicality_score + $presentation_score;
    
    if ($existing_evaluation) {
        // Update existing evaluation
        $sql = "UPDATE evaluations 
                SET innovation_score = ?, technical_score = ?, practicality_score = ?, 
                    presentation_score = ?, total_score = ?, comments = ?, evaluated_at = NOW() 
                WHERE evaluation_id = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "dddddsi", $innovation_score, $technical_score, $practicality_score, 
                                 $presentation_score, $total_score, $comments, $existing_evaluation['evaluation_id']);
            
            if (mysqli_stmt_execute($stmt)) {
                // Update project status to approved if not already
                if ($project['status'] === 'submitted') {
                    $update_sql = "UPDATE projects SET status = 'approved' WHERE project_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $project_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
                
                // Set success message
                $_SESSION['message'] = "Evaluation updated successfully";
                $_SESSION['message_type'] = "success";
                
                // Redirect to judge panel
                header("Location: " . BASE_URL . "/pages/judge.php");
                exit();
            } else {
                $_SESSION['message'] = "Error updating evaluation: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
            
            mysqli_stmt_close($stmt);
        }
    } else {
        // Create new evaluation
        $sql = "INSERT INTO evaluations (project_id, judge_id, innovation_score, technical_score, 
                practicality_score, presentation_score, total_score, comments, evaluated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iiddddds", $project_id, $user_id, $innovation_score, $technical_score, 
                                 $practicality_score, $presentation_score, $total_score, $comments);
            
            if (mysqli_stmt_execute($stmt)) {
                // Update project status to approved if not already
                if ($project['status'] === 'submitted') {
                    $update_sql = "UPDATE projects SET status = 'approved' WHERE project_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $project_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
                
                // Set success message
                $_SESSION['message'] = "Project evaluated successfully";
                $_SESSION['message_type'] = "success";
                
                // Redirect to judge panel
                header("Location: " . BASE_URL . "/pages/judge.php");
                exit();
            } else {
                $_SESSION['message'] = "Error submitting evaluation: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

// Get team members
$team_members = [];
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.username, tm.is_leader 
        FROM team_members tm 
        JOIN users u ON tm.user_id = u.user_id 
        WHERE tm.team_id = ? 
        ORDER BY tm.is_leader DESC, u.first_name, u.last_name";

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
$project_files = [];
$sql = "SELECT * FROM project_files WHERE project_id = ? ORDER BY uploaded_at DESC";

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

// Include header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Evaluate Project</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/judge.php">Judge Panel</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Evaluate Project</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <?php include '../includes/messages.php'; ?>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Project Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Project Details</h5>
                </div>
                <div class="card-body">
                    <h4><?php echo htmlspecialchars($project['title']); ?></h4>
                    <p class="text-muted">
                        <strong>Team:</strong> <?php echo htmlspecialchars($project['team_name']); ?><br>
                        <strong>Submitted:</strong> <?php echo date('F j, Y, g:i a', strtotime($project['submission_date'])); ?>
                    </p>
                    
                    <div class="mb-3">
                        <h5>Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                    </div>
                    
                    <?php if (!empty($project['tech_stack'])): ?>
                    <div class="mb-3">
                        <h5>Technologies Used</h5>
                        <div>
                            <?php foreach(explode(',', $project['tech_stack']) as $tech): ?>
                                <span class="badge bg-light text-dark me-1"><?php echo trim($tech); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['demo_url'])): ?>
                    <div class="mb-3">
                        <h5>Demo URL</h5>
                        <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" target="_blank"><?php echo htmlspecialchars($project['demo_url']); ?></a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['code_repo'])): ?>
                    <div class="mb-3">
                        <h5>Code Repository</h5>
                        <a href="<?php echo htmlspecialchars($project['code_repo']); ?>" target="_blank"><?php echo htmlspecialchars($project['code_repo']); ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Project Files Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Project Files</h5>
                </div>
                <div class="card-body">
                    <?php if (count($project_files) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($project_files as $file): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                                    <td><?php echo htmlspecialchars($file['file_type']); ?></td>
                                    <td><?php echo formatFileSize($file['file_size']); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL . '/' . $file['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No files have been uploaded for this project.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Evaluation Form Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Evaluation Form</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="innovation_score" class="form-label">Innovation (1-10)</label>
                            <input type="number" class="form-control" id="innovation_score" name="innovation_score" min="1" max="10" value="<?php echo $existing_evaluation ? $existing_evaluation['innovation_score'] : ''; ?>" required>
                            <div class="form-text">Originality and creativity of the solution</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="technical_score" class="form-label">Technical Implementation (1-10)</label>
                            <input type="number" class="form-control" id="technical_score" name="technical_score" min="1" max="10" value="<?php echo $existing_evaluation ? $existing_evaluation['technical_score'] : ''; ?>" required>
                            <div class="form-text">Quality of code and technical execution</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="practicality_score" class="form-label">Practicality (1-10)</label>
                            <input type="number" class="form-control" id="practicality_score" name="practicality_score" min="1" max="10" value="<?php echo $existing_evaluation ? $existing_evaluation['practicality_score'] : ''; ?>" required>
                            <div class="form-text">Usefulness and real-world applicability</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="presentation_score" class="form-label">Presentation (1-10)</label>
                            <input type="number" class="form-control" id="presentation_score" name="presentation_score" min="1" max="10" value="<?php echo $existing_evaluation ? $existing_evaluation['presentation_score'] : ''; ?>" required>
                            <div class="form-text">Documentation and overall presentation</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments</label>
                            <textarea class="form-control" id="comments" name="comments" rows="5"><?php echo $existing_evaluation ? htmlspecialchars($existing_evaluation['comments']) : ''; ?></textarea>
                            <div class="form-text">Provide feedback to the team (optional)</div>
                        </div>
                        
                        <button type="submit" name="submit_evaluation" class="btn btn-primary">
                            <?php echo $existing_evaluation ? 'Update Evaluation' : 'Submit Evaluation'; ?>
                        </button>
                        <a href="<?php echo BASE_URL; ?>/pages/judge.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
            
            <!-- Team Members Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Team Members</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($team_members as $member): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            <?php if ($member['is_leader']): ?>
                            <span class="badge bg-primary">Team Leader</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Function to format file size
function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// Include footer
require_once '../includes/footer.php';
?> 