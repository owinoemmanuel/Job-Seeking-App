<?php
// Reset password page: opened from the emailed link to pick a new password.
require_once __DIR__ . '/includes/bootstrap.php';

// already logged in, so there is nothing to reset
if (is_logged_in()) {
    redirect('index.php');
}

ensure_password_resets_table();
$db = getDB();

// the token comes in the link (GET) and is passed on by the form (POST)
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$reset = null;

// a real token is 64 hex characters, so anything else never reaches the query
if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
    // only the hash is stored, so hash the token from the link and compare that.
    // An expired link finds nothing.
    $stmt = $db->prepare('SELECT reset_id, user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW()');
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch() ?: null;
}

$errors = [];

// only handle the form while the link is still valid
if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE users SET password = ? WHERE user_id = ?')
               ->execute([password_hash($password, PASSWORD_BCRYPT), $reset['user_id']]);
            // links work once, so delete this one and any other links this person has
            $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Something went wrong while saving your new password. Please try again.';
        }

        if (!$errors) {
            // done, go and log in with the new password
            flash('success', 'Password updated. You can now log in with your new password.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Reset password — KaziLink';
$base = '';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <?php if (!$reset): ?>
      <!-- used, expired or wrongly copied link -->
      <h1>This link isn't valid</h1>
      <p class="auth-sub">Reset links work once and expire after 1 hour. This one has either been used, has expired, or was copied incorrectly.</p>
      <a href="forgot-password.php" class="btn btn-primary btn-block">Get a new reset link</a>
      <div class="auth-foot"><a href="login.php">&larr; Back to log in</a></div>
    <?php else: ?>
      <!-- valid link: the new password form -->
      <h1>Choose a new password</h1>
      <p class="auth-sub">Pick something you'll remember — at least 6 characters.</p>

      <?php if ($errors): ?>
        <div class="flash flash-error" style="position:static; margin-bottom:18px;">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="reset-password.php">
        <?= csrf_field() ?>
        <!-- the token goes along with the form so the next request can find the reset -->
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group">
          <label for="password">New password</label>
          <input type="password" id="password" name="password" required minlength="6" autofocus>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm new password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Update password</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
