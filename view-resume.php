<?php
// Opens a saved CV file, but only for people who are allowed to see it.
require_once __DIR__ . '/includes/bootstrap.php';
// no CV is public, so log in first
require_login();

$db = getDB();
$resumeId = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare('
    SELECT r.*, js.user_id AS seeker_user_id, js.seeker_id
    FROM resumes r
    JOIN job_seekers js ON js.seeker_id = r.seeker_id
    WHERE r.resume_id = ?
');
$stmt->execute([$resumeId]);
$resume = $stmt->fetch();

// who can see the CV: admins, the seeker who owns it, and employers that this
// seeker has applied to. Everyone else is refused.
$authorized = false;
if ($resume) {
    if (current_role() === 'admin') {
        $authorized = true;
    } elseif (current_role() === 'job_seeker' && (int) $resume['seeker_user_id'] === current_user_id()) {
        $authorized = true;
    } elseif (current_role() === 'employer') {
        $check = $db->prepare('
            SELECT 1 FROM applications a
            JOIN jobs j ON j.job_id = a.job_id
            WHERE a.seeker_id = ? AND j.employer_id = ?
            LIMIT 1
        ');
        $check->execute([$resume['seeker_id'], current_employer_id()]);
        $authorized = (bool) $check->fetchColumn();
    }
}

// small page to show instead of a broken link when the CV can't be shown
function render_cv_message(string $title, string $message): void
{
    $pageTitle = $title . ' — KaziLink';
    $base = '';
    include __DIR__ . '/includes/header.php';
    echo '<div class="section container"><div class="empty-state"><h3>' . e($title) . '</h3><p>' . e($message) . '</p>'
       . '<a href="javascript:history.back()" class="btn btn-outline">Go back</a></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// same 404 whether the CV is missing or off limits, so nobody can guess which ids exist
if (!$resume || !$authorized) {
    http_response_code(404);
    render_cv_message('CV not found', 'This CV does not exist, or you do not have permission to view it.');
}

// the record can exist while the file is gone from the uploads folder
$fullPath = __DIR__ . '/' . $resume['file_path'];
if (!is_file($fullPath)) {
    http_response_code(404);
    render_cv_message('CV not found', 'This CV file could not be found on the server. The job seeker may have removed it.');
}

// tell the browser what kind of file this is
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    default => 'application/octet-stream',
};

// inline opens it in the browser instead of downloading it, and nosniff stops the
// browser from guessing a different type
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($resume['file_name']) . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
