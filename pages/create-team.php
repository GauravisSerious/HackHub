<?php
// Include configuration file
require_once '../includes/config.php';
// Include auth functions
require_once '../includes/auth-new.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Set message
    $_SESSION['message'] = "You must log in to create a team";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $team_name = trim($_POST["team_name"]);
    $team_description = trim($_POST["team_description"]);
    $max_members = (int)$_POST["max_members"];
    
    // Validate team name
    if (empty($team_name)) {
        $errors['team_name'] = "Please enter a team name";
    }
    
    // Validate description
    if (empty($team_description)) {
        $errors['team_description'] = "Please enter a team description";
    }
    
    // Validate max members
    if ($max_members < 2 || $max_members > 5) {
        $errors['max_members'] = "Team size must be between 2 and 5 members";
    }
    
    // Validate team members
    $team_members = [];
    $valid_member_count = 0;
    
    // Debug - log the raw POST data for members
    error_log("Team creation POST data for members:");
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'member_') === 0) {
            error_log("  $key: $value");
        }
    }
    
    // Iterate through all potential members based on max_members
    for ($i = 1; $i < $max_members; $i++) {
        // Check if both name and email are provided for this member
        if (isset($_POST["member_name_$i"]) && !empty($_POST["member_name_$i"]) && 
            isset($_POST["member_email_$i"]) && !empty($_POST["member_email_$i"])) {
            
            $name = trim($_POST["member_name_$i"]);
            $email = trim($_POST["member_email_$i"]);
            
            // Basic email validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors["member_email_$i"] = "Please enter a valid email address for member $i";
            } else {
                // Only count valid members
                $valid_member_count++;
                
                $team_members[] = [
                    'name' => $name,
                    'email' => $email,
                    'index' => $i
                ];
            }
        }
    }
    
    // Log the count of valid members
    error_log("Valid team members found: $valid_member_count out of expected " . ($max_members - 1));
    
    // If no errors, create the team and members
    if (empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Create the team
            $sql = "INSERT INTO teams (team_name, team_description, created_by) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $team_name, $team_description, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $team_id = mysqli_insert_id($conn);
                
                // Add the current user as team leader
                $sql = "INSERT INTO team_members (team_id, user_id, is_leader) VALUES (?, ?, 1)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $team_id, $user_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to add team leader: " . mysqli_error($conn));
                }
                
                // Debug log
                error_log("Creating team: $team_id with leader user_id: $user_id and " . count($team_members) . " additional members");
                
                // Track successful additions for feedback
                $members_added = 1; // Start with 1 for the leader
                $members_skipped = 0;
                $members_failed = 0;
                $members_not_found = 0;
                
                // Process team members - send invitations
                foreach ($team_members as $member) {
                    // Check if user with this email exists
                    $sql = "SELECT user_id FROM users WHERE email = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "s", $member['email']);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_store_result($stmt);
                    
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        // User exists, bind the result
                        mysqli_stmt_bind_result($stmt, $member_user_id);
                        mysqli_stmt_fetch($stmt);
                        
                        // Log for debugging
                        error_log("Adding user ID $member_user_id to team $team_id (Email: {$member['email']})");
                        
                        // Check if user is already in another team
                        $check_sql = "SELECT team_id FROM team_members WHERE user_id = ?";
                        $check_stmt = mysqli_prepare($conn, $check_sql);
                        mysqli_stmt_bind_param($check_stmt, "i", $member_user_id);
                        mysqli_stmt_execute($check_stmt);
                        mysqli_stmt_store_result($check_stmt);
                        
                        if (mysqli_stmt_num_rows($check_stmt) > 0) {
                            // User is already in a team, log this but continue
                            error_log("User ID $member_user_id is already in another team. Skipping...");
                            $members_skipped++;
                            continue;
                        }
                        
                        // Add user directly to the team as a member
                        $sql = "INSERT INTO team_members (team_id, user_id, is_leader) VALUES (?, ?, 0)";
                        $member_stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($member_stmt, "ii", $team_id, $member_user_id);
                        $result = mysqli_stmt_execute($member_stmt);
                        
                        // Log the result
                        if ($result) {
                            error_log("Successfully added user ID $member_user_id to team $team_id");
                            $members_added++;
                        } else {
                            error_log("Failed to add user ID $member_user_id to team $team_id: " . mysqli_error($conn));
                            $members_failed++;
                            // Continue with the process instead of stopping on error
                        }
                    } else {
                        // User does not exist, log this for troubleshooting
                        error_log("User with email {$member['email']} does not exist in the database.");
                        $members_not_found++;
                        
                        // This is where you would implement invitation functionality
                        // For now we'll just record the information for debugging
                        error_log("Invitation would be sent to {$member['name']} at {$member['email']}");
                    }
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Set success flags to prepare for automatic redirect
                $_SESSION['new_members_added'] = true;
                $_SESSION['total_members_count'] = $members_added;
                $_SESSION['members_details'] = [
                    'added' => $members_added,
                    'skipped' => $members_skipped,
                    'failed' => $members_failed,
                    'not_found' => $members_not_found,
                    'expected' => $max_members
                ];
                
                // Set success message
                $_SESSION['message'] = "Team created successfully! Added $members_added member(s).";
                $_SESSION['message_type'] = "success";
                
                // Redirect to team details page with a refresh parameter to ensure data is loaded
                header("Location: " . BASE_URL . "/pages/team-details.php?id=" . $team_id . "&updated=true");
                exit();
            } else {
                throw new Exception("Error creating team");
            }
            
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
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Create a New Team</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($errors['db'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $errors['db']; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form id="createTeamForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="mb-3">
                            <label for="team_name" class="form-label">Team Name *</label>
                            <input type="text" class="form-control <?php echo isset($errors['team_name']) ? 'is-invalid' : ''; ?>" 
                                id="team_name" name="team_name" value="<?php echo isset($team_name) ? $team_name : ''; ?>" required>
                            <?php if (isset($errors['team_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['team_name']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="team_description" class="form-label">Team Description *</label>
                            <textarea class="form-control <?php echo isset($errors['team_description']) ? 'is-invalid' : ''; ?>" 
                                id="team_description" name="team_description" rows="3" required><?php echo isset($team_description) ? $team_description : ''; ?></textarea>
                            <?php if (isset($errors['team_description'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['team_description']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="max_members" class="form-label">Team Size (including you) *</label>
                            <select class="form-select <?php echo isset($errors['max_members']) ? 'is-invalid' : ''; ?>" 
                                id="max_members" name="max_members" required>
                                <option value="" disabled selected>Select team size</option>
                                <option value="2" <?php echo (isset($max_members) && $max_members == 2) ? 'selected' : ''; ?>>2 members</option>
                                <option value="3" <?php echo (isset($max_members) && $max_members == 3) ? 'selected' : ''; ?>>3 members</option>
                                <option value="4" <?php echo (isset($max_members) && $max_members == 4) ? 'selected' : ''; ?>>4 members</option>
                                <option value="5" <?php echo (isset($max_members) && $max_members == 5) ? 'selected' : ''; ?>>5 members</option>
                            </select>
                            <?php if (isset($errors['max_members'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['max_members']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <hr>
                        <h5>Team Members</h5>
                        <p class="text-muted">You will be automatically added as the team leader. Add details for your other team members below.</p>
                        
                        <div id="team-members-container">
                            <!-- Team member inputs will be dynamically added here -->
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary">Create Team</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const maxMembersSelect = document.getElementById('max_members');
    const teamMembersContainer = document.getElementById('team-members-container');
    
    // Function to update team member fields
    function updateTeamMemberFields() {
        const maxMembers = parseInt(maxMembersSelect.value) || 0;
        teamMembersContainer.innerHTML = '';
        
        console.log(`Creating ${maxMembers - 1} member fields`); // Debug log
        
        // Don't add fields for the first member (the team leader)
        for (let i = 1; i < maxMembers; i++) {
            const memberDiv = document.createElement('div');
            memberDiv.className = 'card mb-3';
            memberDiv.innerHTML = `
                <div class="card-body">
                    <h6>Team Member ${i}</h6>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label for="member_name_${i}" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="member_name_${i}" name="member_name_${i}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="member_email_${i}" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="member_email_${i}" name="member_email_${i}" required>
                            <div class="form-text">An invitation will be sent to this email address if they don't have an account.</div>
                        </div>
                    </div>
                </div>
            `;
            teamMembersContainer.appendChild(memberDiv);
        }
    }
    
    // Update fields when team size changes
    maxMembersSelect.addEventListener('change', updateTeamMemberFields);
    
    // Initial update (in case of page reload with prefilled values)
    if (maxMembersSelect.value) {
        updateTeamMemberFields();
    }
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?> 