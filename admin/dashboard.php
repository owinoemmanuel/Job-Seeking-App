<?php
// Admin: overview of the site with counts, jobs waiting for approval and the newest accounts.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('admin');

$db = getDB();

// numbers for the stat cards
$totalSeekers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'job_seeker'")->fetchColumn();
$totalEmployers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'employer'")->fetchColumn();
$totalJobs = (int) $db->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
$pendingJobs = (int) $db->query("SELECT COUNT(*) FROM jobs WHERE status = 'pending'")->fetchColumn();
$activeJobs = (int) $db->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn();
$totalApplications = (int) $db->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$suspendedUsers = (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();
$openReports = (int) $db->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();

// oldest first, so jobs that have waited longest get reviewed first
$pendingJobsList = $db->query("
    SELECT j.job_id, j.title, j.created_at, e.company_name
    FROM jobs j JOIN employers e ON e.employer_id = j.employer_id
    WHERE j.status = 'pending' ORDER BY j.created_at ASC LIMIT 6
")->fetchAll();

$recentUsers = $db->query('SELECT user_id, full_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 6')->fetchAll();

// header/sidebar settings
$pageTitle = 'Admin dashboard — KaziLink';
$base = '../';
$active = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>System overview</h1><p>A snapshot of everything happening on KaziLink.</p></div></div>

    <!-- stat cards, two rows -->
    <div class="stat-cards">
      <div class="stat-card"><div class="num"><?= number_format($totalSeekers) ?></div><div class="label">Job seekers</div></div>
      <div class="stat-card"><div class="num"><?= number_format($totalEmployers) ?></div><div class="label">Employers</div></div>
      <div class="stat-card"><div class="num"><?= number_format($totalJobs) ?></div><div class="label">Total jobs posted</div></div>
      <div class="stat-card"><div class="num"><?= number_format($totalApplications) ?></div><div class="label">Total applications</div></div>
    </div>
    <div class="stat-cards">
      <div class="stat-card"><div class="num"><?= number_format($activeJobs) ?></div><div class="label">Active listings</div></div>
      <div class="stat-card"><div class="num"><?= number_format($pendingJobs) ?></div><div class="label">Awaiting approval</div></div>
      <div class="stat-card"><div class="num"><?= number_format($suspendedUsers) ?></div><div class="label">Suspended accounts</div></div>
      <div class="stat-card"><div class="num"><?= number_format($openReports) ?></div><div class="label">Open reports</div></div>
    </div>

    <!-- pending jobs, the admin approves them on the jobs page -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h3>Jobs awaiting approval</h3><a href="manage-jobs.php?status=pending" class="btn btn-outline btn-sm">Review all</a></div>
      <div class="card-body table-scroll">
        <?php if ($pendingJobsList): ?>
          <table class="data-table">
            <thead><tr><th>Title</th><th>Employer</th><th>Submitted</th></tr></thead>
            <tbody>
            <?php foreach ($pendingJobsList as $job): ?>
              <tr>
                <td class="row-title"><?= e($job['title']) ?></td>
                <td><?= e($job['company_name']) ?></td>
                <td><?= time_ago($job['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><h3>Nothing waiting for review</h3></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- latest sign-ups -->
    <div class="card">
      <div class="card-header"><h3>Newest accounts</h3></div>
      <div class="card-body table-scroll">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
          <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td class="row-title"><?= e($u['full_name']) ?></td>
              <td><?= e($u['email']) ?></td>
              <td><span class="tag"><?= e(str_replace('_', ' ', ucfirst($u['role']))) ?></span></td>
              <td><?= time_ago($u['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
