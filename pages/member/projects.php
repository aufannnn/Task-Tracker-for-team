<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

$db  = getDB();
$uid = currentUser()['id'];

// Fetch projects this member belongs to
$projects = $db->prepare(
    "SELECT p.*,
            u.name AS creator_name,
            COUNT(DISTINCT pm2.user_id) AS member_count,
            COUNT(DISTINCT t.id) AS task_count,
            SUM(t.status = 'done') AS done_count,
            COUNT(DISTINCT CASE WHEN t.assigned_to = :uid THEN t.id END) AS my_task_count,
            SUM(CASE WHEN t.assigned_to = :uid2 AND t.status = 'done' THEN 1 ELSE 0 END) AS my_done_count
     FROM projects p
     JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = :uid3
     JOIN users u ON u.id = p.created_by
     LEFT JOIN project_members pm2 ON pm2.project_id = p.id
     LEFT JOIN tasks t ON t.project_id = p.id
     GROUP BY p.id
     ORDER BY p.created_at DESC"
);
$projects->execute([':uid' => $uid, ':uid2' => $uid, ':uid3' => $uid]);
$projects = $projects->fetchAll();

$pageTitle = 'Proyek Saya';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Proyek Saya</h1>
    <p>Daftar proyek yang kamu ikuti</p>
  </div>
</div>

<?php if (empty($projects)): ?>
  <div class="empty-state">
    <div class="empty-icon">📁</div>
    <h3>Belum ada proyek</h3>
    <p>Kamu belum dimasukkan ke proyek mana pun. Hubungi admin kamu.</p>
  </div>
<?php else: ?>
  <div class="project-grid">
    <?php foreach ($projects as $p):
      $total = (int)$p['task_count'];
      $done  = (int)$p['done_count'];
      $pct   = $total > 0 ? round($done / $total * 100) : 0;

      $myTotal = (int)$p['my_task_count'];
      $myDone  = (int)$p['my_done_count'];
    ?>
    <div class="project-card" style="cursor:default">
      <div class="project-card-name"><?= h($p['name']) ?></div>
      <div class="project-card-desc">
        <?= $p['description']
          ? h(mb_substr($p['description'], 0, 100)) . (mb_strlen($p['description']) > 100 ? '…' : '')
          : '<em style="color:var(--muted)">Tidak ada deskripsi</em>' ?>
      </div>

      <!-- Overall progress -->
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <span class="text-muted text-sm">Progress Tim</span>
          <span class="text-sm"><?= $done ?>/<?= $total ?> tugas (<?= $pct ?>%)</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
      </div>

      <!-- My contribution -->
      <?php if ($myTotal > 0): ?>
      <div style="background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:10px 12px;margin-bottom:14px">
        <div class="text-muted text-sm" style="margin-bottom:4px">Tugasku di proyek ini</div>
        <div style="font-weight:600;font-size:13px">
          <?= $myDone ?>/<?= $myTotal ?> tugas selesai
        </div>
      </div>
      <?php endif; ?>

      <div class="project-card-footer">
        <div style="display:flex;gap:12px">
          <span class="text-muted text-sm">👥 <?= $p['member_count'] ?></span>
          <span class="text-muted text-sm">Oleh: <?= h($p['creator_name']) ?></span>
        </div>
        <a href="<?= BASE_URL ?>/pages/member/project_view.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Lihat Detail</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
