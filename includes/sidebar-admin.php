<?php
// Side menu for the admin pages. The page sets $active to the current page key.
$active = $active ?? '';
function aclass($key, $active) { return $key === $active ? 'active' : ''; }

// number shown next to "Reports & complaints" so new ones are easy to spot
$openComplaints = (int) getDB()->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();
?>
<aside class="dash-sidebar">
  <div class="side-label">Overview</div>
  <a href="dashboard.php" class="<?= aclass('dashboard', $active) ?>">Dashboard</a>

  <div class="side-label">Manage</div>
  <a href="manage-seekers.php" class="<?= aclass('seekers', $active) ?>">Job seekers</a>
  <a href="manage-employers.php" class="<?= aclass('employers', $active) ?>">Employers</a>
  <a href="manage-jobs.php" class="<?= aclass('jobs', $active) ?>">Jobs</a>
  <a href="manage-categories.php" class="<?= aclass('categories', $active) ?>">Categories</a>
  <a href="applications.php" class="<?= aclass('applications', $active) ?>">Applications</a>
  <a href="reports.php" class="<?= aclass('reports', $active) ?>">Reports &amp; complaints<?= $openComplaints ? ' <span class="pill pill-pending">' . $openComplaints . '</span>' : '' ?></a>

  <div class="side-label">Account</div>
  <a href="../logout.php">Log out</a>
</aside>
