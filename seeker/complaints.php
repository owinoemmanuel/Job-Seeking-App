<?php
// Seeker: send a complaint to the admin and read the replies.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$userId = current_user_id();

// old databases need a few extra columns before this page can work
ensure_complaint_columns();

$types = [
    'job'      => 'A job listing',
    'employer' => 'An employer',
    'general'  => 'KaziLink itself (website problem, my account, anything else)',
];

// shorter names for the "Your complaints" list below
$typeNames = ['job' => 'Job listing', 'employer' => 'Employer', 'user' => 'User', 'general' => 'KaziLink'];

$errors = [];

// The "Report this listing" link on a job page sends us here with ?job_id=12,
// so start the form already pointed at that job.
$form = ['type' => 'general', 'job_id' => 0, 'employer_id' => 0, 'subject' => '', 'details' => ''];
if (isset($_GET['job_id'])) {
    $form['type'] = 'job';
    $form['job_id'] = (int) $_GET['job_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // delete one of my complaints, but only after the admin has dealt with it
    if (isset($_POST['delete_id'])) {
        $reportId = (int) $_POST['delete_id'];

        // reporter_id is in the query so nobody can delete another person's complaint
        $stmt = $db->prepare("UPDATE reports SET deleted_by_seeker = 1 WHERE report_id = ? AND reporter_id = ? AND status <> 'open'");
        $stmt->execute([$reportId, $userId]);

        if ($stmt->rowCount() > 0) {
            purge_deleted_complaint($reportId);
            flash('info', 'Complaint deleted.');
        } else {
            flash('error', 'Only complaints the admin has reviewed or dismissed can be deleted.');
        }
        redirect('complaints.php');
    }

    $form['type'] = $_POST['type'] ?? 'general';
    $form['job_id'] = (int) ($_POST['job_id'] ?? 0);
    $form['employer_id'] = (int) ($_POST['employer_id'] ?? 0);
    $form['subject'] = trim($_POST['subject'] ?? '');
    $form['details'] = trim($_POST['details'] ?? '');

    if (!isset($types[$form['type']])) {
        $form['type'] = 'general';
    }

    // work out what the complaint points at
    $targetId = null;
    if ($form['type'] === 'job') {
        $check = $db->prepare('SELECT job_id FROM jobs WHERE job_id = ?');
        $check->execute([$form['job_id']]);
        if ($check->fetch()) {
            $targetId = $form['job_id'];
        } else {
            $errors[] = 'Please choose the job you are complaining about.';
        }
    } elseif ($form['type'] === 'employer') {
        $check = $db->prepare('SELECT employer_id FROM employers WHERE employer_id = ?');
        $check->execute([$form['employer_id']]);
        if ($check->fetch()) {
            $targetId = $form['employer_id'];
        } else {
            $errors[] = 'Please choose the employer you are complaining about.';
        }
    }

    if ($form['subject'] === '') {
        $errors[] = 'Give your complaint a short subject.';
    } elseif (mb_strlen($form['subject']) > 150) {
        $errors[] = 'The subject is too long (150 characters at most).';
    }

    if (mb_strlen($form['details']) < 10) {
        $errors[] = 'Please explain what happened (at least 10 characters).';
    } elseif (mb_strlen($form['details']) > 2000) {
        $errors[] = 'The details are too long (2000 characters at most).';
    }

    // stop one person from flooding the admin with complaints
    if (!$errors) {
        $recent = $db->prepare('SELECT COUNT(*) FROM reports WHERE reporter_id = ? AND created_at > (NOW() - INTERVAL 1 DAY)');
        $recent->execute([$userId]);
        if ((int) $recent->fetchColumn() >= 5) {
            $errors[] = 'You have already sent 5 complaints today. Please wait for a reply before sending more.';
        }
    }

    if (!$errors) {
        $stmt = $db->prepare('INSERT INTO reports (reporter_id, target_type, target_id, subject, reason) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $form['type'], $targetId, $form['subject'], $form['details']]);

        flash('success', 'Your complaint has been sent. You will get a notification when the admin replies.');
        redirect('complaints.php');
    }
}

// the lists behind the two drop-downs
$jobStmt = $db->prepare("
    SELECT j.job_id, j.title, e.company_name
    FROM jobs j JOIN employers e ON e.employer_id = j.employer_id
    WHERE j.status = 'active' OR j.job_id = ?
    ORDER BY j.title
    LIMIT 300
");
$jobStmt->execute([$form['job_id']]);
$jobOptions = $jobStmt->fetchAll();

$employerOptions = $db->query('SELECT employer_id, company_name FROM employers ORDER BY company_name LIMIT 300')->fetchAll();

// this person's earlier complaints, without the ones they deleted
$stmt = $db->prepare("
    SELECT r.*, j.title AS job_title, e.company_name
    FROM reports r
    LEFT JOIN jobs j ON r.target_type = 'job' AND j.job_id = r.target_id
    LEFT JOIN employers e ON r.target_type = 'employer' AND e.employer_id = r.target_id
    WHERE r.reporter_id = ? AND r.deleted_by_seeker = 0
    ORDER BY r.created_at DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$complaints = $stmt->fetchAll();

$pageTitle = 'Complaints — KaziLink';
$base = '../';
$active = 'complaints';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Complaints</h1><p>Tell the admin team about a problem. Their reply shows up on this page and in your notifications. Once a complaint is reviewed or dismissed you can delete it.</p></div></div>

    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h3>Send a complaint</h3></div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="flash flash-error" style="position:static; margin-bottom:18px;">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="complaints.php">
          <?= csrf_field() ?>

          <div class="form-group">
            <label for="type">What is it about?</label>
            <select id="type" name="type">
              <?php foreach ($types as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $form['type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" id="job-field">
            <label for="job_id">Which job?</label>
            <select id="job_id" name="job_id">
              <option value="0">Choose a job...</option>
              <?php foreach ($jobOptions as $job): ?>
                <option value="<?= (int) $job['job_id'] ?>" <?= $form['job_id'] === (int) $job['job_id'] ? 'selected' : '' ?>><?= e($job['title']) ?> (<?= e($job['company_name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" id="employer-field">
            <label for="employer_id">Which employer?</label>
            <select id="employer_id" name="employer_id">
              <option value="0">Choose an employer...</option>
              <?php foreach ($employerOptions as $emp): ?>
                <option value="<?= (int) $emp['employer_id'] ?>" <?= $form['employer_id'] === (int) $emp['employer_id'] ? 'selected' : '' ?>><?= e($emp['company_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" maxlength="150" value="<?= e($form['subject']) ?>" placeholder="e.g. This job asks for money before an interview" required>
          </div>

          <div class="form-group">
            <label for="details">What happened?</label>
            <textarea id="details" name="details" rows="5" maxlength="2000" placeholder="Give as much detail as you can so the admin can look into it." required><?= e($form['details']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">Send complaint</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Your complaints</h3></div>
      <div class="card-body">
        <?php if ($complaints): foreach ($complaints as $c): ?>
          <div class="complaint">
            <div class="complaint-head">
              <div>
                <h4><?= e(complaint_subject($c)) ?></h4>
                <div class="meta">
                  <?= e($typeNames[$c['target_type']] ?? 'Other') ?>
                  <?php if ($c['job_title']): ?> &middot; <?= e($c['job_title']) ?><?php endif; ?>
                  <?php if ($c['company_name']): ?> &middot; <?= e($c['company_name']) ?><?php endif; ?>
                  &middot; sent <?= time_ago($c['created_at']) ?>
                </div>
              </div>
              <span class="pill <?= status_pill_class($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span>
            </div>

            <p><?= e($c['reason']) ?></p>

            <?php if (!empty($c['admin_reply'])): ?>
              <div class="complaint-reply">
                <strong>Admin reply</strong><?php if ($c['replied_at']): ?> <span class="text-muted">&middot; <?= time_ago($c['replied_at']) ?></span><?php endif; ?>
                <div><?= e($c['admin_reply']) ?></div>
              </div>
            <?php elseif ($c['status'] === 'open'): ?>
              <div class="text-muted text-sm" style="margin-top:10px;">Waiting for the admin team to look at this.</div>
            <?php endif; ?>

            <!-- the delete button only shows once the complaint is no longer open -->
            <?php if ($c['status'] !== 'open'): ?>
              <form method="post" class="complaint-delete" data-confirm="Delete this complaint? It will be removed from your list.">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= (int) $c['report_id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><h3>No complaints yet</h3><p>If something is wrong with a job, an employer or the website, tell us above.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script>
  // only show the job or employer drop-down that matches what the complaint is about
  (function () {
    var type = document.getElementById('type');
    var jobField = document.getElementById('job-field');
    var employerField = document.getElementById('employer-field');

    function update() {
      jobField.style.display = type.value === 'job' ? 'block' : 'none';
      employerField.style.display = type.value === 'employer' ? 'block' : 'none';
    }

    type.addEventListener('change', update);
    update();
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
