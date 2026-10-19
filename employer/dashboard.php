<?php
// Employer: quick numbers for my jobs, the latest applicants and my newest postings.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('employer');

$db = getDB();
$employerId = current_employer_id();

// counts for the stat cards, all for this employer only
$activeJobsStmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'active'");
$activeJobsStmt->execute([$employerId]);
$activeJobs = (int) $activeJobsStmt->fetchColumn();

$pendingJobsStmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'pending'");
$pendingJobsStmt->execute([$employerId]);
$pendingJobs = (int) $pendingJobsStmt->fetchColumn();

$totalApplicantsStmt = $db->prepare("
    SELECT COUNT(*) FROM applications a JOIN jobs j ON j.job_id = a.job_id WHERE j.employer_id = ?
");
$totalApplicantsStmt->execute([$employerId]);
$totalApplicants = (int) $totalApplicantsStmt->fetchColumn();

// still on 'Applied' means I have not looked at them yet
$newApplicantsStmt = $db->prepare("
    SELECT COUNT(*) FROM applications a JOIN jobs j ON j.job_id = a.job_id
    WHERE j.employer_id = ? AND a.status = 'Applied'
");
$newApplicantsStmt->execute([$employerId]);
$newApplicants = (int) $newApplicantsStmt->fetchColumn();

// the 6 newest applicants across all my jobs
$recentAppsStmt = $db->prepare("
    SELECT a.*, j.title, js.seeker_id, u.full_name
    FROM applications a
    JOIN jobs j ON j.job_id = a.job_id
    JOIN job_seekers js ON js.seeker_id = a.seeker_id
    JOIN users u ON u.user_id = js.user_id
    WHERE j.employer_id = ?
    ORDER BY a.applied_at DESC LIMIT 6
");
$recentAppsStmt->execute([$employerId]);
$recentApps = $recentAppsStmt->fetchAll();

// my 5 newest job posts
$myJobsStmt = $db->prepare('SELECT job_id, title, status, created_at FROM jobs WHERE employer_id = ? ORDER BY created_at DESC LIMIT 5');
$myJobsStmt->execute([$employerId]);
$myJobs = $myJobsStmt->fetchAll();

$pageTitle = 'Employer dashboard — KaziLink';
$base = '../';
$active = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-employer.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div><h1>Welcome back, <?= e(explode(' ', current_name())[0]) ?></h1><p>Here's how your job postings are performing.</p></div>
      <a href="post-job.php" class="btn btn-primary">Post a job</a>
    </div>

    <!-- stat cards -->
    <div class="stat-cards">
      <div class="stat-card"><div class="num"><?= $activeJobs ?></div><div class="label">Active job postings</div></div>
      <div class="stat-card"><div class="num"><?= $pendingJobs ?></div><div class="label">Awaiting approval</div></div>
      <div class="stat-card"><div class="num"><?= $totalApplicants ?></div><div class="label">Total applicants</div></div>
      <div class="stat-card"><div class="num"><?= $newApplicants ?></div><div class="label">New (unreviewed)</div></div>
    </div>

    <!-- recent applicants -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h3>Recent applicants</h3><a href="applicants.php" class="btn btn-outline btn-sm">View all</a></div>
      <div class="card-body table-scroll">
        <?php if ($recentApps): ?>
          <table class="data-table">
            <thead><tr><th>Candidate</th><th>Job</th><th>Applied</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recentApps as $app): ?>
              <tr>
                <td class="row-title"><?= e($app['full_name']) ?></td>
                <td><?= e($app['title']) ?></td>
                <td><?= time_ago($app['applied_at']) ?></td>
                <td><span class="pill <?= status_pill_class($app['status']) ?>"><?= e($app['status']) ?></span></td>
                <td><a href="applicants.php?job_id=<?= (int) $app['job_id'] ?>" class="btn btn-outline btn-sm">Review</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><h3>No applicants yet</h3><p>Once candidates apply to your jobs, they'll appear here.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- my latest jobs -->
    <div class="card">
      <div class="card-header"><h3>Your job postings</h3><a href="my-jobs.php" class="btn btn-outline btn-sm">Manage all</a></div>
      <div class="card-body table-scroll">
        <?php if ($myJobs): ?>
          <table class="data-table">
            <thead><tr><th>Title</th><th>Status</th><th>Posted</th></tr></thead>
            <tbody>
            <?php foreach ($myJobs as $job): ?>
              <tr>
                <td class="row-title"><a href="../job-details.php?id=<?= (int) $job['job_id'] ?>"><?= e($job['title']) ?></a></td>
                <td><span class="pill <?= status_pill_class($job['status']) ?>"><?= e(ucfirst($job['status'])) ?></span></td>
                <td><?= time_ago($job['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><h3>You haven't posted a job yet</h3><a href="post-job.php" class="btn btn-primary">Post your first job</a></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
