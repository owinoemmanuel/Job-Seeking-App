<?php
// Log in page for job seekers, employers and admins.
require_once __DIR__ . '/includes/bootstrap.php';

// already logged in, so send them home
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$email = '';
// optional page to go back to after logging in (it comes in the link, then in a hidden field)
$redirectTarget = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

// form submitted: check the details and log in
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Enter both your email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // one message for a wrong email and a wrong password, so nobody can tell which emails have accounts
        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Incorrect email or password.';
        } elseif ($user['status'] === 'suspended') {
            $errors[] = 'This account has been suspended. Contact the administrator.';
        } else {
            login_user($user);
            flash('success', 'Hi, ' . explode(' ', $user['full_name'])[0] . '! You are now logged in.');
            // go back to where they came from (only links on this site count), else to their dashboard
            $default = match ($user['role']) {
                'job_seeker' => 'seeker/dashboard.php',
                'employer'   => 'employer/dashboard.php',
                'admin'      => 'admin/dashboard.php',
                default      => 'index.php',
            };
            redirect(safe_redirect_target($redirectTarget, $default));
        }
    }
}

$pageTitle = 'Log in — KaziLink';
$base = '';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-split">
  <?php include __DIR__ . '/includes/auth-brand.php'; /* logo panel on the left, edit the logo in that file */ ?>

  <main class="auth-panel">
  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="auth-sub">Log in to your KaziLink account.</p>

    <!-- error messages, if any -->
    <?php if ($errors): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:18px;">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <?= csrf_field() ?>
      <!-- carries the redirect target through the form post -->
      <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">
      <div class="form-group">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <div class="form-link-row"><a href="forgot-password.php">Forgot password?</a></div>
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>

    <div class="auth-foot">Don't have an account? <a href="register.php<?= $redirectTarget ? '?redirect=' . urlencode($redirectTarget) : '' ?>">Sign up</a></div>
    <!-- demo accounts for trying the site -->
    <p class="text-muted text-sm" style="text-align:center; margin-top:18px;">
          </p>
  </div>
  </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
