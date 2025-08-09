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

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to dashboard
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT user_id, username, password, first_name, last_name, user_type, profile_pic FROM users WHERE username = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);
                
                // Check if username exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {                    
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $user_id, $username, $hashed_password, $first_name, $last_name, $user_type, $profile_pic);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["username"] = $username;
                            $_SESSION["first_name"] = $first_name;
                            $_SESSION["last_name"] = $last_name;
                            $_SESSION["user_type"] = $user_type;
                            $_SESSION["profile_image"] = $profile_pic;
                            
                            // Redirect user to dashboard
                            header("location: dashboard.php");
                        } else {
                            // Password is not valid
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}

// Set page title
$title = "Login";

// Include a simplified header (without navigation)
include '../includes/simple-header.php';
?>

<div class="login-page">
    <div class="container">
        <div class="row min-vh-100 align-items-center justify-content-center">
            <div class="col-lg-8">
                <div class="login-wrapper animate-fade-in">
                    <div class="row shadow-lg rounded-lg overflow-hidden">
                        <div class="col-md-5 d-none d-md-block login-banner" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);">
                            <div class="login-banner-content p-4 d-flex flex-column justify-content-center text-white h-100">
                                <h2 class="mb-4">Welcome to Hackhub</h2>
                                <p class="mb-4">Showcase your innovation and collaborate with talented teams from around the world.</p>
                                <div class="login-features">
                                    <p><i class="fas fa-check-circle me-2"></i> Create and manage teams</p>
                                    <p><i class="fas fa-check-circle me-2"></i> Submit project proposals</p>
                                    <p><i class="fas fa-check-circle me-2"></i> Receive expert feedback</p>
                                    <p><i class="fas fa-check-circle me-2"></i> Win amazing prizes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7 bg-white">
                            <div class="login-form-wrapper p-4 p-md-5">
                                <div class="text-center mb-4">
                                    <h1 class="mb-3 text-primary">Sign In</h1>
                                    <p class="text-muted">Access your hackathon dashboard</p>
                                </div>
                                
                                <?php 
                                if (!empty($login_err)) {
                                    echo '<div class="alert alert-danger">' . $login_err . '</div>';
                                }
                                ?>
                                
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <div class="mb-4">
                                        <label for="username" class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="fas fa-user text-muted"></i>
                                            </span>
                                            <input type="text" class="form-control border-start-0 <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                                                id="username" name="username" placeholder="Enter your username" 
                                                value="<?php echo $username; ?>">
                                        </div>
                                        <div class="text-danger small mt-1"><?php echo $username_err; ?></div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between">
                                            <label for="password" class="form-label">Password</label>
                                            <a href="forgot-password.php" class="text-primary small">Forgot password?</a>
                                        </div>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="fas fa-lock text-muted"></i>
                                            </span>
                                            <input type="password" class="form-control border-start-0 <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                                id="password" name="password" placeholder="Enter your password">
                                        </div>
                                        <div class="text-danger small mt-1"><?php echo $password_err; ?></div>
                                    </div>
                                    
                                    <div class="mb-4 form-check">
                                        <input type="checkbox" class="form-check-input" id="remember">
                                        <label class="form-check-label" for="remember">Remember me</label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                                    </div>
                                </form>
                                
                                <div class="text-center mt-4">
                                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary">Create an account</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.login-page {
    min-height: 100vh;
    background-color: var(--background);
}

.login-banner {
    position: relative;
    overflow: hidden;
}

.login-banner::before {
    content: '';
    position: absolute;
    top: -100px;
    right: -100px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
}

.login-banner::after {
    content: '';
    position: absolute;
    bottom: -50px;
    left: -50px;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
}

.login-banner-content {
    position: relative;
    z-index: 1;
}

.login-features p {
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
}

.login-features p i {
    color: rgba(255, 255, 255, 0.8);
}

.input-group-text {
    background-color: transparent;
}
</style>

<?php
// Include a simplified footer
include '../includes/simple-footer.php';
?> 