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
    body { margin: 0; background: #fff; }

    .auth-split {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
    }

    /* ── LEFT PANEL ─────────────────────────────────── */
    .auth-left-panel {
      background: linear-gradient(160deg, #1e3a8a 0%, #1d4ed8 55%, #3b82f6 100%);
      display: flex; flex-direction: column;
      justify-content: space-between;
      padding: 48px;
      position: relative; overflow: hidden;
    }

    /* decorative circles */
    .auth-left-panel::before {
      content: '';
      position: absolute; top: -120px; right: -120px;
      width: 420px; height: 420px; border-radius: 50%;
      background: rgba(255,255,255,0.05);
      pointer-events: none;
    }
    .auth-left-panel::after {
      content: '';
      position: absolute; bottom: -80px; left: -80px;
      width: 300px; height: 300px; border-radius: 50%;
      background: rgba(255,255,255,0.05);
      pointer-events: none;
    }

    .left-top { position: relative; z-index: 1; }
    .left-logo {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 56px;
    }
    .left-logo-icon {
      width: 38px; height: 38px; border-radius: 9px;
      background: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 17px; color: #fff;
    }
    .left-logo-text { font-size: 17px; font-weight: 700; color: #fff; }

    .left-headline h1 {
      font-size: 34px; font-weight: 800; color: #fff;
      line-height: 1.2; margin-bottom: 14px;
      letter-spacing: -0.5px;
    }
    .left-headline p {
      font-size: 15px; color: rgba(255,255,255,0.65);
      line-height: 1.65; max-width: 340px;
    }

    .left-features {
      margin-top: 44px;
      display: flex; flex-direction: column; gap: 14px;
      position: relative; z-index: 1;
    }
    .left-feature {
      display: flex; align-items: flex-start; gap: 12px;
    }
    .left-feature-icon {
      width: 34px; height: 34px; border-radius: 8px;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.18);
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0; margin-top: 1px;
    }
    .left-feature-text strong {
      display: block; font-size: 13px; font-weight: 600;
      color: rgba(255,255,255,0.92); margin-bottom: 2px;
    }
    .left-feature-text span {
      font-size: 12px; color: rgba(255,255,255,0.5);
    }

    .left-bottom {
      position: relative; z-index: 1;
      font-size: 12px; color: rgba(255,255,255,0.35);
    }

    /* ── RIGHT PANEL ─────────────────────────────────── */
    .auth-right-panel {
      background: #fff;
      display: flex; align-items: center; justify-content: center;
      padding: 48px;
    }
    .auth-form-box { width: 100%; max-width: 360px; }

    .form-heading { margin-bottom: 32px; }
    .form-heading h2 {
      font-size: 24px; font-weight: 700;
      color: #0f172a; margin-bottom: 6px;
    }
    .form-heading p { font-size: 14px; color: #64748b; }

    .form-group-login { margin-bottom: 18px; }
    .form-label-login {
      display: block; font-size: 13px; font-weight: 500;
      color: #334155; margin-bottom: 6px;
    }
    .form-input-login {
      width: 100%; padding: 10px 14px;
      border: 1.5px solid #e2eaf8;
      border-radius: 8px; font-size: 14px;
      font-family: inherit; color: #0f172a;
      background: #f8faff; outline: none;
      transition: all 0.15s ease;
      box-sizing: border-box;
    }
    .form-input-login:focus {
      border-color: #2563eb;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .form-input-login::placeholder { color: #94a3b8; }

    .login-submit {
      width: 100%; padding: 11px;
      background: linear-gradient(135deg, #1d4ed8, #2563eb);
      color: #fff; font-size: 15px; font-weight: 600;
      border: none; border-radius: 8px; cursor: pointer;
      font-family: inherit; transition: all 0.15s ease;
      box-shadow: 0 2px 10px rgba(37,99,235,0.3);
      margin-top: 6px;
    }
    .login-submit:hover {
      background: linear-gradient(135deg, #1e40af, #1d4ed8);
      box-shadow: 0 4px 16px rgba(37,99,235,0.4);
      transform: translateY(-1px);
    }
    .login-submit:active { transform: translateY(0); }

    .login-error-box {
      background: #fef2f2; border: 1px solid #fecaca;
      color: #991b1b; border-radius: 8px;
      padding: 10px 13px; font-size: 13px;
      margin-bottom: 20px; line-height: 1.5;
    }

    .login-divider {
      display: flex; align-items: center; gap: 12px;
      margin: 22px 0; color: #cbd5e1; font-size: 12px;
    }
    .login-divider::before, .login-divider::after {
      content: ''; flex: 1; height: 1px; background: #e2e8f0;
    }

    .login-register-link {
      text-align: center; font-size: 13px; color: #64748b;
    }
    .login-register-link a {
      color: #2563eb; font-weight: 500; text-decoration: none;
    }
    .login-register-link a:hover { text-decoration: underline; }

    /* responsive */
    @media (max-width: 900px) {
      .auth-split { grid-template-columns: 1fr; }
      .auth-left-panel { display: none; }
      .auth-right-panel { padding: 32px 24px; min-height: 100vh; }
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
