<?php
// Logs the person out and sends them to the home page.
require_once __DIR__ . '/includes/bootstrap.php';
logout_user();
// the old session is gone, so start a new one just to carry the message below
session_start();
flash('info', 'You have been logged out.');
redirect('index.php');
