<?php
// Seeker: read the notifications sent to your account.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$userId = current_user_id();

// opening this page counts as reading them all
$db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);

// only the 50 newest
$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications — KaziLink';
$base = '../';
$active = 'notifications';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Notifications</h1><p>Updates on your applications and account.</p></div></div>

    <div class="card">
      <div class="card-body">
        <!-- everything is marked read above, so every item gets the read style -->
        <?php if ($notifications): foreach ($notifications as $n): ?>
          <div class="notif-item read">
            <div class="notif-dot"></div>
            <div style="flex:1;">
              <h4><?= e($n['title']) ?></h4>
              <p><?= e($n['message']) ?></p>
              <time><?= time_ago($n['created_at']) ?></time>
            </div>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><h3>No notifications yet</h3><p>We'll let you know when something changes with your applications.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
