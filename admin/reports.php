<?php
// Admin: read the complaints job seekers sent, reply to them and set a status.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('admin');

$db = getDB();

// old databases need a few extra columns before this page can work
ensure_complaint_columns();

// the only statuses a complaint can have
$statuses = ['open', 'reviewed', 'dismissed'];

// save the reply and status for one complaint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // delete a complaint from this list, but only after it has been dealt with
    // (the job seeker keeps their own copy until they delete it too)
    if (isset($_POST['delete_id'])) {
        $reportId = (int) $_POST['delete_id'];

        $stmt = $db->prepare("UPDATE reports SET deleted_by_admin = 1 WHERE report_id = ? AND status <> 'open'");
        $stmt->execute([$reportId]);

        if ($stmt->rowCount() > 0) {
            purge_deleted_complaint($reportId);
            log_admin_action(current_user_id(), 'Deleted report #' . $reportId, 'report', $reportId);
            flash('info', 'Complaint deleted.');
        } else {
            flash('error', 'Only complaints that are reviewed or dismissed can be deleted.');
        }
        redirect('reports.php');
    }

    $reportId = (int) ($_POST['report_id'] ?? 0);
    $newStatus = $_POST['set_status'] ?? '';
    $reply = trim($_POST['admin_reply'] ?? '');

    $stmt = $db->prepare('SELECT * FROM reports WHERE report_id = ?');
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    // stop if the complaint is missing, the status is made up or the reply is too long
    if (!$report || !in_array($newStatus, $statuses, true)) {
        flash('error', 'That complaint could not be updated.');
    } elseif (mb_strlen($reply) > 2000) {
        flash('error', 'The reply is too long (2000 characters at most).');
    } else {
        $statusChanged = $report['status'] !== $newStatus;
        $replyChanged = $reply !== trim((string) $report['admin_reply']);

        // nothing new, so no update and no notification
        if (!$statusChanged && !$replyChanged) {
            flash('info', 'Nothing changed.');
            redirect('reports.php');
        }

        // keep the old reply time unless the reply itself was edited
        $repliedAt = $report['replied_at'];
        if ($replyChanged) {
            $repliedAt = $reply === '' ? null : date('Y-m-d H:i:s');
        }

        // a complaint that is opened again shows up on the job seeker's page again
        $seekerDeleted = $newStatus === 'open' ? 0 : (int) $report['deleted_by_seeker'];

        $update = $db->prepare('UPDATE reports SET status = ?, admin_reply = ?, replied_at = ?, deleted_by_seeker = ? WHERE report_id = ?');
        $update->execute([$newStatus, $reply === '' ? null : $reply, $repliedAt, $seekerDeleted, $reportId]);

        log_admin_action(current_user_id(), 'Set report #' . $reportId . ' to ' . $newStatus, 'report', $reportId);

        // let the job seeker know
        $message = 'Your complaint "' . complaint_subject($report) . '" is now marked ' . $newStatus . '.';
        if ($reply !== '') {
            $message .= ' Admin reply: ' . $reply;
        }
        notify((int) $report['reporter_id'], 'Update on your complaint', $message);

        flash('success', 'Complaint updated and the job seeker has been notified.');
    }

    redirect('reports.php');
}

// filter by status, 10 complaints per page
// complaints the admin has already deleted are left out
$status = $_GET['status'] ?? '';
$where = 'r.deleted_by_admin = 0';
$params = [];
if (in_array($status, $statuses, true)) {
    $where .= ' AND r.status = ?';
    $params[] = $status;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$countStmt = $db->prepare("SELECT COUNT(*) FROM reports r WHERE $where");
$countStmt->execute($params);
[$totalPages, $page, $offset] = paginate((int) $countStmt->fetchColumn(), $perPage, $page);

// a complaint is about a job or an employer, so those two joins are optional
// open complaints are listed first
$stmt = $db->prepare("
    SELECT r.*, u.full_name AS reporter_name, u.email AS reporter_email,
           j.title AS job_title, e.company_name
    FROM reports r
    JOIN users u ON u.user_id = r.reporter_id
    LEFT JOIN jobs j ON r.target_type = 'job' AND j.job_id = r.target_id
    LEFT JOIN employers e ON r.target_type = 'employer' AND e.employer_id = r.target_id
    WHERE $where
    ORDER BY FIELD(r.status, 'open', 'reviewed', 'dismissed'), r.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

// what to show for the type of complaint
$typeNames = ['job' => 'Job listing', 'employer' => 'Employer', 'user' => 'User', 'general' => 'KaziLink'];

$pageTitle = 'Reports & complaints — KaziLink';
$base = '../';
$active = 'reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Reports &amp; complaints</h1><p>Complaints sent by job seekers about listings, employers or the website. Your reply is sent to them as a notification. Once a complaint is reviewed or dismissed you can delete it.</p></div></div>

    <!-- status filter -->
    <form class="filter-bar" method="get" data-autosubmit style="grid-template-columns: 1fr;">
      <div class="form-group" style="margin:0;">
        <label>Status</label>
        <select name="status">
          <option value="">All complaints</option>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <!-- one block per complaint, with a reply form -->
    <div class="card">
      <div class="card-body">
        <?php if ($reports): foreach ($reports as $r): ?>
          <div class="complaint">
            <div class="complaint-head">
              <div>
                <h4><?= e(complaint_subject($r)) ?></h4>
                <div class="meta">
                  From <?= e($r['reporter_name']) ?> (<?= e($r['reporter_email']) ?>) &middot; <?= time_ago($r['created_at']) ?>
                </div>
                <div class="meta">
                  <span class="tag"><?= e($typeNames[$r['target_type']] ?? 'Other') ?></span>
                  <?php if ($r['job_title']): ?>
                    <a href="../job-details.php?id=<?= (int) $r['target_id'] ?>"><?= e($r['job_title']) ?></a>
                  <?php endif; ?>
                  <?php if ($r['company_name']): ?><?= e($r['company_name']) ?><?php endif; ?>
                </div>
              </div>
              <span class="pill <?= status_pill_class($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
            </div>

            <p><?= e($r['reason']) ?></p>

            <form method="post" class="complaint-form">
              <?= csrf_field() ?>
              <input type="hidden" name="report_id" value="<?= (int) $r['report_id'] ?>">
              <div class="form-group">
                <label for="reply-<?= (int) $r['report_id'] ?>">Your reply to the job seeker</label>
                <textarea id="reply-<?= (int) $r['report_id'] ?>" name="admin_reply" rows="3" maxlength="2000" placeholder="Explain what you found or what you did about it."><?= e($r['admin_reply'] ?? '') ?></textarea>
              </div>
              <div class="complaint-actions">
                <select name="set_status" aria-label="Status">
                  <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Save and notify</button>

                <!-- only after the complaint is dealt with; it submits the small delete form below -->
                <?php if ($r['status'] !== 'open'): ?>
                  <button type="submit" form="delete-<?= (int) $r['report_id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete this complaint? The job seeker will still see their own copy.">Delete</button>
                <?php endif; ?>
              </div>
            </form>

            <?php if ($r['status'] !== 'open'): ?>
              <form method="post" id="delete-<?= (int) $r['report_id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= (int) $r['report_id'] ?>">
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><h3>No complaints here</h3><p>Complaints from job seekers will show up on this page.</p></div>
        <?php endif; ?>

        <!-- page links -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <?php if ($p === $page): ?><span class="current"><?= $p ?></span><?php else: ?><a href="?status=<?= e($status) ?>&page=<?= $p ?>"><?= $p ?></a><?php endif; ?>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
