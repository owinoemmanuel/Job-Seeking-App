<?php
// Connects to MySQL. Edit the DB_ constants below to match your setup.

// XAMPP, WAMP and MAMP usually use user root with no password
define('DB_HOST', 'localhost');
define('DB_NAME', 'kazilink');   // created by database/kazilink.sql
define('DB_USER', 'root');
define('DB_PASS', '');

// one shared connection, only opened the first time this is called
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // stop with a readable message when MySQL can't be reached
            die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #e2b8b8;background:#fdf2f2;border-radius:8px;color:#7a2727;">'
                . '<h2 style="margin-top:0;">Database connection failed</h2>'
                . '<p>KaziLink could not connect to MySQL. Check that MySQL is running and that '
                . '<code>config/database.php</code> has the correct host, database name, username and password.</p>'
                . '<p style="font-size:13px;color:#9a4a4a;">Detail: ' . htmlspecialchars($e->getMessage()) . '</p>'
                . '</div>');
        }
    }

    return $pdo;
}
