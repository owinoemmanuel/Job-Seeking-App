<?php
// Home page: search box, latest jobs and categories.
require_once __DIR__ . '/includes/bootstrap.php';

$db = getDB();

// numbers for the hero stats (jobs only count when they are active)
$totalJobs = (int) $db->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn();
$totalEmployers = (int) $db->query("SELECT COUNT(*) FROM employers")->fetchColumn();
$totalSeekers = (int) $db->query("SELECT COUNT(*) FROM job_seekers")->fetchColumn();

// the 6 newest active jobs. LEFT JOIN so a job with no category still shows up
$latestJobs = $db->query("
    SELECT j.*, e.company_name, c.category_name
    FROM jobs j
    JOIN employers e ON e.employer_id = j.employer_id
    LEFT JOIN job_categories c ON c.category_id = j.category_id
    WHERE j.status = 'active'
    ORDER BY j.created_at DESC
    LIMIT 6
")->fetchAll();

// each category with how many active jobs it has, biggest first
$categories = $db->query("
    SELECT c.category_id, c.category_name, COUNT(j.job_id) AS job_count
    FROM job_categories c
    LEFT JOIN jobs j ON j.category_id = c.category_id AND j.status = 'active'
    GROUP BY c.category_id, c.category_name
    ORDER BY job_count DESC
")->fetchAll();

$pageTitle = 'KaziLink — Find work. Hire talent.';
// $base is the path back to the site root, empty here because this page is in the root folder
$base = '';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero background: swap the url() in the style below for your own image (a link to an image online works too).
     The dark layer on top is in assets/css/style.css under .hero::before if you want it lighter or darker. -->
<section class="hero" style="background-image: url('assets/images/home-bg.jpg');">
  <div class="container">
    <div>
      <div class="hero-eyebrow">A job board built for Kenya</div>
      <h1>Find work you're proud of. Hire people who'll stay.</h1>
      <p class="lead">KaziLink connects job seekers with employers across every county — search real openings, build a profile once, and apply in a few clicks.</p>

      <!-- quick search, jobs.php reads these two boxes -->
      <form action="jobs.php" method="get" class="search-panel">
        <input type="text" name="q" placeholder="Job title or keyword (e.g. ICT Intern)">
        <input type="text" name="location" placeholder="Location (e.g. Meru)">
        <button type="submit" class="btn btn-primary">Search jobs</button>
      </form>

      <div class="hero-stats">
        <div>
          <div class="stat-num"><?= number_format($totalJobs) ?></div>
          <div class="stat-label">Open positions</div>
        </div>
        <div>
          <div class="stat-num"><?= number_format($totalEmployers) ?></div>
          <div class="stat-label">Employers hiring</div>
        </div>
        <div>
          <div class="stat-num"><?= number_format($totalSeekers) ?></div>
          <div class="stat-label">Job seekers registered</div>
        </div>
      </div>
    </div>

    <div class="hero-side">
      <h3>Why job seekers use KaziLink</h3>
      <ul>
        <li>One profile — CV, education and skills — used for every application</li>
        <li>Filter jobs by location, category, type and experience level</li>
        <li>Track every application from "Applied" to "Accepted" in one dashboard</li>
        <li>Get notified the moment an employer reviews your application</li>
      </ul>
      <a href="register.php?role=job_seeker" class="btn btn-primary btn-block" style="margin-top:18px;">Create your free profile</a>
    </div>
  </div>
</section>

<!-- latest jobs, each row opens the job details page -->
<section class="section section-alt">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Latest openings</h2>
        <p>Newly posted roles from employers on KaziLink.</p>
      </div>
      <a href="jobs.php" class="btn btn-outline">View all jobs</a>
    </div>

    <?php if ($latestJobs): ?>
      <div class="job-list">
        <?php foreach ($latestJobs as $job): ?>
          <a class="job-row" href="job-details.php?id=<?= (int) $job['job_id'] ?>">
            <div>
              <div class="job-row-title"><?= e($job['title']) ?></div>
              <div class="job-row-company"><?= e($job['company_name']) ?><?= $job['location'] ? ' · ' . e($job['location']) : '' ?></div>
            </div>
            <div class="job-row-meta"><span class="tag"><?= e($job['job_type'] ?: 'Full-time') ?></span></div>
            <div class="job-row-meta"><span><?= e($job['category_name'] ?? 'General') ?></span></div>
            <div class="job-row-meta"><span><?= format_money($job['salary'] !== null ? (float)$job['salary'] : null) ?></span></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <h3>No jobs posted yet</h3>
        <p>Check back soon, or <a href="register.php?role=employer">post the first one</a>.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- category pills that open jobs.php already filtered -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Browse by category</h2>
        <p>Pick a field to see everything currently open.</p>
      </div>
    </div>
    <div class="category-strip">
      <?php foreach ($categories as $cat): ?>
        <a class="category-pill" href="jobs.php?category=<?= (int) $cat['category_id'] ?>">
          <?= e($cat['category_name']) ?> <span class="text-muted">(<?= (int) $cat['job_count'] ?>)</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- how it works, one panel for seekers and one for employers -->
<section class="section section-alt" id="how-it-works">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>How it works</h2>
        <p>Three steps, whichever side of the hiring table you're on.</p>
      </div>
    </div>
    <div class="two-col-panels">
      <div class="panel panel-accent">
        <h3 style="font-size:19px;">For job seekers</h3>
        <div class="divider"></div>
        <div style="display:flex; flex-direction:column; gap:16px;">
          <div><div class="step-num">1</div><h4 style="margin:0 0 4px;font-size:15.5px;">Build your profile</h4><p class="text-muted text-sm">Add your education, work experience, skills and CV once.</p></div>
          <div><div class="step-num">2</div><h4 style="margin:0 0 4px;font-size:15.5px;">Search and apply</h4><p class="text-muted text-sm">Filter by location, category, job type and experience level.</p></div>
          <div><div class="step-num">3</div><h4 style="margin:0 0 4px;font-size:15.5px;">Track your status</h4><p class="text-muted text-sm">Follow every application from Applied through to Accepted.</p></div>
        </div>
      </div>
      <div class="panel panel-accent-green">
        <h3 style="font-size:19px;">For employers</h3>
        <div class="divider"></div>
        <div style="display:flex; flex-direction:column; gap:16px;">
          <div><div class="step-num" style="color:var(--savanna);">1</div><h4 style="margin:0 0 4px;font-size:15.5px;">Set up your company profile</h4><p class="text-muted text-sm">Add your company details and logo so candidates know who's hiring.</p></div>
          <div><div class="step-num" style="color:var(--savanna);">2</div><h4 style="margin:0 0 4px;font-size:15.5px;">Post a job</h4><p class="text-muted text-sm">Your listing goes live once an administrator approves it.</p></div>
          <div><div class="step-num" style="color:var(--savanna);">3</div><h4 style="margin:0 0 4px;font-size:15.5px;">Review and shortlist</h4><p class="text-muted text-sm">Read CVs, shortlist or reject, straight from your dashboard.</p></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- sign-up buttons at the bottom -->
<section class="cta-band">
  <div class="container">
    <h2>Ready to get started?</h2>
    <p>It takes less than two minutes to create an account.</p>
    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
      <a href="register.php?role=job_seeker" class="btn btn-dark">I'm looking for work</a>
      <a href="register.php?role=employer" class="btn btn-primary">I'm hiring</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
