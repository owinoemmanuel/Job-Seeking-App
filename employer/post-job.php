<?php
// Employer: post a new job. It waits for admin approval before it shows publicly.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('employer');

$db = getDB();
$employerId = current_employer_id();
// categories for the dropdown
$categories = $db->query('SELECT * FROM job_categories ORDER BY category_name')->fetchAll();

// the saved company details are used to fill the form in
$profileStmt = $db->prepare('SELECT company_name, description FROM employers WHERE employer_id = ?');
$profileStmt->execute([$employerId]);
$employerProfile = $profileStmt->fetch() ?: ['company_name' => '', 'description' => ''];

// $old holds the form values, so they stay in the boxes when there is an error
$errors = [];
$old = [
    'title' => '', 'category_id' => '', 'description' => '', 'requirements' => '', 'location' => '',
    'job_type' => 'Full-time', 'experience_level' => 'Entry', 'salary' => '', 'deadline' => '',
    'company_name' => $employerProfile['company_name'] ?? '',
    'about_company' => $employerProfile['description'] ?? '',
];

// form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $old['title'] = trim($_POST['title'] ?? '');
    $old['category_id'] = $_POST['category_id'] ?? '';
    $old['description'] = trim($_POST['description'] ?? '');
    $old['requirements'] = trim($_POST['requirements'] ?? '');
    $old['location'] = trim($_POST['location'] ?? '');
    $old['job_type'] = $_POST['job_type'] ?? '';
    $old['experience_level'] = $_POST['experience_level'] ?? '';
    $old['salary'] = trim($_POST['salary'] ?? '');
    $old['deadline'] = $_POST['deadline'] ?? '';
    $old['company_name'] = trim($_POST['company_name'] ?? '');
    $old['about_company'] = trim($_POST['about_company'] ?? '');

    // required fields, and salary has to be a number
    if ($old['title'] === '') $errors[] = 'Job title is required.';
    if ($old['description'] === '') $errors[] = 'Job description is required.';
    if ($old['company_name'] === '') $errors[] = 'Company name is required.';
    if ($old['salary'] !== '' && !is_numeric($old['salary'])) $errors[] = 'Salary must be a number.';

    if (!$errors) {
        // also save the company details to the profile, candidates see them on the job page
        $db->prepare('UPDATE employers SET company_name = ?, description = ? WHERE employer_id = ?')
           ->execute([$old['company_name'], $old['about_company'], $employerId]);

        // new jobs always start as pending until an admin approves them
        // (optional fields left empty are saved as NULL)
        $stmt = $db->prepare('
            INSERT INTO jobs (employer_id, category_id, title, description, requirements, location, job_type, experience_level, salary, deadline, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $employerId,
            $old['category_id'] ?: null,
            $old['title'],
            $old['description'],
            $old['requirements'],
            $old['location'],
            $old['job_type'],
            $old['experience_level'],
            $old['salary'] !== '' ? $old['salary'] : null,
            $old['deadline'] ?: null,
            'pending',
        ]);
        flash('success', 'Job submitted! It will appear publicly once an administrator approves it.');
        redirect('my-jobs.php');
    }
}

$pageTitle = 'Post a job — KaziLink';
$base = '../';
$active = 'post-job';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-employer.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Post a job</h1><p>New postings are reviewed by an administrator before going live.</p></div></div>

    <!-- error messages -->
    <?php if ($errors): ?>
      <div class="flash flash-error" style="position:static; margin-bottom:18px;"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <!-- the job form, company name and about text also update the profile -->
        <form method="post">
          <?= csrf_field() ?>

          <div class="form-row">
            <div class="form-group">
              <label for="company_name">Company name</label>
              <input type="text" id="company_name" name="company_name" value="<?= e($old['company_name']) ?>" required placeholder="e.g. Abacus Technologies">
            </div>
            <div class="form-group">
              <label for="title">Job title</label>
              <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" required placeholder="e.g. ICT Intern">
            </div>
          </div>

          <div class="form-group">
            <label for="about_company">About your company</label>
            <textarea id="about_company" name="about_company" rows="3" placeholder="A sentence or two candidates will see on the job listing..."><?= e($old['about_company']) ?></textarea>
            <div class="field-hint">This updates your company profile and is shown to every candidate who views this job.</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="category_id">Category</label>
              <select id="category_id" name="category_id">
                <option value="">Select a category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= (int) $cat['category_id'] ?>" <?= (string) $old['category_id'] === (string) $cat['category_id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="location">Location</label>
              <input type="text" id="location" name="location" value="<?= e($old['location']) ?>" placeholder="e.g. Meru">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="job_type">Job type</label>
              <select id="job_type" name="job_type">
                <?php foreach (['Full-time','Part-time','Internship','Contract'] as $type): ?>
                  <option value="<?= $type ?>" <?= $old['job_type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="experience_level">Experience level</label>
              <select id="experience_level" name="experience_level">
                <?php foreach (['Entry','Mid','Senior'] as $lvl): ?>
                  <option value="<?= $lvl ?>" <?= $old['experience_level'] === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="salary">Monthly salary (KES, optional)</label>
              <input type="number" id="salary" name="salary" value="<?= e($old['salary']) ?>" placeholder="e.g. 40000">
            </div>
            <div class="form-group">
              <label for="deadline">Application deadline</label>
              <input type="date" id="deadline" name="deadline" value="<?= e($old['deadline']) ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="description">Job description</label>
            <textarea id="description" name="description" rows="6" required><?= e($old['description']) ?></textarea>
          </div>

          <div class="form-group">
            <label for="requirements">Requirements</label>
            <textarea id="requirements" name="requirements" rows="4"><?= e($old['requirements']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">Submit for approval</button>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
