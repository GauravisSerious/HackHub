<?php
// Include configuration file
require_once '../includes/config.php';
// Include auth functions
require_once '../includes/auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to edit projects";
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

// Check if user is a team member
$isTeamMember = false;

$sql = "SELECT * FROM team_members WHERE team_id = ? AND user_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $project['team_id'], $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $isTeamMember = true;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Check access permission
if (!$isTeamMember && !isAdmin()) {
    // Set message
    $_SESSION['message'] = "You don't have permission to edit this project";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Check if project can be edited (only draft or rejected projects)
if (!($project['status'] == 'draft' || $project['status'] == 'rejected')) {
    // Set message
    $_SESSION['message'] = "This project cannot be edited at this time";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to project details
    header("Location: " . BASE_URL . "/pages/project-details.php?id=" . $project_id);
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $tech_stack = trim($_POST['tech_stack']);
    $github_link = trim($_POST['github_link'] ?? '');
    $demo_link = trim($_POST['demo_link'] ?? '');
    $pdf_filename = ""; // Will be set if a new PDF is uploaded
    
    // Process PDF file upload (if provided)
    $pdf_err = "";
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
    
    // Validate title
    if (empty($title)) {
        $_SESSION['form_error'] = "Project title is required";
    } 
    // Validate description
    else if (empty($description)) {
        $_SESSION['form_error'] = "Project description is required";
    }
    // Validate tech stack
    else if (empty($tech_stack)) {
        $_SESSION['form_error'] = "Technologies used is required";
    }
    // Check for PDF upload errors
    else if (!empty($pdf_err)) {
        $_SESSION['form_error'] = $pdf_err;
    }
    else {
        // Update project in database
        $sql = "UPDATE projects 
                SET title = ?, description = ?, tech_stack = ?, github_link = ?, demo_link = ?, status = ?, updated_at = NOW() 
                WHERE project_id = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // If project was rejected, change status back to draft
            $status = $project['status'] == 'rejected' ? 'draft' : $project['status'];
            
            mysqli_stmt_bind_param($stmt, "ssssssi", $title, $description, $tech_stack, $github_link, $demo_link, $status, $project_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // If a new PDF was uploaded, add it to project_files
                if (!empty($pdf_filename)) {
                    $file_sql = "INSERT INTO project_files (project_id, file_name, file_type, uploaded_by, file_path) 
                                VALUES (?, ?, 'pdf', ?, ?)";
                    
                    if ($file_stmt = mysqli_prepare($conn, $file_sql)) {
                        $file_path = "uploads/project_pdfs/" . $pdf_filename;
                        mysqli_stmt_bind_param($file_stmt, "isis", $project_id, $pdf_filename, $user_id, $file_path);
                        
                        if (!mysqli_stmt_execute($file_stmt)) {
                            // Log the error, but don't stop the process
                            error_log("Error saving PDF file record: " . mysqli_error($conn));
                        }
                        
                        mysqli_stmt_close($file_stmt);
                    }
                }
                
                // Set success message
                $_SESSION['message'] = "Project updated successfully! You can now submit it for review.";
                $_SESSION['message_type'] = "success";
                
                // Redirect to project details
                header("Location: " . BASE_URL . "/pages/project-details.php?id=" . $project_id);
                exit();
            } else {
                // Set error message
                $_SESSION['form_error'] = "Oops! Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

// Get PDF file from project_files table
$pdf_file = null;
$pdf_sql = "SELECT * FROM project_files WHERE project_id = ? AND file_type = 'pdf' ORDER BY created_at DESC LIMIT 1";
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

// Include header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Edit Project</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $project['team_id']; ?>"><?php echo $project['team_name']; ?></a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project_id; ?>"><?php echo $project['title']; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <?php if (isset($_SESSION['form_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php 
            echo $_SESSION['form_error']; 
            unset($_SESSION['form_error']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($project['status'] == 'rejected' && !empty($project['admin_feedback'])): ?>
    <div class="alert alert-warning">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Rejection Feedback:</h6>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($project['admin_feedback'])); ?></p>
    </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $project_id; ?>" method="post" enctype="multipart/form-data" class="form-container">
        <div class="row">
            <!-- Left Column - Project Information -->
            <div class="col-md-6">
                <div class="form-section">
                    <div class="form-header">
                        <div class="form-icon"><i class="fas fa-info-circle" style="color: #007bff;"></i></div>
                        <h5>Project Information</h5>
                    </div>
                    <div class="form-body">
                        <!-- Project Title Field -->
                        <div class="mb-3">
                            <label for="title" class="form-label">Project Title</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($project['title']); ?>" required>
                        </div>
                        
                        <!-- Project Description Field -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Project Description</label>
                            <textarea name="description" class="form-control" rows="6" required><?php echo htmlspecialchars($project['description']); ?></textarea>
                            <div class="form-text">Describe your project, its purpose, and what problem it solves.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Technical Details -->
            <div class="col-md-6">
                <div class="form-section">
                    <div class="form-header">
                        <div class="form-icon"><i class="fas fa-code" style="color: #00c3ff;"></i></div>
                        <h5>Technical Details</h5>
                    </div>
                    <div class="form-body">
                        <!-- Technologies Used Field -->
                        <div class="mb-3">
                            <label for="tech_stack" class="form-label">Technologies Used</label>
                            <input type="text" name="tech_stack" class="form-control" value="<?php echo htmlspecialchars($project['tech_stack']); ?>" required>
                            <div class="form-text">Separate technologies with commas (e.g., React, Node.js, MongoDB)</div>
                        </div>
                        
                        <!-- GitHub Repository Link Field -->
                        <div class="mb-3">
                            <label for="github_link" class="form-label">GitHub Repository Link</label>
                            <input type="url" name="github_link" class="form-control" value="<?php echo htmlspecialchars($project['github_link']); ?>">
                            <div class="form-text">Optional: Link to your project's source code</div>
                        </div>
                        
                        <!-- Demo Link Field -->
                        <div class="mb-3">
                            <label for="demo_link" class="form-label">Demo URL</label>
                            <input type="url" name="demo_link" class="form-control" value="<?php echo htmlspecialchars($project['demo_link']); ?>">
                            <div class="form-text">Optional: Link to your project's live demo</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentation Section - Full Width -->
        <div class="row">
            <div class="col-12">
                <div class="form-section">
                    <div class="form-header">
                        <div class="form-icon"><i class="fas fa-file-alt" style="color: #007bff;"></i></div>
                        <h5>Documentation</h5>
                    </div>
                    <div class="form-body">
                        <!-- Project PDF Field -->
                        <div class="mb-3">
                            <label for="project_pdf" class="form-label">Project PDF</label>
                            <input type="file" name="project_pdf" id="project_pdf" class="form-control" accept=".pdf">
                            <div class="form-text">
                                Optional: Upload a PDF document with additional project details (max 25MB)
                                <?php if ($pdf_file): ?>
                                <br>Current file: <a href="<?php echo BASE_URL; ?>/<?php echo $pdf_file['file_path']; ?>" target="_blank"><?php echo $pdf_file['file_name']; ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="form-actions">
            <div class="row">
                <div class="col-md-12 d-flex justify-content-end gap-2">
                    <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Project</button>
                    
                    <?php if ($project['status'] == 'rejected' || $project['status'] == 'draft'): ?>
                    <a href="<?php echo BASE_URL; ?>/pages/submit-project.php?id=<?php echo $project_id; ?>" class="btn btn-success">
                        <i class="fas fa-paper-plane me-1"></i> Submit for Review
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
/* Form Styling */
.form-container {
    max-width: 900px;
    margin: 0 auto;
    font-size: 0.9rem;
}

.form-section {
    background: #ffffff;
    border-radius: 6px;
    margin-bottom: 0.9rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    height: 100%;
    border: 1px solid #e0e0e0;
}

.form-header {
    display: flex;
    align-items: center;
    background: #f8f9fa;
    padding: 0.75rem 1.2rem;
    border-radius: 6px 6px 0 0;
    border-bottom: 1px solid #e0e0e0;
}

.form-icon {
    font-size: 1.3rem;
    margin-right: 0.8rem;
    color: #0d6efd;
}

.form-header h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 500;
    color: #212529;
}

.form-body {
    padding: 0.75rem 1rem;
}

.form-actions {
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.form-actions .btn {
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-weight: 500;
}

.btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
}

.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-success {
    background-color: #20c997;
    border-color: #20c997;
}

.gap-2 {
    gap: 0.5rem;
}

input.form-control, textarea.form-control, select.form-control {
    background-color: #ffffff;
    border: 1px solid #ced4da;
    color: #212529;
    min-height: 32px;
}

textarea.form-control {
    min-height: 80px;
}

.form-control:focus {
    background-color: #ffffff;
    border-color: #0d6efd;
    color: #212529;
}

.form-text {
    color: #6c757d;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

/* Styling to match the image */
body {
    background-color: #f8f9fa;
}

.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 1rem;
}

.breadcrumb-item a {
    color: #0d6efd;
}

.breadcrumb-item.active {
    color: #6c757d;
}

.container {
    padding-top: 1rem;
    padding-bottom: 2rem;
}

/* Override Bootstrap default dark theme if needed */
.form-label {
    color: #212529;
    margin-bottom: 0.3rem;
    font-weight: 500;
}
</style>

<?php
// Include footer
require_once '../includes/footer.php';
?> 