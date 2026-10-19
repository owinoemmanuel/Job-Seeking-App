<?php
// Side menu for the job seeker pages. The page sets $active to the current page key.
$active = $active ?? '';
$unread = unread_notifications_count(current_user_id());
function sclass($key, $active) { return $key === $active ? 'active' : ''; }
?>
<aside class="dash-sidebar">
  <div class="side-label">Job seeker</div>
  <a href="dashboard.php" class="<?= sclass('dashboard', $active) ?>">Dashboard</a>
  <a href="../jobs.php">Find jobs</a>
  <a href="applications.php" class="<?= sclass('applications', $active) ?>">My applications</a>
  <a href="saved-jobs.php" class="<?= sclass('saved', $active) ?>">Saved jobs</a>

  <div class="side-label">Profile</div>
  <a href="profile.php" class="<?= sclass('profile', $active) ?>">Personal details</a>
  <a href="education.php" class="<?= sclass('education', $active) ?>">Education</a>
  <a href="experience.php" class="<?= sclass('experience', $active) ?>">Work experience</a>
  <a href="skills.php" class="<?= sclass('skills', $active) ?>">Skills</a>
  <a href="resume.php" class="<?= sclass('resume', $active) ?>">CV / Resume</a>

  <div class="side-label">Account</div>
  <a href="notifications.php" class="<?= sclass('notifications', $active) ?>">Notifications<?= $unread ? ' <span class="pill pill-pending">' . $unread . '</span>' : '' ?></a>
  <a href="complaints.php" class="<?= sclass('complaints', $active) ?>">Complaints</a>
  <a href="../logout.php">Log out</a>
</aside>
