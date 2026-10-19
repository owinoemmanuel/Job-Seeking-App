<?php
// Bottom of every page: the footer and main.js. The page sets $base, same as for header.php.
$base = $base ?? '';
?>
<footer class="site-footer">
  <div class="container">
    <div>
      <div class="brand" style="color:#fff; margin-bottom:12px;"><span class="brand-mark">Kazi</span>Link</div>
      <p style="max-width:38ch; color:rgba(255,255,255,0.6); font-size:14px;">
        Connecting job seekers and employers across Kenya — post a role, build a profile, and track every application in one place.
      </p>
    </div>
    <div>
      <h4>For job seekers</h4>
      <ul>
        <li><a href="<?= e($base) ?>jobs.php">Browse jobs</a></li>
        <li><a href="<?= e($base) ?>register.php?role=job_seeker">Create an account</a></li>
        <li><a href="<?= e($base) ?>login.php">Log in</a></li>
      </ul>
    </div>
    <div>
      <h4>For employers</h4>
      <ul>
        <li><a href="<?= e($base) ?>register.php?role=employer">Post a job</a></li>
        <li><a href="<?= e($base) ?>login.php">Employer login</a></li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    &copy; <?= date('Y') ?> KaziLink. Built to connect jobseekers to there dream jobs<br>
    For more Info Email: owinoe084@gmail.com
  </div>
</footer>
<script src="<?= e($base) ?>assets/js/main.js"></script>
</body>
</html>
