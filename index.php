<?php
// Start output buffering to catch any unexpected output from includes
ob_start();

require_once 'includes/config.php';
// Include auth functions
require_once 'includes/auth-new.php';

// Capture any output from includes
$unexpected_output = ob_get_clean();

// If there's unexpected output (like BOM), log it but don't display it
if (!empty($unexpected_output)) {
    error_log("Unexpected output from includes: " . bin2hex($unexpected_output));
}

// Get hackathon settings
$settingsQuery = $conn->query("SELECT * FROM hackathon_settings LIMIT 1");
$hackathonSettings = null;

if ($settingsQuery && $settingsQuery->num_rows > 0) {
    $hackathonSettings = $settingsQuery->fetch_assoc();
    
    // Calculate time remaining
    $registrationDeadline = strtotime($hackathonSettings['registration_deadline']);
    $submissionDeadline = strtotime($hackathonSettings['submission_deadline']);
    $currentTime = time();
    
    $registrationOpen = ($currentTime < $registrationDeadline);
    $submissionOpen = ($currentTime < $submissionDeadline);
    
    $registrationDaysLeft = floor(($registrationDeadline - $currentTime) / (60 * 60 * 24));
    $submissionDaysLeft = floor(($submissionDeadline - $currentTime) / (60 * 60 * 24));
}

// Get some stats for the homepage
$statsQuery = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE user_type = 'participant') as participant_count,
    (SELECT COUNT(*) FROM teams) as team_count,
    (SELECT COUNT(*) FROM projects) as project_count");

$stats = $statsQuery ? $statsQuery->fetch_assoc() : null;

$title = "Welcome to Hackhub";
include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="position-relative overflow-hidden text-center bg-dark text-white">
    <div class="col-md-8 p-lg-5 mx-auto my-5">
        <h1 class="display-4 fw-bold"><?php echo $hackathonSettings ? htmlspecialchars($hackathonSettings['name']) : 'Hackhub'; ?></h1>
        <p class="lead fw-normal">
            <?php echo $hackathonSettings ? htmlspecialchars($hackathonSettings['description']) : 'Join us for an amazing hackathon experience! Collaborate, code, and create innovative solutions.'; ?>
        </p>
        <div class="d-flex gap-3 justify-content-center lead mb-4">
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="btn btn-primary btn-lg px-4 me-md-2">Go to Dashboard</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-primary btn-lg px-4 me-md-2">Register Now</a>
                <a href="<?php echo BASE_URL; ?>/pages/login.php" class="btn btn-outline-light btn-lg px-4">Login</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="product-device shadow-sm d-none d-md-block"></div>
    <div class="product-device product-device-2 shadow-sm d-none d-md-block"></div>
</div>

<!-- Countdown Section -->
<?php if ($hackathonSettings && ($registrationOpen || $submissionOpen)): ?>
<div class="bg-light py-5">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title">Registration <?php echo $registrationOpen ? 'Closes In' : 'Closed'; ?></h3>
                        <?php if ($registrationOpen): ?>
                            <div class="display-4 my-3 text-primary">
                                <?php echo $registrationDaysLeft; ?> days
                            </div>
                            <p class="card-text">
                                Registration deadline: <?php echo date('F j, Y, g:i a', $registrationDeadline); ?>
                            </p>
                            <?php if (!isLoggedIn()): ?>
                                <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-outline-primary">Register Now</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="display-4 my-3 text-danger">
                                <i class="fas fa-clock"></i>
                            </div>
                            <p class="card-text">
                                Registration closed on <?php echo date('F j, Y, g:i a', $registrationDeadline); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title">Submission <?php echo $submissionOpen ? 'Closes In' : 'Closed'; ?></h3>
                        <?php if ($submissionOpen): ?>
                            <div class="display-4 my-3 text-success">
                                <?php echo $submissionDaysLeft; ?> days
                            </div>
                            <p class="card-text">
                                Project submission deadline: <?php echo date('F j, Y, g:i a', $submissionDeadline); ?>
                            </p>
                            <?php if (isLoggedIn()): ?>
                                <a href="<?php echo BASE_URL; ?>/pages/projects.php" class="btn btn-outline-success">Go to Projects</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="display-4 my-3 text-danger">
                                <i class="fas fa-clock"></i>
                            </div>
                            <p class="card-text">
                                Submission closed on <?php echo date('F j, Y, g:i a', $submissionDeadline); ?>
                            </p>
                            <a href="<?php echo BASE_URL; ?>/pages/leaderboard.php" class="btn btn-outline-dark">View Results</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Section -->
<?php if ($stats): ?>
<div class="container py-5">
    <h2 class="text-center mb-5">Hackathon Stats</h2>
    <div class="row text-center">
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="d-inline-block p-4 bg-primary text-white rounded-circle mb-3">
                <i class="fas fa-users fa-3x"></i>
            </div>
            <h3><?php echo $stats['participant_count']; ?></h3>
            <p class="lead">Participants</p>
        </div>
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="d-inline-block p-4 bg-success text-white rounded-circle mb-3">
                <i class="fas fa-user-friends fa-3x"></i>
            </div>
            <h3><?php echo $stats['team_count']; ?></h3>
            <p class="lead">Teams</p>
        </div>
        <div class="col-md-4">
            <div class="d-inline-block p-4 bg-info text-white rounded-circle mb-3">
                <i class="fas fa-project-diagram fa-3x"></i>
            </div>
            <h3><?php echo $stats['project_count']; ?></h3>
            <p class="lead">Projects</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Features Section -->
<div class="bg-light py-5">
    <div class="container">
        <h2 class="text-center mb-5">Hackathon Features</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-users fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Team Formation</h4>
                        <p class="card-text">Create your own team or join an existing one. Collaborate with talented individuals to bring your ideas to life.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-laptop-code fa-3x text-success"></i>
                        </div>
                        <h4 class="card-title">Project Submission</h4>
                        <p class="card-text">Submit your projects with ease. Include details, upload files, and showcase your work to the judges.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-trophy fa-3x text-warning"></i>
                        </div>
                        <h4 class="card-title">Leaderboard</h4>
                        <p class="card-text">Track your standing in real-time. See how your project ranks against others and aim for the top spot.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Call to Action Section -->
<div class="container py-5 text-center">
    <h2 class="mb-4">Ready to Join the Hackathon?</h2>
    <p class="lead mb-4">Don't miss this opportunity to showcase your skills, collaborate with others, and create something amazing!</p>
    <?php if (isLoggedIn()): ?>
        <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
    <?php else: ?>
        <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-primary btn-lg me-2">Register Now</a>
        <a href="<?php echo BASE_URL; ?>/pages/login.php" class="btn btn-outline-secondary btn-lg">Login</a>
    <?php endif; ?>
</div>

<style>
.bg-dark {
    background: linear-gradient(135deg, #4b6cb7, #182848);
}
</style>

<?php include 'includes/footer.php'; ?> 