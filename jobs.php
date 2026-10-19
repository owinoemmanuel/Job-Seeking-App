<?php
// Public page: search the open jobs with filters.
require_once __DIR__ . '/includes/bootstrap.php';

$db = getDB();

// filters come from the URL, so a search can be bookmarked or shared
$q         = trim($_GET['q'] ?? '');
$location  = trim($_GET['location'] ?? '');
$categoryId = $_GET['category'] ?? '';
$jobType   = $_GET['job_type'] ?? '';
$expLevel  = $_GET['experience_level'] ?? '';
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 8;

// only active jobs, plus one more condition for each filter that was filled in
$where = ["j.status = 'active'"];
$params = [];

if ($q !== '') {
    $where[] = '(j.title LIKE ? OR j.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($location !== '') {
    $where[] = 'j.location LIKE ?';
    $params[] = "%$location%";
}
if ($categoryId !== '') {
    $where[] = 'j.category_id = ?';
    // make it a number, so junk in the URL just becomes 0
    $params[] = (int) $categoryId;
}
if ($jobType !== '') {
    $where[] = 'j.job_type = ?';
    $params[] = $jobType;
}
if ($expLevel !== '') {
    $where[] = 'j.experience_level = ?';
    $params[] = $expLevel;
}

$whereSql = implode(' AND ', $where);

// count every match first, so we know how many pages there are
$countStmt = $db->prepare("SELECT COUNT(*) FROM jobs j WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
[$totalPages, $page, $offset] = paginate($totalRows, $perPage, $page);

// only this page of jobs. perPage and offset are numbers we worked out ourselves,
// so they can go straight into the SQL
$stmt = $db->prepare("
    SELECT j.*, e.company_name, c.category_name
    FROM jobs j
    JOIN employers e ON e.employer_id = j.employer_id
    LEFT JOIN job_categories c ON c.category_id = j.category_id
    WHERE $whereSql
    ORDER BY j.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// all categories, for the dropdown
$categories = $db->query('SELECT * FROM job_categories ORDER BY category_name')->fetchAll();

// for a logged-in job seeker, find which jobs they already saved
$seekerSavedIds = [];
if (current_role() === 'job_seeker') {
    $sid = current_seeker_id();
    $savedStmt = $db->prepare('SELECT job_id FROM saved_jobs WHERE seeker_id = ?');
    $savedStmt->execute([$sid]);
    $seekerSavedIds = array_column($savedStmt->fetchAll(), 'job_id');
}

// builds the query string for links: keeps the current filters and changes
// only what is passed in (like the page number)
function qs(array $overrides = [])
{
    $params = array_merge($_GET, $overrides);
    return http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}

$pageTitle = 'Find jobs — KaziLink';
$base = '';
include __DIR__ . '/includes/header.php';
?>

<!-- dark banner with the number of results -->
<div class="section-tight" style="background:var(--ink);">
  <div class="container">
    <h1 style="color:#fff; font-size:30px; margin-bottom:6px;">Find your next role</h1>
    <p style="color:rgba(255,255,255,0.7); margin:0;"><?= number_format($totalRows) ?> open position<?= $totalRows === 1 ? '' : 's' ?> right now.</p>
  </div>
</div>

<div class="section">
  <div class="container">

    <!-- the dropdowns submit the form by themselves when they change (data-autosubmit) -->
    <form class="filter-bar" method="get" data-autosubmit>
      <div class="form-group" style="margin:0;">
        <label>Keyword</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Job title...">
      </div>
      <div class="form-group" style="margin:0;">
        <label>Location</label>
        <input type="text" name="location" value="<?= e($location) ?>" placeholder="e.g. Meru">
      </div>
      <div class="form-group" style="margin:0;">
        <label>Category</label>
        <select name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['category_id'] ?>" <?= (string) $categoryId === (string) $cat['category_id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;">
        <label>Job type</label>
        <select name="job_type">
          <option value="">Any type</option>
          <?php foreach (['Full-time','Part-time','Internship','Contract'] as $type): ?>
            <option value="<?= e($type) ?>" <?= $jobType === $type ? 'selected' : '' ?>><?= e($type) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;">
        <label>Experience</label>
        <select name="experience_level">
          <option value="">Any level</option>
          <?php foreach (['Entry','Mid','Senior'] as $lvl): ?>
            <option value="<?= e($lvl) ?>" <?= $expLevel === $lvl ? 'selected' : '' ?>><?= e($lvl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="grid-column: 1 / -1; display:flex; gap:10px;">
        <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
        <a href="jobs.php" class="btn btn-outline btn-sm">Clear</a>
      </div>
    </form>

    <?php if ($jobs): ?>
      <div class="job-list">
        <?php foreach ($jobs as $job):
          $isSaved = in_array($job['job_id'], $seekerSavedIds);
        ?>
          <div class="job-row">
            <div>
              <a href="job-details.php?id=<?= (int) $job['job_id'] ?>" class="job-row-title" style="display:block;"><?= e($job['title']) ?></a>
              <div class="job-row-company"><?= e($job['company_name']) ?></div>
            </div>
            <div class="job-row-meta">
              <span class="tag"><?= e($job['job_type'] ?: 'Full-time') ?></span>
              <span class="tag tag-gold"><?= e($job['experience_level'] ?: 'Any level') ?></span>
            </div>
            <div class="job-row-meta">
              <span>📍 <?= e($job['location'] ?: 'Remote') ?></span>
              <span><?= e($job['category_name'] ?? 'General') ?></span>
            </div>
            <div class="job-row-meta">
              <span><?= format_money($job['salary'] !== null ? (float) $job['salary'] : null) ?></span>
              <a href="job-details.php?id=<?= (int) $job['job_id'] ?>" class="btn btn-outline btn-sm">View</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- page numbers, only when there is more than one page -->
      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <?php if ($p === $page): ?>
              <span class="current"><?= $p ?></span>
            <?php else: ?>
              <a href="jobs.php?<?= e(qs(['page' => $p])) ?>"><?= $p ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <!-- nothing matched the filters -->
      <div class="empty-state">
        <h3>No jobs match your search</h3>
        <p>Try broadening your filters or clearing them entirely.</p>
        <a href="jobs.php" class="btn btn-outline">Clear filters</a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
