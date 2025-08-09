<?php
// Start output buffering to catch any unexpected output from includes
ob_start();

// Include configuration file
require_once '../includes/config.php';
// Include auth functions if not already included
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
    $_SESSION['message'] = "You must log in to view teams";
    $_SESSION['message_type'] = "warning";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/pages/login.php");
    exit();
}

// Get user's team ID if any
$user_id = $_SESSION['user_id'];
$userTeamId = null;
$userTeamMembersCount = 0;
$userTeamMembers = []; // Array to store team member names

// First, try to get the team ID using a direct query
$direct_sql = "SELECT team_id FROM team_members WHERE user_id = $user_id LIMIT 1";
$direct_result = mysqli_query($conn, $direct_sql);
if ($direct_result && mysqli_num_rows($direct_result) > 0) {
    $row = mysqli_fetch_assoc($direct_result);
    $userTeamId = $row['team_id'];
    
    // If we found a team ID, get the members
    if ($userTeamId) {
        $members_sql = "SELECT u.first_name, u.last_name, tm.is_leader 
                       FROM team_members tm 
                       JOIN users u ON tm.user_id = u.user_id 
                       WHERE tm.team_id = $userTeamId 
                       ORDER BY tm.is_leader DESC";
        $members_result = mysqli_query($conn, $members_sql);
        
        if ($members_result) {
            $userTeamMembersCount = mysqli_num_rows($members_result);
            while ($member = mysqli_fetch_assoc($members_result)) {
                $userTeamMembers[] = [
                    'name' => $member['first_name'] . ' ' . $member['last_name'],
                    'is_leader' => $member['is_leader']
                ];
            }
            
            error_log("Found $userTeamMembersCount members for team $userTeamId");
        }
    }
}

// If the direct query didn't work, fallback to the parameterized query
if (!$userTeamId) {
    $sql = "SELECT tm.team_id, COUNT(*) as member_count 
            FROM team_members tm 
            WHERE tm.team_id IN (SELECT team_id FROM team_members WHERE user_id = ?) 
            GROUP BY tm.team_id";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                $userTeamId = $row['team_id'];
                $userTeamMembersCount = $row['member_count'];
            }
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Log for debugging
if ($userTeamId) {
    error_log("User $user_id is in team $userTeamId with $userTeamMembersCount members");
}

// Pagination variables
$limit = 10; // Number of results per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$searchParam = '';

if (!empty($search)) {
    $searchCondition = "AND (t.team_name LIKE ? OR t.team_description LIKE ?)";
    $searchParam = "%{$search}%";
}

// Count total teams for pagination
$total_teams = 0;
$sql = "SELECT COUNT(*) FROM teams t WHERE 1=1 " . $searchCondition;

if ($stmt = mysqli_prepare($conn, $sql)) {
    if (!empty($search)) {
        mysqli_stmt_bind_param($stmt, "ss", $searchParam, $searchParam);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $total_teams);
        mysqli_stmt_fetch($stmt);
    }
    
    mysqli_stmt_close($stmt);
}

$total_pages = ceil($total_teams / $limit);

// Get teams with member count
$teams = [];
$sql = "SELECT t.team_id, t.team_name, t.team_description, t.created_at, 
               u.first_name, u.last_name, u.username,
               (SELECT COUNT(*) FROM team_members WHERE team_id = t.team_id) as member_count
        FROM teams t
        JOIN users u ON t.created_by = u.user_id
        WHERE 1=1 " . $searchCondition . "
        ORDER BY t.created_at DESC
        LIMIT ?, ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    if (!empty($search)) {
        mysqli_stmt_bind_param($stmt, "ssii", $searchParam, $searchParam, $offset, $limit);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $offset, $limit);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $teams[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Include header
require_once '../includes/header.php';
?>

<!-- Dark theme styling -->
<style>
    /* Revert body to light theme */
    body {
        background-color: #f8f9fa;
        color: #212529;
    }
    
    /* Team members display - light theme */
    .team-members-display {
        background: linear-gradient(to right, #f8f9fa, #ffffff);
        border-left: 3px solid #0d6efd;
        font-size: 0.95rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.2s;
        color: #212529;
    }
    
    .team-members-display:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    /* Search styling - light theme */
    .search-container .form-control:focus {
        box-shadow: none;
        border-color: #ced4da;
    }
    
    .search-container .input-group-text {
        border-radius: 0.375rem 0 0 0.375rem;
        background-color: #f8f9fa;
        border-color: #ced4da;
    }
    
    .search-container .form-control {
        border-radius: 0 0.375rem 0.375rem 0;
        background-color: #ffffff;
        border-color: #ced4da;
        color: #212529;
    }
    
    /* Teams section header - light theme */
    .teams-header h1 {
        font-weight: 600;
        color: #212529;
    }
    
    .teams-header p {
        color: #6c757d;
    }
    
    /* Teams container styling - darker light gray */
    .teams-container {
        background-color: #d9d9d9 !important;
        border: 1px solid rgba(0,0,0,0.1);
        box-shadow: inset 0 0 0.5rem rgba(0,0,0,0.08);
    }
    
    /* Card hover effect */
    .card.shadow-sm {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: none;
        background-color: #ffffff !important;
    }
    
    .card.shadow-sm:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    /* Light theme card text colors */
    .card .card-title {
        color: #212529;
    }
    
    .card .card-text {
        color: #495057;
    }
    
    .card .text-muted {
        color: #6c757d !important;
    }
    
    /* Card footer */
    .card .card-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }
    
    /* Improved contrast for cards */
    .card.bg-dark-subtle {
        box-shadow: 0 0.125rem 0.25rem rgba(255,255,255,0.05);
    }
    
    /* Teams container heading */
    .teams-container .alert {
        background-color: #ffffff;
        border-color: rgba(0,0,0,0.1);
    }
</style>

<div class="container">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h1 class="mb-1">Teams</h1>
            <p class="text-muted mb-0">Browse available teams or create your own</p>
        </div>
        <div class="col-md-6 text-end">
            <?php if (!$userTeamId && !isAdmin()): ?>
            <a href="<?php echo BASE_URL; ?>/pages/create-team.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Create Team
            </a>
            <?php elseif ($userTeamId): ?>
            <div class="d-flex flex-column align-items-end">
                <a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $userTeamId; ?>&refresh=<?php echo time(); ?>" class="btn btn-primary">
                    <i class="fas fa-users me-2"></i> My Team (<?php echo $userTeamMembersCount; ?> members)
                </a>
                
                <?php if (!empty($userTeamMembers)): ?>
                <div class="mt-2 bg-light rounded px-3 py-2 text-dark text-start">
                    <small class="text-muted d-block mb-1">Team members:</small>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($userTeamMembers as $member): ?>
                        <li>
                            <?php echo htmlspecialchars($member['name']); ?>
                            <?php if ($member['is_leader']): ?><span class="text-primary fw-medium">(L)</span><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="row mb-4">
        <div class="col-12">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="d-flex">
                <div class="input-group flex-grow-1">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-primary"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="search" name="search" 
                           placeholder="Search teams by name or description" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-primary ms-2 px-4">Search</button>
            </form>
        </div>
    </div>
    
    <!-- Teams List -->
    <div class="row">
        <div class="col-12">
            <div class="p-4 rounded mb-4 teams-container">
                <div class="row">
                    <?php if (count($teams) > 0): ?>
                        <?php foreach ($teams as $team): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $team['team_name']; ?></h5>
                                    <p class="card-text text-muted small">Created by <?php echo $team['first_name'] . ' ' . $team['last_name']; ?> (@<?php echo $team['username']; ?>)</p>
                                    <p class="card-text"><?php echo substr($team['team_description'], 0, 150) . (strlen($team['team_description']) > 150 ? '...' : ''); ?></p>
                                </div>
                                <div class="card-footer d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-users me-1"></i> <?php echo $team['member_count']; ?> members
                                    </small>
                                    <a href="<?php echo BASE_URL; ?>/pages/team-details.php?id=<?php echo $team['team_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo !empty($search) ? 'No teams found matching your search criteria.' : 'No teams available yet. Be the first to create one!'; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="row mt-3">
        <div class="col-md-12">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo BASE_URL; ?>/pages/teams.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo BASE_URL; ?>/pages/teams.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo BASE_URL; ?>/pages/teams.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>

<script>
    // Add classes to elements for styling
    document.addEventListener('DOMContentLoaded', function() {
        // Add class to team members display
        const teamMembersElements = document.querySelectorAll('.bg-light.rounded.px-3.py-2');
        teamMembersElements.forEach(elem => {
            elem.classList.add('team-members-display');
        });
        
        // Add class to search container
        const searchForm = document.querySelector('.d-flex');
        if (searchForm) {
            searchForm.classList.add('search-container');
        }
        
        // Add class to header section
        const headerRow = document.querySelector('.row.mb-4.align-items-center');
        if (headerRow) {
            headerRow.classList.add('teams-header');
        }
    });
</script> 