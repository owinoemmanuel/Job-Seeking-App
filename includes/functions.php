<?php
// Helper functions that most pages use.

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

// checks the "go back to" address after login/register (like a job the person wanted to apply for)
// and refuses full web addresses, so nobody gets sent to another site
function safe_redirect_target(?string $target, string $default): string
{
    if (!$target) return $default;
    if (preg_match('#^https?://#i', $target)) return $default;
    if (str_starts_with($target, '//')) return $default;
    if (str_contains($target, '\\')) return $default;
    return $target;
}

function base_url(string $path = ''): string
{
    // works at the web root or in a sub-folder
    $root = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    // the app root comes from APP_ROOT_URL if it is defined
    static $appRoot = null;
    if ($appRoot === null) {
        $appRoot = defined('APP_ROOT_URL') ? APP_ROOT_URL : '';
    }
    return $appRoot . '/' . ltrim($path, '/');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(400);
        die('Security check failed. Please go back and try again.');
    }
}

function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('d M Y', $timestamp);
}

function format_money(?float $amount): string
{
    if ($amount === null) return 'Not disclosed';
    return 'KES ' . number_format($amount, 0);
}

function status_pill_class(string $status): string
{
    $status = strtolower($status);
    return match (true) {
        in_array($status, ['active', 'accepted', 'approved', 'shortlisted']) => 'pill-success',
        in_array($status, ['pending', 'under review', 'applied']) => 'pill-pending',
        in_array($status, ['rejected', 'closed', 'suspended', 'dismissed']) => 'pill-danger',
        in_array($status, ['interview']) => 'pill-info',
        default => 'pill-neutral',
    };
}

// saves one uploaded file and returns its path, or null if it fails
// $allowed is a list of extensions, lowercase without the dot
function handle_upload(string $fieldName, string $subfolder, array $allowed, int $maxBytes = 5 * 1024 * 1024): ?string
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'There was a problem uploading the file.');
        return null;
    }
    if ($file['size'] > $maxBytes) {
        flash('error', 'File is too large. Maximum size is ' . round($maxBytes / 1024 / 1024) . 'MB.');
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        flash('error', 'Unsupported file type. Allowed: ' . implode(', ', $allowed));
        return null;
    }

    $uploadDir = __DIR__ . '/../assets/uploads/' . $subfolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeName = uniqid($subfolder . '_', true) . '.' . $ext;
    $destination = $uploadDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        flash('error', 'Could not save the uploaded file.');
        return null;
    }

    return 'assets/uploads/' . $subfolder . '/' . $safeName;
}

// adds a notification for this user
function notify(int $userId, string $title, string $message): void
{
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $title, $message]);
}

// records what an admin did, for the audit trail
function log_admin_action(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null): void
{
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO admin_actions (admin_id, action, target_type, target_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$adminId, $action, $targetType, $targetId]);
}

// Older databases have a simpler reports table. This adds the missing columns
// the first time a complaints page opens, so nobody has to re-import and lose data.
function ensure_complaint_columns(): void
{
    static $done = false;
    if ($done) return;

    $db = getDB();
    $hasSubject = $db->query("SHOW COLUMNS FROM reports LIKE 'subject'")->fetch();
    if (!$hasSubject) {
        $db->exec("
            ALTER TABLE reports
                MODIFY target_type ENUM('job','user','employer','general') NOT NULL DEFAULT 'general',
                MODIFY target_id INT NULL,
                ADD COLUMN subject VARCHAR(150) NULL AFTER target_id,
                ADD COLUMN admin_reply TEXT NULL AFTER status,
                ADD COLUMN replied_at DATETIME NULL AFTER admin_reply
        ");
    }

    // the two columns that make deleting a complaint possible
    $hasDeleteFlags = $db->query("SHOW COLUMNS FROM reports LIKE 'deleted_by_admin'")->fetch();
    if (!$hasDeleteFlags) {
        $db->exec("
            ALTER TABLE reports
                ADD COLUMN deleted_by_seeker TINYINT(1) NOT NULL DEFAULT 0 AFTER replied_at,
                ADD COLUMN deleted_by_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER deleted_by_seeker
        ");
    }
    $done = true;
}

// When both the job seeker and the admin have deleted a complaint, nobody can
// see it any more, so remove the row for good.
function purge_deleted_complaint(int $reportId): void
{
    $stmt = getDB()->prepare('DELETE FROM reports WHERE report_id = ? AND deleted_by_seeker = 1 AND deleted_by_admin = 1');
    $stmt->execute([$reportId]);
}

// old reports have no subject, so make one from the type
function complaint_subject(array $report): string
{
    if (!empty($report['subject'])) return $report['subject'];

    return match ($report['target_type']) {
        'job'      => 'Report about a job listing',
        'employer' => 'Report about an employer',
        'user'     => 'Report about a user',
        default    => 'General complaint',
    };
}

function paginate(int $totalRows, int $perPage, int $currentPage): array
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [$totalPages, $currentPage, $offset];
}

// password reset

// creates the password_resets table if it is missing. It is in kazilink.sql for
// new installs, this covers older imports so nobody has to re-import and lose data
function ensure_password_resets_table(): void
{
    static $done = false;
    if ($done) return;
    getDB()->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            reset_id    INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            token_hash  CHAR(64) NOT NULL UNIQUE,
            expires_at  DATETIME NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    $done = true;
}

// should the reset link show on the page? see SHOW_RESET_LINK_ON_SCREEN in bootstrap.php
function should_show_reset_link(): bool
{
    if (SHOW_RESET_LINK_ON_SCREEN === true) return true;
    if (SHOW_RESET_LINK_ON_SCREEN === false) return false;
    // 'auto' means only when the visitor is on the same computer as the server
    // (REMOTE_ADDR can't be faked like the Host header can)
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

// the app's address, for building full links (call it from a page in the root folder)
function app_base_url(): string
{
    if (APP_BASE_URL !== '') return rtrim(APP_BASE_URL, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $dir;
}

// tries to send the reset email with mail(), returns false if the server has no mail set up
function send_reset_email(string $toEmail, string $fullName, string $link): bool
{
    $host = preg_replace('/[^a-z0-9.\-]/i', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]) ?: 'localhost';
    $subject = 'Reset your KaziLink password';
    $body = "Hi " . preg_replace('/[\r\n]+/', ' ', $fullName) . ",\n\n"
          . "We received a request to reset your KaziLink password. Use the link below to choose a new one.\n"
          . "It works once and expires in 1 hour:\n\n"
          . $link . "\n\n"
          . "If you didn't ask for this, you can ignore this email — your password won't change.\n";
    $headers = "From: KaziLink <no-reply@{$host}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($toEmail, $subject, $body, $headers);
}
