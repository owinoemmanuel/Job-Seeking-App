<?php
// Sign-up page for job seekers and employers.
require_once __DIR__ . '/includes/bootstrap.php';

// nothing to sign up for when already logged in
if (is_logged_in()) {
    redirect('index.php');
}

// only job_seeker and employer can be picked here, anything else (like admin) becomes job_seeker
$role = $_GET['role'] ?? $_POST['role'] ?? 'job_seeker';
if (!in_array($role, ['job_seeker', 'employer'], true)) {
    $role = 'job_seeker';
}

$errors = [];
// what was typed, so the form can be filled in again if something is wrong
$old = ['fullName' => '', 'email' => '', 'phone' => '', 'companyName' => ''];
// page to return to after signing up
$redirectTarget = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

// form submitted: check everything, then create the account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $role       = in_array($_POST['role'] ?? '', ['job_seeker', 'employer'], true) ? $_POST['role'] : 'job_seeker';
    $fullName   = trim($_POST['full_name'] ?? '');
    $email      = trim(strtolower($_POST['email'] ?? ''));
    $phone      = trim($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $companyName = trim($_POST['company_name'] ?? '');

    $old = compact('fullName', 'email', 'phone', 'companyName');

    // basic checks, each problem adds a message to show
    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if ($role === 'employer' && $companyName === '') $errors[] = 'Company name is required.';

    // the email has to be new, because it is what people log in with
    if (!$errors) {
        $db = getDB();
        $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    // save the user and the seeker/employer row together, or not at all
    if (!$errors) {
        $db = getDB();
        $db->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare('INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$fullName, $email, $phone, $hash, $role]);
            $userId = (int) $db->lastInsertId();

            if ($role === 'job_seeker') {
                $stmt = $db->prepare('INSERT INTO job_seekers (user_id) VALUES (?)');
                $stmt->execute([$userId]);
            } else {
                $stmt = $db->prepare('INSERT INTO employers (user_id, company_name, company_email, company_phone) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $companyName, $email, $phone]);
            }

            $db->commit();

            // log them in straight away so they don't have to type it all again
            $userRow = ['user_id' => $userId, 'role' => $role, 'full_name' => $fullName];
            login_user($userRow);
            notify($userId, 'Welcome to KaziLink', 'Your account has been created. Complete your profile to get the most out of KaziLink.');

            flash('success', 'Account created! Welcome to KaziLink.');
            // back to the page they came from if there is one, else their own dashboard
            $default = $role === 'job_seeker' ? 'seeker/dashboard.php' : 'employer/dashboard.php';
            redirect(safe_redirect_target($redirectTarget, $default));
        } catch (Exception $e) {
            // something failed halfway, so undo what was saved
            $db->rollBack();
            $errors[] = 'Something went wrong while creating your account. Please try again.';
        }
    }
}

$pageTitle = 'Create an account — KaziLink';
$base = '';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-split">
  <?php include __DIR__ . '/includes/auth-brand.php'; /* logo panel on the left, edit the logo in that file */ ?>

  <main class="auth-panel">
  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="auth-sub">Join KaziLink as a job seeker or an employer.</p>

    <!-- error messages, if any -->
    <?php if ($errors): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:18px;">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="register.php">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">

      <!-- pick a role, the radio buttons show or hide the company name box -->
      <div class="role-choice">
        <label>
          <input type="radio" name="role" value="job_seeker" onchange="document.getElementById('company-field').style.display='none'" <?= $role === 'job_seeker' ? 'checked' : '' ?>>
          I'm looking for a job
          <span class="desc">Search and apply for jobs</span>
        </label>
        <label>
          <input type="radio" name="role" value="employer" onchange="document.getElementById('company-field').style.display='block'" <?= $role === 'employer' ? 'checked' : '' ?>>
          I'm hiring
          <span class="desc">Post jobs and review applicants</span>
        </label>
      </div>

      <div class="form-group">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($old['fullName']) ?>" required>
      </div>

      <!-- only needed for employers -->
      <div class="form-group" id="company-field" style="display: <?= $role === 'employer' ? 'block' : 'none' ?>;">
        <label for="company_name">Company name</label>
        <input type="text" id="company_name" name="company_name" value="<?= e($old['companyName']) ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
        </div>
        <div class="form-group">
          <label for="phone">Phone number</label>
          <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="07xx xxx xxx">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required minlength="6">
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>

    <div class="auth-foot">Already have an account? <a href="login.php<?= $redirectTarget ? '?redirect=' . urlencode($redirectTarget) : '' ?>">Log in</a></div>
  </div>
  </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
