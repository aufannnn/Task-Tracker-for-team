<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireAdmin();

$db = getDB();

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$email || !$password) {
            flashMessage('error', 'Semua field wajib diisi.');
        } else {
            $exists = $db->prepare("SELECT id FROM users WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                flashMessage('error', 'Email sudah terdaftar.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
                   ->execute([$name, $email, $hash, 'member']);
                flashMessage('success', 'Pengguna berhasil ditambahkan.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        $me = currentUser()['id'];
        if ($id === $me) {
            flashMessage('error', 'Tidak bisa menghapus akun sendiri.');
        } else {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            flashMessage('success', 'Pengguna dihapus.');
        }
    }

    redirect('/pages/admin/members.php');
}

// ── Fetch all users ──────────────────────────────────────────
$users = $db->query(
    "SELECT u.*,
            COUNT(DISTINCT pm.project_id) AS project_count,
            COUNT(DISTINCT t.id) AS task_count
     FROM users u
     LEFT JOIN project_members pm ON pm.user_id = u.id
     LEFT JOIN tasks t ON t.assigned_to = u.id
     GROUP BY u.id ORDER BY u.role ASC, u.name ASC"
)->fetchAll();

$pageTitle = 'Manajemen Anggota';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Manajemen Anggota</h1>
    <p>Kelola semua pengguna sistem</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('modal-create-user')">+ Tambah Pengguna</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nama</th>
          <th>Email</th>
          <th>Role</th>
          <th>Proyek</th>
          <th>Tugas</th>
          <th>Bergabung</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($users)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">Belum ada pengguna.</td></tr>
      <?php else: foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--accent);color:#fff;
                          display:flex;align-items:center;justify-content:center;
                          font-family:var(--font-head);font-weight:700;font-size:13px;flex-shrink:0">
                <?= strtoupper(substr($u['name'], 0, 1)) ?>
              </div>
              <span style="font-weight:500"><?= h($u['name']) ?></span>
            </div>
          </td>
          <td><span class="text-muted text-sm"><?= h($u['email']) ?></span></td>
          <td><?= roleBadge($u['role']) ?></td>
          <td><span class="text-sm"><?= $u['project_count'] ?> proyek</span></td>
          <td><span class="text-sm"><?= $u['task_count'] ?> tugas</span></td>
          <td><span class="text-muted text-sm"><?= formatDate($u['created_at']) ?></span></td>
          <td>
            <?php if ($u['id'] !== currentUser()['id']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                data-confirm="Hapus pengguna <?= h($u['name']) ?>?">Hapus</button>
            </form>
            <?php else: ?>
              <span class="text-muted text-sm">Kamu</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── MODAL: Create User ──────────────────────────────────── -->
<div class="modal-overlay" id="modal-create-user">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Tambah Pengguna</span>
      <button class="modal-close" onclick="closeModal('modal-create-user')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="form-group">
        <label class="form-label">Nama Lengkap *</label>
        <input type="text" name="name" class="form-control" placeholder="Nama anggota" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control" placeholder="email@domain.com" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password *</label>
        <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter" required minlength="8">
        <div class="form-hint">Pengguna bisa memilih peran (Admin/Member) saat login.</div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-user')">Batal</button>
        <button type="submit" class="btn btn-primary">Tambahkan</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
