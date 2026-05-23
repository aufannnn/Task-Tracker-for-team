<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

$db  = getDB();
$uid = currentUser()['id'];
$pid = (int)($_GET['id'] ?? 0);

// Verify member belongs to this project
$check = $db->prepare("SELECT p.* FROM projects p JOIN project_members pm ON pm.project_id = p.id WHERE p.id = ? AND pm.user_id = ?");
$check->execute([$pid, $uid]);
$project = $check->fetch();
if (!$project) {
    flashMessage('error', 'Proyek tidak ditemukan atau kamu bukan anggotanya.');
    redirect('/pages/member/projects.php');
}

// Handle status update (member can update own tasks from here too)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $tid    = (int)($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['todo', 'in_progress', 'done'])) {
        $db->prepare("UPDATE tasks SET status=? WHERE id=? AND assigned_to=? AND project_id=?")
           ->execute([$status, $tid, $uid, $pid]);
        flashMessage('success', 'Status diperbarui.');
    }
    redirect("/pages/member/project_view.php?id=$pid");
}

// Fetch tasks grouped by status
$tasks = $db->prepare(
    "SELECT t.*, u.name AS assignee_name
     FROM tasks t
     LEFT JOIN users u ON u.id = t.assigned_to
     WHERE t.project_id = ?
     ORDER BY t.created_at DESC"
);
$tasks->execute([$pid]);
$allTasks = $tasks->fetchAll();

$byStatus = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($allTasks as $t) {
    $byStatus[$t['status']][] = $t;
}

// Fetch members
$members = $db->prepare(
    "SELECT u.id, u.name, pm.role AS project_role,
            COUNT(t.id) AS task_count,
            SUM(t.status='done') AS done_count
     FROM project_members pm
     JOIN users u ON u.id = pm.user_id
     LEFT JOIN tasks t ON t.assigned_to = u.id AND t.project_id = ?
     WHERE pm.project_id = ?
     GROUP BY u.id, pm.role ORDER BY pm.role ASC, u.name ASC"
);
$members->execute([$pid, $pid]);
$members = $members->fetchAll();

$total = count($allTasks);
$done  = count($byStatus['done']);
$pct   = $total > 0 ? round($done / $total * 100) : 0;

$pageTitle = h($project['name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.kanban-pager {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 14px;
  flex-wrap: wrap;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}
.kanban-pager-btn {
  min-width: 28px;
  height: 28px;
  padding: 0 6px;
  border: 1.5px solid var(--border);
  background: var(--bg2);
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-family: inherit;
  color: var(--text-2);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  line-height: 1;
}
.kanban-pager-btn:hover:not(:disabled) {
  border-color: var(--accent);
  color: var(--accent);
  background: var(--accent-light);
}
.kanban-pager-btn.active {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
  font-weight: 700;
}
.kanban-pager-btn:disabled {
  opacity: 0.3;
  cursor: default;
}
.kanban-pager-ellipsis {
  font-size: 13px;
  color: var(--muted);
  padding: 0 2px;
  line-height: 28px;
}
.kanban-pager-info {
  font-size: 11px;
  color: var(--muted);
  margin-left: 6px;
  white-space: nowrap;
}
</style>

<div class="page-header">
  <div>
    <div style="margin-bottom:4px">
      <a href="<?= BASE_URL ?>/pages/member/projects.php" style="color:var(--muted);text-decoration:none;font-size:13px">← Proyek Saya</a>
    </div>
    <h1><?= h($project['name']) ?></h1>
    <?php if ($project['description']): ?>
      <p><?= h($project['description']) ?></p>
    <?php endif; ?>
  </div>
</div>

<!-- Progress bar -->
<div class="card" style="margin-bottom:24px;padding:18px 24px">
  <div style="display:flex;align-items:center;gap:24px">
    <div style="flex:1">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span class="text-muted text-sm">Progress Tim</span>
        <span style="font-weight:600;font-family:var(--font-head)"><?= $pct ?>%</span>
      </div>
      <div class="progress-bar" style="height:8px">
        <div class="progress-fill" style="width:<?= $pct ?>%"></div>
      </div>
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

<!-- Tabs -->
<div data-tabs>
<div class="tabs">
  <button class="tab-btn active" data-tab="tab-kanban">Kanban Board</button>
  <button class="tab-btn" data-tab="tab-team">Anggota Tim (<?= count($members) ?>)</button>
</div>

<!-- Kanban (read-only for others, editable for own tasks) -->
<div class="tab-panel active" id="tab-kanban">
  <div class="kanban">
    <?php
    $cols = [
      'todo'        => ['label' => 'To Do',       'dot' => 'var(--muted)'],
      'in_progress' => ['label' => 'In Progress',  'dot' => 'var(--warning)'],
      'done'        => ['label' => 'Done',         'dot' => 'var(--success)'],
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
        $isMyTask = ((int)$t['assigned_to'] === $uid);
        $overdue  = isOverdue($t['deadline'], $t['status']);
      ?>
        <div class="task-card" style="<?= $isMyTask ? 'border-color:var(--accent)' : '' ?>">
          <?php if ($isMyTask): ?>
            <div style="font-size:11px;color:var(--accent2);margin-bottom:6px;font-weight:600">● Tugasku</div>
          <?php endif; ?>
          <div class="task-card-title"><?= h($t['title']) ?></div>
          <?php if ($t['description']): ?>
            <div class="text-muted text-sm" style="margin-bottom:8px;font-size:12px"><?= h(mb_substr($t['description'], 0, 80)) ?><?= mb_strlen($t['description']) > 80 ? '…' : '' ?></div>
          <?php endif; ?>
          <div class="task-card-meta">
            <?php if ($t['deadline']): ?>
              <span class="task-card-deadline <?= $overdue ? 'overdue' : '' ?>">
                📅 <?= formatDate($t['deadline']) ?><?= $overdue ? ' ⚠' : '' ?>
              </span>
            <?php endif; ?>
            <span class="task-card-assignee">👤 <?= $t['assignee_name'] ? h($t['assignee_name']) : '—' ?></span>
          </div>
          <!-- Member can update status of their own tasks -->
          <?php if ($isMyTask): ?>
          <div style="margin-top:10px">
            <form method="POST" style="display:flex;gap:6px">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <select name="status" class="form-control" style="padding:4px 8px;font-size:12px;flex:1">
                <option value="todo"        <?= $t['status']==='todo'        ? 'selected':'' ?>>To Do</option>
                <option value="in_progress" <?= $t['status']==='in_progress' ? 'selected':'' ?>>In Progress</option>
                <option value="done"        <?= $t['status']==='done'        ? 'selected':'' ?>>Done</option>
              </select>
              <button type="submit" class="btn btn-success btn-sm">✓</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Team tab -->
<div class="tab-panel" id="tab-team">
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Anggota</th><th>Role</th><th>Tugas</th><th>Progress</th></tr></thead>
        <tbody>
        <?php foreach ($members as $m):
          $mTotal = (int)$m['task_count'];
          $mDone  = (int)$m['done_count'];
          $mPct   = $mTotal > 0 ? round($mDone / $mTotal * 100) : 0;
        ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:32px;height:32px;border-radius:50%;
                            background:<?= $m['id']==$uid ? 'var(--accent)' : 'var(--bg3)' ?>;
                            color:<?= $m['id']==$uid ? '#fff' : 'var(--muted)' ?>;
                            border:1px solid var(--border);
                            display:flex;align-items:center;justify-content:center;
                            font-family:var(--font-head);font-weight:700;font-size:13px">
                  <?= strtoupper(substr($m['name'], 0, 1)) ?>
                </div>
                <span style="font-weight:500">
                  <?= h($m['name']) ?>
                  <?= $m['id'] == $uid ? '<span class="text-muted text-sm"> (Kamu)</span>' : '' ?>
                </span>
              </div>
            </td>
            <td><?= roleBadge($m['role']) ?></td>
            <td><span class="text-sm"><?= $mDone ?>/<?= $mTotal ?> selesai</span></td>
            <td style="min-width:120px">
              <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $mPct ?>%"></div>
              </div>
              <div class="text-muted text-sm" style="margin-top:3px"><?= $mPct ?>%</div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<script>
// ── Kanban Pagination ─────────────────────────────────────────
(function () {
  const PER_PAGE = 10;

  function buildPager(cards, col) {
    if (cards.length <= PER_PAGE) return;

    let current = 1;
    const total = Math.ceil(cards.length / PER_PAGE);

    const pager = document.createElement('div');
    pager.className = 'kanban-pager';
    col.appendChild(pager);

    function pageRange(cur, tot) {
      if (tot <= 7) return Array.from({length: tot}, (_, i) => i + 1);
      const pages = [1];
      if (cur > 3) pages.push('…');
      for (let i = Math.max(2, cur - 1); i <= Math.min(tot - 1, cur + 1); i++) pages.push(i);
      if (cur < tot - 2) pages.push('…');
      pages.push(tot);
      return pages;
    }

    function render(page) {
      current = page;
      const start = (page - 1) * PER_PAGE;
      const end   = page * PER_PAGE;
      cards.forEach(function (c, i) {
        c.style.display = (i >= start && i < end) ? '' : 'none';
      });

      pager.innerHTML = '';

      // Prev
      const prev = document.createElement('button');
      prev.className = 'kanban-pager-btn';
      prev.textContent = '‹';
      prev.disabled = page === 1;
      prev.onclick = function () { render(current - 1); };
      pager.appendChild(prev);

      // Page numbers
      pageRange(page, total).forEach(function (p) {
        if (p === '…') {
          const el = document.createElement('span');
          el.className = 'kanban-pager-ellipsis';
          el.textContent = '…';
          pager.appendChild(el);
        } else {
          const btn = document.createElement('button');
          btn.className = 'kanban-pager-btn' + (p === page ? ' active' : '');
          btn.textContent = p;
          btn.onclick = (function (pg) { return function () { render(pg); }; })(p);
          pager.appendChild(btn);
        }
      });

      // Next
      const next = document.createElement('button');
      next.className = 'kanban-pager-btn';
      next.textContent = '›';
      next.disabled = page === total;
      next.onclick = function () { render(current + 1); };
      pager.appendChild(next);

      // Info
      const info = document.createElement('span');
      info.className = 'kanban-pager-info';
      const s = (page - 1) * PER_PAGE + 1;
      const e = Math.min(page * PER_PAGE, cards.length);
      info.textContent = s + '–' + e + ' dari ' + cards.length;
      pager.appendChild(info);
    }

    render(1);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.kanban-col').forEach(function (col) {
      const cards = Array.from(col.querySelectorAll('.task-card'));
      buildPager(cards, col);
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
