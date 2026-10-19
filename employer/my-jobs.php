<?php
// Employer: list my job posts, with buttons to close, reopen or delete them.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('employer');

$db = getDB();
$employerId = current_employer_id();

// one of the close / reopen / delete buttons was pressed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $jobId = (int) ($_POST['job_id'] ?? 0);

    // every query also checks employer_id, so only my own jobs can be changed
    if (isset($_POST['close_job'])) {
        $db->prepare("UPDATE jobs SET status = 'closed' WHERE job_id = ? AND employer_id = ?")->execute([$jobId, $employerId]);
        flash('info', 'Job closed. It is no longer visible to job seekers.');
    } elseif (isset($_POST['reopen_job'])) {
        // reopening sends it back to pending, the admin has to approve it again
        $db->prepare("UPDATE jobs SET status = 'pending' WHERE job_id = ? AND employer_id = ?")->execute([$jobId, $employerId]);
        flash('success', 'Job resubmitted for admin approval.');
    } elseif (isset($_POST['delete_job'])) {
        $db->prepare('DELETE FROM jobs WHERE job_id = ? AND employer_id = ?')->execute([$jobId, $employerId]);
        flash('info', 'Job deleted.');
    }
    redirect('my-jobs.php');
}

// my jobs, newest first, with how many people applied to each
$stmt = $db->prepare("
    SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) AS applicant_count
    FROM jobs j WHERE j.employer_id = ? ORDER BY j.created_at DESC
");
$stmt->execute([$employerId]);
$jobs = $stmt->fetchAll();

$pageTitle = 'My job postings — KaziLink';
$base = '../';
$active = 'my-jobs';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-employer.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div><h1>My job postings</h1><p>Edit, close or review applicants for each listing.</p></div>
      <a href="post-job.php" class="btn btn-primary">Post a job</a>
    </div>

    <!-- jobs table -->
    <div class="card">
      <div class="card-body table-scroll">
        <?php if ($jobs): ?>
          <table class="data-table">
            <thead><tr><th>Title</th><th>Status</th><th>Applicants</th><th>Posted</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($jobs as $job): ?>
              <tr>
                <td>
                  <div class="row-title"><?= e($job['title']) ?></div>
                  <div class="row-sub"><?= e($job['location'] ?: 'Remote') ?> &middot; <?= e($job['job_type'] ?: 'Full-time') ?></div>
                </td>
                <td><span class="pill <?= status_pill_class($job['status']) ?>"><?= e(ucfirst($job['status'])) ?></span></td>
                <td><a href="applicants.php?job_id=<?= (int) $job['job_id'] ?>"><?= (int) $job['applicant_count'] ?> applicant<?= $job['applicant_count'] == 1 ? '' : 's' ?></a></td>
                <td><?= time_ago($job['created_at']) ?></td>
                <td>
                  <div class="table-actions">
                    <a href="edit-job.php?id=<?= (int) $job['job_id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                    <!-- active jobs can be closed, closed jobs can be reopened -->
                    <?php if ($job['status'] === 'active'): ?>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $job['job_id'] ?>"><button type="submit" name="close_job" class="btn btn-outline btn-sm">Close</button></form>
                    <?php elseif ($job['status'] === 'closed'): ?>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $job['job_id'] ?>"><button type="submit" name="reopen_job" class="btn btn-outline btn-sm">Reopen</button></form>
                    <?php endif; ?>
                    <form method="post" data-confirm="Delete this job posting permanently?"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $job['job_id'] ?>"><button type="submit" name="delete_job" class="btn btn-danger btn-sm">Delete</button></form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><h3>You haven't posted any jobs yet</h3><a href="post-job.php" class="btn btn-primary">Post your first job</a></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
