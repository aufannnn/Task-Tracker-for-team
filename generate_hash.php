<?php
/**
 * generate_hash.php
 * Jalankan sekali untuk generate akun pertama.
 * HAPUS FILE INI setelah selesai setup!
 *
 * Akses: http://localhost/task_tracker/generate_hash.php
 */

// Daftar akun yang akan dibuat
$accounts = [
    ['name' => 'Admin Kelompok 7',  'email' => 'admin@tasktracker.com',  'password' => 'admin123',  'role' => 'admin'],
    ['name' => 'Hening Wijaya',     'email' => 'hening@tasktracker.com', 'password' => 'member123', 'role' => 'member'],
    ['name' => 'Aufan Damays',      'email' => 'aufan@tasktracker.com',  'password' => 'member123', 'role' => 'member'],
    ['name' => 'Alexis Pratama',    'email' => 'alexis@tasktracker.com', 'password' => 'member123', 'role' => 'member'],
    ['name' => 'Rayhan Ghifari',    'email' => 'rayhan@tasktracker.com', 'password' => 'member123', 'role' => 'member'],
];

$rows = [];
foreach ($accounts as $a) {
    $hash = password_hash($a['password'], PASSWORD_BCRYPT);
    $rows[] = "('" . addslashes($a['name']) . "', '" . addslashes($a['email']) . "', '$hash', '{$a['role']}')";
}

$sql = "INSERT INTO users (name, email, password, role) VALUES\n" . implode(",\n", $rows) . ";";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Generate Hash — Setup</title>
  <style>
    body { font-family: monospace; background: #0f0f11; color: #e8e8f0; padding: 40px; max-width: 900px; }
    h2 { color: #7c6af7; }
    pre { background: #18181d; border: 1px solid #2e2e38; border-radius: 8px; padding: 20px; overflow-x: auto; color: #2dd4a0; white-space: pre-wrap; word-break: break-all; }
    .warning { background: rgba(240,82,82,.1); border: 1px solid rgba(240,82,82,.3); color: #f05252; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
    .step { margin-bottom: 24px; }
    .step h3 { color: #b8acff; margin-bottom: 8px; }
    button { background: #7c6af7; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; }
    button:hover { background: #6a5ae0; }
  </style>
</head>
<body>
  <h2>⚙ Task Tracker — Setup Akun</h2>

  <div class="warning">
    ⚠ <strong>HAPUS FILE INI</strong> setelah selesai setup! File ini tidak boleh bisa diakses di production.
  </div>

  <div class="step">
    <h3>Langkah 1: Buka phpMyAdmin</h3>
    <p>Pastikan database <code>task_tracker</code> sudah di-import dari <code>task_tracker_db.sql</code>.</p>
  </div>

  <div class="step">
    <h3>Langkah 2: Jalankan query ini di phpMyAdmin → SQL</h3>
    <pre id="sql-output"><?= htmlspecialchars($sql) ?></pre>
    <button onclick="copySQL()">📋 Copy Query</button>
  </div>

  <div class="step">
    <h3>Akun yang akan dibuat:</h3>
    <table style="border-collapse:collapse;width:100%">
      <thead>
        <tr style="border-bottom:1px solid #2e2e38">
          <th style="text-align:left;padding:8px 12px;color:#7a7a90">Nama</th>
          <th style="text-align:left;padding:8px 12px;color:#7a7a90">Email</th>
          <th style="text-align:left;padding:8px 12px;color:#7a7a90">Password</th>
          <th style="text-align:left;padding:8px 12px;color:#7a7a90">Role</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($accounts as $a): ?>
        <tr style="border-bottom:1px solid #2e2e38">
          <td style="padding:8px 12px"><?= htmlspecialchars($a['name']) ?></td>
          <td style="padding:8px 12px;color:#7c6af7"><?= htmlspecialchars($a['email']) ?></td>
          <td style="padding:8px 12px;color:#f5a623"><?= htmlspecialchars($a['password']) ?></td>
          <td style="padding:8px 12px;color:<?= $a['role']==='admin' ? '#b8acff' : '#7a7a90' ?>"><?= $a['role'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="step">
    <h3>Langkah 3: Login & hapus file ini</h3>
    <p>Setelah query dijalankan, <a href="<?= BASE_URL ?>/login.php" style="color:#7c6af7">login di sini</a>, lalu hapus file <code>generate_hash.php</code> dari server.</p>
  </div>

<script>
function copySQL() {
  const text = document.getElementById('sql-output').textContent;
  navigator.clipboard.writeText(text).then(() => alert('Query berhasil dicopy!'));
}
</script>
</body>
</html>
