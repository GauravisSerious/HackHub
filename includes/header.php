<?php
require_once 'config.php';
// Include auth functions
require_once 'auth-new.php';

// Update session data to ensure profile image is current
if (function_exists('updateSessionData') && isset($_SESSION['user_id'])) {
    updateSessionData();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title . ' - ' : ''; ?>Hackathon Management System</title>
    <!-- Favicon -->
    <link rel="icon" href="<?php echo BASE_URL; ?>/images/favicon.ico" type="image/x-icon">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom styles -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/modern-style.css" rel="stylesheet">
    <!-- jQuery first, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Navigation bar styling */
        .navbar {
            background: #1a1a2e !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            padding: 0.4rem 1rem;
        }
        
        /* Profile avatar styling */
        .profile-avatar {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.5);
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .nav-link:hover .profile-avatar {
            border-color: rgba(255,255,255,0.8);
            transform: scale(1.05);
        }
        
        /* Brand styling */
        .navbar-brand {
            font-weight: bold;
            font-size: 1.1rem;
            color: white !important;
            display: flex;
            align-items: center;
            background: linear-gradient(to right, #00c6ff, #0072ff);
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 114, 255, 0.3);
            transition: all 0.3s ease;
            margin-right: 1.5rem;
        }
        
        .navbar-brand:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 114, 255, 0.4);
        }
        
        .navbar-brand .code-icon {
            margin-right: 5px;
            font-weight: 900;
            font-size: 1.3rem;
            color: #fff;
            text-shadow: 0px 0px 6px rgba(255, 255, 255, 0.5);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.8; }
            50% { opacity: 1; }
            100% { opacity: 0.8; }
        }
        
        .navbar-brand .hub-text {
            background: linear-gradient(to right, #ffeb3b, #ffc107);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 600;
        }
        
        /* Navigation links */
        .navbar .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-weight: 500;
            padding: 0.3rem 0.7rem;
            margin: 0 0.2rem;
            border-radius: 4px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        
        .navbar .nav-link:hover {
            color: #ffffff !important;
            background-color: rgba(255,255,255,0.1);
        }
        
        .navbar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: #ffffff !important;
        }
        
        /* Icon styling */
        .navbar .nav-link i {
            margin-right: 4px;
        }
        
        /* User profile */
        .navbar .dropdown-toggle img {
            border: 2px solid rgba(255,255,255,0.5);
            transition: all 0.2s ease;
            width: 24px;
            height: 24px;
        }
        
        .navbar .dropdown-toggle:hover img {
            border-color: rgba(255,255,255,0.8);
        }
        
        /* Dropdown styling */
        .navbar .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            border-radius: 0.5rem;
            padding: 0.4rem 0;
            margin-top: 0.4rem;
        }
        
        .navbar .dropdown-item {
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .navbar .dropdown-item:hover {
            background-color: rgba(98, 0, 234, 0.05);
        }
        
        .navbar .dropdown-item i {
            width: 18px;
            margin-right: 8px;
            color: #6200ea;
        }
        
        /* Login/Register buttons */
        .navbar .btn-outline-primary {
            border-color: #00c6ff;
            color: #00c6ff;
            padding: 0.3rem 0.7rem;
        }
        
        .navbar .btn-primary {
            background: linear-gradient(to right, #00c6ff, #0072ff);
            border: none;
            padding: 0.3rem 0.7rem;
        }
        
        /* Navbar toggler for mobile */
        .navbar .navbar-toggler {
            border-color: rgba(255,255,255,0.3);
            padding: 0.25rem 0.5rem;
        }
        
        .navbar .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.7%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
            width: 1.2em;
            height: 1.2em;
        }
    </style>
    <!-- Initialize Bootstrap components -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
        });
    </script>
</head>
<?php
$user_role_class = '';
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin') {
        $user_role_class = 'role-admin';
    } elseif ($_SESSION['user_type'] === 'judge') {
        $user_role_class = 'role-judge';
    } else {
        $user_role_class = 'role-participant';
    }
}
?>
<body class="<?php echo $user_role_class; ?>">
    <!-- Modern Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php">
                <span class="code-icon">&lt;/&gt;</span> Hackhub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard.php') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/pages/dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/teams.php') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/pages/teams.php">
                                <i class="fas fa-users me-1"></i> Teams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/projects.php') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/pages/projects.php">
                                <i class="fas fa-project-diagram me-1"></i> Projects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/leaderboard.php') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/pages/leaderboard.php">
                                <i class="fas fa-trophy me-1"></i> Leaderboard
                            </a>
                        </li>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin.php') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/pages/admin.php">
                                    <i class="fas fa-user-shield me-1"></i> Admin
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($_SERVER['REQUEST_URI'] == '/index.php' || $_SERVER['REQUEST_URI'] == '/') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php">
                                <i class="fas fa-home me-1"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/about.php') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/pages/about.php">
                                <i class="fas fa-info-circle me-1"></i> About
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/contact.php') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/pages/contact.php">
                                <i class="fas fa-envelope me-1"></i> Contact
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown user-dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php 
                                // Get user profile image
                                $user_profile_image = BASE_URL . '/assets/images/avatar.png';
                                // Check if user has a profile image
                                if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                                    // Ensure the path is correct
                                    if (strpos($_SESSION['profile_image'], 'uploads/profile_pics/') !== false) {
                                        $user_profile_image = BASE_URL . '/' . $_SESSION['profile_image'];
                                    } else {
                                        // If the path is incomplete
                                        $user_profile_image = BASE_URL . '/uploads/profile_pics/' . basename($_SESSION['profile_image']);
                                    }
                                }
                                ?>
                                <img src="<?php echo $user_profile_image; ?>" alt="Profile" class="profile-avatar rounded-circle" style="width: 30px; height: 30px; object-fit: cover;">
                                <span class="ms-1 d-none d-md-inline-block"><?php echo $_SESSION['username']; ?></span>
                            </a>
                            <ul class="dropdown-menu">
                                <li class="dropdown-header"><?php echo ucfirst($_SESSION['user_type']); ?></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/profile.php">
                                    <i class="fas fa-user me-2 text-muted"></i> My Profile
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/includes/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item me-2">
                            <a class="nav-link btn btn-outline-primary px-3" href="<?php echo BASE_URL; ?>/pages/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white px-3" href="<?php echo BASE_URL; ?>/pages/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Messages/Alerts -->
    <?php if (isset($_SESSION['message'])): ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show animate-slide-in" role="alert">
            <?php if ($_SESSION['message_type'] === 'success'): ?>
                <i class="fas fa-check-circle me-2"></i>
            <?php elseif ($_SESSION['message_type'] === 'danger'): ?>
                <i class="fas fa-exclamation-circle me-2"></i>
            <?php elseif ($_SESSION['message_type'] === 'warning'): ?>
                <i class="fas fa-exclamation-triangle me-2"></i>
            <?php elseif ($_SESSION['message_type'] === 'info'): ?>
                <i class="fas fa-info-circle me-2"></i>
            <?php endif; ?>
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php 
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    endif; 
    ?>

    <!-- Main Content Container -->
    <div class="container my-4"><?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Project status messages - displayed only on dashboard -->
    <?php 
    // Check if we're on the dashboard page
    $current_file = basename($_SERVER['PHP_SELF']);
    if (isset($_SESSION['project_status_message']) && $_SESSION['project_status_message'] && $current_file === 'dashboard.php'): 
    ?>
    <div class="alert <?php echo ($_SESSION['project_status'] == 'approved') ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <strong>Project Update:</strong>
        Your project "<a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $_SESSION['project_id']; ?>"><?php echo htmlspecialchars($_SESSION['project_title']); ?></a>" has been 
        <?php if ($_SESSION['project_status'] == 'approved'): ?>
            <strong class="text-success">approved</strong> by the admin.
        <?php else: ?>
            <strong class="text-danger">rejected</strong> by the admin.
            <?php if (isset($_SESSION['rejection_reason'])): ?>
            <div class="mt-2 border-left border-danger pl-3 ml-3">
                <strong>Reason:</strong> <?php echo htmlspecialchars($_SESSION['rejection_reason']); ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="clearProjectStatus()"></button>
    </div>
    
    <script>
    function clearProjectStatus() {
        fetch('<?php echo BASE_URL; ?>/includes/clear_project_status.php', {
            method: 'POST'
        });
    }
    </script>
    <?php endif; ?> 
    
    <!-- Fix dropdown issue -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure Bootstrap is loaded
            if (typeof bootstrap !== 'undefined') {
                // Manually initialize dropdown
                const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
                dropdownElementList.forEach(function(dropdownToggle) {
                    const dropdown = new bootstrap.Dropdown(dropdownToggle);
                    
                    // Add click event directly
                    dropdownToggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        dropdown.toggle();
                    });
                });
                
                // Ensure dropdown items are clickable
                const dropdownItems = document.querySelectorAll('.dropdown-item');
                dropdownItems.forEach(function(item) {
                    item.addEventListener('click', function(e) {
                        if (this.getAttribute('href')) {
                            window.location.href = this.getAttribute('href');
                        }
                    });
                });
                
                // Enhance navbar with code icon
                enhanceNavbar();
            } else {
                console.error("Bootstrap is not loaded properly");
            }
        });
        
        // Function to enhance the navbar
        function enhanceNavbar() {
            // Add code icon to the brand
            const navbarBrand = document.querySelector('.navbar-brand');
            if (navbarBrand) {
                let brandText = navbarBrand.innerHTML;
                if (brandText.includes('Hackathon')) {
                    brandText = brandText.replace('Hackathon', '<span class="code-icon">&lt;/&gt;</span> Hack');
                    navbarBrand.innerHTML = brandText;
                }
            }
        }
    </script>
</body>
</html> 