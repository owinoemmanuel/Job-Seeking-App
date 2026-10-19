<?php
// Seeker: add and remove the places you studied.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$seekerId = current_seeker_id();

// one form post handles both removing an entry and adding a new one
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (isset($_POST['delete_id'])) {
        // seeker_id is in the query so nobody can delete another person's entry
        $stmt = $db->prepare('DELETE FROM education WHERE education_id = ? AND seeker_id = ?');
        $stmt->execute([(int) $_POST['delete_id'], $seekerId]);
        flash('info', 'Education entry removed.');
    } else {
        $institution = trim($_POST['institution'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $field = trim($_POST['field_of_study'] ?? '');
        // an empty date is saved as null, not as an empty string
        $start = $_POST['start_date'] ?: null;
        $end = $_POST['end_date'] ?: null;

        if ($institution === '') {
            flash('error', 'Institution name is required.');
        } else {
            $stmt = $db->prepare('INSERT INTO education (seeker_id, institution, qualification, field_of_study, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$seekerId, $institution, $qualification, $field, $start, $end]);
            flash('success', 'Education added.');
        }
    }
    // go back to the page so a refresh does not post the form again
    redirect('education.php');
}

// my entries, newest first
$stmt = $db->prepare('SELECT * FROM education WHERE seeker_id = ? ORDER BY start_date DESC');
$stmt->execute([$seekerId]);
$entries = $stmt->fetchAll();

$pageTitle = 'Education — KaziLink';
$base = '../';
$active = 'education';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Education</h1><p>Add your academic background.</p></div></div>

    <!-- form for a new entry -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h3>Add education</h3></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="form-row">
            <div class="form-group">
              <label for="institution">Institution</label>
              <input type="text" id="institution" name="institution" required placeholder="e.g. Meru University of Science and Technology">
            </div>
            <div class="form-group">
              <label for="qualification">Qualification</label>
              <input type="text" id="qualification" name="qualification" placeholder="e.g. Diploma in ICT">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="field_of_study">Field of study</label>
              <input type="text" id="field_of_study" name="field_of_study" placeholder="e.g. Information Technology">
            </div>
            <div class="form-group">
              <label>Duration</label>
              <div style="display:flex; gap:10px;">
                <input type="date" name="start_date">
                <input type="date" name="end_date">
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Add education</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Your education history</h3></div>
      <div class="card-body">
        <!-- no end date means they are still studying, so it shows Present -->
        <?php if ($entries): foreach ($entries as $entry): ?>
          <div class="entry-row">
            <div>
              <h4><?= e($entry['qualification'] ?: 'Studies') ?><?= $entry['field_of_study'] ? ' — ' . e($entry['field_of_study']) : '' ?></h4>
              <div class="meta"><?= e($entry['institution']) ?></div>
              <div class="meta"><?= $entry['start_date'] ? date('M Y', strtotime($entry['start_date'])) : '' ?> &ndash; <?= $entry['end_date'] ? date('M Y', strtotime($entry['end_date'])) : 'Present' ?></div>
            </div>
            <form method="post" data-confirm="Remove this education entry?">
              <?= csrf_field() ?>
              <input type="hidden" name="delete_id" value="<?= (int) $entry['education_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Remove</button>
            </form>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><h3>No education added yet</h3><p>Add your qualifications so employers can see them.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
