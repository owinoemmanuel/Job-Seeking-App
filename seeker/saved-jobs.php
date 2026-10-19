<?php
// Seeker: the jobs you bookmarked to look at later.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$seekerId = current_seeker_id();

// un-bookmark a job, only if the saved row belongs to this seeker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    require_csrf();
    $db->prepare('DELETE FROM saved_jobs WHERE saved_id = ? AND seeker_id = ?')->execute([(int) $_POST['remove_id'], $seekerId]);
    flash('info', 'Removed from saved jobs.');
    redirect('saved-jobs.php');
}

// saved jobs with their company, latest saved first
$stmt = $db->prepare("
    SELECT sj.saved_id, j.job_id, j.title, j.location, j.job_type, j.status, e.company_name
    FROM saved_jobs sj
    JOIN jobs j ON j.job_id = sj.job_id
    JOIN employers e ON e.employer_id = j.employer_id
    WHERE sj.seeker_id = ?
    ORDER BY sj.saved_at DESC
");
$stmt->execute([$seekerId]);
$saved = $stmt->fetchAll();

$pageTitle = 'Saved jobs — KaziLink';
$base = '../';
$active = 'saved';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Saved jobs</h1><p>Jobs you've bookmarked to apply for later.</p></div></div>

    <?php if ($saved): ?>
      <div class="job-list">
        <?php foreach ($saved as $job): ?>
          <div class="job-row">
            <div>
              <a class="job-row-title" href="../job-details.php?id=<?= (int) $job['job_id'] ?>"><?= e($job['title']) ?></a>
              <div class="job-row-company"><?= e($job['company_name']) ?></div>
            </div>
            <div class="job-row-meta"><span class="tag"><?= e($job['job_type'] ?: 'Full-time') ?></span></div>
            <div class="job-row-meta"><span>📍 <?= e($job['location'] ?: 'Remote') ?></span></div>
            <div style="display:flex; gap:8px;">
              <!-- a job that has closed stays in the list but cannot be applied for -->
              <?php if ($job['status'] === 'active'): ?>
                <a href="../seeker/apply.php?job_id=<?= (int) $job['job_id'] ?>" class="btn btn-primary btn-sm">Apply</a>
              <?php endif; ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="remove_id" value="<?= (int) $job['saved_id'] ?>"><button type="submit" class="btn btn-outline btn-sm">Remove</button></form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card"><div class="card-body empty-state">
        <h3>No saved jobs yet</h3>
        <p>Bookmark jobs while browsing to find them here later.</p>
        <a href="../jobs.php" class="btn btn-primary">Browse jobs</a>
      </div></div>
    <?php endif; ?>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
