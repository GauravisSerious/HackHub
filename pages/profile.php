<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if viewing another user's profile or own profile
$profile_user_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];
$viewing_own_profile = ($profile_user_id == $_SESSION['user_id']);

// Get user data of the profile being viewed
$userQuery = "SELECT * FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $profile_user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    // User not found, redirect to dashboard
    $_SESSION['message'] = "User profile not found";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$user = $userResult->fetch_assoc();
$userStmt->close();

// Initialize error variables
$updateSuccess = false;
$emailErr = $passwordErr = $confirmPasswordErr = "";

// Process form submission - only allowed when viewing own profile
if ($viewing_own_profile && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $bio = trim($_POST['bio']);
    $skills = trim($_POST['skills']);
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    // Validate email
    if (empty($email)) {
        $emailErr = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailErr = "Invalid email format";
    } else {
        // Check if email exists for another user
        $emailCheckQuery = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $emailCheckStmt = $conn->prepare($emailCheckQuery);
        $emailCheckStmt->bind_param("si", $email, $profile_user_id);
        $emailCheckStmt->execute();
        $emailCheckStmt->store_result();
        if ($emailCheckStmt->num_rows > 0) {
            $emailErr = "Email is already used by another account";
        }
        $emailCheckStmt->close();
    }
    
    // Validate password change if requested
    if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
        if (empty($currentPassword)) {
            $passwordErr = "Current password is required to change password";
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $passwordErr = "Current password is incorrect";
        } elseif (empty($newPassword)) {
            $passwordErr = "New password is required";
        } elseif (strlen($newPassword) < 6) {
            $passwordErr = "New password must be at least 6 characters";
        } elseif (empty($confirmPassword)) {
            $confirmPasswordErr = "Please confirm new password";
        } elseif ($newPassword != $confirmPassword) {
            $confirmPasswordErr = "Passwords do not match";
        }
    }
    
    // If no errors, update profile
    if (empty($emailErr) && empty($passwordErr) && empty($confirmPasswordErr)) {
        // Start building the query
        $updateFields = [];
        $updateTypes = "";
        $updateParams = [];
        
        // Add fields to update
        $updateFields[] = "email = ?";
        $updateTypes .= "s";
        $updateParams[] = $email;
        
        $updateFields[] = "first_name = ?";
        $updateTypes .= "s";
        $updateParams[] = $first_name;
        
        $updateFields[] = "last_name = ?";
        $updateTypes .= "s";
        $updateParams[] = $last_name;
        
        $updateFields[] = "bio = ?";
        $updateTypes .= "s";
        $updateParams[] = $bio;
        
        $updateFields[] = "skills = ?";
        $updateTypes .= "s";
        $updateParams[] = $skills;
        
        // Update password if requested
        if (!empty($newPassword) && empty($passwordErr) && empty($confirmPasswordErr)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateFields[] = "password = ?";
            $updateTypes .= "s";
            $updateParams[] = $hashedPassword;
        }
        
        // Add user_id for WHERE clause
        $updateTypes .= "i";
        $updateParams[] = $profile_user_id;
        
        // Build and execute query
        $updateQuery = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        
        // Create the bind_param arguments dynamically
        $bindParams = [$updateTypes];
        foreach ($updateParams as $key => $value) {
            $bindParams[] = &$updateParams[$key];
        }
        
        call_user_func_array([$updateStmt, 'bind_param'], $bindParams);
        
        if ($updateStmt->execute()) {
            $updateSuccess = true;
            
            // Update session information
            $_SESSION['email'] = $email;
            
            // Refresh user data
            $userQuery = "SELECT * FROM users WHERE user_id = ?";
            $userStmt = $conn->prepare($userQuery);
            $userStmt->bind_param("i", $profile_user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $user = $userResult->fetch_assoc();
            $userStmt->close();
            
            // Set success message
            $_SESSION['message'] = "Profile updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating profile: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        }
        
        $updateStmt->close();
    }
}

// Handle profile picture upload
if ($viewing_own_profile && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Verify file extension
        if (in_array(strtolower($filetype), $allowed)) {
            // Check file size - 5MB maximum
            $maxsize = 5 * 1024 * 1024;
            if ($_FILES['profile_pic']['size'] < $maxsize) {
                // Create unique filename
                $new_filename = 'user_' . $profile_user_id . '_' . time() . '.' . $filetype;
                $upload_path = '../uploads/profile_pics/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }
                
                // Upload file
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path . $new_filename)) {
                    // Update database with new profile pic - note the path structure
                    $pic_path = 'uploads/profile_pics/' . $new_filename;
                    $updatePicQuery = "UPDATE users SET profile_pic = ? WHERE user_id = ?";
                    $updatePicStmt = $conn->prepare($updatePicQuery);
                    $updatePicStmt->bind_param("si", $pic_path, $profile_user_id);
                    
                    if ($updatePicStmt->execute()) {
                        $_SESSION['message'] = "Profile picture updated successfully!";
                        $_SESSION['message_type'] = "success";
                        
                        // Also update the profile_image in the session
                        $_SESSION['profile_image'] = $pic_path;
                        
                        // Refresh user data
                        $userQuery = "SELECT * FROM users WHERE user_id = ?";
                        $userStmt = $conn->prepare($userQuery);
                        $userStmt->bind_param("i", $profile_user_id);
                        $userStmt->execute();
                        $userResult = $userStmt->get_result();
                        $user = $userResult->fetch_assoc();
                        $userStmt->close();
                    } else {
                        $_SESSION['message'] = "Error updating profile picture in database.";
                        $_SESSION['message_type'] = "danger";
                    }
                    
                    $updatePicStmt->close();
                } else {
                    $_SESSION['message'] = "Error uploading file.";
                    $_SESSION['message_type'] = "danger";
                }
            } else {
                $_SESSION['message'] = "File is too large. Maximum size is 5MB.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Please select a file to upload.";
        $_SESSION['message_type'] = "warning";
    }
}

// Get team information for this user
$teamQuery = "SELECT t.team_id, t.team_name, tm.is_leader 
              FROM teams t 
              JOIN team_members tm ON t.team_id = tm.team_id 
              WHERE tm.user_id = ?";
$teamStmt = $conn->prepare($teamQuery);
$teamStmt->bind_param("i", $profile_user_id);
$teamStmt->execute();
$teamResult = $teamStmt->get_result();
$team = $teamResult->fetch_assoc();
$teamStmt->close();

// Get pending team invitations for this user
$invitations = [];
if ($viewing_own_profile) {
    $invitationQuery = "SELECT tr.id, tr.team_id, t.team_name, t.team_description, 
                        CONCAT(u.first_name, ' ', u.last_name) as leader_name 
                        FROM team_requests tr 
                        JOIN teams t ON tr.team_id = t.team_id 
                        JOIN team_members tm ON t.team_id = tm.team_id AND tm.is_leader = 1 
                        JOIN users u ON tm.user_id = u.user_id 
                        WHERE tr.user_id = ? AND tr.status = 'pending'";
    $invitationStmt = $conn->prepare($invitationQuery);
    $invitationStmt->bind_param("i", $profile_user_id);
    $invitationStmt->execute();
    $invitationResult = $invitationStmt->get_result();
    
    while ($row = $invitationResult->fetch_assoc()) {
        $invitations[] = $row;
    }
    
    $invitationStmt->close();
}

// Handle invitation responses
if ($viewing_own_profile && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    
    // Validate request belongs to user
    $sql = "SELECT team_id FROM team_requests WHERE id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $request_id, $profile_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $team_id = $row['team_id'];
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                if ($action === 'accept') {
                    // First check if user is already in another team
                    $check_sql = "SELECT COUNT(*) as team_count FROM team_members WHERE user_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "i", $profile_user_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $check_row = mysqli_fetch_assoc($check_result);
                    
                    if ($check_row['team_count'] > 0) {
                        throw new Exception("You are already a member of another team");
                    }
                    
                    // Add user to team members
                    $add_sql = "INSERT INTO team_members (team_id, user_id, is_leader) VALUES (?, ?, 0)";
                    $add_stmt = mysqli_prepare($conn, $add_sql);
                    mysqli_stmt_bind_param($add_stmt, "ii", $team_id, $profile_user_id);
                    mysqli_stmt_execute($add_stmt);
                }
                
                // Update request status
                $status = ($action === 'accept') ? 'approved' : 'rejected';
                $update_sql = "UPDATE team_requests SET status = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $status, $request_id);
                mysqli_stmt_execute($update_stmt);
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Set success message
                $action_text = ($action === 'accept') ? 'accepted' : 'declined';
                $_SESSION['message'] = "Team invitation $action_text successfully";
                $_SESSION['message_type'] = "success";
                
                // If user accepted the invitation, refresh the page to show the new team
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
                
            } catch (Exception $e) {
                // Rollback transaction
                mysqli_rollback($conn);
                
                // Set error message
                $_SESSION['message'] = "Error: " . $e->getMessage();
                $_SESSION['message_type'] = "danger";
            }
        } else {
            // Invalid request
            $_SESSION['message'] = "Invalid invitation request";
            $_SESSION['message_type'] = "danger";
        }
    }
}

$title = $viewing_own_profile ? "My Profile" : htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "'s Profile";
include '../includes/header.php';
?>

<div class="container">
    <div class="profile-container">
        <!-- Profile Section -->
        <div class="profile-section">
            <!-- Profile Picture -->
            <?php if (!empty($user['profile_pic'])): ?>
                <img src="<?php echo BASE_URL . '/' . $user['profile_pic']; ?>" alt="Profile Picture" class="profile-pic">
            <?php else: ?>
                <div class="profile-pic">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            
            <!-- User Info -->
            <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
            <p class="username">@<?php echo htmlspecialchars($user['username']); ?></p>
            <p class="user-role"><?php echo ucfirst(htmlspecialchars($user['user_type'])); ?></p>
            
            <!-- Skills -->
            <?php if (!empty($user['skills'])): ?>
                <div class="skills-container">
                    <?php foreach(explode(',', $user['skills']) as $skill): ?>
                        <span class="badge"><?php echo trim(htmlspecialchars($skill)); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Picture Upload (only for own profile) -->
            <?php if ($viewing_own_profile): ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="mt-3">
                    <div class="input-group input-group-sm">
                        <input class="form-control form-control-sm" type="file" id="profile_pic" name="profile_pic" accept="image/*">
                        <button type="submit" name="upload_photo" class="btn btn-sm btn-primary">Upload</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Account Information Section -->
        <div class="account-section">
            <div class="account-header">Account Information</div>
            <div class="account-body">
                <div class="list-item">
                    <strong>Username:</strong>
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="list-item">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="list-item">
                    <strong>Joined:</strong>
                    <span><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                </div>
                <?php if ($team): ?>
                <div class="list-item">
                    <strong>Team:</strong>
                    <div>
                        <span><?php echo htmlspecialchars($team['team_name']); ?></span>
                        <span class="badge bg-<?php echo $team['is_leader'] ? 'primary' : 'secondary'; ?>">
                            <?php echo $team['is_leader'] ? 'Leader' : 'Member'; ?>
                        </span>
                    </div>
                </div>
                <div class="list-item text-center">
                    <a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $team['team_id']; ?>" class="team-btn">
                        View Team
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Team Invitations Section (only for own profile) -->
        <?php if ($viewing_own_profile && !empty($invitations)): ?>
        <div class="invitations-section">
            <div class="invitations-header">
                <div>
                    <i class="fas fa-envelope me-2"></i>Team Invitations
                    <span class="badge bg-danger"><?php echo count($invitations); ?></span>
                </div>
                <a href="<?php echo BASE_URL; ?>/pages/team-invitations.php" class="view-all-btn">
                    <i class="fas fa-external-link-alt me-1"></i> View All
                </a>
            </div>
            <div class="invitations-body">
                <?php foreach ($invitations as $invitation): ?>
                <div class="invitation-card">
                    <div class="invitation-details">
                        <h5><?php echo htmlspecialchars($invitation['team_name']); ?></h5>
                        <p class="invitation-text">
                            <span class="text-primary"><?php echo htmlspecialchars($invitation['leader_name']); ?></span> has invited you to join their team
                        </p>
                        <div class="invitation-description">
                            <?php echo nl2br(htmlspecialchars($invitation['team_description'])); ?>
                        </div>
                    </div>
                    <div class="invitation-actions">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                            <input type="hidden" name="request_id" value="<?php echo $invitation['id']; ?>">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn btn-success btn-sm">Accept</button>
                        </form>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                            <input type="hidden" name="request_id" value="<?php echo $invitation['id']; ?>">
                            <input type="hidden" name="action" value="decline">
                            <button type="submit" class="btn btn-danger btn-sm">Decline</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif ($viewing_own_profile): ?>
        <div class="invitations-section">
            <div class="invitations-header">
                <div>
                    <i class="fas fa-envelope me-2"></i>Team Invitations
                </div>
                <a href="<?php echo BASE_URL; ?>/pages/team-invitations.php" class="view-all-btn">
                    <i class="fas fa-external-link-alt me-1"></i> View All
                </a>
            </div>
            <div class="invitations-body">
                <div class="invitation-card">
                    <div class="invitation-details text-center py-3">
                        <p class="mb-0">You have no pending team invitations.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- About Section (for other profiles) -->
        <?php if (!$viewing_own_profile && (!empty($user['bio']) || !empty($user['skills']))): ?>
        <div class="about-section">
            <div class="about-header">About <?php echo htmlspecialchars($user['first_name']); ?></div>
            <div class="about-body">
                <?php if (!empty($user['bio'])): ?>
                <div class="mb-3">
                    <h6 class="mb-2">Bio</h6>
                    <p><?php echo nl2br(htmlspecialchars($user['bio'] ?? '')); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Edit Profile Section (only for own profile) -->
        <?php if ($viewing_own_profile): ?>
            <!-- Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show py-2 mb-2" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>
            
            <!-- Edit Profile form would go here if needed -->
        <?php endif; ?>
    </div>
</div>

<!-- Add some additional CSS for better alignment -->
<style>
/* Dark theme and centered layout */
body {
    background-color: #202124 !important;
    color: #e8eaed !important;
}

/* Center the content and optimize for one-page view */
.container {
    max-width: 960px;
    margin: 0 auto;
    padding-top: 1rem;
    padding-bottom: 1rem;
}

/* Profile layout */
.profile-container {
    max-width: 800px;
    margin: 0 auto;
}

/* Left profile section */
.profile-section {
    background-color: #2a2a2a;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
    text-align: center;
}

/* Account info section */
.account-section {
    background-color: #2a2a2a;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    overflow: hidden;
}

.account-section .account-header {
    background-color: #0d6efd;
    color: white;
    padding: 0.75rem 1rem;
    font-weight: 500;
}

.account-section .account-body {
    padding: 0;
}

.account-section .list-item {
    padding: 0.5rem 1rem;
    border-bottom: 1px solid #3a3a3a;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.account-section .list-item:last-child {
    border-bottom: none;
}

/* About section */
.about-section {
    background-color: #2a2a2a;
    border-radius: 0.5rem;
    overflow: hidden;
}

.about-section .about-header {
    background-color: #0d6efd;
    color: white;
    padding: 0.75rem 1rem;
    font-weight: 500;
}

.about-section .about-body {
    padding: 1rem;
}

/* Profile picture */
.profile-pic {
    width: 120px !important;
    height: 120px !important;
    margin: 0 auto 1rem auto;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #0d6efd;
    color: white;
    font-size: 3rem;
    font-weight: bold;
}

/* Team badge */
.badge {
    font-weight: 500;
    padding: 0.3em 0.6em;
    margin-left: 0.5rem;
}

/* Team button */
.team-btn {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    background-color: #0d6efd;
    color: white;
    text-decoration: none;
    border-radius: 0.25rem;
    margin-top: 0.5rem;
    font-size: 0.9rem;
}

.team-btn:hover {
    background-color: #0b5ed7;
    color: white;
}

/* Ensure text is properly aligned */
h1, h2, h3, h4, h5, h6, p {
    margin: 0;
    padding: 0;
    color: #e8eaed;
}

.username {
    color: #aaa;
    margin-bottom: 0.5rem;
}

.user-role {
    color: #aaa;
    margin-bottom: 1rem;
}

/* Override Bootstrap card styles */
.card {
    background-color: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

/* Skills badges */
.skills-container .badge {
    background-color: #0d6efd;
    color: white;
    margin: 0.2rem;
}

/* Team invitations section */
.invitations-section {
    background-color: #2a2a2a;
    border-radius: 0.5rem;
    overflow: hidden;
    margin-bottom: 1rem;
}

.invitations-header {
    background-color: #dc3545;
    color: white;
    padding: 0.75rem 1rem;
    font-weight: 500;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.invitations-header .badge {
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.invitations-body {
    padding: 0;
}

.invitation-card {
    padding: 1rem;
    border-bottom: 1px solid #3a3a3a;
}

.invitation-card:last-child {
    border-bottom: none;
}

.invitation-details h5 {
    margin-bottom: 0.5rem;
    color: #fff;
}

.invitation-text {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.invitation-description {
    font-size: 0.85rem;
    color: #ccc;
    margin-bottom: 1rem;
    padding: 0.5rem;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 0.25rem;
    border-left: 3px solid #0d6efd;
}

.invitation-actions {
    display: flex;
    gap: 0.5rem;
}

.invitation-actions .btn {
    padding: 0.25rem 0.75rem;
    font-size: 0.85rem;
}

.view-all-btn {
    background-color: #0d6efd;
    color: white;
    text-decoration: none;
    border-radius: 0.25rem;
    padding: 0.3rem 0.8rem;
    margin-left: 0.5rem;
    font-size: 0.9rem;
}

.view-all-btn:hover {
    background-color: #0b5ed7;
    color: white;
}
</style>

<?php include '../includes/footer.php'; ?>

<script>
// Ensure dark theme is properly applied
document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('bg-dark');
    document.body.classList.add('text-light');
});
</script> 