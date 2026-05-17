<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireAdmin();

$db = getDB();

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            flashMessage('error', 'Nama proyek wajib diisi.');
        } else {
            $uid = currentUser()['id'];
            $stmt = $db->prepare("INSERT INTO projects (name, description, created_by) VALUES (?,?,?)");
            $stmt->execute([$name, $desc, $uid]);
            $pid = $db->lastInsertId();
            flashMessage('success', 'Proyek berhasil dibuat.');
        }
    }

    if ($action === 'delete') {
        $id  = (int)($_POST['project_id'] ?? 0);
        $uid = currentUser()['id'];
        $db->prepare("DELETE FROM projects WHERE id = ? AND created_by = ?")->execute([$id, $uid]);
        flashMessage('success', 'Proyek berhasil dihapus.');
    }

    redirect('/pages/admin/projects.php');
}

// ── Fetch projects milik admin ini saja ─────────────────────
$uid = currentUser()['id'];
$stmtProjects = $db->prepare(
    "SELECT p.*, u.name AS creator_name,
            COUNT(DISTINCT pm.user_id) AS member_count,
            COUNT(DISTINCT t.id) AS task_count,
            SUM(t.status='done') AS done_count
     FROM projects p
     JOIN users u ON u.id = p.created_by
     LEFT JOIN project_members pm ON pm.project_id = p.id
     LEFT JOIN tasks t ON t.project_id = p.id
     WHERE p.created_by = ?
     GROUP BY p.id ORDER BY p.created_at DESC"
);
$stmtProjects->execute([$uid]);
$projects = $stmtProjects->fetchAll();

$pageTitle = 'Manajemen Proyek';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Manajemen Proyek</h1>
    <p>Buat dan kelola semua proyek tim kamu</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('modal-create-project')">+ Proyek Baru</button>
</div>

<?php if (empty($projects)): ?>
  <div class="empty-state">
    <div class="empty-icon">📁</div>
    <h3>Belum ada proyek</h3>
    <p>Mulai dengan membuat proyek pertama kamu.</p>
    <button class="btn btn-primary" style="margin-top:16px" onclick="openModal('modal-create-project')">+ Buat Proyek</button>
  </div>
<?php else: ?>
  <div class="project-grid">
    <?php foreach ($projects as $p):
      $pct = $p['task_count'] > 0 ? round(($p['done_count'] / $p['task_count']) * 100) : 0;
    ?>
    <div class="project-card" style="cursor:default">
      <div class="project-card-name"><?= h($p['name']) ?></div>
      <div class="project-card-desc"><?= $p['description'] ? h(mb_substr($p['description'], 0, 100)) . '…' : '<em class="text-muted">Tidak ada deskripsi</em>' ?></div>

      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span class="text-muted text-sm">Progress</span>
          <span class="text-sm"><?= $pct ?>%</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
      </div>

      <div class="project-card-footer">
        <div style="display:flex;gap:12px">
          <span class="text-muted text-sm">👥 <?= $p['member_count'] ?> anggota</span>
          <span class="text-muted text-sm">📋 <?= $p['task_count'] ?> tugas</span>
        </div>
        <div style="display:flex;gap:8px">
          <a href="<?= BASE_URL ?>/pages/admin/project_detail.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Buka</a>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <button type="submit" class="btn btn-danger btn-sm"
              data-confirm="Hapus proyek '<?= h($p['name']) ?>'? Semua tugas akan ikut terhapus.">Hapus</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ── MODAL: Create Project ─────────────────────────────── -->
<div class="modal-overlay" id="modal-create-project">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Buat Proyek Baru</span>
      <button class="modal-close" onclick="closeModal('modal-create-project')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="form-group">
        <label class="form-label">Nama Proyek *</label>
        <input type="text" name="name" class="form-control" placeholder="misal: Website Kampus" required>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" placeholder="Deskripsi singkat proyek..."></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-project')">Batal</button>
        <button type="submit" class="btn btn-primary">Buat Proyek</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
