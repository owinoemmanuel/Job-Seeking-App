<?php
// Include this first on every page. It sets up errors, the database, helpers and the session.

// Keep this false. When it is on, a PHP warning prints into the page, even inside
// a value="..." box, and gets saved as real data when the form is submitted
// (that is how "Undefined array key" ended up as a name while testing).
// Warnings go to php-error.log instead. Set it to true only while developing.
const APP_DEBUG = false;

// Forgot password. XAMPP/WAMP usually can't send email, so on your own computer
// the reset link is shown on the page. That is fine on a private machine, but on a
// live site anyone could reset anyone's password just by typing their email.
//   'auto'  show the link only when the page is opened from the same computer
//           as the server (localhost), on a real website it is emailed instead
//   true    always show it on screen (never on a live site)
//   false   never show it, email only
const SHOW_RESET_LINK_ON_SCREEN = 'auto';

// The public address of the app, used for the link in reset emails,
// e.g. 'https://www.yoursite.com/kazilink'. Leave empty on localhost.
// Set it on a live site, or the link uses whatever address the visitor typed (which can be faked).
const APP_BASE_URL = '';

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../php-error.log');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
