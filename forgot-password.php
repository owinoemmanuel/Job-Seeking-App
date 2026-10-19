<?php
// Forgot password page: takes an email and makes a one-time reset link.
require_once __DIR__ . '/includes/bootstrap.php';

// someone who is logged in doesn't need a reset link
if (is_logged_in()) {
    redirect('index.php');
}

// create the password_resets table if this database doesn't have it yet
ensure_password_resets_table();

$errors = [];
$email = '';
$submitted = false;
$resetLink = null;   // only set on localhost, where email usually can't be sent

// form submitted: find the account and make the reset link
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $db = getDB();

        // clear out links that have expired
        $db->exec('DELETE FROM password_resets WHERE expires_at < NOW()');

        // only active accounts can reset a password
        $stmt = $db->prepare("SELECT user_id, full_name, email FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // one link per person, a new request replaces the old one
            $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['user_id']]);

            // the real token only goes in the emailed link, the database keeps just its hash
            $token = bin2hex(random_bytes(32));
            $db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
               ->execute([$user['user_id'], hash('sha256', $token)]);

            $link = app_base_url() . '/reset-password.php?token=' . $token;
            send_reset_email($user['email'], $user['full_name'], $link);

            if (should_show_reset_link()) {
                $resetLink = $link;
            }
        }

        // same reply whether or not the email exists, so nobody can use this
        // to find out who has an account
        $submitted = true;
    }
}

$pageTitle = 'Forgot password — KaziLink';
$base = '';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <?php if ($submitted): ?>
      <!-- same text whether or not the email exists -->
      <h1>Check your email</h1>
      <p class="auth-sub">If an account exists for <strong><?= e($email) ?></strong>, we've sent a link to reset its password. The link works once and expires in 1 hour.</p>

      <!-- link shown on screen for testing, only on localhost -->
      <?php if ($resetLink): ?>
        <div class="flash flash-info" style="position:static; margin-bottom:18px; animation:none;">
          <strong>Running on your own computer.</strong> It usually can't send email, so here is your reset link:
          <p style="margin:10px 0 0;"><a href="<?= e($resetLink) ?>" class="btn btn-primary btn-sm">Reset my password</a></p>
          <p class="text-sm" style="margin:10px 0 0;">This on-screen link only appears on localhost. On a live website it is emailed instead.</p>
        </div>
      <?php endif; ?>

      <div class="auth-foot"><a href="login.php">&larr; Back to log in</a></div>
    <?php else: ?>
      <!-- first visit: ask for the email -->
      <h1>Forgot your password?</h1>
      <p class="auth-sub">Enter the email you signed up with and we'll send you a link to choose a new password.</p>

      <?php if ($errors): ?>
        <div class="flash flash-error" style="position:static; margin-bottom:18px;">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="forgot-password.php">
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
      </form>

      <div class="auth-foot">Remembered it? <a href="login.php">Log in</a></div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
