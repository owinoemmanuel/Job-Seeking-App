<?php
// Seeker: apply for a job by choosing a CV and writing a cover letter.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$seekerId = current_seeker_id();
// the job id is in the link at first, then in a hidden field once the form is sent
$jobId = (int) ($_GET['job_id'] ?? $_POST['job_id'] ?? 0);

// only jobs that are still open can be applied for
$jobStmt = $db->prepare("
    SELECT j.*, e.company_name, e.user_id AS employer_user_id
    FROM jobs j JOIN employers e ON e.employer_id = j.employer_id
    WHERE j.job_id = ? AND j.status = 'active'
");
$jobStmt->execute([$jobId]);
$job = $jobStmt->fetch();

// closed or missing job, so go back to the job list
if (!$job) {
    flash('error', 'This job is no longer available.');
    redirect('../jobs.php');
}

// no second application for the same job
$dupCheck = $db->prepare('SELECT application_id FROM applications WHERE job_id = ? AND seeker_id = ?');
$dupCheck->execute([$jobId, $seekerId]);
if ($dupCheck->fetch()) {
    flash('info', 'You have already applied for this job.');
    redirect('applications.php');
}

// my CVs, default first so it comes up selected
$resumeStmt = $db->prepare('SELECT * FROM resumes WHERE seeker_id = ? ORDER BY is_default DESC, uploaded_at DESC');
$resumeStmt->execute([$seekerId]);
$resumes = $resumeStmt->fetchAll();

$errors = [];

// form was sent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $resumeId = (int) ($_POST['resume_id'] ?? 0);
    $coverLetter = trim($_POST['cover_letter'] ?? '');

    if (!$resumes) {
        $errors[] = 'Please upload a CV before applying.';
    } elseif (!$resumeId) {
        $errors[] = 'Choose which CV to send.';
    }

    if (!$errors) {
        $stmt = $db->prepare('INSERT INTO applications (job_id, seeker_id, resume_id, cover_letter, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$jobId, $seekerId, $resumeId, $coverLetter, 'Applied']);

        // tell the employer someone applied
        notify($job['employer_user_id'], 'New application received', 'A candidate applied for "' . $job['title'] . '".');

        flash('success', 'Application submitted! You can track its status from "My applications".');
        redirect('applications.php');
    }
}

$pageTitle = 'Apply — ' . e($job['title']);
$base = '../';
$active = 'applications';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Apply for <?= e($job['title']) ?></h1>
        <p><?= e($job['company_name']) ?></p>
      </div>
      <a href="../job-details.php?id=<?= $jobId ?>" class="btn btn-outline">&larr; Back to job</a>
    </div>

    <?php if ($errors): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:18px;">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- no CV on file yet, so point them to the upload page instead of the form -->
    <?php if (!$resumes): ?>
      <div class="card"><div class="card-body empty-state">
        <h3>You need a CV to apply</h3>
        <p>Upload your CV first, then come back to apply for this job.</p>
        <a href="resume.php" class="btn btn-primary">Upload your CV</a>
      </div></div>
    <?php else: ?>
      <div class="card">
        <div class="card-body">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="job_id" value="<?= $jobId ?>">

            <div class="form-group">
              <label for="resume_id">Which CV should we send?</label>
              <select id="resume_id" name="resume_id" required>
                <?php foreach ($resumes as $r): ?>
                  <option value="<?= (int) $r['resume_id'] ?>" <?= $r['is_default'] ? 'selected' : '' ?>><?= e($r['file_name']) ?><?= $r['is_default'] ? ' (default)' : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="cover_letter">Cover letter (optional)</label>
              <textarea id="cover_letter" name="cover_letter" rows="6" placeholder="Tell the employer why you're a good fit..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Submit application</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
