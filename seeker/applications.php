<?php
// Seeker: see every job you applied for and where each one stands.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$seekerId = current_seeker_id();

// you can only withdraw your own application, and only while it is still early on
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_id'])) {
    require_csrf();
    $stmt = $db->prepare("DELETE FROM applications WHERE application_id = ? AND seeker_id = ? AND status IN ('Applied','Under Review')");
    $stmt->execute([(int) $_POST['withdraw_id'], $seekerId]);
    flash('info', 'Application withdrawn.');
    redirect('applications.php');
}

// all my applications with the job and company, newest first
$stmt = $db->prepare("
    SELECT a.*, j.title, j.job_id, e.company_name
    FROM applications a
    JOIN jobs j ON j.job_id = a.job_id
    JOIN employers e ON e.employer_id = j.employer_id
    WHERE a.seeker_id = ?
    ORDER BY a.applied_at DESC
");
$stmt->execute([$seekerId]);
$applications = $stmt->fetchAll();

// the steps on the progress bar, in order
$stages = ['Applied', 'Under Review', 'Shortlisted', 'Interview', 'Accepted'];

$pageTitle = 'My applications — KaziLink';
$base = '../';
$active = 'applications';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>My applications</h1><p>Track every application from submission to decision.</p></div></div>

    <!-- one card per application -->
    <?php if ($applications): foreach ($applications as $app): ?>
      <div class="card">
        <div class="card-body">
          <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <div>
              <h4 style="margin:0 0 4px; font-size:16px;"><a href="../job-details.php?id=<?= (int) $app['job_id'] ?>"><?= e($app['title']) ?></a></h4>
              <div class="meta text-muted text-sm"><?= e($app['company_name']) ?> &middot; Applied <?= time_ago($app['applied_at']) ?></div>
            </div>
            <div style="display:flex; gap:8px; align-items:flex-start;">
              <span class="pill <?= status_pill_class($app['status']) ?>"><?= e($app['status']) ?></span>
              <?php if (in_array($app['status'], ['Applied', 'Under Review'])): ?>
                <form method="post" data-confirm="Withdraw this application?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="withdraw_id" value="<?= (int) $app['application_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Withdraw</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <!-- a rejected application is not on the bar, so it gets a plain message -->
          <?php if ($app['status'] === 'Rejected'): ?>
            <div class="pill pill-danger">This application was not successful</div>
          <?php else: ?>
            <div class="status-track">
              <?php foreach ($stages as $i => $stage):
                $currentIdx = array_search($app['status'], $stages);
                $cls = $currentIdx === false ? '' : ($i < $currentIdx ? 'done' : ($i === $currentIdx ? 'current' : ''));
              ?>
                <div class="node <?= $cls ?>"><?= e($stage) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; else: ?>
      <div class="card"><div class="card-body empty-state">
        <h3>You haven't applied for any jobs yet</h3>
        <p>Browse open roles and submit your first application.</p>
        <a href="../jobs.php" class="btn btn-primary">Find jobs</a>
      </div></div>
    <?php endif; ?>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
