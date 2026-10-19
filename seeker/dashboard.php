<?php
// Seeker: home page with your numbers, profile progress, recent applications and job ideas.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$seekerId = current_seeker_id();

// the numbers for the cards at the top
$appCountStmt = $db->prepare('SELECT COUNT(*) FROM applications WHERE seeker_id = ?');
$appCountStmt->execute([$seekerId]);
$totalApplications = (int) $appCountStmt->fetchColumn();

$savedCountStmt = $db->prepare('SELECT COUNT(*) FROM saved_jobs WHERE seeker_id = ?');
$savedCountStmt->execute([$seekerId]);
$totalSaved = (int) $savedCountStmt->fetchColumn();

$shortlistedStmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE seeker_id = ? AND status IN ('Shortlisted','Interview')");
$shortlistedStmt->execute([$seekerId]);
$totalShortlisted = (int) $shortlistedStmt->fetchColumn();

// my profile row, used for the completeness check below
$profileStmt = $db->prepare('SELECT * FROM job_seekers WHERE seeker_id = ?');
$profileStmt->execute([$seekerId]);
$profile = $profileStmt->fetch();

// roughly how complete the profile is, shown as a percentage on the dashboard
$checks = [
    'Personal summary' => !empty($profile['professional_summary']),
    'Location set'      => !empty($profile['location']),
];
$eduCount = $db->prepare('SELECT COUNT(*) FROM education WHERE seeker_id = ?'); $eduCount->execute([$seekerId]);
$checks['Education added'] = (int) $eduCount->fetchColumn() > 0;
$expCount = $db->prepare('SELECT COUNT(*) FROM work_experience WHERE seeker_id = ?'); $expCount->execute([$seekerId]);
$checks['Work experience added'] = (int) $expCount->fetchColumn() > 0;
$skillCount = $db->prepare('SELECT COUNT(*) FROM job_seeker_skills WHERE seeker_id = ?'); $skillCount->execute([$seekerId]);
$checks['Skills added'] = (int) $skillCount->fetchColumn() > 0;
$resumeCount = $db->prepare('SELECT COUNT(*) FROM resumes WHERE seeker_id = ?'); $resumeCount->execute([$seekerId]);
$checks['CV uploaded'] = (int) $resumeCount->fetchColumn() > 0;

// every item counts the same
$completed = count(array_filter($checks));
$completionPct = round(($completed / count($checks)) * 100);

// the last 5 applications for the table
$recentApps = $db->prepare("
    SELECT a.*, j.title, e.company_name
    FROM applications a
    JOIN jobs j ON j.job_id = a.job_id
    JOIN employers e ON e.employer_id = j.employer_id
    WHERE a.seeker_id = ?
    ORDER BY a.applied_at DESC LIMIT 5
");
$recentApps->execute([$seekerId]);
$recentApps = $recentApps->fetchAll();

// newest open jobs that I have not applied for yet
$recommendedStmt = $db->prepare("
    SELECT j.job_id, j.title, j.location, e.company_name
    FROM jobs j JOIN employers e ON e.employer_id = j.employer_id
    WHERE j.status = 'active'
    AND j.job_id NOT IN (SELECT job_id FROM applications WHERE seeker_id = ?)
    ORDER BY j.created_at DESC LIMIT 4
");
$recommendedStmt->execute([$seekerId]);
$recommended = $recommendedStmt->fetchAll();

$pageTitle = 'My dashboard — KaziLink';
$base = '../';
$active = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Welcome back, <?= e(explode(' ', current_name())[0]) ?></h1>
        <p>Here's what's happening with your job search.</p>
      </div>
      <a href="../jobs.php" class="btn btn-primary">Find jobs</a>
    </div>

    <div class="stat-cards">
      <div class="stat-card"><div class="num"><?= $totalApplications ?></div><div class="label">Applications sent</div></div>
      <div class="stat-card"><div class="num"><?= $totalShortlisted ?></div><div class="label">Shortlisted / interview</div></div>
      <div class="stat-card"><div class="num"><?= $totalSaved ?></div><div class="label">Saved jobs</div></div>
      <div class="stat-card"><div class="num"><?= $completionPct ?>%</div><div class="label">Profile complete</div></div>
    </div>

    <!-- the reminder goes away once the profile is complete -->
    <?php if ($completionPct < 100): ?>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
          <h3 style="margin-top:0; font-size:16px;">Finish setting up your profile</h3>
          <p class="text-muted text-sm">Employers are more likely to shortlist complete profiles.</p>
          <!-- only the missing items are listed, each linking to the page that fixes it -->
          <ul style="margin:0; padding-left:20px; font-size:14px;">
            <?php foreach ($checks as $label => $done): if (!$done): ?>
              <li><?= e($label) ?> — <a href="<?= $label === 'CV uploaded' ? 'resume.php' : (str_contains($label, 'Education') ? 'education.php' : (str_contains($label, 'experience') ? 'experience.php' : (str_contains($label, 'Skills') ? 'skills.php' : 'profile.php'))) ?>">complete now</a></li>
            <?php endif; endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h3>Recent applications</h3>
        <a href="applications.php" class="btn btn-outline btn-sm">View all</a>
      </div>
      <div class="card-body table-scroll">
        <?php if ($recentApps): ?>
          <table class="data-table">
            <thead><tr><th>Job</th><th>Company</th><th>Applied</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentApps as $app): ?>
              <tr>
                <td><a href="../job-details.php?id=<?= (int) $app['job_id'] ?>" class="row-title"><?= e($app['title']) ?></a></td>
                <td><?= e($app['company_name']) ?></td>
                <td><?= time_ago($app['applied_at']) ?></td>
                <td><span class="pill <?= status_pill_class($app['status']) ?>"><?= e($app['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><h3>No applications yet</h3><p>Once you apply for jobs, they'll show up here.</p><a href="../jobs.php" class="btn btn-outline">Browse jobs</a></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- suggestions, hidden if there is nothing to suggest -->
    <?php if ($recommended): ?>
      <div class="card">
        <div class="card-header"><h3>Jobs you might like</h3></div>
        <div class="card-body">
          <div class="job-list" style="border-top:none;">
            <?php foreach ($recommended as $job): ?>
              <a class="job-row" href="../job-details.php?id=<?= (int) $job['job_id'] ?>">
                <div><div class="job-row-title"><?= e($job['title']) ?></div><div class="job-row-company"><?= e($job['company_name']) ?></div></div>
                <div class="job-row-meta"><span>📍 <?= e($job['location'] ?: 'Remote') ?></span></div>
                <div></div>
                <div></div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
