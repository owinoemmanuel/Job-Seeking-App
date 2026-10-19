<?php
// Seeker: choose the skills that go on your profile.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('job_seeker');

$db = getDB();
$seekerId = current_seeker_id();

// two forms post here: one adds a new skill, the other saves the whole picked list
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!empty($_POST['new_skill'])) {
        // a skill typed in by hand, reuse it if somebody already added it
        $newSkill = trim($_POST['new_skill']);
        $existing = $db->prepare('SELECT skill_id FROM skills WHERE skill_name = ?');
        $existing->execute([$newSkill]);
        $row = $existing->fetch();
        if ($row) {
            $skillId = $row['skill_id'];
        } else {
            $db->prepare('INSERT INTO skills (skill_name) VALUES (?)')->execute([$newSkill]);
            $skillId = $db->lastInsertId();
        }
        // insert ignore, so picking the same skill twice does not cause an error
        $ins = $db->prepare('INSERT IGNORE INTO job_seeker_skills (seeker_id, skill_id) VALUES (?, ?)');
        $ins->execute([$seekerId, $skillId]);
        flash('success', 'Skill added.');
        redirect('skills.php');
    }

    // saving replaces the old list with whatever is picked now
    $db->prepare('DELETE FROM job_seeker_skills WHERE seeker_id = ?')->execute([$seekerId]);
    // ids come from the browser, so make them numbers and drop repeats
    $skillIds = array_unique(array_map('intval', $_POST['skill_ids'] ?? []));
    if ($skillIds) {
        $stmt = $db->prepare('INSERT IGNORE INTO job_seeker_skills (seeker_id, skill_id) VALUES (?, ?)');
        foreach ($skillIds as $sid) {
            $stmt->execute([$seekerId, $sid]);
        }
    }
    flash('success', 'Skills updated.');
    redirect('skills.php');
}

// every skill for the drop-down, plus the ones this person already has
$allSkills = $db->query('SELECT * FROM skills ORDER BY skill_name')->fetchAll();
$mySkillsStmt = $db->prepare('SELECT skill_id FROM job_seeker_skills WHERE seeker_id = ?');
$mySkillsStmt->execute([$seekerId]);
$mySkillIds = array_column($mySkillsStmt->fetchAll(), 'skill_id');

$pageTitle = 'Skills — KaziLink';
$base = '../';
$active = 'skills';
include __DIR__ . '/../includes/header.php';
?>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/sidebar-seeker.php'; ?>

  <main class="dash-main">
    <div class="dash-header"><div><h1>Skills</h1><p>Pick skills that match the jobs you want.</p></div></div>

    <div class="card">
      <div class="card-body">
        <form method="post" id="skills-form" data-selected='<?= json_encode(array_map('strval', $mySkillIds)) ?>'>
          <?= csrf_field() ?>
          <!-- main.js fills in the chips and hidden inputs from data-selected -->
          <div id="skill-chips" style="margin-bottom:14px;"></div>
          <div id="skill-hidden-inputs"></div>

          <div class="form-group">
            <label for="skill-select">Add a skill</label>
            <select id="skill-select">
              <option value="">Choose a skill to add...</option>
              <?php foreach ($allSkills as $skill): ?>
                <option value="<?= (int) $skill['skill_id'] ?>"><?= e($skill['skill_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="btn btn-primary">Save skills</button>
        </form>

        <!-- second form, for a skill that is not in the drop-down -->
        <div class="divider"></div>

        <form method="post" style="display:flex; gap:10px; max-width:420px;">
          <?= csrf_field() ?>
          <input type="text" name="new_skill" placeholder="Don't see your skill? Type it here" required>
          <button type="submit" class="btn btn-outline">Add</button>
        </form>
      </div>
    </div>
  </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
