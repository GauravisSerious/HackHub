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
    $_SESSION['message'] = "You must log in to create a project";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Check if user has a team
$user_id = $_SESSION['user_id'];
$team_id = 0;
$isTeamLeader = false;

$sql = "SELECT tm.team_id, tm.is_leader, t.team_name 
        FROM team_members tm 
        JOIN teams t ON tm.team_id = t.team_id 
        WHERE tm.user_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $team_id = $row['team_id'];
            $isTeamLeader = $row['is_leader'] == 1;
            $team_name = $row['team_name'];
        } else {
            // Set message
            $_SESSION['message'] = "You need to be part of a team to create a project";
            $_SESSION['message_type'] = "warning";
            
            // Redirect to teams page
            header("Location: " . BASE_URL . "/pages/teams.php");
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

// Define variables and initialize with empty values
$title = $description = $tech_stack = $github_link = $demo_link = "";
$title_err = $description_err = $tech_stack_err = $github_link_err = $demo_link_err = $pdf_err = "";
$pdf_filename = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter a project title.";
    } else {
        $title = trim($_POST["title"]);
    }
    
    // Validate description
    if (empty(trim($_POST["description"]))) {
        $description_err = "Please enter a project description.";
    } else {
        $description = trim($_POST["description"]);
    }
    
    // Validate tech stack
    if (empty(trim($_POST["tech_stack"]))) {
        $tech_stack_err = "Please enter the technologies used.";
    } else {
        $tech_stack = trim($_POST["tech_stack"]);
    }
    
    // Validate GitHub link (optional)
    if (!empty(trim($_POST["github_link"]))) {
        $github_link = trim($_POST["github_link"]);
        // Basic GitHub URL validation
        if (!preg_match('/^https?:\/\/(www\.)?github\.com\/[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+\/?$/', $github_link)) {
            $github_link_err = "Please enter a valid GitHub repository URL.";
        }
    }
    
    // Validate demo link (optional)
    if (!empty(trim($_POST["demo_link"]))) {
        $demo_link = trim($_POST["demo_link"]);
        // Basic URL validation
        if (!filter_var($demo_link, FILTER_VALIDATE_URL)) {
            $demo_link_err = "Please enter a valid URL.";
        }
    }
    
    // Process PDF file upload (optional)
    if (isset($_FILES['project_pdf']) && $_FILES['project_pdf']['size'] > 0) {
        $allowed_types = ['application/pdf'];
        $max_size = 25 * 1024 * 1024; // 25MB
        
        $file_tmp = $_FILES['project_pdf']['tmp_name'];
        $file_size = $_FILES['project_pdf']['size'];
        $file_type = $_FILES['project_pdf']['type'];
        
        // Check file size
        if ($file_size > $max_size) {
            $pdf_err = "PDF file is too large. Maximum size is 25MB.";
        }
        // Check file type
        else if (!in_array($file_type, $allowed_types)) {
            $pdf_err = "Only PDF files are allowed.";
        }
        else {
            // Create uploads directory if it doesn't exist
            $upload_dir = "../uploads/project_pdfs/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $pdf_filename = uniqid() . '_' . $_FILES['project_pdf']['name'];
            $upload_path = $upload_dir . $pdf_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $pdf_err = "Error uploading PDF file. Please try again.";
                $pdf_filename = "";
            }
        }
    }
    
    // Check if draft or submit
    $is_draft = isset($_POST["save_draft"]) && $_POST["save_draft"] == 1;
    $status = $is_draft ? 'draft' : 'submitted';
    
    // Check input errors before inserting in database
    if (empty($title_err) && empty($description_err) && empty($tech_stack_err) && empty($github_link_err) && empty($demo_link_err) && empty($pdf_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO projects (team_id, title, description, tech_stack, github_link, demo_link, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "issssss", $param_team_id, $param_title, $param_description, $param_tech_stack, $param_github_link, $param_demo_link, $param_status);
            
            // Set parameters
            $param_team_id = $team_id;
            $param_title = $title;
            $param_description = $description;
            $param_tech_stack = $tech_stack;
            $param_github_link = $github_link;
            $param_demo_link = $demo_link;
            $param_status = $status;
            
            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                $project_id = mysqli_insert_id($conn);
                
                // Handle PDF upload separately (if a file was uploaded)
                if (!empty($pdf_filename)) {
                    // Create a record in the project_files table
                    $file_sql = "INSERT INTO project_files (project_id, file_name, file_type, file_path, uploaded_by) 
                                 VALUES (?, ?, 'pdf', ?, ?)";
                    
                    if ($file_stmt = mysqli_prepare($conn, $file_sql)) {
                        $file_path = "uploads/project_pdfs/" . $pdf_filename;
                        mysqli_stmt_bind_param($file_stmt, "issi", $project_id, $pdf_filename, $file_path, $user_id);
                        
                        if (!mysqli_stmt_execute($file_stmt)) {
                            // Log the error, but don't stop the process
                            error_log("Error saving PDF file record: " . mysqli_error($conn));
                        }
                        
                        mysqli_stmt_close($file_stmt);
                    }
                }
                
                // Set success message
                if ($is_draft) {
                    $_SESSION['message'] = "Project saved as draft successfully!";
                } else {
                    $_SESSION['message'] = "Project submitted successfully!";
                }
                $_SESSION['message_type'] = "success";
                
                // Redirect to project details page
                header("Location: " . BASE_URL . "/pages/project-details.php?id=" . $project_id);
                exit();
            } else {
                // Set error message
                $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
                $_SESSION['message_type'] = "danger";
            }
            
            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
}

// Include header
require_once '../includes/header.php';
?>

<style>
    /* Modern container styling */
    .form-container {
        background-color: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        overflow: hidden;
        max-width: 900px;
        margin: 0 auto;
        font-size: 0.9rem;
    }
    
    .form-header {
        background: linear-gradient(135deg, #4a6cf7, #2b3ddb);
        color: white;
        padding: 0.75rem 1.2rem;
        position: relative;
    }
    
    .form-header::after {
        content: "";
        position: absolute;
        bottom: -5px;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0));
    }
    
    .form-icon {
        font-size: 1.3rem;
        margin-right: 0.6rem;
        color: rgba(255,255,255,0.9);
    }
    
    .form-section {
        background-color: white;
        border-radius: 6px;
        padding: 0.75rem 1rem;
        margin-bottom: 0.9rem;
        box-shadow: 0 0.1rem 0.2rem rgba(0, 0, 0, 0.05);
        height: 100%;
    }
    
    .form-section-title {
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
        color: #4a6cf7;
    }
    
    .form-section-icon {
        font-size: 1rem;
        margin-right: 0.4rem;
    }
    
    .form-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
        padding: 0.7rem 1rem;
    }
    
    /* Form control styling */
    .form-control:focus {
        box-shadow: 0 0 0 0.15rem rgba(74, 108, 247, 0.25);
        border-color: #4a6cf7;
    }
    
    .required-field {
        color: #dc3545;
        font-weight: bold;
        font-size: 0.8rem;
    }
    
    .btn-submit-project {
        background: linear-gradient(135deg, #4a6cf7, #2b3ddb);
        border: none;
        padding: 0.4rem 0.9rem;
        transition: all 0.3s ease;
    }
    
    .btn-submit-project:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.25rem 0.5rem rgba(43, 61, 219, 0.2);
    }
    
    .help-tooltip {
        color: #6c757d;
        cursor: help;
        transition: color 0.2s ease;
    }
    
    .help-tooltip:hover {
        color: #4a6cf7;
    }
    
    /* Compact form styling */
    .form-container .mb-3 {
        margin-bottom: 0.5rem !important;
    }
    
    .form-container .form-control,
    .form-container .input-group-text {
        padding: 0.25rem 0.4rem;
        font-size: 0.8rem;
        min-height: 32px;
    }
    
    .form-container .form-text {
        font-size: 0.7rem;
        margin-top: 0.2rem;
        color: #6c757d;
    }
    
    .form-container textarea.form-control {
        min-height: 80px;
    }
    
    .form-container h3 {
        font-size: 1.2rem;
    }
    
    .form-container h4 {
        font-size: 1rem;
    }
    
    .form-container p {
        font-size: 0.8rem;
    }
    
    .form-container .form-label {
        margin-bottom: 0.2rem;
    }
    
    .form-container .form-check-label {
        line-height: 1.3;
    }
    
    .form-container .btn {
        font-size: 0.8rem;
    }
    
    .form-content {
        padding: 0.9rem;
    }
    
    .two-column-section {
        margin-bottom: 0.6rem;
    }
</style>

<div class="container py-3">
    <div class="row mb-3">
        <div class="col-md-12">
            <h1 class="h3 fw-bold">Create New Project</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $team_id; ?>"><?php echo $team_name; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create Project</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="form-container">
                <div class="form-header d-flex align-items-center">
                    <div class="form-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div>
                        <h3 class="mb-0">Launch Your Project</h3>
                        <p class="mb-0">Showcase your team's innovation to the world</p>
                    </div>
                </div>
                
                <div class="form-content">
                    <div id="form-error-msg" class="alert alert-danger py-2 px-3" style="display: none;">
                        <i class="fas fa-exclamation-circle me-2"></i> Please fix the errors in the form below.
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="project-form" enctype="multipart/form-data">
                        <div class="row two-column-section">
                            <!-- Project Information Section (LEFT SIDE) -->
                            <div class="col-md-6">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <div class="form-section-icon"><i class="fas fa-info-circle"></i></div>
                                        <h4 class="mb-0">Project Information</h4>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label small">Project Title <span class="required-field">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                            <input type="text" name="title" id="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $title; ?>" required>
                                            <span class="input-group-text help-tooltip" data-bs-toggle="tooltip" data-bs-placement="top" title="Choose a clear, descriptive name for your project">
                                                <i class="fas fa-question-circle"></i>
                                            </span>
                                        </div>
                                        <div class="invalid-feedback small"><?php echo $title_err; ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label small">Project Description <span class="required-field">*</span></label>
                                        <textarea name="description" id="description" class="form-control form-control-sm <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>" rows="4" required><?php echo $description; ?></textarea>
                                        <div class="invalid-feedback small"><?php echo $description_err; ?></div>
                                        <div class="form-text">Describe your project, its purpose, features, and how it works.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Technical Details Section (RIGHT SIDE) -->
                            <div class="col-md-6">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <div class="form-section-icon"><i class="fas fa-code"></i></div>
                                        <h4 class="mb-0">Technical Details</h4>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tech_stack" class="form-label small">Technologies Used <span class="required-field">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                                            <input type="text" name="tech_stack" id="tech_stack" class="form-control <?php echo (!empty($tech_stack_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $tech_stack; ?>" required>
                                            <span class="input-group-text help-tooltip" data-bs-toggle="tooltip" data-bs-placement="top" title="Separate technologies with commas: HTML, CSS, JavaScript, etc.">
                                                <i class="fas fa-question-circle"></i>
                                            </span>
                                        </div>
                                        <div class="invalid-feedback small"><?php echo $tech_stack_err; ?></div>
                                        <div class="form-text">List the technologies, languages, frameworks used (e.g., HTML, CSS, JavaScript, React, Node.js, etc.)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="github_link" class="form-label small">GitHub Repository URL</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fab fa-github"></i></span>
                                            <input type="url" name="github_link" id="github-url" class="form-control <?php echo (!empty($github_link_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $github_link; ?>" placeholder="https://github.com/username/repository">
                                        </div>
                                        <div class="invalid-feedback small"><?php echo $github_link_err; ?></div>
                                        <div class="form-text">Link to your project's GitHub repository (optional but recommended)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="demo_link" class="form-label small">Demo URL</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                            <input type="url" name="demo_link" id="demo_link" class="form-control <?php echo (!empty($demo_link_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $demo_link; ?>" placeholder="https://your-demo-site.com">
                                        </div>
                                        <div class="invalid-feedback small"><?php echo $demo_link_err; ?></div>
                                        <div class="form-text">Link to a live demo of your project (optional)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Documentation Section (BOTTOM) -->
                        <div class="row">
                            <div class="col-12">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <div class="form-section-icon"><i class="fas fa-file-pdf"></i></div>
                                        <h4 class="mb-0">Documentation</h4>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="project_pdf" class="form-label small">Project PDF</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fas fa-upload"></i></span>
                                            <input type="file" name="project_pdf" id="project_pdf" class="form-control <?php echo (!empty($pdf_err)) ? 'is-invalid' : ''; ?>" accept=".pdf">
                                        </div>
                                        <div class="invalid-feedback small"><?php echo $pdf_err; ?></div>
                                        <div class="form-text">Upload a PDF document with additional project details, diagrams, or screenshots (max 25MB)</div>
                                    </div>
                                    
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" id="confirm_ready">
                                        <label class="form-check-label small" for="confirm_ready">
                                            You can save your project as a draft to continue working on it later, or submit it for review once you're ready.
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-footer d-flex justify-content-between">
                            <button type="submit" name="save_draft" value="1" class="btn btn-secondary btn-sm">
                                <i class="fas fa-save me-1"></i> Save as Draft
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm btn-submit-project">
                                <i class="fas fa-paper-plane me-1"></i> Submit Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Form validation
        const form = document.getElementById('project-form');
        const errorMsg = document.getElementById('form-error-msg');
        
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                errorMsg.style.display = 'block';
                
                // Scroll to error message
                errorMsg.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            
            form.classList.add('was-validated');
        });
    });
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?> 