<?php
// Admin: list the employers, search them and suspend or reactivate an account.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('admin');

$db = getDB();

// suspend / reactivate button was pressed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);

    if (isset($_POST['toggle_status'])) {
        $stmt = $db->prepare('SELECT status FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $current = $stmt->fetchColumn();
        // one button does both jobs, it flips whatever the status is now
        $newStatus = $current === 'active' ? 'suspended' : 'active';
        $db->prepare('UPDATE users SET status = ? WHERE user_id = ?')->execute([$newStatus, $userId]);
        log_admin_action(current_user_id(), 'Set user #' . $userId . ' status to ' . $newStatus, 'user', $userId);
        flash('success', 'Account ' . ($newStatus === 'suspended' ? 'suspended' : 'reactivated') . '.');
    }
    // keep the search text so the list does not reset
    redirect('manage-employers.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}

// search by name, email or company name
$q = trim($_GET['q'] ?? '');
$sql = "
    SELECT u.*, emp.company_name, emp.employer_id,
    (SELECT COUNT(*) FROM jobs j WHERE j.employer_id = emp.employer_id) AS job_count
    FROM users u JOIN employers emp ON emp.user_id = u.user_id WHERE u.role = 'employer'
";
$params = [];
if ($q !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR emp.company_name LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$employers = $stmt->fetchAll();

$pageTitle = 'Manage employers — KaziLink';
$base = '../';
$active = 'employers';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Employers</h1><p><?= count($employers) ?> registered.</p></div></div>

    <!-- search box -->
    <form method="get" class="filter-bar" style="grid-template-columns: 1fr auto;">
      <div class="form-group" style="margin:0;"><label>Search</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="Name, company or email..."></div>
      <div><button type="submit" class="btn btn-outline">Search</button></div>
    </form>

    <!-- employers table -->
    <div class="card">
      <div class="card-body table-scroll">
        <?php if ($employers): ?>
          <table class="data-table">
            <thead><tr><th>Company</th><th>Contact</th><th>Jobs posted</th><th>Joined</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($employers as $emp): ?>
              <tr>
                <td class="row-title"><?= e($emp['company_name']) ?></td>
                <td><?= e($emp['full_name']) ?><div class="row-sub"><?= e($emp['email']) ?></div></td>
                <td><a href="manage-jobs.php?employer_id=<?= (int) $emp['employer_id'] ?>"><?= (int) $emp['job_count'] ?></a></td>
                <td><?= time_ago($emp['created_at']) ?></td>
                <td><span class="pill <?= status_pill_class($emp['status']) ?>"><?= e(ucfirst($emp['status'])) ?></span></td>
                <td>
                  <form method="post" data-confirm="<?= $emp['status'] === 'active' ? 'Suspend' : 'Reactivate' ?> this employer?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $emp['user_id'] ?>">
                    <button type="submit" name="toggle_status" class="btn btn-sm <?= $emp['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>"><?= $emp['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><h3>No employers found</h3></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
