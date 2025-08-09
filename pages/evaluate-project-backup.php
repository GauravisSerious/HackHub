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
    $_SESSION['message'] = "Invalid project selection";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to judge panel
    header("Location: " . BASE_URL . "/pages/judge.php");
    exit();
}

$project_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get project details
$sql = "SELECT p.*, t.team_name
        FROM projects p 
        JOIN teams t ON p.team_id = t.team_id 
        WHERE p.project_id = ? AND p.status = 'approved'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $project = mysqli_fetch_assoc($result);
        } else {
            // Set message
            $_SESSION['message'] = "Project not found or not available for evaluation";
            $_SESSION['message_type'] = "warning";
            
            // Redirect to judge panel
            header("Location: " . BASE_URL . "/pages/judge.php");
            exit();
        }
    } else {
        // Set message
        $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
        $_SESSION['message_type'] = "danger";
        
        // Redirect to judge panel
        header("Location: " . BASE_URL . "/pages/judge.php");
        exit();
    }
    
    mysqli_stmt_close($stmt);
}

// Check if the judge has already evaluated this project
$has_evaluated = false;
$existing_evaluation = array();

$sql = "SELECT * FROM evaluations WHERE project_id = ? AND judge_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $project_id, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $has_evaluated = true;
            $existing_evaluation = mysqli_fetch_assoc($result);
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    $innovation_score = isset($_POST['innovation_score']) ? intval($_POST['innovation_score']) : 0;
    $implementation_score = isset($_POST['implementation_score']) ? intval($_POST['implementation_score']) : 0;
    $impact_score = isset($_POST['impact_score']) ? intval($_POST['impact_score']) : 0;
    $presentation_score = isset($_POST['presentation_score']) ? intval($_POST['presentation_score']) : 0;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    
    // Basic validation
    $errors = array();
    
    if ($innovation_score < 1 || $innovation_score > 10) {
        $errors[] = "Innovation score must be between 1 and 10";
    }
    
    if ($implementation_score < 1 || $implementation_score > 10) {
        $errors[] = "Implementation score must be between 1 and 10";
    }
    
    if ($impact_score < 1 || $impact_score > 10) {
        $errors[] = "Impact score must be between 1 and 10";
    }
    
    if ($presentation_score < 1 || $presentation_score > 10) {
        $errors[] = "Presentation score must be between 1 and 10";
    }
    
    // If no errors, proceed with database operations
    if (empty($errors)) {
        // Calculate total score
        $total_score = $innovation_score + $implementation_score + $impact_score + $presentation_score;
        
        if ($has_evaluated) {
            // Update existing evaluation
            $sql = "UPDATE evaluations 
                    SET innovation_score = ?, implementation_score = ?, impact_score = ?, 
                        presentation_score = ?, total_score = ?, comments = ?, evaluated_at = NOW() 
                    WHERE project_id = ? AND judge_id = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "iiiiisii", $innovation_score, $implementation_score, 
                                       $impact_score, $presentation_score, $total_score, $comments, 
                                       $project_id, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Set success message
                    $_SESSION['message'] = "Evaluation updated successfully!";
                    $_SESSION['message_type'] = "success";
                    
                    // Redirect to judge panel
                    header("Location: " . BASE_URL . "/pages/judge.php");
                    exit();
                } else {
                    $errors[] = "Error updating evaluation: " . mysqli_error($conn);
                }
                
                mysqli_stmt_close($stmt);
            }
        } else {
            // Insert new evaluation
            $sql = "INSERT INTO evaluations 
                    (project_id, judge_id, innovation_score, implementation_score, impact_score, 
                     presentation_score, total_score, comments, evaluated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "iiiiiiis", $project_id, $user_id, $innovation_score, 
                                       $implementation_score, $impact_score, $presentation_score, 
                                       $total_score, $comments);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Set success message
                    $_SESSION['message'] = "Project evaluated successfully!";
                    $_SESSION['message_type'] = "success";
                    
                    // Redirect to judge panel
                    header("Location: " . BASE_URL . "/pages/judge.php");
                    exit();
                } else {
                    $errors[] = "Error submitting evaluation: " . mysqli_error($conn);
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
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
    
    <div class="row">
        <div class="col-md-8">
            <!-- Project Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Project Details</h5>
                </div>
                <div class="card-body">
                    <h4><?php echo htmlspecialchars($project['title']); ?></h4>
                    <p class="text-muted">Team: <?php echo htmlspecialchars($project['team_name']); ?></p>
                    
                    <div class="mb-3">
                        <h6>Description</h6>
                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Technologies Used</h6>
                        <p><?php echo htmlspecialchars($project['tech_stack']); ?></p>
                    </div>
                    
                    <?php if (!empty($project['github_link'])): ?>
                    <div class="mb-3">
                        <h6>GitHub Repository</h6>
                        <a href="<?php echo htmlspecialchars($project['github_link']); ?>" target="_blank" class="d-flex align-items-center">
                            <i class="fab fa-github me-2"></i> <?php echo htmlspecialchars($project['github_link']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['demo_link'])): ?>
                    <div class="mb-3">
                        <h6>Demo Link</h6>
                        <a href="<?php echo htmlspecialchars($project['demo_link']); ?>" target="_blank" class="d-flex align-items-center">
                            <i class="fas fa-external-link-alt me-2"></i> <?php echo htmlspecialchars($project['demo_link']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-search me-2"></i> View Full Project
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Evaluation Form Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $has_evaluated ? 'Update Evaluation' : 'Submit Evaluation'; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $project_id; ?>" method="post">
                        <!-- Innovation Score -->
                        <div class="mb-3">
                            <label for="innovation_score" class="form-label">Innovation & Creativity (1-10)</label>
                            <input type="number" name="innovation_score" id="innovation_score" class="form-control" min="1" max="10" 
                                   value="<?php echo $has_evaluated ? $existing_evaluation['innovation_score'] : ''; ?>" required>
                            <div class="form-text">Assess the uniqueness and creativity of the solution.</div>
                        </div>
                        
                        <!-- Technical Implementation Score -->
                        <div class="mb-3">
                            <label for="implementation_score" class="form-label">Technical Implementation (1-10)</label>
                            <input type="number" name="implementation_score" id="implementation_score" class="form-control" min="1" max="10" 
                                   value="<?php echo $has_evaluated ? $existing_evaluation['implementation_score'] : ''; ?>" required>
                            <div class="form-text">Evaluate code quality, technical complexity, and implementation.</div>
                        </div>
                        
                        <!-- Impact & Usefulness Score -->
                        <div class="mb-3">
                            <label for="impact_score" class="form-label">Impact & Usefulness (1-10)</label>
                            <input type="number" name="impact_score" id="impact_score" class="form-control" min="1" max="10" 
                                   value="<?php echo $has_evaluated ? $existing_evaluation['impact_score'] : ''; ?>" required>
                            <div class="form-text">Consider how well the project solves a real problem.</div>
                        </div>
                        
                        <!-- Presentation & UI/UX Score -->
                        <div class="mb-3">
                            <label for="presentation_score" class="form-label">Presentation & UI/UX (1-10)</label>
                            <input type="number" name="presentation_score" id="presentation_score" class="form-control" min="1" max="10" 
                                   value="<?php echo $has_evaluated ? $existing_evaluation['presentation_score'] : ''; ?>" required>
                            <div class="form-text">Rate the design, usability, and overall presentation.</div>
                        </div>
                        
                        <!-- Comments -->
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments (Optional)</label>
                            <textarea name="comments" id="comments" class="form-control" rows="4"><?php echo $has_evaluated ? $existing_evaluation['comments'] : ''; ?></textarea>
                            <div class="form-text">Provide feedback, suggestions, or comments to the team.</div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $has_evaluated ? 'Update Evaluation' : 'Submit Evaluation'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?> 