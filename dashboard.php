<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();

$db   = getDB();
$user = currentUser();
$uid  = $user['id'];

if ($user['role'] === 'admin') {
    // ── Admin stats ──────────────────────────────────────────
    // Hanya hitung proyek & tugas yang dibuat oleh admin ini
    $stmtTP = $db->prepare("SELECT COUNT(*) FROM projects WHERE created_by = ?");
    $stmtTP->execute([$uid]); $totalProjects = $stmtTP->fetchColumn();

    $stmtTM = $db->prepare(
        "SELECT COUNT(DISTINCT pm.user_id) FROM project_members pm
         JOIN projects p ON p.id = pm.project_id
         WHERE p.created_by = ? AND pm.user_id != ?"
    );
    $stmtTM->execute([$uid, $uid]); $totalMembers = $stmtTM->fetchColumn();

    $stmtTT = $db->prepare("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE p.created_by=?");
    $stmtTT->execute([$uid]); $totalTasks = $stmtTT->fetchColumn();

    $stmtDT = $db->prepare("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE p.created_by=? AND t.status='done'");
    $stmtDT->execute([$uid]); $doneTasks = $stmtDT->fetchColumn();

    $stmtIP = $db->prepare("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE p.created_by=? AND t.status='in_progress'");
    $stmtIP->execute([$uid]); $inProgTasks = $stmtIP->fetchColumn();

    $stmtOD = $db->prepare("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE p.created_by=? AND t.status!='done' AND t.deadline < CURDATE()");
    $stmtOD->execute([$uid]); $overdueTasks = $stmtOD->fetchColumn();

    // Recent tasks dari proyek milik admin ini
    $stmtRT = $db->prepare(
        "SELECT t.*, p.name AS project_name, u.name AS assignee_name
         FROM tasks t
         JOIN projects p ON p.id = t.project_id
         LEFT JOIN users u ON u.id = t.assigned_to
         WHERE p.created_by = ?
         ORDER BY t.updated_at DESC LIMIT 8"
    );
    $stmtRT->execute([$uid]);
    $recentTasks = $stmtRT->fetchAll();

    // Projects summary milik admin ini
    $stmtPS = $db->prepare(
        "SELECT p.*, u.name AS creator_name,
                COUNT(t.id) AS task_count,
                SUM(t.status='done') AS done_count
         FROM projects p
         JOIN users u ON u.id = p.created_by
         LEFT JOIN tasks t ON t.project_id = p.id
         WHERE p.created_by = ?
         GROUP BY p.id ORDER BY p.created_at DESC LIMIT 5"
    );
    $stmtPS->execute([$uid]);
    $projects = $stmtPS->fetchAll();

} else {
    // ── Member stats ─────────────────────────────────────────
    $myTotal    = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
    $myTotal->execute([$uid]); $myTotal = $myTotal->fetchColumn();

    $myTodo     = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status='todo'");
    $myTodo->execute([$uid]); $myTodo = $myTodo->fetchColumn();

    $myInProg   = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status='in_progress'");
    $myInProg->execute([$uid]); $myInProg = $myInProg->fetchColumn();

    $myDone     = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status='done'");
    $myDone->execute([$uid]); $myDone = $myDone->fetchColumn();

    $myOverdue  = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status!='done' AND deadline < CURDATE()");
    $myOverdue->execute([$uid]); $myOverdue = $myOverdue->fetchColumn();

    $myTasks = $db->prepare(
        "SELECT t.*, p.name AS project_name
         FROM tasks t
         JOIN projects p ON p.id = t.project_id
         WHERE t.assigned_to = ?
         ORDER BY FIELD(t.status,'in_progress','todo','done'), t.deadline ASC
         LIMIT 10"
    );
    $myTasks->execute([$uid]);
    $myTasks = $myTasks->fetchAll();
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Selamat datang, <?= h(explode(' ', $user['name'])[0]) ?> 👋</h1>
    <p><?= $user['role'] === 'admin' ? 'Panel Admin — lihat semua proyek dan tim' : 'Dashboard anggota tim kamu' ?></p>
  </div>
  <?php if ($user['role'] === 'admin'): ?>
    <a href="<?= BASE_URL ?>/pages/admin/projects.php" class="btn btn-primary">+ Proyek Baru</a>
  <?php endif; ?>
</div>

<?php if ($user['role'] === 'admin'): ?>

<!-- ── ADMIN DASHBOARD ───────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Total Proyek</div>
    <div class="stat-value"><?= $totalProjects ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Anggota Tim</div>
    <div class="stat-value"><?= $totalMembers ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Selesai</div>
    <div class="stat-value"><?= $doneTasks ?></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">In Progress</div>
    <div class="stat-value"><?= $inProgTasks ?></div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Overdue</div>
    <div class="stat-value"><?= $overdueTasks ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

  <!-- Recent tasks -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Aktivitas Terbaru</span>
      <a href="<?= BASE_URL ?>/pages/admin/projects.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Tugas</th><th>Proyek</th><th>Status</th><th>Assignee</th>
        </tr></thead>
        <tbody>
        <?php if (empty($recentTasks)): ?>
          <tr><td colspan="4" class="text-muted" style="text-align:center;padding:24px">Belum ada tugas.</td></tr>
        <?php else: foreach ($recentTasks as $t): ?>
          <tr>
            <td><?= h($t['title']) ?>
              <?php if (isOverdue($t['deadline'], $t['status'])): ?>
                <span class="badge badge-overdue" style="margin-left:6px">Overdue</span>
              <?php endif; ?>
            </td>
            <td><span class="text-muted text-sm"><?= h($t['project_name']) ?></span></td>
            <td><?= statusBadge($t['status']) ?></td>
            <td><span class="text-sm"><?= $t['assignee_name'] ? h($t['assignee_name']) : '—' ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Projects summary -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Proyek Aktif</span>
    </div>
    <?php if (empty($projects)): ?>
      <div class="empty-state"><div class="empty-icon">📁</div><h3>Belum ada proyek</h3></div>
    <?php else: foreach ($projects as $p):
      $pct = $p['task_count'] > 0 ? round(($p['done_count'] / $p['task_count']) * 100) : 0;
    ?>
      <div style="margin-bottom:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <a href="<?= BASE_URL ?>/pages/admin/project_detail.php?id=<?= $p['id'] ?>" style="font-weight:500;color:var(--text);text-decoration:none;font-size:14px">
            <?= h($p['name']) ?>
          </a>
          <span class="text-muted text-sm"><?= $p['done_count'] ?>/<?= $p['task_count'] ?> tugas</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<?php else: ?>

<!-- ── MEMBER DASHBOARD ──────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Total Tugas</div>
    <div class="stat-value"><?= $myTotal ?></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">In Progress</div>
    <div class="stat-value"><?= $myInProg ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">To Do</div>
    <div class="stat-value"><?= $myTodo ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Selesai</div>
    <div class="stat-value"><?= $myDone ?></div>
  </div>
  <?php if ($myOverdue > 0): ?>
  <div class="stat-card red">
    <div class="stat-label">Overdue</div>
    <div class="stat-value"><?= $myOverdue ?></div>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Tugas Kamu</span>
    <a href="<?= BASE_URL ?>/pages/member/my_tasks.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
  </div>
  <?php if (empty($myTasks)): ?>
    <div class="empty-state"><div class="empty-icon">✅</div><h3>Belum ada tugas untuk kamu</h3><p>Tunggu admin assign tugas ya.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Tugas</th><th>Proyek</th><th>Deadline</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($myTasks as $t): $overdue = isOverdue($t['deadline'], $t['status']); ?>
        <tr>
          <td><?= h($t['title']) ?>
            <?php if ($overdue): ?><span class="badge badge-overdue" style="margin-left:6px">Overdue</span><?php endif; ?>
          </td>
          <td><span class="text-muted text-sm"><?= h($t['project_name']) ?></span></td>
          <td><span class="text-sm <?= $overdue ? 'text-danger' : 'text-muted' ?>"><?= formatDate($t['deadline']) ?></span></td>
          <td><?= statusBadge($t['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
