<?php
session_start();
require_once 'koneksi.php';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$msg = ''; $user = null;

// Cari akun berdasarkan email -> username terisi otomatis
if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Masukkan email yang valid.';
    } else {
        $s = mysqli_prepare($koneksi, "SELECT id, username FROM users WHERE email=? AND status='aktif' LIMIT 1");
        mysqli_stmt_bind_param($s, 's', $email);
        mysqli_stmt_execute($s);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
        if (!$user) $msg = 'Email tidak terdaftar atau akun belum aktif.';
    }
}

// Simpan password baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $p  = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) $msg = 'Sesi tidak valid. Muat ulang halaman.';
    elseif (strlen($p) < 6) $msg = 'Password minimal 6 karakter.';
    elseif ($p !== $p2)     $msg = 'Konfirmasi password tidak sama.';
    else {
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $s = mysqli_prepare($koneksi, "UPDATE users SET password=?, updated_at=NOW() WHERE id=?");
        mysqli_stmt_bind_param($s, 'si', $hash, $user['id']);
        mysqli_stmt_execute($s);
        header('Location: login.php?reset=1');
        exit;
    }
}
$e = fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lupa Password — ARSIKAS</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
       background:#0E1524;font-family:Inter,sans-serif;color:#0F172A}
  .card{width:100%;max-width:440px;background:#E3E6EC;border-radius:18px;padding:32px 28px;box-shadow:0 24px 60px rgba(0,0,0,.45)}
  h2{margin:0 0 6px;font-size:22px} p.sub{margin:0 0 22px;font-size:13.5px;font-weight:600;color:#3F4A66}
  label{display:block;font-size:12.5px;font-weight:700;color:#3F4A66;margin:0 0 6px}
  input{width:100%;box-sizing:border-box;padding:12px;margin-bottom:16px;border:1px solid #C3CAD6;border-radius:10px;
        background:#F1F3F7;font-size:14px;font-weight:600}
  input[readonly]{background:#D5DAE3;color:#3F4A66}
  button{width:100%;padding:12px;border:1px solid #957A30;border-radius:10px;
        background:linear-gradient(180deg,#B9962A,#957A30);color:#1A1300;font-size:14.5px;font-weight:800;cursor:pointer}
  .alert{padding:10px 14px;border-radius:9px;margin-bottom:16px;font-size:13px;font-weight:600;background:#FDE2E0;color:#B3382E}
  a.back{display:block;text-align:center;margin-top:18px;font-size:12.5px;font-weight:700;color:#7A5F12;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <h2>Lupa password?</h2>

<?php if (!$user): ?>
  <p class="sub">Masukkan email akun Anda, username akan terisi otomatis.</p>
  <?php if ($msg): ?><div class="alert"><?= $e($msg) ?></div><?php endif; ?>
  <form method="get" autocomplete="off">
    <label>Email</label>
    <input type="email" name="email" placeholder="nama@instansi.go.id" value="<?= $e($email) ?>" required autofocus>
    <button>Lanjut</button>
  </form>

<?php else: ?>
  <p class="sub">Buat password baru untuk akun Anda.</p>
  <?php if ($msg): ?><div class="alert"><?= $e($msg) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="email" value="<?= $e($email) ?>">
    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
    <label>Email</label>
    <input type="email" value="<?= $e($email) ?>" readonly>
    <label>Username</label>
    <input type="text" value="<?= $e($user['username']) ?>" readonly>
    <label>Password Baru</label>
    <input type="password" name="password" placeholder="Minimal 6 karakter" required autofocus>
    <label>Konfirmasi Password Baru</label>
    <input type="password" name="password2" placeholder="Ulangi password baru" required>
    <button>Simpan Password Baru</button>
  </form>
<?php endif; ?>

  <a class="back" href="login.php">← Kembali ke login</a>
</div>
</body>
</html>