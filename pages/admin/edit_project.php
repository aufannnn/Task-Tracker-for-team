<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireAdmin();

$db  = getDB();
$pid = (int)($_GET['id'] ?? 0);

$currentUid = currentUser()['id'];
$project = $db->prepare("SELECT * FROM projects WHERE id = ?");
$project->execute([$pid]);
$project = $project->fetch();
if (!$project) {
    flashMessage('error', 'Proyek tidak ditemukan.');
    redirect('/pages/admin/projects.php');
}
if ((int)$project['created_by'] !== (int)$currentUid) {
    flashMessage('error', 'Kamu hanya bisa mengedit proyek yang kamu buat sendiri.');
    redirect('/pages/admin/projects.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!$name) {
        flashMessage('error', 'Nama proyek wajib diisi.');
        redirect("/pages/admin/edit_project.php?id=$pid");
    }

    $db->prepare("UPDATE projects SET name=?, description=? WHERE id=?")
       ->execute([$name, $desc ?: null, $pid]);
    flashMessage('success', 'Proyek berhasil diperbarui.');
    redirect("/pages/admin/project_detail.php?id=$pid");
}

$pageTitle = 'Edit Proyek';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <div style="margin-bottom:4px">
      <a href="<?= BASE_URL ?>/pages/admin/project_detail.php?id=<?= $pid ?>" style="color:var(--muted);text-decoration:none;font-size:13px">← Kembali ke Proyek</a>
    </div>
    <h1>Edit Proyek</h1>
    <p>Ubah informasi dasar proyek</p>
  </div>
</div>

<div style="max-width:560px">
  <div class="card">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="form-group">
        <label class="form-label">Nama Proyek *</label>
        <input type="text" name="name" class="form-control"
               value="<?= h($project['name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="4"
                  placeholder="Deskripsi singkat proyek..."><?= h($project['description'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <a href="<?= BASE_URL ?>/pages/admin/project_detail.php?id=<?= $pid ?>" class="btn btn-secondary">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
