<?php
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    // Tambah BASE_URL otomatis jika url dimulai dengan /
    if (defined('BASE_URL') && strpos($url, BASE_URL) !== 0) {
        $url = BASE_URL . $url;
    }
    header("Location: $url");
    exit;
}

function statusBadge(string $status): string {
    $map = [
        'todo'        => ['label' => 'To Do',       'class' => 'badge-todo'],
        'in_progress' => ['label' => 'In Progress',  'class' => 'badge-progress'],
        'done'        => ['label' => 'Done',          'class' => 'badge-done'],
    ];
    $s = $map[$status] ?? ['label' => $status, 'class' => 'badge-todo'];
    return '<span class="badge ' . $s['class'] . '">' . $s['label'] . '</span>';
}

function roleBadge(string $role): string {
    if ($role === 'admin') {
        return '<span class="badge badge-admin">Admin</span>';
    }
    return '<span class="badge badge-member">Member</span>';
}

function formatDate(?string $date): string {
    if (!$date) return '—';
    return date('d M Y', strtotime($date));
}

function isOverdue(?string $deadline, string $status): bool {
    if (!$deadline || $status === 'done') return false;
    return strtotime($deadline) < strtotime('today');
}

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

function url(string $path): string {
    return BASE_URL . $path;
}
