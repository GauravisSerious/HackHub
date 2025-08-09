<?php
// Only show actions for admin or judge users
if (isAdmin() || isJudge()): ?>
    <div class="project-actions mt-3">
        <?php if ($project['status'] == 'submitted'): ?>
            <form action="<?php echo BASE_URL; ?>/includes/process_project.php" method="POST" class="d-inline">
                <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Approve Project
                </button>
            </form>

            <!-- The Reject button with unique data attribute - no onclick handler -->
            <a href="#" class="btn btn-danger reject-project-btn" data-project-id="<?php echo $project['project_id']; ?>">
                <i class="fas fa-times"></i> Reject Project
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?> 