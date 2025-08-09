<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Get the user's ID
$user_id = $_SESSION['user_id'];

// Get hackathon settings for deadlines
$settingsQuery = $conn->query("SELECT * FROM hackathon_settings LIMIT 1");
$hackathonSettings = null;
$submissionOpen = true;

if ($settingsQuery && $settingsQuery->num_rows > 0) {
    $hackathonSettings = $settingsQuery->fetch_assoc();
    $submissionDeadline = strtotime($hackathonSettings['submission_deadline']);
    $currentTime = time();
    $submissionOpen = ($currentTime < $submissionDeadline);
}

// Get user teams
$teamQuery = "SELECT t.team_id, t.team_name 
              FROM teams t 
              JOIN team_members tm ON t.team_id = tm.team_id 
              WHERE tm.user_id = ?";
$teamStmt = $conn->prepare($teamQuery);
$teamStmt->bind_param("i", $user_id);
$teamStmt->execute();
$teamResult = $teamStmt->get_result();
$userTeams = [];

while ($team = $teamResult->fetch_assoc()) {
    $userTeams[] = $team;
}
$teamStmt->close();

// Get projects (based on user role)
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
$isJudge = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'judge';

if ($isAdmin) {
    // Admins can see all projects
    $projectQuery = "SELECT p.*, t.team_name FROM projects p 
                    JOIN teams t ON p.team_id = t.team_id 
                    ORDER BY p.status ASC, p.updated_at DESC";
    $projectStmt = $conn->prepare($projectQuery);
    $projectStmt->execute();
} else if ($isJudge) {
    // Judges can see submitted and approved projects
    $projectQuery = "SELECT p.*, t.team_name FROM projects p 
                    JOIN teams t ON p.team_id = t.team_id 
                    WHERE p.status IN ('submitted', 'under_review', 'approved')
                    ORDER BY p.status ASC, p.updated_at DESC";
    $projectStmt = $conn->prepare($projectQuery);
    $projectStmt->execute();
} else {
    // Regular users can see their team's projects and all approved projects
    $projectQuery = "SELECT p.*, t.team_name FROM projects p 
                    JOIN teams t ON p.team_id = t.team_id 
                    LEFT JOIN team_members tm ON t.team_id = tm.team_id AND tm.user_id = ?
                    WHERE tm.user_id IS NOT NULL OR p.status = 'approved'
                    ORDER BY p.status ASC, p.updated_at DESC";
    $projectStmt = $conn->prepare($projectQuery);
    $projectStmt->bind_param("i", $user_id);
    $projectStmt->execute();
}

$projectResult = $projectStmt->get_result();
$projects = [];

while ($project = $projectResult->fetch_assoc()) {
    $projects[] = $project;
}
$projectStmt->close();

// Set page title
$title = "Projects";
include '../includes/header.php';
?>

<style>
    /* Using the same styling as teams container */
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
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Projects</h1>
        
        <!-- Only show create button to users who are in a team and if submissions are open -->
        <?php if (!empty($userTeams) && $submissionOpen): ?>
            <a href="<?php echo BASE_URL; ?>/pages/create-project.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Create New Project
            </a>
        <?php elseif (empty($userTeams) && $submissionOpen && !$isAdmin): ?>
            <a href="<?php echo BASE_URL; ?>/pages/teams.php" class="btn btn-outline-primary">
                <i class="fas fa-users"></i> Join a Team First
            </a>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/messages.php'; ?>
    
    <?php if (!$submissionOpen): ?>
        <div class="alert alert-warning">
            <i class="fas fa-clock"></i> <strong>Project submissions are now closed.</strong>
            <?php if ($hackathonSettings): ?>
                The submission deadline was <?php echo date('F j, Y, g:i a', $submissionDeadline); ?>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($projects)): ?>
        <div class="alert alert-info">
            <p class="mb-0">No projects found. 
                <?php if ($submissionOpen): ?>
                    <?php if (empty($userTeams)): ?>
                        Join or create a team to start working on a project!
                    <?php else: ?>
                        Start creating a project for your team!
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Project filters (optional) -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="projectSearch" placeholder="Search projects...">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="under_review">Under Review</option>
                            <option value="approved">Approved</option>
                            <?php if ($isAdmin): ?>
                                <option value="rejected">Rejected</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="resetFilters">
                            Reset Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Projects grid -->
        <div class="p-4 rounded mb-4 teams-container">
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="projectsList">
                <?php foreach ($projects as $project): ?>
                    <div class="col project-card" data-status="<?php echo $project['status']; ?>">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <?php
                                $statusClass = '';
                                $statusIcon = '';
                                switch ($project['status']) {
                                    case 'draft':
                                        $statusClass = 'bg-secondary';
                                        $statusIcon = 'fa-pencil';
                                        break;
                                    case 'submitted':
                                        $statusClass = 'bg-primary';
                                        $statusIcon = 'fa-paper-plane';
                                        break;
                                    case 'under_review':
                                        $statusClass = 'bg-info';
                                        $statusIcon = 'fa-magnifying-glass';
                                        break;
                                    case 'approved':
                                        $statusClass = 'bg-success';
                                        $statusIcon = 'fa-check';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'bg-danger';
                                        $statusIcon = 'fa-times';
                                        break;
                                }
                                ?>
                                <div class="badge <?php echo $statusClass; ?> text-white position-absolute top-0 end-0 m-2">
                                    <i class="fas <?php echo $statusIcon; ?>"></i> 
                                    <?php echo ucfirst($project['status']); ?>
                                </div>
                                
                                <h5 class="card-title"><?php echo htmlspecialchars($project['title']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <i class="fas fa-users"></i> <?php echo htmlspecialchars($project['team_name']); ?>
                                </h6>
                                <p class="card-text text-truncate">
                                    <?php echo htmlspecialchars($project['description']); ?>
                                </p>
                                
                                <?php if (!empty($project['tech_stack'])): ?>
                                    <div class="mb-2">
                                        <?php foreach(explode(',', $project['tech_stack']) as $tech): ?>
                                            <span class="badge bg-light text-dark me-1"><?php echo trim($tech); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php if ($project['submission_date']): ?>
                                            Submitted: <span class="time-ago" data-timestamp="<?php echo strtotime($project['submission_date']); ?>"><?php echo time_ago($project['submission_date']); ?></span>
                                        <?php else: ?>
                                            Created: <span class="time-ago" data-timestamp="<?php echo strtotime($project['created_at']); ?>"><?php echo time_ago($project['created_at']); ?></span>
                                        <?php endif; ?>
                                    </small>
                                    <a href="<?php echo BASE_URL; ?>/pages/project-details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const projectsList = document.getElementById('projectsList');
                const projectSearch = document.getElementById('projectSearch');
                const statusFilter = document.getElementById('statusFilter');
                const resetFilters = document.getElementById('resetFilters');
                const projectCards = document.querySelectorAll('.project-card');
                
                function filterProjects() {
                    const searchTerm = projectSearch.value.toLowerCase();
                    const statusTerm = statusFilter.value.toLowerCase();
                    
                    projectCards.forEach(card => {
                        const projectTitle = card.querySelector('.card-title').textContent.toLowerCase();
                        const projectDesc = card.querySelector('.card-text').textContent.toLowerCase();
                        const teamName = card.querySelector('.card-subtitle').textContent.toLowerCase();
                        const projectStatus = card.dataset.status;
                        
                        const matchesSearch = projectTitle.includes(searchTerm) || 
                                           projectDesc.includes(searchTerm) || 
                                           teamName.includes(searchTerm);
                        
                        const matchesStatus = statusTerm === '' || projectStatus === statusTerm;
                        
                        if (matchesSearch && matchesStatus) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }
                
                projectSearch.addEventListener('input', filterProjects);
                statusFilter.addEventListener('change', filterProjects);
                
                resetFilters.addEventListener('click', function() {
                    projectSearch.value = '';
                    statusFilter.value = '';
                    filterProjects();
                });
            });
        </script>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?> 