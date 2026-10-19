<?php
// Employer: see the full profile of someone who applied to one of my jobs.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('employer');

$db = getDB();
$employerId = current_employer_id();
$seekerId = (int) ($_GET['seeker_id'] ?? 0);

// employers can only open profiles of people who applied to their jobs
$guard = $db->prepare("
    SELECT COUNT(*) FROM applications a JOIN jobs j ON j.job_id = a.job_id
    WHERE a.seeker_id = ? AND j.employer_id = ?
");
$guard->execute([$seekerId, $employerId]);
if (!$guard->fetchColumn()) {
    flash('error', 'That candidate has not applied to any of your jobs.');
    redirect('applicants.php');
}

// the seeker's profile, then their education, experience, skills and CVs
$stmt = $db->prepare('SELECT u.full_name, u.email, u.phone, js.* FROM job_seekers js JOIN users u ON u.user_id = js.user_id WHERE js.seeker_id = ?');
$stmt->execute([$seekerId]);
$profile = $stmt->fetch();

$eduStmt = $db->prepare('SELECT * FROM education WHERE seeker_id = ? ORDER BY start_date DESC');
$eduStmt->execute([$seekerId]);
$education = $eduStmt->fetchAll();

$expStmt = $db->prepare('SELECT * FROM work_experience WHERE seeker_id = ? ORDER BY start_date DESC');
$expStmt->execute([$seekerId]);
$experience = $expStmt->fetchAll();

$skillsStmt = $db->prepare('SELECT s.skill_name, jss.proficiency FROM job_seeker_skills jss JOIN skills s ON s.skill_id = jss.skill_id WHERE jss.seeker_id = ?');
$skillsStmt->execute([$seekerId]);
$skills = $skillsStmt->fetchAll();

// default CV first
$resumeStmt = $db->prepare('SELECT * FROM resumes WHERE seeker_id = ? ORDER BY is_default DESC');
$resumeStmt->execute([$seekerId]);
$resumes = $resumeStmt->fetchAll();

$pageTitle = e($profile['full_name']) . ' — KaziLink';
$base = '../';
$active = 'applicants';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-employer.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div><h1><?= e($profile['full_name']) ?></h1><p><?= e($profile['email']) ?><?= $profile['phone'] ? ' · ' . e($profile['phone']) : '' ?></p></div>
      <a href="applicants.php" class="btn btn-outline">&larr; Back to applicants</a>
    </div>

    <!-- left side: summary, work experience, education. right side: skills and CVs -->
    <div style="display:grid; grid-template-columns: 1fr 300px; gap:20px; align-items:start;">
      <div>
        <div class="card" style="margin-bottom:20px;">
          <div class="card-header"><h3>Summary</h3></div>
          <div class="card-body">
            <p><?= e($profile['professional_summary'] ?: 'No summary provided.') ?></p>
            <p class="text-muted text-sm mb-0">📍 <?= e($profile['location'] ?: 'Location not set') ?></p>
          </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
          <div class="card-header"><h3>Work experience</h3></div>
          <div class="card-body">
            <?php if ($experience): foreach ($experience as $exp): ?>
              <div class="entry-row">
                <div>
                  <h4><?= e($exp['job_title'] ?: 'Role') ?> &middot; <?= e($exp['company_name']) ?></h4>
                  <?php if ($exp['description']): ?><p class="meta" style="margin:4px 0;"><?= e($exp['description']) ?></p><?php endif; ?>
                  <div class="meta"><?= $exp['start_date'] ? date('M Y', strtotime($exp['start_date'])) : '' ?> &ndash; <?= $exp['end_date'] ? date('M Y', strtotime($exp['end_date'])) : 'Present' ?></div>
                </div>
              </div>
            <?php endforeach; else: ?>
              <p class="text-muted text-sm mb-0">No work experience listed.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3>Education</h3></div>
          <div class="card-body">
            <?php if ($education): foreach ($education as $edu): ?>
              <div class="entry-row">
                <div>
                  <h4><?= e($edu['qualification'] ?: 'Studies') ?><?= $edu['field_of_study'] ? ' — ' . e($edu['field_of_study']) : '' ?></h4>
                  <div class="meta"><?= e($edu['institution']) ?></div>
                  <div class="meta"><?= $edu['start_date'] ? date('M Y', strtotime($edu['start_date'])) : '' ?> &ndash; <?= $edu['end_date'] ? date('M Y', strtotime($edu['end_date'])) : 'Present' ?></div>
                </div>
              </div>
            <?php endforeach; else: ?>
              <p class="text-muted text-sm mb-0">No education listed.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div>
        <div class="card" style="margin-bottom:20px;">
          <div class="card-header"><h3>Skills</h3></div>
          <div class="card-body">
            <?php if ($skills): foreach ($skills as $skill): ?>
              <span class="tag" style="margin:0 6px 6px 0; display:inline-block;"><?= e($skill['skill_name']) ?></span>
            <?php endforeach; else: ?>
              <p class="text-muted text-sm mb-0">No skills listed.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- CVs open in a new tab through view-resume.php -->
        <div class="card">
          <div class="card-header"><h3>CV / Resume</h3></div>
          <div class="card-body">
            <?php if ($resumes): foreach ($resumes as $r): ?>
              <a href="../view-resume.php?id=<?= (int) $r['resume_id'] ?>" target="_blank" class="btn btn-outline btn-block" style="margin-bottom:8px;">📄 <?= e($r['file_name']) ?></a>
            <?php endforeach; else: ?>
              <p class="text-muted text-sm mb-0">No CV uploaded.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
