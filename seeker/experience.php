<?php
// Seeker: add and remove the jobs you have done before.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$seekerId = current_seeker_id();

// one form post handles both removing a role and adding a new one
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (isset($_POST['delete_id'])) {
        // seeker_id is in the query so you can only remove your own roles
        $stmt = $db->prepare('DELETE FROM work_experience WHERE experience_id = ? AND seeker_id = ?');
        $stmt->execute([(int) $_POST['delete_id'], $seekerId]);
        flash('info', 'Work experience removed.');
    } else {
        $company = trim($_POST['company_name'] ?? '');
        $title = trim($_POST['job_title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        // blank dates go in as null
        $start = $_POST['start_date'] ?: null;
        $end = $_POST['end_date'] ?: null;

        // company is the only required field
        if ($company === '') {
            flash('error', 'Company name is required.');
        } else {
            $stmt = $db->prepare('INSERT INTO work_experience (seeker_id, company_name, job_title, description, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$seekerId, $company, $title, $description, $start, $end]);
            flash('success', 'Work experience added.');
        }
    }
    // redirect after the post so a refresh does not add the role twice
    redirect('experience.php');
}

// my roles, most recent first
$stmt = $db->prepare('SELECT * FROM work_experience WHERE seeker_id = ? ORDER BY start_date DESC');
$stmt->execute([$seekerId]);
$entries = $stmt->fetchAll();

$pageTitle = 'Work experience — KaziLink';
$base = '../';
$active = 'experience';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Work experience</h1><p>Add roles you've held before.</p></div></div>

    <!-- form for a new role -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h3>Add work experience</h3></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="form-row">
            <div class="form-group">
              <label for="job_title">Job title</label>
              <input type="text" id="job_title" name="job_title" placeholder="e.g. IT Support Assistant">
            </div>
            <div class="form-group">
              <label for="company_name">Company</label>
              <input type="text" id="company_name" name="company_name" required placeholder="e.g. Abacus Technologies">
            </div>
          </div>
          <div class="form-group">
            <label for="description">What did you do there?</label>
            <textarea id="description" name="description" placeholder="Key responsibilities and achievements..."></textarea>
          </div>
          <div class="form-group">
            <label>Duration</label>
            <div style="display:flex; gap:10px; max-width:400px;">
              <input type="date" name="start_date">
              <input type="date" name="end_date">
            </div>
            <div class="field-hint">Leave end date blank if this is your current role.</div>
          </div>
          <button type="submit" class="btn btn-primary">Add experience</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Your work history</h3></div>
      <div class="card-body">
        <!-- a role with no end date is the current one, so it shows Present -->
        <?php if ($entries): foreach ($entries as $entry): ?>
          <div class="entry-row">
            <div>
              <h4><?= e($entry['job_title'] ?: 'Role') ?> &middot; <?= e($entry['company_name']) ?></h4>
              <?php if ($entry['description']): ?><p class="meta" style="margin:4px 0;"><?= e($entry['description']) ?></p><?php endif; ?>
              <div class="meta"><?= $entry['start_date'] ? date('M Y', strtotime($entry['start_date'])) : '' ?> &ndash; <?= $entry['end_date'] ? date('M Y', strtotime($entry['end_date'])) : 'Present' ?></div>
            </div>
            <form method="post" data-confirm="Remove this work experience entry?">
              <?= csrf_field() ?>
              <input type="hidden" name="delete_id" value="<?= (int) $entry['experience_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Remove</button>
            </form>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><h3>No work experience added yet</h3><p>Even short or informal roles are worth adding.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
