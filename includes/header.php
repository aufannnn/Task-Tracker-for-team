<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Session timeout: 2 jam tidak aktif → logout otomatis
startSession();
$timeout = 7200; // 2 jam
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

$user = currentUser();
$curPage = basename($_SERVER['PHP_SELF']);
$curPath = $_SERVER['PHP_SELF'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'Task Tracker' ?> — Task Tracker</title>  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar">
  <a href="<?= BASE_URL ?>/dashboard.php" class="nav-brand">
    <span class="brand-icon">◈</span>
    <span>TaskTracker</span>
  </a>
  <div class="nav-links">
    <a href="<?= BASE_URL ?>/dashboard.php"
       class="nav-link <?= $curPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>

    <?php if ($user['role'] === 'admin'): ?>
      <a href="<?= BASE_URL ?>/pages/admin/projects.php"
         class="nav-link <?= strpos($curPath, '/admin/') !== false ? 'active' : '' ?>">Proyek</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/pages/member/my_tasks.php"
         class="nav-link <?= $curPage === 'my_tasks.php' ? 'active' : '' ?>">Tugas Saya</a>
      <a href="<?= BASE_URL ?>/pages/member/projects.php"
         class="nav-link <?= in_array($curPage, ['projects.php','project_view.php']) ? 'active' : '' ?>">Proyek Saya</a>
    <?php endif; ?>
  </div>

  <div class="nav-user">
    <!-- Overdue bell (only for member) -->
    <?php if ($user['role'] === 'member'):
      $db = getDB();
      $overdueCount = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to=? AND status!='done' AND deadline < CURDATE()");
      $overdueCount->execute([$user['id']]);
      $overdueCount = (int)$overdueCount->fetchColumn();
      if ($overdueCount > 0): ?>
        <a href="<?= BASE_URL ?>/pages/member/my_tasks.php?status=in_progress" class="overdue-bell" title="<?= $overdueCount ?> tugas overdue">
          🔔 <span class="overdue-count"><?= $overdueCount ?></span>
        </a>
      <?php endif;
    endif; ?>

    <span class="user-chip" style="cursor:default">
      <span class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
      <span class="user-name"><?= h($user['name']) ?></span>
      <?= roleBadge($user['role']) ?>
    </span>
    <a href="<?= BASE_URL ?>/logout.php" class="btn-logout">Keluar</a>
  </div>
</nav>

<main class="main-content">
<?php
if (isset($_GET['timeout'])): ?>
  <div class="alert alert-error">Sesi kamu telah berakhir karena tidak aktif. Silakan login kembali.</div>
<?php endif;
$success = getFlash('success');
$error   = getFlash('error');
if ($success): ?>
  <div class="alert alert-success"><?= $success ?></div>
<?php endif;
if ($error): ?>
  <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>
