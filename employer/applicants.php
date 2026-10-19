<?php
// Employer: look at the people who applied to a job and change their status.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('employer');

$db = getDB();
$employerId = current_employer_id();

// my own jobs, for the dropdown
$jobsStmt = $db->prepare('SELECT job_id, title FROM jobs WHERE employer_id = ? ORDER BY created_at DESC');
$jobsStmt->execute([$employerId]);
$myJobs = $jobsStmt->fetchAll();

// filters from the URL
$jobId = (int) ($_GET['job_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

// status dropdown on a card was changed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $appId = (int) ($_POST['application_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $validStatuses = ['Applied', 'Under Review', 'Shortlisted', 'Interview', 'Accepted', 'Rejected'];

    if (in_array($newStatus, $validStatuses, true)) {
        // an employer may only change applications for their own jobs
        $check = $db->prepare("
            SELECT a.application_id, a.status AS old_status, j.title, js.user_id
            FROM applications a JOIN jobs j ON j.job_id = a.job_id JOIN job_seekers js ON js.seeker_id = a.seeker_id
            WHERE a.application_id = ? AND j.employer_id = ?
        ");
        $check->execute([$appId, $employerId]);
        $row = $check->fetch();
        if ($row) {
            $db->prepare('UPDATE applications SET status = ? WHERE application_id = ?')->execute([$newStatus, $appId]);
            // the job seeker gets told about the new status
            notify($row['user_id'], 'Application update', 'Your application for "' . $row['title'] . '" is now: ' . $newStatus . '.');
            flash('success', 'Application status updated.');
        }
    }
    // back to the same filtered list
    redirect('applicants.php?' . http_build_query(array_filter(['job_id' => $jobId ?: null, 'status' => $statusFilter ?: null])));
}

// always limited to my jobs, the filters only narrow it down more
$where = ['j.employer_id = ?'];
$params = [$employerId];
if ($jobId) { $where[] = 'a.job_id = ?'; $params[] = $jobId; }
if ($statusFilter) { $where[] = 'a.status = ?'; $params[] = $statusFilter; }
$whereSql = implode(' AND ', $where);

// resumes is a LEFT JOIN because some people apply without a CV
$stmt = $db->prepare("
    SELECT a.*, j.title AS job_title, js.seeker_id, js.location, u.full_name, u.email, r.file_name, r.file_path
    FROM applications a
    JOIN jobs j ON j.job_id = a.job_id
    JOIN job_seekers js ON js.seeker_id = a.seeker_id
    JOIN users u ON u.user_id = js.user_id
    LEFT JOIN resumes r ON r.resume_id = a.resume_id
    WHERE $whereSql
    ORDER BY a.applied_at DESC
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

// the header and sidebar read these
$pageTitle = 'Applicants — KaziLink';
$base = '../';
$active = 'applicants';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-employer.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Applicants</h1><p>Review CVs and move candidates through your hiring pipeline.</p></div></div>

    <!-- filters, they submit by themselves when changed -->
    <form class="filter-bar" method="get" data-autosubmit style="grid-template-columns: 2fr 1fr;">
      <div class="form-group" style="margin:0;">
        <label>Job posting</label>
        <select name="job_id">
          <option value="">All my jobs</option>
          <?php foreach ($myJobs as $job): ?>
            <option value="<?= (int) $job['job_id'] ?>" <?= $jobId === (int) $job['job_id'] ? 'selected' : '' ?>><?= e($job['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;">
        <label>Status</label>
        <select name="status">
          <option value="">Any status</option>
          <?php foreach (['Applied','Under Review','Shortlisted','Interview','Accepted','Rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <!-- one card per applicant -->
    <?php if ($applications): foreach ($applications as $app): ?>
      <div class="card">
        <div class="card-body">
          <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap;">
            <div style="display:flex; gap:14px;">
              <img class="avatar" src="https://api.dicebear.com/7.x/initials/svg?seed=<?= urlencode($app['full_name']) ?>" alt="">
              <div>
                <h4 style="margin:0 0 3px; font-size:16px;"><?= e($app['full_name']) ?></h4>
                <div class="text-muted text-sm"><?= e($app['email']) ?><?= $app['location'] ? ' · ' . e($app['location']) : '' ?></div>
                <div class="text-muted text-sm">Applied for <strong><?= e($app['job_title']) ?></strong> &middot; <?= time_ago($app['applied_at']) ?></div>
              </div>
            </div>
            <div style="text-align:right;">
              <span class="pill <?= status_pill_class($app['status']) ?>"><?= e($app['status']) ?></span>
            </div>
          </div>

          <?php if ($app['cover_letter']): ?>
            <p class="text-sm" style="margin-top:14px; background:var(--stone); padding:12px 14px; border-radius:6px;"><?= nl2br(e($app['cover_letter'])) ?></p>
          <?php endif; ?>

          <div class="divider"></div>
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; gap:10px;">
              <a href="view-applicant.php?seeker_id=<?= (int) $app['seeker_id'] ?>" class="btn btn-outline btn-sm">View full profile</a>
              <?php if ($app['resume_id']): ?>
                <a href="../view-resume.php?id=<?= (int) $app['resume_id'] ?>" target="_blank" class="btn btn-outline btn-sm">View CV</a>
              <?php else: ?>
                <span class="text-muted text-sm">No CV on file</span>
              <?php endif; ?>
            </div>
            <!-- changing the dropdown sends the form right away -->
            <form method="post" style="display:flex; gap:8px;">
              <?= csrf_field() ?>
              <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
              <select name="new_status" onchange="this.form.submit()">
                <?php foreach (['Applied','Under Review','Shortlisted','Interview','Accepted','Rejected'] as $s): ?>
                  <option value="<?= $s ?>" <?= $app['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; else: ?>
      <div class="card"><div class="card-body empty-state"><h3>No applicants match this filter</h3><p>Try a different job posting or status.</p></div></div>
    <?php endif; ?>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
