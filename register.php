<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();

if (isLoggedIn()) redirect('/dashboard.php');

$errors = [];
$values = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $values   = compact('name', 'email');

    if (!$name)                                         $errors[] = 'Nama lengkap wajib diisi.';
    if (!$email)                                        $errors[] = 'Email wajib diisi.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (!$password)                                     $errors[] = 'Password wajib diisi.';
    elseif (strlen($password) < 8)                      $errors[] = 'Password minimal 8 karakter.';
    if ($password !== $confirm)                         $errors[] = 'Konfirmasi password tidak cocok.';

    if (empty($errors)) {
        $db = getDB();
        $exists = $db->prepare("SELECT id FROM users WHERE email = ?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $errors[] = 'Email sudah terdaftar. Silakan login.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'member')")
               ->execute([$name, $email, $hash]);
            login($email, $password);
            redirect('/choose_role.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar — Task Tracker</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body { margin: 0; background: #fff; }
    .auth-split { min-height: 100vh; display: grid; grid-template-columns: 1fr 1fr; }
    .auth-left-panel {
      background: linear-gradient(160deg, #1e3a8a 0%, #1d4ed8 55%, #3b82f6 100%);
      display: flex; flex-direction: column; justify-content: space-between;
      padding: 48px; position: relative; overflow: hidden;
    }
    .auth-left-panel::before {
      content: ''; position: absolute; top: -120px; right: -120px;
      width: 420px; height: 420px; border-radius: 50%;
      background: rgba(255,255,255,0.05); pointer-events: none;
    }
    .auth-left-panel::after {
      content: ''; position: absolute; bottom: -80px; left: -80px;
      width: 300px; height: 300px; border-radius: 50%;
      background: rgba(255,255,255,0.05); pointer-events: none;
    }
    .left-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 48px; position: relative; z-index:1; }
    .left-logo-icon {
      width: 38px; height: 38px; border-radius: 9px;
      background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 17px; color: #fff;
    }
    .left-logo-text { font-size: 17px; font-weight: 700; color: #fff; }
    .left-headline { position: relative; z-index:1; }
    .left-headline h1 { font-size: 32px; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 12px; letter-spacing: -0.5px; }
    .left-headline p { font-size: 14px; color: rgba(255,255,255,0.62); line-height: 1.65; }
    .left-steps { margin-top: 40px; display: flex; flex-direction: column; gap: 16px; position: relative; z-index:1; }
    .left-step { display: flex; align-items: flex-start; gap: 12px; }
    .left-step-num {
      width: 28px; height: 28px; border-radius: 50%;
      background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; margin-top: 1px;
    }
    .left-step-text strong { display: block; font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.9); margin-bottom: 2px; }
    .left-step-text span   { font-size: 12px; color: rgba(255,255,255,0.48); }
    .left-bottom { font-size: 12px; color: rgba(255,255,255,0.32); position: relative; z-index:1; }

    .auth-right-panel {
      background: #fff; display: flex; align-items: center;
      justify-content: center; padding: 48px; overflow-y: auto;
    }
    .auth-form-box { width: 100%; max-width: 360px; }
    .form-heading { margin-bottom: 28px; }
    .form-heading h2 { font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 5px; }
    .form-heading p { font-size: 13px; color: #64748b; }

    .form-group-login { margin-bottom: 16px; }
    .form-label-login { display: block; font-size: 13px; font-weight: 500; color: #334155; margin-bottom: 5px; }
    .form-input-login {
      width: 100%; padding: 10px 14px; border: 1.5px solid #e2eaf8;
      border-radius: 8px; font-size: 14px; font-family: inherit;
      color: #0f172a; background: #f8faff; outline: none;
      transition: all 0.15s ease; box-sizing: border-box;
    }
    .form-input-login:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .form-input-login::placeholder { color: #94a3b8; }
    .form-hint-login { font-size: 11px; color: #94a3b8; margin-top: 4px; }

    .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

    .login-submit {
      width: 100%; padding: 11px;
      background: linear-gradient(135deg, #1d4ed8, #2563eb);
      color: #fff; font-size: 15px; font-weight: 600;
      border: none; border-radius: 8px; cursor: pointer;
      font-family: inherit; transition: all 0.15s ease;
      box-shadow: 0 2px 10px rgba(37,99,235,0.3); margin-top: 4px;
    }
    .login-submit:hover { background: linear-gradient(135deg, #1e40af, #1d4ed8); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(37,99,235,0.4); }
    .login-submit:active { transform: translateY(0); }

    .login-error-box { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: 10px 13px; font-size: 13px; margin-bottom: 18px; line-height: 1.6; }
    .login-info-box  { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; border-radius: 8px; padding: 10px 13px; font-size: 12px; margin-bottom: 18px; line-height: 1.5; }

    .login-link { margin-top: 18px; text-align: center; font-size: 13px; color: #64748b; }
    .login-link a { color: #2563eb; font-weight: 500; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }

    @media (max-width: 900px) {
      .auth-split { grid-template-columns: 1fr; }
      .auth-left-panel { display: none; }
      .auth-right-panel { padding: 32px 20px; min-height: 100vh; align-items: flex-start; padding-top: 48px; }
    }
    @media (max-width: 480px) { .form-row-2 { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="auth-split">
  <div class="auth-left-panel">
    <div>
      <div class="left-logo">
        <div class="left-logo-icon">◈</div>
        <span class="left-logo-text">TaskTracker</span>
      </div>
      <div class="left-headline">
        <h1>Mulai perjalanan<br>produktif kamu.</h1>
        <p>Daftar sekarang dan kelola tugas tim kamu dengan lebih terstruktur dan efisien.</p>
      </div>
      <div class="left-steps">
        <div class="left-step">
          <div class="left-step-num">1</div>
          <div class="left-step-text">
            <strong>Buat akun</strong>
            <span>Isi data diri dan buat akun dalam hitungan detik</span>
          </div>
        </div>
        <div class="left-step">
          <div class="left-step-num">2</div>
          <div class="left-step-text">
            <strong>Pilih peran</strong>
            <span>Jadi Admin untuk memimpin atau Member untuk bergabung</span>
          </div>
        </div>
        <div class="left-step">
          <div class="left-step-num">3</div>
          <div class="left-step-text">
            <strong>Mulai bekerja</strong>
            <span>Buat proyek, assign tugas, dan pantau progress tim</span>
          </div>
        </div>
      </div>
    </div>
    <div class="left-bottom">Kelompok 7 · Teknik Komputer · Universitas Diponegoro 2026</div>
  </div>

  <div class="auth-right-panel">
    <div class="auth-form-box">
      <div class="form-heading">
        <h2>Buat akun baru</h2>
        <p>Sudah punya akun? <a href="<?= BASE_URL ?>/login.php" style="color:#2563eb;font-weight:500;text-decoration:none">Masuk di sini</a></p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="login-error-box">
          <?php foreach ($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="login-info-box">
        ℹ Kamu bisa pilih peran (Admin / Member) setelah mendaftar.
      </div>

      <form method="POST" action="<?= BASE_URL ?>/register.php">
        <div class="form-group-login">
          <label class="form-label-login">Nama Lengkap</label>
          <input type="text" name="name" class="form-input-login" placeholder="Nama kamu" value="<?= htmlspecialchars($values['name']) ?>" required autofocus>
        </div>
        <div class="form-group-login">
          <label class="form-label-login">Email</label>
          <input type="email" name="email" class="form-input-login" placeholder="email@kamu.com" value="<?= htmlspecialchars($values['email']) ?>" required>
        </div>
        <div class="form-row-2">
          <div class="form-group-login">
            <label class="form-label-login">Password</label>
            <input type="password" name="password" class="form-input-login" placeholder="Min. 8 karakter" required minlength="8">
          </div>
          <div class="form-group-login">
            <label class="form-label-login">Konfirmasi</label>
            <input type="password" name="confirm_password" class="form-input-login" placeholder="Ulangi" required>
          </div>
        </div>
        <button type="submit" class="login-submit">Buat Akun →</button>
      </form>

    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
