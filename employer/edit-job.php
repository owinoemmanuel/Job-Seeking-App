<?php
// Employer: change one of my own job posts and save it.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('employer');

$db = getDB();
$employerId = current_employer_id();
$jobId = (int) ($_GET['id'] ?? $_POST['job_id'] ?? 0);

// the employer_id check means nobody can load someone else's job
$jobStmt = $db->prepare('SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?');
$jobStmt->execute([$jobId, $employerId]);
$job = $jobStmt->fetch();

// no job found, or it is not mine
if (!$job) {
    flash('error', 'Job not found.');
    redirect('my-jobs.php');
}

// categories for the dropdown
$categories = $db->query('SELECT * FROM job_categories ORDER BY category_name')->fetchAll();
$errors = [];

// form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title = trim($_POST['title'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $jobType = $_POST['job_type'] ?? '';
    $expLevel = $_POST['experience_level'] ?? '';
    $salary = trim($_POST['salary'] ?? '');
    $deadline = $_POST['deadline'] ?? '';

    // only title, description and a numeric salary are checked
    if ($title === '') $errors[] = 'Job title is required.';
    if ($description === '') $errors[] = 'Job description is required.';
    if ($salary !== '' && !is_numeric($salary)) $errors[] = 'Salary must be a number.';

    if (!$errors) {
        // a rejected job goes back to pending when edited, so the admin sees it again
        $newStatus = $job['status'] === 'rejected' ? 'pending' : $job['status'];

        $stmt = $db->prepare('
            UPDATE jobs SET category_id=?, title=?, description=?, requirements=?, location=?, job_type=?, experience_level=?, salary=?, deadline=?, status=?
            WHERE job_id=? AND employer_id=?
        ');
        // optional fields that are left empty are saved as NULL
        $stmt->execute([
            $categoryId ?: null, $title, $description, $requirements, $location, $jobType, $expLevel,
            $salary !== '' ? $salary : null, $deadline ?: null, $newStatus, $jobId, $employerId,
        ]);
        flash('success', 'Job updated' . ($newStatus === 'pending' ? ' and resubmitted for approval.' : '.'));
        redirect('my-jobs.php');
    }

    // after an error, put what was typed back into $job so the form does not show the old saved values
    $job = array_merge($job, [
        'title' => $title, 'category_id' => $categoryId, 'description' => $description,
        'requirements' => $requirements, 'location' => $location, 'job_type' => $jobType,
        'experience_level' => $expLevel, 'salary' => $salary, 'deadline' => $deadline,
    ]);
}

$pageTitle = 'Edit job — KaziLink';
$base = '../';
$active = 'my-jobs';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-employer.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Edit job</h1><p><?= e($job['title']) ?></p></div></div>

    <!-- error messages -->
    <?php if ($errors): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:18px;"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <!-- the job id goes along so the page knows which job to save when the form is sent -->
          <input type="hidden" name="job_id" value="<?= $jobId ?>">

          <div class="form-group">
            <label for="title">Job title</label>
            <input type="text" id="title" name="title" value="<?= e($job['title']) ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="category_id">Category</label>
              <select id="category_id" name="category_id">
                <option value="">Select a category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= (int) $cat['category_id'] ?>" <?= (string) $job['category_id'] === (string) $cat['category_id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="location">Location</label>
              <input type="text" id="location" name="location" value="<?= e($job['location']) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="job_type">Job type</label>
              <select id="job_type" name="job_type">
                <?php foreach (['Full-time','Part-time','Internship','Contract'] as $type): ?>
                  <option value="<?= $type ?>" <?= $job['job_type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="experience_level">Experience level</label>
              <select id="experience_level" name="experience_level">
                <?php foreach (['Entry','Mid','Senior'] as $lvl): ?>
                  <option value="<?= $lvl ?>" <?= $job['experience_level'] === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="salary">Monthly salary (KES, optional)</label>
              <input type="number" id="salary" name="salary" value="<?= e($job['salary']) ?>">
            </div>
            <div class="form-group">
              <label for="deadline">Application deadline</label>
              <input type="date" id="deadline" name="deadline" value="<?= e($job['deadline']) ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="description">Job description</label>
            <textarea id="description" name="description" rows="6" required><?= e($job['description']) ?></textarea>
          </div>

          <div class="form-group">
            <label for="requirements">Requirements</label>
            <textarea id="requirements" name="requirements" rows="4"><?= e($job['requirements']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
