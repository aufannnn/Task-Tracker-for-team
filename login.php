<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();

if (isLoggedIn()) redirect('/dashboard.php');

$error = '';
if (isset($_GET['timeout'])) $error = 'Sesi berakhir karena tidak aktif. Silakan login kembali.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Email dan password wajib diisi.';
    } elseif (!login($email, $password)) {
        $error = 'Email atau password salah.';
    } else {
        redirect('/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Task Tracker</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 45%, #3b82f6 100%);
      font-family: system-ui, -apple-system, sans-serif;
    }

    /* decorative blobs on body */
    body::before {
      content: '';
      position: fixed; top: -200px; right: -200px;
      width: 600px; height: 600px; border-radius: 50%;
      background: rgba(255,255,255,0.04);
      pointer-events: none; z-index: 0;
    }
    body::after {
      content: '';
      position: fixed; bottom: -150px; left: -150px;
      width: 450px; height: 450px; border-radius: 50%;
      background: rgba(255,255,255,0.04);
      pointer-events: none; z-index: 0;
    }

    /* ── LAYOUT ─────────────────────────────────── */
    .auth-split {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      position: relative; z-index: 1;
    }

    /* LEFT PANEL — hidden, card is centered */
    .auth-left-panel { display: none; }

    /* ── CARD ─────────────────────────────────── */
    .auth-right-panel {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 24px 64px rgba(0,0,0,0.22), 0 4px 16px rgba(0,0,0,0.08);
      padding: 48px 44px 44px;
      width: 100%;
      max-width: 420px;
      animation: slideUp 0.4s cubic-bezier(0.16,1,0.3,1) both;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .auth-form-box { width: 100%; }

    /* logo pill at top of card */
    .auth-right-panel::before {
      content: '◈  TaskTracker';
      display: flex; align-items: center;
      font-size: 13px; font-weight: 700;
      color: #2563eb; letter-spacing: 0.3px;
      margin-bottom: 28px;
      padding: 6px 14px;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 999px;
      width: fit-content;
    }

    /* ── HEADING ─────────────────────────────────── */
    .form-heading { margin-bottom: 28px; }
    .form-heading h2 {
      font-size: 26px; font-weight: 800;
      color: #0f172a; margin-bottom: 6px;
      letter-spacing: -0.4px; line-height: 1.2;
    }
    .form-heading p { font-size: 14px; color: #64748b; line-height: 1.5; }

    /* ── FORM FIELDS ─────────────────────────────────── */
    .form-group-login { margin-bottom: 18px; }
    .form-label-login {
      display: block; font-size: 13px; font-weight: 600;
      color: #334155; margin-bottom: 7px; letter-spacing: 0.1px;
    }
    .form-input-login {
      width: 100%; padding: 11px 14px;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px; font-size: 14px;
      font-family: inherit; color: #0f172a;
      background: #f8faff; outline: none;
      transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
      box-sizing: border-box;
    }
    .form-input-login:focus {
      border-color: #2563eb;
      background: #fff;
      box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
    }
    .form-input-login::placeholder { color: #b0bec5; }

    /* ── BUTTON ─────────────────────────────────── */
    .login-submit {
      width: 100%; padding: 12px;
      background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
      color: #fff; font-size: 15px; font-weight: 700;
      border: none; border-radius: 10px; cursor: pointer;
      font-family: inherit; letter-spacing: 0.2px;
      transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
      box-shadow: 0 4px 14px rgba(37,99,235,0.35);
      margin-top: 8px;
    }
    .login-submit:hover {
      background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
      box-shadow: 0 6px 20px rgba(37,99,235,0.45);
      transform: translateY(-1px);
    }
    .login-submit:active { transform: translateY(0); box-shadow: 0 2px 8px rgba(37,99,235,0.3); }

    /* ── ERROR ─────────────────────────────────── */
    .login-error-box {
      background: #fef2f2; border: 1.5px solid #fecaca;
      color: #991b1b; border-radius: 10px;
      padding: 11px 14px; font-size: 13px;
      margin-bottom: 20px; line-height: 1.55;
      display: flex; align-items: flex-start; gap: 8px;
    }

    /* ── DIVIDER ─────────────────────────────────── */
    .login-divider {
      display: flex; align-items: center; gap: 12px;
      margin: 22px 0; color: #cbd5e1; font-size: 12px;
    }
    .login-divider::before, .login-divider::after {
      content: ''; flex: 1; height: 1px; background: #e2e8f0;
    }

    /* ── REGISTER LINK ─────────────────────────────────── */
    .login-register-link {
      text-align: center; font-size: 13px; color: #64748b;
      margin-top: 20px; padding-top: 20px;
      border-top: 1px solid #f1f5f9;
    }
    .login-register-link a {
      color: #2563eb; font-weight: 600; text-decoration: none;
    }
    .login-register-link a:hover { text-decoration: underline; }

    /* ── RESPONSIVE ─────────────────────────────────── */
    @media (max-width: 480px) {
      .auth-right-panel { padding: 36px 24px 32px; border-radius: 16px; }
    }
  </style>
</head>
<body>
<div class="auth-split">

  <!-- LEFT -->
  <div class="auth-left-panel">
    <div class="left-top">
      <div class="left-logo">
        <div class="left-logo-icon">◈</div>
        <span class="left-logo-text">TaskTracker</span>
      </div>
      <div class="left-headline">
        <h1>Kelola tugas tim<br>lebih efektif.</h1>
        <p>Platform terpusat untuk pembagian tugas, pemantauan progress, dan koordinasi tim yang lebih baik.</p>
      </div>
      <div class="left-features">
        <div class="left-feature">
          <div class="left-feature-icon">📋</div>
          <div class="left-feature-text">
            <strong>Kanban Board</strong>
            <span>Visualisasi tugas To Do, In Progress, Done</span>
          </div>
        </div>
        <div class="left-feature">
          <div class="left-feature-icon">👥</div>
          <div class="left-feature-text">
            <strong>Manajemen Tim</strong>
            <span>Assign tugas langsung ke anggota</span>
          </div>
        </div>
        <div class="left-feature">
          <div class="left-feature-icon">📊</div>
          <div class="left-feature-text">
            <strong>Progress Real-time</strong>
            <span>Pantau perkembangan proyek setiap saat</span>
          </div>
        </div>
        <div class="left-feature">
          <div class="left-feature-icon">🔔</div>
          <div class="left-feature-text">
            <strong>Notifikasi Overdue</strong>
            <span>Peringatan otomatis tugas yang terlambat</span>
          </div>
        </div>
      </div>
    </div>
    <div class="left-bottom">
      Kelompok 7 · Teknik Komputer · Universitas Diponegoro 2026
    </div>
  </div>

  <!-- RIGHT -->
  <div class="auth-right-panel">
    <div class="auth-form-box">
      <div class="form-heading">
        <h2>Masuk ke akun</h2>
        <p>Belum punya akun? <a href="<?= BASE_URL ?>/register.php" style="color:#2563eb;font-weight:500;text-decoration:none">Daftar sekarang</a></p>
      </div>

      <?php if ($error): ?>
        <div class="login-error-box">⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>/login.php">
        <div class="form-group-login">
          <label class="form-label-login">Email</label>
          <input type="email" name="email" class="form-input-login"
                 placeholder="email@kamu.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group-login">
          <label class="form-label-login">Password</label>
          <input type="password" name="password" class="form-input-login"
                 placeholder="••••••••" required>
        </div>
        <button type="submit" class="login-submit">Masuk →</button>
      </form>

    </div>
  </div>

</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
