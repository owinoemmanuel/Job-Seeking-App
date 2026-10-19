<?php
// Admin: list the job seekers, search them and suspend or reactivate an account.
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
        // same toggle as the employers page, suspended and active swap
        $newStatus = $current === 'active' ? 'suspended' : 'active';
        $db->prepare('UPDATE users SET status = ? WHERE user_id = ?')->execute([$newStatus, $userId]);
        log_admin_action(current_user_id(), 'Set user #' . $userId . ' status to ' . $newStatus, 'user', $userId);
        flash('success', 'Account ' . ($newStatus === 'suspended' ? 'suspended' : 'reactivated') . '.');
    }
    // go back to the same search
    redirect('manage-seekers.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}

// optional search on name or email
$q = trim($_GET['q'] ?? '');
$sql = "SELECT u.*, js.location FROM users u JOIN job_seekers js ON js.user_id = u.user_id WHERE u.role = 'job_seeker'";
$params = [];
if ($q !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ?)';
    $params = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$seekers = $stmt->fetchAll();

$pageTitle = 'Manage job seekers — KaziLink';
$base = '../';
$active = 'seekers';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-admin.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Job seekers</h1><p><?= count($seekers) ?> registered.</p></div></div>

    <!-- search box -->
    <form method="get" class="filter-bar" style="grid-template-columns: 1fr auto;">
      <div class="form-group" style="margin:0;"><label>Search</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="Name or email..."></div>
      <div><button type="submit" class="btn btn-outline">Search</button></div>
    </form>

    <!-- job seekers table -->
    <div class="card">
      <div class="card-body table-scroll">
        <?php if ($seekers): ?>
          <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Location</th><th>Joined</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($seekers as $s): ?>
              <tr>
                <td class="row-title"><?= e($s['full_name']) ?></td>
                <td><?= e($s['email']) ?></td>
                <td><?= e($s['location'] ?: '—') ?></td>
                <td><?= time_ago($s['created_at']) ?></td>
                <td><span class="pill <?= status_pill_class($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                <td>
                  <form method="post" data-confirm="<?= $s['status'] === 'active' ? 'Suspend' : 'Reactivate' ?> this account?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $s['user_id'] ?>">
                    <button type="submit" name="toggle_status" class="btn btn-sm <?= $s['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>"><?= $s['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-state"><h3>No job seekers found</h3></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
