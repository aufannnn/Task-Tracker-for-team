<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();

if (!isLoggedIn()) redirect('/login.php');
if (empty($_SESSION['need_role_choice'])) redirect('/dashboard.php');

$db   = getDB();
$user = currentUser();

// Ambil role asli dari database
$dbUser = $db->prepare("SELECT role FROM users WHERE id = ?");
$dbUser->execute([$user['id']]);
$dbRole = $dbUser->fetchColumn(); // 'admin' atau 'member'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $chosen = $_POST['role'] ?? 'member';
    if (!in_array($chosen, ['admin', 'member'])) $chosen = 'member';

    // Set role di session untuk sesi ini saja — tidak ubah database
    $_SESSION['role'] = $chosen;

    // Hapus flag
    unset($_SESSION['need_role_choice']);

    if ($chosen === 'admin') {
        flashMessage('success', 'Kamu login sebagai Admin untuk sesi ini.');
    } else {
        flashMessage('success', 'Kamu login sebagai Member untuk sesi ini.');
    }

    redirect('/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pilih Peran — Task Tracker</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="role-picker-page">
  <div class="role-picker-box">

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <div style="width:34px;height:34px;border-radius:8px;background:var(--accent);
                  display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px">◈</div>
      <span style="font-weight:700;font-size:16px;color:var(--blue-800)">TaskTracker</span>
    </div>

    <h2>Hai, <?= h(explode(' ', $user['name'])[0]) ?>! 👋</h2>
    <p class="sub">
      Mau login sebagai apa untuk sesi ini?
      <br><span style="font-size:12px;color:var(--muted-2)">Pilihan ini hanya berlaku untuk sesi login sekarang.</span>
    </p>

    <form method="POST" action="<?= BASE_URL ?>/choose_role.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="role" id="role-input" value="member">

      <div class="role-cards">
        <div class="role-card" id="card-admin" onclick="selectRole('admin')">
          <span class="role-card-icon">👑</span>
          <div class="role-card-title">Admin / Ketua Tim</div>
          <div class="role-card-desc">
            Buat dan kelola proyek, assign tugas ke anggota, pantau progress seluruh tim.
          </div>
          <span class="role-card-tag tag-admin">Admin</span>
        </div>

        <div class="role-card selected" id="card-member" onclick="selectRole('member')">
          <span class="role-card-icon">👤</span>
          <div class="role-card-title">Member / Anggota Tim</div>
          <div class="role-card-desc">
            Lihat dan kerjakan tugas yang di-assign ke kamu, pantau progress proyek tim.
          </div>
          <span class="role-card-tag tag-member">Member</span>
        </div>
      </div>

      <div id="info-admin" style="display:none;background:var(--blue-50);border:1px solid var(--blue-200);border-radius:var(--radius-sm);padding:11px 14px;margin-bottom:18px;font-size:13px;color:var(--blue-700)">
        ℹ Kamu akan masuk sebagai <strong>Admin</strong> untuk sesi ini. Kamu bisa membuat proyek baru dan mengelola anggota tim.
      </div>
      <div id="info-member" style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:11px 14px;margin-bottom:18px;font-size:13px;color:var(--muted)">
        ℹ Kamu akan masuk sebagai <strong>Member</strong> untuk sesi ini. Kamu bisa melihat dan mengerjakan tugas yang di-assign ke kamu.
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center">
        Lanjutkan →
      </button>
    </form>

    <p style="margin-top:14px;text-align:center;font-size:12px;color:var(--muted-2)">
      Kamu bisa ganti peran dengan logout dan login kembali.
    </p>
  </div>
</div>

<script>
function selectRole(role) {
  document.getElementById('role-input').value = role;
  document.getElementById('card-admin').classList.toggle('selected', role === 'admin');
  document.getElementById('card-member').classList.toggle('selected', role === 'member');
  document.getElementById('info-admin').style.display  = role === 'admin'  ? 'block' : 'none';
  document.getElementById('info-member').style.display = role === 'member' ? 'block' : 'none';
}
</script>
</body>
</html>
