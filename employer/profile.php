<?php
// Employer: edit my company profile and the contact name on my account.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('employer');

$db = getDB();
$userId = current_user_id();
$employerId = current_employer_id();

// my user row together with the company row
$stmt = $db->prepare('SELECT u.full_name, u.email, u.phone, emp.* FROM users u JOIN employers emp ON emp.user_id = u.user_id WHERE u.user_id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$errors = [];

// form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $companyEmail = trim($_POST['company_email'] ?? '');
    $companyPhone = trim($_POST['company_phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($fullName === '') $errors[] = 'Your name is required.';
    if ($companyName === '') $errors[] = 'Company name is required.';

    // keep the old logo unless a new file was uploaded
    $logoPath = $profile['logo'];
    $uploaded = handle_upload('logo', 'logos', ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024);
    if ($uploaded) $logoPath = $uploaded;

    if (!$errors) {
        $db->prepare('UPDATE users SET full_name = ? WHERE user_id = ?')->execute([$fullName, $userId]);
        $db->prepare('UPDATE employers SET company_name=?, company_email=?, company_phone=?, location=?, website=?, description=?, logo=? WHERE employer_id=?')
           ->execute([$companyName, $companyEmail, $companyPhone, $location, $website, $description, $logoPath, $employerId]);
        // so the new name shows in the header straight away
        $_SESSION['full_name'] = $fullName;
        flash('success', 'Profile updated.');
        redirect('profile.php');
    }
    // there was an error, so show what was typed instead of the saved values
    $profile = array_merge($profile, [
        'full_name' => $fullName, 'company_name' => $companyName, 'company_email' => $companyEmail,
        'company_phone' => $companyPhone, 'location' => $location, 'website' => $website, 'description' => $description,
    ]);
    $profile['logo'] = $logoPath;
}

$pageTitle = 'Company profile — KaziLink';
$base = '../';
$active = 'profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-employer.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Company profile</h1><p>This is what candidates see on your job postings.</p></div></div>

    <!-- error messages -->
    <?php if ($errors): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:18px;"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <!-- logo preview and upload -->
          <div style="display:flex; align-items:center; gap:18px; margin-bottom:26px;">
            <img class="profile-photo-lg" style="border-radius:12px;" src="<?= $profile['logo'] ? e('../' . $profile['logo']) : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($profile['company_name']) ?>" alt="">
            <div>
              <label style="margin-bottom:6px;">Company logo</label>
              <input type="file" name="logo" accept="image/*">
              <div class="field-hint">JPG, PNG or WEBP, up to 2MB.</div>
            </div>
          </div>

          <!-- contact name and company details -->
          <div class="form-group">
            <label for="full_name">Your name</label>
            <input type="text" id="full_name" name="full_name" value="<?= e($profile['full_name']) ?>" required>
            <div class="field-hint">The contact person for this account — shown as "Hi, {name}" when you're logged in.</div>
          </div>

          <div class="form-group">
            <label for="company_name">Company name</label>
            <input type="text" id="company_name" name="company_name" value="<?= e($profile['company_name']) ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="company_email">Company email</label>
              <input type="email" id="company_email" name="company_email" value="<?= e($profile['company_email']) ?>">
            </div>
            <div class="form-group">
              <label for="company_phone">Company phone</label>
              <input type="tel" id="company_phone" name="company_phone" value="<?= e($profile['company_phone']) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="location">Location</label>
              <input type="text" id="location" name="location" value="<?= e($profile['location']) ?>" placeholder="e.g. Meru">
            </div>
            <div class="form-group">
              <label for="website">Website</label>
              <input type="url" id="website" name="website" value="<?= e($profile['website']) ?>" placeholder="https://...">
            </div>
          </div>

          <div class="form-group">
            <label for="description">About the company</label>
            <textarea id="description" name="description" placeholder="Tell candidates what your company does..."><?= e($profile['description']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
