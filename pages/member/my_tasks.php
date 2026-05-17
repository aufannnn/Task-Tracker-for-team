<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

$db  = getDB();
$uid = currentUser()['id'];

// ── Handle POST: update status ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $tid    = (int)($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['todo','in_progress','done'])) {
        flashMessage('error', 'Status tidak valid.');
    } else {
        // Only update if task is assigned to this user
        $stmt = $db->prepare("UPDATE tasks SET status=? WHERE id=? AND assigned_to=?");
        $stmt->execute([$status, $tid, $uid]);
        if ($stmt->rowCount() > 0) {
            flashMessage('success', 'Status tugas diperbarui.');
        } else {
            flashMessage('error', 'Gagal memperbarui status.');
        }
    }
    redirect('/pages/member/my_tasks.php');
}

// ── Fetch ─────────────────────────────────────────────────────
$filter = $_GET['status'] ?? 'all';
$validFilters = ['all', 'todo', 'in_progress', 'done'];
if (!in_array($filter, $validFilters)) $filter = 'all';

$sql = "SELECT t.*, p.name AS project_name,
               GROUP_CONCAT(tc.name SEPARATOR ', ') AS categories
        FROM tasks t
        JOIN projects p ON p.id = t.project_id
        LEFT JOIN task_category_map tcm ON tcm.task_id = t.id
        LEFT JOIN task_categories tc ON tc.id = tcm.category_id
        WHERE t.assigned_to = ?";

$params = [$uid];
if ($filter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $filter;
}
$sql .= " GROUP BY t.id ORDER BY FIELD(t.status,'in_progress','todo','done'), t.deadline ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Counts per status
$counts = $db->prepare(
    "SELECT status, COUNT(*) as cnt FROM tasks WHERE assigned_to=? GROUP BY status"
);
$counts->execute([$uid]);
$countMap = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
foreach ($counts->fetchAll() as $row) { $countMap[$row['status']] = $row['cnt']; }
$totalCount = array_sum($countMap);

$pageTitle = 'Tugas Saya';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Tugas Saya</h1>
    <p>Semua tugas yang di-assign kepadamu</p>
  </div>
</div>

<!-- ── FILTER TABS ────────────────────────────────────────── -->
<div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
  <?php
  $filters = [
    'all'         => ['label' => 'Semua',       'count' => $totalCount],
    'in_progress' => ['label' => 'In Progress', 'count' => $countMap['in_progress']],
    'todo'        => ['label' => 'To Do',       'count' => $countMap['todo']],
    'done'        => ['label' => 'Selesai',     'count' => $countMap['done']],
  ];
  foreach ($filters as $key => $f): ?>
    <a href="?status=<?= $key ?>"
       class="btn <?= $filter === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
       style="gap:8px">
      <?= $f['label'] ?>
      <span style="background:rgba(255,255,255,.15);padding:1px 8px;border-radius:99px;font-size:11px">
        <?= $f['count'] ?>
      </span>
    </a>
  <?php endforeach; ?>
</div>

<!-- ── TASKS TABLE ────────────────────────────────────────── -->
<div class="card">
  <?php if (empty($tasks)): ?>
    <div class="empty-state">
      <div class="empty-icon">✅</div>
      <h3><?= $filter === 'done' ? 'Belum ada tugas selesai' : 'Tidak ada tugas' ?></h3>
      <p>
        <?php if ($filter === 'all'): ?>Belum ada tugas yang di-assign ke kamu.
        <?php elseif ($filter === 'todo'): ?>Tidak ada tugas dengan status To Do.
        <?php elseif ($filter === 'in_progress'): ?>Tidak ada tugas yang sedang dikerjakan.
        <?php else: ?>Belum ada tugas yang selesai.<?php endif; ?>
      </p>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tugas</th>
          <th>Proyek</th>
          <th>Kategori</th>
          <th>Deadline</th>
          <th>Status</th>
          <th>Ubah Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tasks as $t):
        $overdue = isOverdue($t['deadline'], $t['status']);
      ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= h($t['title']) ?></div>
            <?php if ($t['description']): ?>
              <div class="text-muted text-sm" style="margin-top:3px"><?= h(mb_substr($t['description'], 0, 80)) ?><?= mb_strlen($t['description']) > 80 ? '…' : '' ?></div>
            <?php endif; ?>
            <?php if ($overdue): ?>
              <span class="badge badge-overdue" style="margin-top:4px">Overdue</span>
            <?php endif; ?>
          </td>
          <td><span class="text-muted text-sm"><?= h($t['project_name']) ?></span></td>
          <td>
            <?php if ($t['categories']): ?>
              <span class="badge badge-admin" style="font-size:11px"><?= h($t['categories']) ?></span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="text-sm <?= $overdue ? 'text-danger' : 'text-muted' ?>">
              <?= formatDate($t['deadline']) ?>
            </span>
          </td>
          <td><?= statusBadge($t['status']) ?></td>
          <td>
            <form method="POST" style="display:inline-flex;gap:6px;align-items:center">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <select name="status" class="form-control" style="padding:5px 10px;font-size:13px;width:auto">
                <option value="todo"        <?= $t['status']==='todo'        ? 'selected' : '' ?>>To Do</option>
                <option value="in_progress" <?= $t['status']==='in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="done"        <?= $t['status']==='done'        ? 'selected' : '' ?>>Done</option>
              </select>
              <button type="submit" class="btn btn-success btn-sm">Simpan</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
