<?php
require_once __DIR__ . '/../config/database.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void {
    startSession();
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    // Redirect ke choose_role HANYA jika perlu dan bukan sedang di halaman itu sendiri
    $current = basename($_SERVER['PHP_SELF']);
    $skip    = ['choose_role.php', 'logout.php', 'login.php', 'register.php'];
    if (!empty($_SESSION['need_role_choice']) && !in_array($current, $skip)) {
        header('Location: ' . BASE_URL . '/choose_role.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function currentUser(): array {
    startSession();
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['role']      ?? '',
    ];
}

function login(string $email, string $password): bool {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['last_activity'] = time();
        // Selalu tampilkan pilihan role setiap login
        $_SESSION['need_role_choice'] = true;
        return true;
    }
    return false;
}

function logout(): void {
    startSession();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

function flashMessage(string $key, string $message): void {
    startSession();
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string {
    startSession();
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}
