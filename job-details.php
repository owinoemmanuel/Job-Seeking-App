<?php
// Public page: one job in full, with apply and save buttons for job seekers.
require_once __DIR__ . '/includes/bootstrap.php';

// cast to int so a missing or bad id just becomes 0 and finds nothing
$jobId = (int) ($_GET['id'] ?? 0);
$db = getDB();

// the job plus its company and category
$stmt = $db->prepare("
    SELECT j.*, e.company_name, e.location AS company_location, e.description AS company_description,
           e.website, e.logo, c.category_name
    FROM jobs j
    JOIN employers e ON e.employer_id = j.employer_id
    LEFT JOIN job_categories c ON c.category_id = j.category_id
    WHERE j.job_id = ?
");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

// the public only sees active jobs. Admins can open any job.
if (!$job || ($job['status'] !== 'active' && current_role() !== 'admin')) {
    // an employer can still preview their own job when it isn't active
    $ownsIt = current_role() === 'employer' && $job && (int) $job['employer_id'] === current_employer_id();
    if (!$ownsIt) {
        // missing or not allowed: show the same not found page for both
        http_response_code(404);
        $pageTitle = 'Job not found — KaziLink';
        $base = '';
        include __DIR__ . '/includes/header.php';
        echo '<div class="section container"><div class="empty-state"><h3>This job posting could not be found</h3><p>It may have been closed or removed.</p><a href="jobs.php" class="btn btn-outline">Browse other jobs</a></div></div>';
        include __DIR__ . '/includes/footer.php';
        exit;
    }
}

// only job seekers get the apply and save buttons, so look up what they did already
$isSeeker = current_role() === 'job_seeker';
$alreadyApplied = false;
$isSaved = false;

if ($isSeeker) {
    $seekerId = current_seeker_id();
    $chk = $db->prepare('SELECT application_id FROM applications WHERE job_id = ? AND seeker_id = ?');
    $chk->execute([$jobId, $seekerId]);
    $alreadyApplied = (bool) $chk->fetch();

    $chk2 = $db->prepare('SELECT saved_id FROM saved_jobs WHERE job_id = ? AND seeker_id = ?');
    $chk2->execute([$jobId, $seekerId]);
    $isSaved = (bool) $chk2->fetch();

    // the save button posts back here. Redirect afterwards so a refresh doesn't repeat it
    if (isset($_POST['toggle_save'])) {
        require_csrf();
        if ($isSaved) {
            $del = $db->prepare('DELETE FROM saved_jobs WHERE job_id = ? AND seeker_id = ?');
            $del->execute([$jobId, $seekerId]);
            flash('info', 'Removed from saved jobs.');
        } else {
            $ins = $db->prepare('INSERT INTO saved_jobs (seeker_id, job_id) VALUES (?, ?)');
            $ins->execute([$seekerId, $jobId]);
            flash('success', 'Job saved to your list.');
        }
        redirect('job-details.php?id=' . $jobId);
    }
}

// up to 4 other active jobs in the same category (<=> also matches when both have no category)
$similarStmt = $db->prepare("
    SELECT j.job_id, j.title, e.company_name, j.location
    FROM jobs j JOIN employers e ON e.employer_id = j.employer_id
    WHERE j.status = 'active' AND j.category_id <=> ? AND j.job_id != ?
    ORDER BY j.created_at DESC LIMIT 4
");
$similarStmt->execute([$job['category_id'], $jobId]);
$similarJobs = $similarStmt->fetchAll();

$pageTitle = e($job['title']) . ' at ' . e($job['company_name']) . ' — KaziLink';
$base = '';
include __DIR__ . '/includes/header.php';
?>

<!-- top banner with the main facts about the job -->
<div class="job-detail-header">
  <div class="container">
    <div class="breadcrumb" style="color:rgba(255,255,255,0.6);"><a href="jobs.php" style="color:rgba(255,255,255,0.75);">&larr; All jobs</a></div>
    <!-- shows when someone looks at a job that is not live (the owner or an admin) -->
    <?php if ($job['status'] !== 'active'): ?>
      <span class="pill <?= status_pill_class($job['status']) ?>" style="margin-bottom:10px; display:inline-block;">Status: <?= e(ucfirst($job['status'])) ?> (preview)</span>
    <?php endif; ?>
    <h1><?= e($job['title']) ?></h1>
    <div class="job-detail-meta">
      <span><?= e($job['company_name']) ?></span>
      <span>📍 <?= e($job['location'] ?: 'Remote') ?></span>
      <span><?= e($job['job_type'] ?: 'Full-time') ?></span>
      <span><?= e($job['experience_level'] ?: 'Any level') ?> level</span>
      <span><?= format_money($job['salary'] !== null ? (float) $job['salary'] : null) ?></span>
    </div>
  </div>
</div>

<!-- left column is the description, right column is the company and the buttons -->
<div class="section">
  <div class="container" style="display:grid; grid-template-columns: 1fr 320px; gap:32px; align-items:start;">
    <div>
      <div class="card">
        <div class="card-body">
          <h3 style="margin-top:0;">Job description</h3>
          <p style="white-space:pre-line;"><?= e($job['description']) ?></p>

          <?php if ($job['requirements']): ?>
            <h3>Requirements</h3>
            <p style="white-space:pre-line;"><?= e($job['requirements']) ?></p>
          <?php endif; ?>

          <div class="divider"></div>
          <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; font-size:14px;" class="text-muted">
            <span>Category: <?= e($job['category_name'] ?? 'General') ?></span>
            <span>Posted <?= time_ago($job['created_at']) ?></span>
            <?php if ($job['deadline']): ?><span>Apply before <?= date('d M Y', strtotime($job['deadline'])) ?></span><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
          <h3 style="margin-top:0; font-size:16px;">About <?= e($job['company_name']) ?></h3>
          <p class="text-muted text-sm"><?= e($job['company_description'] ?: 'No company description provided yet.') ?></p>
          <?php if (!empty($job['website'])): ?>
            <a href="<?= e($job['website']) ?>" target="_blank" rel="noopener" class="text-sm">Visit website &rarr;</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-body" style="display:flex; flex-direction:column; gap:10px;">
          <!-- what shows here depends on who is looking: visitor, job seeker or someone else -->
          <?php if (!is_logged_in()): ?>
            <a href="login.php?redirect=<?= urlencode('job-details.php?id=' . $job['job_id']) ?>" class="btn btn-primary btn-block">Log in to apply</a>
            <a href="register.php?role=job_seeker&redirect=<?= urlencode('job-details.php?id=' . $job['job_id']) ?>" class="btn btn-outline btn-block">Create a free account</a>
          <?php elseif ($isSeeker): ?>
            <?php if ($alreadyApplied): ?>
              <button class="btn btn-block" disabled>Already applied</button>
              <a href="seeker/applications.php" class="btn btn-outline btn-block">View my application</a>
            <?php elseif ($job['status'] === 'active'): ?>
              <a href="seeker/apply.php?job_id=<?= (int) $job['job_id'] ?>" class="btn btn-primary btn-block">Apply for this job</a>
            <?php else: ?>
              <button class="btn btn-block" disabled>Not currently open</button>
            <?php endif; ?>
            <form method="post">
              <?= csrf_field() ?>
              <button type="submit" name="toggle_save" class="btn btn-outline btn-block"><?= $isSaved ? 'Remove from saved' : 'Save this job' ?></button>
            </form>
          <?php else: ?>
            <p class="text-muted text-sm" style="margin:0;">Only job seekers can apply for jobs.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- job seekers can report a listing that looks wrong -->
      <?php if ($isSeeker): ?>
        <p style="margin-top:14px;">
          <a href="seeker/complaints.php?job_id=<?= (int) $job['job_id'] ?>" class="text-muted text-sm">Report a problem with this listing</a>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- more jobs from the same category -->
  <?php if ($similarJobs): ?>
    <div class="container" style="margin-top:48px;">
      <div class="section-head"><h2 style="font-size:22px;">Similar jobs</h2></div>
      <div class="job-list">
        <?php foreach ($similarJobs as $sj): ?>
          <a class="job-row" href="job-details.php?id=<?= (int) $sj['job_id'] ?>">
            <div>
              <div class="job-row-title"><?= e($sj['title']) ?></div>
              <div class="job-row-company"><?= e($sj['company_name']) ?></div>
            </div>
            <div class="job-row-meta"><span>📍 <?= e($sj['location'] ?: 'Remote') ?></span></div>
            <div></div>
            <div></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
