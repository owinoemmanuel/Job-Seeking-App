<?php
// Starts the session and has the login and role checks. bootstrap.php includes it after config and functions.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_name(): string
{
    return $_SESSION['full_name'] ?? '';
}

// the whole users row, or null if nobody is logged in
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    // only ask the database once per page
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([current_user_id()]);
    $cached = $stmt->fetch() ?: null;
    return $cached;
}

// the seeker_id of the logged-in job seeker
function current_seeker_id(): ?int
{
    if (current_role() !== 'job_seeker') return null;
    $db = getDB();
    $stmt = $db->prepare('SELECT seeker_id FROM job_seekers WHERE user_id = ?');
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch();
    return $row ? (int) $row['seeker_id'] : null;
}

// the employer_id of the logged-in employer
function current_employer_id(): ?int
{
    if (current_role() !== 'employer') return null;
    $db = getDB();
    $stmt = $db->prepare('SELECT employer_id FROM employers WHERE user_id = ?');
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch();
    return $row ? (int) $row['employer_id'] : null;
}

function login_user(array $user): void
{
    // new session id on login so an old one can't be reused
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $user['user_id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// goes at the top of a protected page. Needs a login, and optionally a role
// (or a list of roles). Suspended accounts get logged out.
function require_login(array|string|null $roles = null): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect(app_relative_path('login.php'));
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT status FROM users WHERE user_id = ?');
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch();

    if (!$row || $row['status'] === 'suspended') {
        logout_user();
        flash('error', 'Your account has been suspended. Contact the administrator for help.');
        redirect(app_relative_path('login.php'));
    }

    if ($roles !== null) {
        $roles = is_array($roles) ? $roles : [$roles];
        if (!in_array(current_role(), $roles, true)) {
            flash('error', 'You do not have permission to view that page.');
            redirect(app_relative_path('index.php'));
        }
    }
}

// works out if the page is inside seeker/, employer/ or admin/ so redirects
// get the ../ they need
function app_relative_path(string $target): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $depthFolders = ['/seeker', '/employer', '/admin'];
    foreach ($depthFolders as $folder) {
        if (substr($scriptDir, -strlen($folder)) === $folder) {
            return '../' . $target;
        }
    }
    return $target;
}

function unread_notifications_count(int $userId): int
{
    $db = getDB();
    $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
