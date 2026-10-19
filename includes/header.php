<?php
// Top of every page (head and nav bar). The page sets $pageTitle and $base first,
// $base is '' in the root folder and '../' inside seeker, employer and admin.
$base = $base ?? '';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'KaziLink') ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>💼</text></svg>">
<link rel="stylesheet" href="<?= e($base) ?>assets/css/style.css">
</head>
<body>

<?php $flashes = get_flashes(); if ($flashes): ?>
<div class="flash-stack">
  <?php foreach ($flashes as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="container">
    <a href="<?= e($base) ?>index.php" class="brand"><span class="brand-mark">Kazi</span>Link</a>
    <button class="nav-toggle" aria-label="Toggle menu">&#9776;</button>
    <nav class="main-nav">
      <a href="<?= e($base) ?>jobs.php">Find jobs</a>
      <?php if (!$user || $user['role'] === 'employer'): ?>
        <a href="<?= e($base) ?>register.php?role=employer">For employers</a>
      <?php endif; ?>
      <a href="<?= e($base) ?>index.php#how-it-works">How it works</a>

      <?php if ($user): ?>
        <?php if ($user['role'] === 'job_seeker'): ?>
          <a href="<?= e($base === '' ? 'seeker/' : '') ?>dashboard.php">Dashboard</a>
        <?php elseif ($user['role'] === 'employer'): ?>
          <a href="<?= e($base === '' ? 'employer/' : '') ?>dashboard.php">Dashboard</a>
        <?php elseif ($user['role'] === 'admin'): ?>
          <a href="<?= e($base === '' ? 'admin/' : '') ?>dashboard.php">Dashboard</a>
        <?php endif; ?>
        <div class="nav-actions">
          <span class="btn btn-outline-light btn-sm" style="pointer-events:none;">Hi, <?= e(explode(' ', $user['full_name'])[0]) ?></span>
          <a href="<?= e($base) ?>logout.php" class="btn btn-primary btn-sm">Log out</a>
        </div>
      <?php else: ?>
        <div class="nav-actions">
          <a href="<?= e($base) ?>login.php" class="btn btn-outline-light btn-sm">Log in</a>
          <a href="<?= e($base) ?>register.php" class="btn btn-primary btn-sm">Sign up free</a>
        </div>
      <?php endif; ?>
    </nav>
  </div>
</header>
