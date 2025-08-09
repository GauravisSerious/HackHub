<?php
// Redirect to the fixed version
$team_id = isset($_GET['id']) ? $_GET['id'] : '';
header("Location: " . dirname($_SERVER['PHP_SELF']) . "/edit-team-fixed.php?id=" . $team_id);
exit();
?> 