<?php
// Seeker: edit your personal details and profile photo.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$userId = current_user_id();
$seekerId = current_seeker_id();

// name, email and phone are in users, the rest is in job_seekers
$stmt = $db->prepare('SELECT u.full_name, u.email, u.phone, js.* FROM users u JOIN job_seekers js ON js.user_id = u.user_id WHERE u.user_id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$errors = [];

// save the changes when the form is sent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $summary = trim($_POST['professional_summary'] ?? '');

    if ($fullName === '') $errors[] = 'Full name is required.';

    // keep the old photo unless a new one was uploaded
    $photoPath = $profile['profile_photo'];
    $uploaded = handle_upload('profile_photo', 'photos', ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024);
    if ($uploaded) $photoPath = $uploaded;

    if (!$errors) {
        $db->prepare('UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?')->execute([$fullName, $phone, $userId]);
        $db->prepare('UPDATE job_seekers SET location = ?, gender = ?, professional_summary = ?, profile_photo = ? WHERE seeker_id = ?')
           ->execute([$location, $gender, $summary, $photoPath, $seekerId]);

        // so the new name shows up straight away around the site
        $_SESSION['full_name'] = $fullName;
        flash('success', 'Profile updated.');
        redirect('profile.php');
    }

    // something went wrong, so show what they typed instead of the saved values
    $profile = array_merge($profile, [
        'full_name' => $fullName, 'phone' => $phone, 'location' => $location,
        'gender' => $gender, 'professional_summary' => $summary,
    ]);
    $profile['profile_photo'] = $photoPath;
}

$pageTitle = 'My profile — KaziLink';
$base = '../';
$active = 'profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div><h1>Personal details</h1><p>This information is visible to employers when you apply.</p></div>
    </div>

    <?php if ($errors): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:18px;">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <!-- no photo yet means a generated avatar with their initials -->
          <div style="display:flex; align-items:center; gap:18px; margin-bottom:26px;">
            <img class="profile-photo-lg" src="<?= $profile['profile_photo'] ? e('../' . $profile['profile_photo']) : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($profile['full_name']) ?>" alt="">
            <div>
              <label style="margin-bottom:6px;">Profile photo</label>
              <input type="file" name="profile_photo" accept="image/*">
              <div class="field-hint">JPG, PNG or WEBP, up to 2MB.</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="full_name">Full name</label>
              <input type="text" id="full_name" name="full_name" value="<?= e($profile['full_name']) ?>" required>
            </div>
            <!-- email is only shown here, it is disabled so it cannot be edited -->
            <div class="form-group">
              <label>Email address</label>
              <input type="email" value="<?= e($profile['email']) ?>" disabled>
              <div class="field-hint">Email can't be changed.</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="phone">Phone number</label>
              <input type="tel" id="phone" name="phone" value="<?= e($profile['phone']) ?>">
            </div>
            <div class="form-group">
              <label for="location">Location</label>
              <input type="text" id="location" name="location" value="<?= e($profile['location']) ?>" placeholder="e.g. Meru">
            </div>
          </div>

          <div class="form-group">
            <label for="gender">Gender</label>
            <select id="gender" name="gender">
              <option value="">Prefer not to say</option>
              <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                <option value="<?= $g ?>" <?= $profile['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="professional_summary">Professional summary</label>
            <textarea id="professional_summary" name="professional_summary" placeholder="A short paragraph employers will see first..."><?= e($profile['professional_summary']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
