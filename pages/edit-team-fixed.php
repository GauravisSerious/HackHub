<?php
// Start output buffering to capture any unexpected output
ob_start();

// Include configuration file
require_once '../includes/config.php';
// Include authentication functions
require_once '../includes/auth-new.php';

// Capture any unexpected output
$unexpected_output = ob_get_clean();
if (!empty($unexpected_output)) {
    error_log("Unexpected output in edit-team-fixed.php: " . bin2hex($unexpected_output));
}

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to edit a team";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];

// Get team ID from URL parameter
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    $_SESSION['message'] = "Invalid team ID";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "/pages/dashboard.php");
    exit();
}

$team_id = trim($_GET['id']);

// Get current team information
$sql = "SELECT * FROM teams WHERE team_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $team = mysqli_fetch_assoc($result);
            $team_name = $team['team_name'];
            $team_description = $team['team_description'];
            $max_members = $team['max_members'] ?? 5; // Default to 5 if not set
        } else {
            $_SESSION['message'] = "No team found with that ID";
            $_SESSION['message_type'] = "danger";
            header("Location: " . BASE_URL . "/pages/dashboard.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Oops! Something went wrong. Please try again later.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . BASE_URL . "/pages/dashboard.php");
        exit();
    }
    
    mysqli_stmt_close($stmt);
}

// Check if user is team leader
$is_leader = false;
$sql = "SELECT is_leader FROM team_members WHERE team_id = ? AND user_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $member = mysqli_fetch_assoc($result);
            $is_leader = (bool)$member['is_leader'];
        } else {
            $_SESSION['message'] = "You are not a member of this team";
            $_SESSION['message_type'] = "danger";
            header("Location: " . BASE_URL . "/pages/dashboard.php");
            exit();
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Only allow team leader to edit team
if (!$is_leader) {
    $_SESSION['message'] = "Only the team leader can edit team details";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
    exit();
}

// Get current team members
$team_members = [];
$sql = "SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, tm.is_leader 
        FROM users u 
        JOIN team_members tm ON u.user_id = tm.user_id 
        WHERE tm.team_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $team_members[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Get current team invitations (pending)
$pending_invitations = [];
$sql = "SELECT tr.id AS request_id, tr.user_id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, tr.created_at 
        FROM team_requests tr
        JOIN users u ON tr.user_id = u.user_id
        WHERE tr.team_id = ? AND tr.status = 'pending'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $team_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $pending_invitations[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Fetch all users for dropdown
$all_users_query = "SELECT user_id, CONCAT(first_name, ' ', last_name) as name, email, username FROM users ORDER BY first_name, last_name";
$all_users_result = mysqli_query($conn, $all_users_query);
$all_users = [];

if ($all_users_result) {
    while ($user = mysqli_fetch_assoc($all_users_result)) {
        $all_users[] = $user;
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $team_name = trim($_POST["team_name"]);
    $team_description = trim($_POST["team_description"]);
    
    // Validate team name
    if (empty($team_name)) {
        $errors['team_name'] = "Please enter a team name";
    }
    
    // Validate description
    if (empty($team_description)) {
        $errors['team_description'] = "Please enter a team description";
    }
    
    // Process new team members
    $new_members = [];
    if (isset($_POST['new_member_name']) && isset($_POST['new_member_email'])) {
        for ($i = 0; $i < count($_POST['new_member_name']); $i++) {
            if (!empty($_POST['new_member_name'][$i]) && !empty($_POST['new_member_email'][$i])) {
                $name = trim($_POST['new_member_name'][$i]);
                $email = trim($_POST['new_member_email'][$i]);
                
                // Basic email validation
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors["new_member_email_$i"] = "Please enter a valid email address";
                    continue;
                }
                
                $new_members[] = [
                    'name' => $name,
                    'email' => $email
                ];
            }
        }
    }
    
    // If no errors, update the team and send invitations
    if (empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update team details
            $sql = "UPDATE teams SET team_name = ?, team_description = ? WHERE team_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $team_name, $team_description, $team_id);
            mysqli_stmt_execute($stmt);
            
            // Send invitations to new members
            $invited_users = [];
            if (!empty($new_members)) {
                foreach ($new_members as $member) {
                    // Check if user with this email exists
                    $sql = "SELECT user_id, first_name, last_name, username FROM users WHERE email = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "s", $member['email']);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        // User exists
                        $user = mysqli_fetch_assoc($result);
                        $member_user_id = $user['user_id'];
                        
                        // Check if user is already a member of this team
                        $check_sql = "SELECT COUNT(*) as count FROM team_members WHERE team_id = ? AND user_id = ?";
                        $check_stmt = mysqli_prepare($conn, $check_sql);
                        mysqli_stmt_bind_param($check_stmt, "ii", $team_id, $member_user_id);
                        mysqli_stmt_execute($check_stmt);
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        $check_row = mysqli_fetch_assoc($check_result);
                        
                        if ($check_row['count'] > 0) {
                            // Already a member, skip
                            continue;
                        }
                        
                        // Check if invitation already exists
                        $check_sql = "SELECT COUNT(*) as count FROM team_requests WHERE team_id = ? AND user_id = ? AND status = 'pending'";
                        $check_stmt = mysqli_prepare($conn, $check_sql);
                        mysqli_stmt_bind_param($check_stmt, "ii", $team_id, $member_user_id);
                        mysqli_stmt_execute($check_stmt);
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        $check_row = mysqli_fetch_assoc($check_result);
                        
                        if ($check_row['count'] > 0) {
                            // Invitation already exists, skip
                            continue;
                        }
                        
                        // Send invitation
                        $sql = "INSERT INTO team_requests (team_id, user_id, message) VALUES (?, ?, ?)";
                        $request_stmt = mysqli_prepare($conn, $sql);
                        $message = "You have been invited to join team: " . $team_name;
                        mysqli_stmt_bind_param($request_stmt, "iis", $team_id, $member_user_id, $message);
                        
                        if (mysqli_stmt_execute($request_stmt)) {
                            // Add to invited users list for display
                            $invited_users[] = [
                                'user_id' => $member_user_id,
                                'name' => $user['first_name'] . ' ' . $user['last_name'],
                                'username' => $user['username'],
                                'email' => $member['email']
                            ];
                        }
                    } else {
                        // User does not exist, store as an error message
                        $errors["user_not_found_" . $member['email']] = "User with email " . $member['email'] . " does not exist in the system.";
                    }
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Store invited users in session for display on team details page
            $_SESSION['invited_users'] = $invited_users;
            
            // Set success message
            if (count($invited_users) > 0) {
                $_SESSION['message'] = count($invited_users) . " invitation(s) sent successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "No new invitations were sent. Users may already be team members or have pending invitations.";
                $_SESSION['message_type'] = "danger";
            }
            
            // Redirect to team details page
            header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            
            // Set error message
            $errors['db'] = "Database error: " . $e->getMessage();
        }
    }
}

// Include header
require_once '../includes/header.php';

// Check if there are invited users to display from session
$show_invited_users = isset($_SESSION['invited_users']) && !empty($_SESSION['invited_users']);
?>

<!-- Add this hidden div to store user data for JavaScript -->
<div id="userData" style="display: none;" data-users='<?php echo htmlspecialchars(json_encode($all_users), ENT_QUOTES, 'UTF-8'); ?>'></div>

<div class="container mt-4">
    <?php if ($show_invited_users): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Invitations Sent Successfully</h4>
                </div>
                <div class="card-body">
                    <h5 class="mb-3">The following users have been invited to join your team:</h5>
                    <div class="row">
                        <?php foreach ($_SESSION['invited_users'] as $user): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <img src="<?php echo BASE_URL; ?>/assets/images/default-avatar.png" class="rounded-circle" width="80" height="80" alt="Profile Image">
                                    </div>
                                    <h5 class="card-title"><?php echo htmlspecialchars($user['name']); ?></h5>
                                    <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                                    <p class="small"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $team_id; ?>" class="btn btn-primary">
                            Return to Team Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php 
    // Clear the invited users from session
    unset($_SESSION['invited_users']);
    endif; 
    ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Invite Team Members</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/teams.php">Teams</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $team_id; ?>"><?php echo $team_name; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Invite Members</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Send Team Invitations</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($errors['db'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $errors['db']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form id="inviteTeamForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $team_id); ?>">
                        <!-- Hidden fields for team name and description to maintain functionality -->
                        <input type="hidden" name="team_name" value="<?php echo $team_name; ?>">
                        <input type="hidden" name="team_description" value="<?php echo $team_description; ?>">
                        
                        <h5>Current Team Members</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($team_members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td>
                                            <?php if ($member['is_leader']): ?>
                                            <span class="badge bg-primary">Team Leader</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Member</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (!empty($pending_invitations)): ?>
                        <h5>Pending Invitations</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Invited On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_invitations as $invitation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invitation['name']); ?></td>
                                        <td><?php echo htmlspecialchars($invitation['email']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($invitation['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <hr>
                        <h5>Invite New Team Members</h5>
                        <p class="text-muted mb-3">Add details for users you want to invite to your team. They will receive an invitation to join your team.</p>
                        
                        <div id="new-members-container">
                            <!-- New member inputs will be added here dynamically -->
                        </div>
                        
                        <button type="button" id="add-member-btn" class="btn btn-outline-secondary mb-4">
                            <i class="fas fa-plus me-2"></i> Add Another Member
                        </button>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Send Invitation</button>
                            <a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $team_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newMembersContainer = document.getElementById('new-members-container');
    const addMemberBtn = document.getElementById('add-member-btn');
    let memberCounter = 0;
    
    // Get user data from the hidden div
    const userData = document.getElementById('userData');
    const users = JSON.parse(userData.getAttribute('data-users'));
    
    // Function to add a new member input row
    function addNewMemberRow() {
        const memberDiv = document.createElement('div');
        memberDiv.className = 'card mb-3';
        memberDiv.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">New Team Member</h6>
                    <button type="button" class="btn-close remove-member" aria-label="Close"></button>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Name *</label>
                        <div class="position-relative">
                            <input type="text" class="form-control member-name-input" name="new_member_name[]" required autocomplete="off">
                            <div class="user-dropdown bg-white border rounded shadow-sm position-absolute w-100 z-index-1000" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control member-email-input" name="new_member_email[]" required>
                        <div class="form-text">Member will be added to the team if their account exists and they're not in another team.</div>
                    </div>
                </div>
            </div>
        `;
        newMembersContainer.appendChild(memberDiv);
        
        // Add event listener to remove button
        memberDiv.querySelector('.remove-member').addEventListener('click', function() {
            newMembersContainer.removeChild(memberDiv);
        });
        
        // Set up the name input dropdown functionality
        const nameInput = memberDiv.querySelector('.member-name-input');
        const emailInput = memberDiv.querySelector('.member-email-input');
        const dropdown = memberDiv.querySelector('.user-dropdown');
        
        // Show dropdown when typing in the name field
        nameInput.addEventListener('input', function() {
            const searchText = this.value.toLowerCase();
            if (searchText.length < 1) {
                dropdown.style.display = 'none';
                return;
            }
            
            // Filter users based on input
            const filteredUsers = users.filter(user => 
                user.name.toLowerCase().includes(searchText) ||
                user.username.toLowerCase().includes(searchText)
            );
            
            // Display the dropdown with matching users
            if (filteredUsers.length > 0) {
                dropdown.innerHTML = '';
                filteredUsers.forEach(user => {
                    const userItem = document.createElement('div');
                    userItem.className = 'p-2 hover-bg-light border-bottom user-item';
                    userItem.style.cursor = 'pointer';
                    userItem.innerHTML = `<strong>${user.name}</strong> (@${user.username})`;
                    
                    // Set the name and email when a user is selected
                    userItem.addEventListener('click', function() {
                        nameInput.value = user.name;
                        emailInput.value = user.email;
                        dropdown.style.display = 'none';
                    });
                    
                    dropdown.appendChild(userItem);
                });
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!nameInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Show dropdown when focusing on name input
        nameInput.addEventListener('focus', function() {
            if (this.value.length > 0) {
                const event = new Event('input');
                this.dispatchEvent(event);
            }
        });
        
        memberCounter++;
    }
    
    // Add first member row by default
    addNewMemberRow();
    
    // Add event listener for adding more members
    addMemberBtn.addEventListener('click', addNewMemberRow);

    // Add some CSS for the dropdown
    const style = document.createElement('style');
    style.textContent = `
        .z-index-1000 { z-index: 1000; }
        .hover-bg-light:hover { background-color: #f8f9fa; }
    `;
    document.head.appendChild(style);
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?> 