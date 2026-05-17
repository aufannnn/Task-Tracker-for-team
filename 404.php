<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Halaman Tidak Ditemukan</title>  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:#0f0f11;--bg2:#18181d;--border:#2e2e38;--text:#e8e8f0;--muted:#7a7a90;--accent:#7c6af7}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center}
    .wrap{padding:40px}
    .code{font-family:'Syne',sans-serif;font-size:120px;font-weight:800;color:var(--accent);opacity:.15;line-height:1;margin-bottom:-20px}
    h1{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;margin-bottom:12px}
    p{color:var(--muted);margin-bottom:28px}
    a{display:inline-block;background:var(--accent);color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:500}
    a:hover{opacity:.85}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="code">404</div>
    <h1>Halaman tidak ditemukan</h1>
    <p>Halaman yang kamu cari tidak ada atau sudah dipindahkan.</p>
    <a href="<?= BASE_URL ?>/">← Kembali ke Dashboard</a>
  </div>
</body>
</html>
