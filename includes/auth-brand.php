<?php
// Logo panel on the left of the log in and sign up pages. Both include this file,
// and the page sets $base first ('' in the root folder). To change the logo:
// replace assets/images/logo.png (same name) or change the src below, and if the
// new logo has a different background, change #02173e under .auth-brand in
// assets/css/style.css to match so the image has no edge.
$base = $base ?? '';
?>
<div class="auth-brand">
  <div class="auth-brand-inner">
    <img src="<?= e($base) ?>assets/images/logo.png" width="962" height="773" alt="KaziLink — Find jobs, grow your career">
  </div>
</div>
