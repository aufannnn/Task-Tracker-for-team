<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireAdmin();

$db  = getDB();
$pid = (int)($_GET['id'] ?? 0);

// Validate project exists DAN milik admin ini
$currentUid = currentUser()['id'];
$project = $db->prepare("SELECT * FROM projects WHERE id = ?");
$project->execute([$pid]);
$project = $project->fetch();
if (!$project) {
    flashMessage('error', 'Proyek tidak ditemukan.');
    redirect('/pages/admin/projects.php');
}
// Cek apakah user adalah pemilik proyek
$isOwner = ((int)$project['created_by'] === (int)$currentUid);
if (!$isOwner) {
    flashMessage('error', 'Kamu tidak punya akses ke proyek ini. Kamu hanya bisa mengelola proyek yang kamu buat sendiri.');
    redirect('/pages/admin/projects.php');
}

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $dl     = $_POST['deadline'] ?? null;
        $assign = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $catId  = (int)($_POST['category_id'] ?? 0) ?: null;
        $uid    = currentUser()['id'];

        if ($title === '') {
            flashMessage('error', 'Judul tugas wajib diisi.');
        } else {
            $stmt = $db->prepare(
                "INSERT INTO tasks (project_id, assigned_to, created_by, title, description, deadline)
                 VALUES (?,?,?,?,?,?)"
            );
            $stmt->execute([$pid, $assign, $uid, $title, $desc ?: null, $dl ?: null]);
            $tid = $db->lastInsertId();
            if ($catId) {
                $db->prepare("INSERT INTO task_category_map (task_id, category_id) VALUES (?,?)")->execute([$tid, $catId]);
            }
            flashMessage('success', 'Tugas berhasil ditambahkan.');
        }
    }

    if ($action === 'update_task') {
        $tid    = (int)($_POST['task_id'] ?? 0);
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $dl     = $_POST['deadline'] ?? null;
        $assign = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $status = $_POST['status'] ?? 'todo';
        if (!in_array($status, ['todo','in_progress','done'])) $status = 'todo';

        $db->prepare(
            "UPDATE tasks SET title=?, description=?, deadline=?, assigned_to=?, status=? WHERE id=? AND project_id=?"
        )->execute([$title, $desc ?: null, $dl ?: null, $assign, $status, $tid, $pid]);
        flashMessage('success', 'Tugas berhasil diperbarui.');
    }

    if ($action === 'delete_task') {
        $tid = (int)($_POST['task_id'] ?? 0);
        $db->prepare("DELETE FROM tasks WHERE id=? AND project_id=?")->execute([$tid, $pid]);
        flashMessage('success', 'Tugas dihapus.');
    }

    if ($action === 'add_member') {
        $email       = trim($_POST['email'] ?? '');
        $memberRole  = in_array($_POST['member_role'] ?? '', ['admin','member']) ? $_POST['member_role'] : 'member';

        if (!$email) {
            flashMessage('error', 'Email wajib diisi.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flashMessage('error', 'Format email tidak valid.');
        } else {
            $find = $db->prepare("SELECT id, name FROM users WHERE email = ?");
            $find->execute([$email]);
            $found = $find->fetch();

            if (!$found) {
                // Akun belum ada — buat otomatis dengan password default
                $autoName = ucfirst(explode('@', $email)[0]);
                $autoPass = password_hash('member123', PASSWORD_BCRYPT);
                $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,'member')")
                   ->execute([$autoName, $email, $autoPass]);
                $newId = $db->lastInsertId();
                $db->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?,?,?)")
                   ->execute([$pid, $newId, $memberRole]);
                $roleLabel = $memberRole === 'admin' ? 'Admin Proyek' : 'Member';
                flashMessage('success', 'Akun baru untuk <strong>' . htmlspecialchars($email) . '</strong> dibuat sebagai <strong>' . $roleLabel . '</strong>. Password default: <strong>member123</strong>');
            } else {
                $check = $db->prepare("SELECT id FROM project_members WHERE project_id=? AND user_id=?");
                $check->execute([$pid, $found['id']]);
                if ($check->fetch()) {
                    flashMessage('error', htmlspecialchars($found['name']) . ' sudah menjadi anggota proyek ini.');
                } else {
                    $db->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?,?,?)")
                       ->execute([$pid, $found['id'], $memberRole]);
                    $roleLabel = $memberRole === 'admin' ? 'Admin Proyek' : 'Member';
                    flashMessage('success', htmlspecialchars($found['name']) . ' berhasil ditambahkan sebagai <strong>' . $roleLabel . '</strong>.');
                }
            }
        }
    }

    if ($action === 'remove_member') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $db->prepare("DELETE FROM project_members WHERE project_id=? AND user_id=?")->execute([$pid, $uid]);
        flashMessage('success', 'Anggota dihapus dari proyek.');
    }

    if ($action === 'update_member_role') {
        $uid     = (int)($_POST['user_id'] ?? 0);
        $newRole = in_array($_POST['member_role'] ?? '', ['admin','member']) ? $_POST['member_role'] : 'member';
        $db->prepare("UPDATE project_members SET role=? WHERE project_id=? AND user_id=?")
           ->execute([$newRole, $pid, $uid]);
        flashMessage('success', 'Role anggota berhasil diubah.');
    }

    if ($action === 'create_category') {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name) {
            $db->prepare("INSERT INTO task_categories (project_id, name) VALUES (?,?)")->execute([$pid, $name]);
            flashMessage('success', 'Kategori berhasil ditambahkan.');
        }
    }

    redirect("/pages/admin/project_detail.php?id=$pid");
}

// ── Fetch data ────────────────────────────────────────────────
$tasks = $db->prepare(
    "SELECT t.*, u.name AS assignee_name,
            GROUP_CONCAT(tc.name SEPARATOR ', ') AS categories
     FROM tasks t
     LEFT JOIN users u ON u.id = t.assigned_to
     LEFT JOIN task_category_map tcm ON tcm.task_id = t.id
     LEFT JOIN task_categories tc ON tc.id = tcm.category_id
     WHERE t.project_id = ?
     GROUP BY t.id
     ORDER BY t.created_at DESC"
);
$tasks->execute([$pid]);
$tasks = $tasks->fetchAll();

$byStatus = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($tasks as $t) { $byStatus[$t['status']][] = $t; }

$members = $db->prepare(
    "SELECT u.id, u.name, u.email, pm.role AS project_role
     FROM project_members pm
     JOIN users u ON u.id = pm.user_id
     WHERE pm.project_id = ?
     ORDER BY pm.role ASC, u.name ASC"
);
$members->execute([$pid]);
$members = $members->fetchAll();
$memberIds = array_column($members, 'id');

$categories = $db->prepare("SELECT * FROM task_categories WHERE project_id = ? ORDER BY name");
$categories->execute([$pid]);
$categories = $categories->fetchAll();

$pageTitle = h($project['name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div style="margin-bottom:4px">
      <a href="<?= BASE_URL ?>/pages/admin/projects.php" style="color:var(--muted);text-decoration:none;font-size:13px">← Semua Proyek</a>
    </div>
    <h1><?= h($project['name']) ?></h1>
    <?php if ($project['description']): ?>
      <p><?= h($project['description']) ?></p>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:10px">
    <a href="<?= BASE_URL ?>/pages/admin/edit_project.php?id=<?= $pid ?>" class="btn btn-secondary">✏ Edit Proyek</a>
    <button class="btn btn-primary" onclick="openModal('modal-create-task')">+ Tambah Tugas</button>
  </div>
</div>

<!-- ── STATS BAR ─────────────────────────────────────────── -->
<?php
$total = count($tasks); $done = count($byStatus['done']); $pct = $total > 0 ? round($done/$total*100) : 0;
?>
<div class="card" style="margin-bottom:24px;padding:18px 24px">
  <div style="display:flex;align-items:center;gap:24px">
    <div style="flex:1">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span class="text-muted text-sm">Progress Proyek</span>
        <span style="font-weight:600;font-family:var(--font-head)"><?= $pct ?>%</span>
      </div>
      <div class="progress-bar" style="height:8px"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
    </div>
    <div style="display:flex;gap:20px">
      <div style="text-align:center">
        <div style="font-size:20px;font-weight:800;font-family:var(--font-head)"><?= count($byStatus['todo']) ?></div>
        <div class="text-muted text-sm">To Do</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:20px;font-weight:800;font-family:var(--font-head);color:var(--warning)"><?= count($byStatus['in_progress']) ?></div>
        <div class="text-muted text-sm">In Progress</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:20px;font-weight:800;font-family:var(--font-head);color:var(--success)"><?= count($byStatus['done']) ?></div>
        <div class="text-muted text-sm">Done</div>
      </div>
    </div>
  </div>
</div>

<!-- ── TABS ──────────────────────────────────────────────── -->
<div data-tabs>
<div class="tabs">
  <button class="tab-btn active" data-tab="tab-tasks">Kanban Tugas</button>
  <button class="tab-btn" data-tab="tab-members">Anggota Tim (<?= count($members) ?>)</button>
  <button class="tab-btn" data-tab="tab-categories">Kategori</button>
</div>

<!-- Tab: Kanban -->
<div class="tab-panel active" id="tab-tasks">
  <div class="kanban">
    <?php
    $cols = [
      'todo'        => ['label' => 'To Do',      'dot' => 'var(--muted)'],
      'in_progress' => ['label' => 'In Progress', 'dot' => 'var(--warning)'],
      'done'        => ['label' => 'Done',        'dot' => 'var(--success)'],
    ];
    foreach ($cols as $status => $col): ?>
    <div class="kanban-col">
      <div class="kanban-col-header">
        <div class="flex gap-8">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= $col['dot'] ?>;display:inline-block;margin-top:2px"></span>
          <span class="kanban-col-title"><?= $col['label'] ?></span>
        </div>
        <span class="kanban-count"><?= count($byStatus[$status]) ?></span>
      </div>

      <?php if (empty($byStatus[$status])): ?>
        <div class="text-muted text-sm" style="text-align:center;padding:20px 0">Kosong</div>
      <?php else: foreach ($byStatus[$status] as $t):
        $overdue = isOverdue($t['deadline'], $t['status']);
      ?>
        <div class="task-card">
          <div class="task-card-title"><?= h($t['title']) ?></div>
          <div class="task-card-meta">
            <?php if ($t['deadline']): ?>
              <span class="task-card-deadline <?= $overdue ? 'overdue' : '' ?>">
                📅 <?= formatDate($t['deadline']) ?><?= $overdue ? ' ⚠' : '' ?>
              </span>
            <?php endif; ?>
            <?php if ($t['assignee_name']): ?>
              <span class="task-card-assignee">👤 <?= h($t['assignee_name']) ?></span>
            <?php endif; ?>
            <?php if ($t['categories']): ?>
              <span class="badge badge-admin" style="font-size:11px"><?= h($t['categories']) ?></span>
            <?php endif; ?>
          </div>
          <div class="task-card-actions">
            <button class="btn btn-secondary btn-sm"
              onclick="openEditModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">Edit</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete_task">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                data-confirm="Hapus tugas '<?= h($t['title']) ?>'?">Hapus</button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Tab: Members -->
<div class="tab-panel" id="tab-members">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Anggota Proyek</span>
      </div>
      <?php if (empty($members)): ?>
        <div class="text-muted text-sm">Belum ada anggota.</div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nama</th><th>Role di Proyek</th><th>Ubah Role</th><th>Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($members as $m): ?>
            <tr>
              <td>
                <div style="font-weight:500;font-size:14px"><?= h($m['name']) ?></div>
                <div class="text-muted text-sm"><?= h($m['email']) ?></div>
              </td>
              <td>
                <?php if ($m['project_role'] === 'admin'): ?>
                  <span class="badge badge-admin">Admin Proyek</span>
                <?php else: ?>
                  <span class="badge badge-member">Member</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" style="display:flex;gap:6px;align-items:center">
                  <input type="hidden" name="action" value="update_member_role">
                  <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <select name="member_role" class="form-control" style="padding:4px 8px;font-size:12px;width:auto">
                    <option value="member" <?= $m['project_role']==='member' ? 'selected' : '' ?>>Member</option>
                    <option value="admin"  <?= $m['project_role']==='admin'  ? 'selected' : '' ?>>Admin Proyek</option>
                  </select>
                  <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                </form>
              </td>
              <td>
                <form method="POST">
                  <input type="hidden" name="action" value="remove_member">
                  <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                    data-confirm="Keluarkan <?= h($m['name']) ?> dari proyek?">Keluarkan</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Tambah Anggota via Email</span></div>
      <form method="POST">
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-group">
          <label class="form-label">Email Pengguna</label>
          <input type="email" name="email" class="form-control"
                 placeholder="contoh: anggota@email.com" required>
          <div class="form-hint">Jika email belum terdaftar, akun baru dibuat otomatis dengan password default: <strong>member123</strong>.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Role di Proyek</label>
          <select name="member_role" class="form-control">
            <option value="member">Member — hanya bisa lihat & update tugas sendiri</option>
            <option value="admin">Admin Proyek — bisa kelola tugas & anggota</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Tambahkan</button>
      </form>
    </div>
  </div>
</div>

<!-- Tab: Categories -->
<div class="tab-panel" id="tab-categories">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
      <div class="card-header"><span class="card-title">Kategori Tugas</span></div>
      <?php if (empty($categories)): ?>
        <div class="text-muted text-sm">Belum ada kategori.</div>
      <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px">
          <?php foreach ($categories as $c): ?>
            <span class="badge badge-admin" style="font-size:13px;padding:6px 14px"><?= h($c['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-header"><span class="card-title">Tambah Kategori</span></div>
      <form method="POST">
        <input type="hidden" name="action" value="create_category">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-group">
          <label class="form-label">Nama Kategori</label>
          <input type="text" name="cat_name" class="form-control" placeholder="misal: Frontend, Backend…" required>
        </div>
        <button type="submit" class="btn btn-primary">Tambahkan</button>
      </form>
    </div>
  </div>
</div>
</div><!-- end data-tabs -->

<!-- ── MODAL: Create Task ────────────────────────────────── -->
<div class="modal-overlay" id="modal-create-task">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Tambah Tugas</span>
      <button class="modal-close" onclick="closeModal('modal-create-task')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_task">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="form-group">
        <label class="form-label">Judul Tugas *</label>
        <input type="text" name="title" class="form-control" placeholder="misal: Buat halaman login" required>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" placeholder="Detail tugas…"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Assign ke</label>
          <select name="assigned_to" class="form-control">
            <option value="">— Belum diassign —</option>
            <?php foreach ($members as $m): ?>
              <option value="<?= $m['id'] ?>"><?= h($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Deadline</label>
          <input type="date" name="deadline" class="form-control">
        </div>
      </div>
      <?php if (!empty($categories)): ?>
      <div class="form-group">
        <label class="form-label">Kategori</label>
        <select name="category_id" class="form-control">
          <option value="">— Tanpa kategori —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-task')">Batal</button>
        <button type="submit" class="btn btn-primary">Tambahkan</button>
      </div>
    </form>
  </div>
</div>

<!-- ── MODAL: Edit Task ──────────────────────────────────── -->
<div class="modal-overlay" id="modal-edit-task">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Tugas</span>
      <button class="modal-close" onclick="closeModal('modal-edit-task')">✕</button>
    </div>
    <form method="POST" id="form-edit-task">
      <input type="hidden" name="action" value="update_task">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="task_id" id="edit-task-id">
      <div class="form-group">
        <label class="form-label">Judul Tugas *</label>
        <input type="text" name="title" id="edit-task-title" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" id="edit-task-desc" class="form-control"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Assign ke</label>
          <select name="assigned_to" id="edit-task-assign" class="form-control">
            <option value="">— Belum diassign —</option>
            <?php foreach ($members as $m): ?>
              <option value="<?= $m['id'] ?>"><?= h($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Deadline</label>
          <input type="date" name="deadline" id="edit-task-deadline" class="form-control">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" id="edit-task-status" class="form-control">
          <option value="todo">To Do</option>
          <option value="in_progress">In Progress</option>
          <option value="done">Done</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-task')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(task) {
  document.getElementById('edit-task-id').value       = task.id;
  document.getElementById('edit-task-title').value    = task.title;
  document.getElementById('edit-task-desc').value     = task.description || '';
  document.getElementById('edit-task-deadline').value = task.deadline || '';
  document.getElementById('edit-task-assign').value   = task.assigned_to || '';
  document.getElementById('edit-task-status').value   = task.status;
  openModal('modal-edit-task');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
