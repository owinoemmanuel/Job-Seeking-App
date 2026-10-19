<?php
// Admin: look through every application on the site and filter by status.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('admin');

$db = getDB();

// optional status filter, the 1=1 is only there so more conditions can be added with AND
$status = $_GET['status'] ?? '';
$where = ['1=1'];
$params = [];
if ($status !== '') { $where[] = 'a.status = ?'; $params[] = $status; }
$whereSql = implode(' AND ', $where);

// 20 per page, paginate() keeps the page number inside the real range
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$countStmt = $db->prepare("SELECT COUNT(*) FROM applications a WHERE $whereSql");
$countStmt->execute($params);
[$totalPages, $page, $offset] = paginate((int) $countStmt->fetchColumn(), $perPage, $page);

// one page of applications, newest first
$stmt = $db->prepare("
    SELECT a.*, j.title AS job_title, e.company_name, u.full_name AS candidate_name
    FROM applications a
    JOIN jobs j ON j.job_id = a.job_id
    JOIN employers e ON e.employer_id = j.employer_id
    JOIN job_seekers js ON js.seeker_id = a.seeker_id
    JOIN users u ON u.user_id = js.user_id
    WHERE $whereSql
    ORDER BY a.applied_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

// the header and sidebar read these, $active picks the highlighted menu link
$pageTitle = 'All applications — KaziLink';
$base = '../';
$active = 'applications';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Applications</h1><p>Every application submitted across the platform.</p></div></div>

    <!-- status filter -->
    <form class="filter-bar" method="get" data-autosubmit style="grid-template-columns: 1fr;">
      <div class="form-group" style="margin:0;">
        <label>Status</label>
        <select name="status">
          <option value="">Any status</option>
          <?php foreach (['Applied','Under Review','Shortlisted','Interview','Accepted','Rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <!-- applications table -->
    <div class="card">
      <div class="card-body table-scroll">
        <?php if ($applications): ?>
          <table class="data-table">
            <thead><tr><th>Candidate</th><th>Job</th><th>Employer</th><th>Applied</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
              <tr>
                <td class="row-title"><?= e($app['candidate_name']) ?></td>
                <td><?= e($app['job_title']) ?></td>
                <td><?= e($app['company_name']) ?></td>
                <td><?= time_ago($app['applied_at']) ?></td>
                <td><span class="pill <?= status_pill_class($app['status']) ?>"><?= e($app['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php if ($totalPages > 1): ?>
            <!-- page links -->
            <div class="pagination">
              <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p === $page): ?><span class="current"><?= $p ?></span><?php else: ?><a href="?status=<?= e($status) ?>&page=<?= $p ?>"><?= $p ?></a><?php endif; ?>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="empty-state"><h3>No applications found</h3></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
