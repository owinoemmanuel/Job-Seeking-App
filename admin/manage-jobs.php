<?php
// Admin: approve, reject or delete job posts, filtered by status or employer.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('admin');

$db = getDB();

// one of the approve / reject / delete buttons was pressed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $jobId = (int) ($_POST['job_id'] ?? 0);

    // need the title and the employer's user id to send them a notification
    $jobStmt = $db->prepare('SELECT j.title, e.user_id FROM jobs j JOIN employers e ON e.employer_id = j.employer_id WHERE j.job_id = ?');
    $jobStmt->execute([$jobId]);
    $jobInfo = $jobStmt->fetch();

    // if the job id is not real we just skip to the redirect
    if ($jobInfo) {
        // approve and reject both tell the employer and write to the admin log
        if (isset($_POST['approve'])) {
            $db->prepare("UPDATE jobs SET status = 'active' WHERE job_id = ?")->execute([$jobId]);
            notify($jobInfo['user_id'], 'Job approved', 'Your posting "' . $jobInfo['title'] . '" is now live.');
            log_admin_action(current_user_id(), 'Approved job #' . $jobId, 'job', $jobId);
            flash('success', 'Job approved and published.');
        } elseif (isset($_POST['reject'])) {
            $db->prepare("UPDATE jobs SET status = 'rejected' WHERE job_id = ?")->execute([$jobId]);
            notify($jobInfo['user_id'], 'Job rejected', 'Your posting "' . $jobInfo['title'] . '" was not approved. You can edit and resubmit it.');
            log_admin_action(current_user_id(), 'Rejected job #' . $jobId, 'job', $jobId);
            flash('info', 'Job rejected.');
        } elseif (isset($_POST['delete'])) {
            $db->prepare('DELETE FROM jobs WHERE job_id = ?')->execute([$jobId]);
            log_admin_action(current_user_id(), 'Deleted job #' . $jobId, 'job', $jobId);
            flash('info', 'Job deleted.');
        }
    }
    // come back to the list with the same filters
    redirect('manage-jobs.php?' . http_build_query($_GET));
}

// both filters are optional
$status = $_GET['status'] ?? '';
$employerId = $_GET['employer_id'] ?? '';
$where = ['1=1'];
$params = [];
if ($status !== '') { $where[] = 'j.status = ?'; $params[] = $status; }
if ($employerId !== '') { $where[] = 'j.employer_id = ?'; $params[] = $employerId; }
$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT j.*, e.company_name,
    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) AS applicant_count
    FROM jobs j JOIN employers e ON e.employer_id = j.employer_id
    WHERE $whereSql ORDER BY j.created_at DESC
");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$pageTitle = 'Manage jobs — KaziLink';
$base = '../';
$active = 'jobs';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Jobs</h1><p><?= count($jobs) ?> matching listings.</p></div></div>

    <!-- status filter -->
    <form class="filter-bar" method="get" data-autosubmit style="grid-template-columns: 1fr;">
      <div class="form-group" style="margin:0;">
        <label>Status</label>
        <select name="status">
          <option value="">All statuses</option>
          <?php foreach (['pending','active','rejected','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <!-- jobs table -->
    <div class="card">
      <div class="card-body table-scroll">
        <?php if ($jobs): ?>
          <table class="data-table">
            <thead><tr><th>Title</th><th>Employer</th><th>Applicants</th><th>Posted</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($jobs as $job): ?>
              <tr>
                <td><a href="../job-details.php?id=<?= (int) $job['job_id'] ?>" class="row-title"><?= e($job['title']) ?></a></td>
                <td><?= e($job['company_name']) ?></td>
                <td><?= (int) $job['applicant_count'] ?></td>
                <td><?= time_ago($job['created_at']) ?></td>
                <td><span class="pill <?= status_pill_class($job['status']) ?>"><?= e(ucfirst($job['status'])) ?></span></td>
                <td>
                  <div class="table-actions">
                    <!-- only pending jobs need an approve / reject decision -->
                    <?php if ($job['status'] === 'pending'): ?>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $job['job_id'] ?>"><button type="submit" name="approve" class="btn btn-success btn-sm">Approve</button></form>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $job['job_id'] ?>"><button type="submit" name="reject" class="btn btn-danger btn-sm">Reject</button></form>
                    <?php endif; ?>
                    <form method="post" data-confirm="Delete this job permanently?"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $job['job_id'] ?>"><button type="submit" name="delete" class="btn btn-outline btn-sm">Delete</button></form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><h3>No jobs match this filter</h3></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
