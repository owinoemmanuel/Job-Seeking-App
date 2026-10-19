<?php
// Seeker: upload, view and delete your CVs, and pick the default one.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$seekerId = current_seeker_id();

// this page takes three kinds of post: delete, make default, or a new upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (isset($_POST['delete_id'])) {
        $rid = (int) $_POST['delete_id'];
        // look it up with seeker_id first so you can only delete your own CV
        $stmt = $db->prepare('SELECT file_path FROM resumes WHERE resume_id = ? AND seeker_id = ?');
        $stmt->execute([$rid, $seekerId]);
        $row = $stmt->fetch();
        if ($row) {
            $db->prepare('DELETE FROM resumes WHERE resume_id = ?')->execute([$rid]);
            // the file on disk goes too, not just the database row
            $fullPath = __DIR__ . '/../' . $row['file_path'];
            if (is_file($fullPath)) @unlink($fullPath);
            flash('info', 'CV removed.');
        }
        redirect('resume.php');
    }

    if (isset($_POST['set_default'])) {
        $rid = (int) $_POST['set_default'];
        // only one CV can be the default, so clear the old one first
        $db->prepare('UPDATE resumes SET is_default = 0 WHERE seeker_id = ?')->execute([$seekerId]);
        $db->prepare('UPDATE resumes SET is_default = 1 WHERE resume_id = ? AND seeker_id = ?')->execute([$rid, $seekerId]);
        flash('success', 'Default CV updated.');
        redirect('resume.php');
    }

    $path = handle_upload('resume_file', 'resumes', ['pdf', 'doc', 'docx'], 5 * 1024 * 1024);
    if ($path) {
        // the very first CV becomes the default automatically
        $countStmt = $db->prepare('SELECT COUNT(*) FROM resumes WHERE seeker_id = ?');
        $countStmt->execute([$seekerId]);
        $isFirst = (int) $countStmt->fetchColumn() === 0;

        $stmt = $db->prepare('INSERT INTO resumes (seeker_id, file_name, file_path, is_default) VALUES (?, ?, ?, ?)');
        $stmt->execute([$seekerId, $_FILES['resume_file']['name'], $path, $isFirst ? 1 : 0]);
        flash('success', 'CV uploaded.');
    }
    redirect('resume.php');
}

// my CVs, newest first
$stmt = $db->prepare('SELECT * FROM resumes WHERE seeker_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$seekerId]);
$resumes = $stmt->fetchAll();

$pageTitle = 'CV / Resume — KaziLink';
$base = '../';
$active = 'resume';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>CV / Resume</h1><p>Upload one or more CVs and choose your default for applications.</p></div></div>

    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h3>Upload a new CV</h3></div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
          <?= csrf_field() ?>
          <input type="file" name="resume_file" accept=".pdf,.doc,.docx" required>
          <button type="submit" class="btn btn-primary">Upload</button>
        </form>
        <div class="field-hint">PDF, DOC or DOCX, up to 5MB.</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Your CVs</h3></div>
      <div class="card-body">
        <?php if ($resumes): foreach ($resumes as $r): ?>
          <!-- the default CV gets a badge and no Make default button -->
          <div class="entry-row">
            <div>
              <h4>📄 <?= e($r['file_name']) ?> <?= $r['is_default'] ? '<span class="pill pill-success" style="margin-left:6px;">Default</span>' : '' ?></h4>
              <div class="meta">Uploaded <?= time_ago($r['uploaded_at']) ?></div>
            </div>
            <div class="table-actions">
              <a href="../view-resume.php?id=<?= (int) $r['resume_id'] ?>" target="_blank" class="btn btn-outline btn-sm">View</a>
              <?php if (!$r['is_default']): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="set_default" value="<?= (int) $r['resume_id'] ?>"><button type="submit" class="btn btn-outline btn-sm">Make default</button></form>
              <?php endif; ?>
              <form method="post" data-confirm="Delete this CV?"><?= csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int) $r['resume_id'] ?>"><button type="submit" class="btn btn-danger btn-sm">Delete</button></form>
            </div>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><h3>No CV uploaded yet</h3><p>You'll need a CV on file before you can apply for jobs.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
