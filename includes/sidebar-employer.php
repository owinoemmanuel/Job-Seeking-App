<?php
// Side menu for the employer pages. The page sets $active to the current page key.
$active = $active ?? '';
$unread = unread_notifications_count(current_user_id());
function eclass($key, $active) { return $key === $active ? 'active' : ''; }
?>
<aside class="dash-sidebar">
  <div class="side-label">Employer</div>
  <a href="dashboard.php" class="<?= eclass('dashboard', $active) ?>">Dashboard</a>
  <a href="post-job.php" class="<?= eclass('post-job', $active) ?>">Post a job</a>
  <a href="my-jobs.php" class="<?= eclass('my-jobs', $active) ?>">My job postings</a>
  <a href="applicants.php" class="<?= eclass('applicants', $active) ?>">Applicants</a>

  <div class="side-label">Account</div>
  <a href="profile.php" class="<?= eclass('profile', $active) ?>">Company profile</a>
  <a href="notifications.php" class="<?= eclass('notifications', $active) ?>">Notifications<?= $unread ? ' <span class="pill pill-pending">' . $unread . '</span>' : '' ?></a>
  <a href="../logout.php">Log out</a>
</aside>
