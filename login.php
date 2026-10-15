<?php
session_start();
require_once 'koneksi.php';

// Jika sudah login, langsung ke dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$mode = ($_GET['daftar'] ?? '') === '1' ? 'register' : 'login';

// -------------------------
// PROSES FORM
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {

        // =========================
        // LOGIN
        // =========================
        if (($_POST['form_type'] ?? '') === 'login') {
            $username = trim($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            $mode = 'login';

            if ($username === '' || $password === '') {
                $error = 'Username dan password wajib diisi.';
            } else {
                $stmt = mysqli_prepare(
                    $koneksi,
                    "SELECT id, username, password, nama_lengkap, email, role, status
                     FROM users
                     WHERE username = ?
                     LIMIT 1"
                );

                if (!$stmt) {
                    $error = 'Terjadi kesalahan saat memproses login.';
                } else {
                    mysqli_stmt_bind_param($stmt, 's', $username);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $user = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);

                    if (!$user || !password_verify($password, $user['password'])) {
                        $error = 'Username atau password salah.';
                    } elseif (strtolower((string)$user['status']) === 'menunggu') {
                        $error = 'Pendaftaran akun Anda masih menunggu persetujuan Administrator.';
                    } elseif (strtolower((string)$user['status']) === 'ditolak') {
                        $error = 'Pendaftaran akun Anda ditolak. Silakan hubungi Administrator.';
                    } elseif (strtolower((string)$user['status']) !== 'aktif') {
                        $error = 'Akun Anda tidak aktif. Silakan hubungi Administrator.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id']      = (int) $user['id'];
                        $_SESSION['username']     = $user['username'];
                        $_SESSION['nama']         = $user['nama_lengkap'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                        $_SESSION['email']        = $user['email'];
                        $_SESSION['role']         = $user['role'];
                        $_SESSION['status']       = $user['status'];

                        $nama = trim($user['nama_lengkap']);
                        $parts = preg_split('/\s+/', $nama);

                        if (count($parts) >= 2) {
                            $initials = strtoupper(
                                substr($parts[0], 0, 1) .
                                substr(end($parts), 0, 1)
                            );
                        } else {
                            $initials = strtoupper(substr($nama, 0, 2));
                        }

                        $_SESSION['initials'] = $initials ?: 'IK';

                        header('Location: index.php');
                        exit;
                    }
                }
            }

        // =========================
        // PENDAFTARAN AKUN PEGAWAI
        // =========================
        } elseif (($_POST['form_type'] ?? '') === 'register') {
            $mode = 'register';

            $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
            $username     = trim($_POST['username'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $password     = (string) ($_POST['password'] ?? '');
            $password2    = (string) ($_POST['password_confirmation'] ?? '');

            if ($nama_lengkap === '' || $username === '' || $email === '' || $password === '' || $password2 === '') {
                $error = 'Semua data pendaftaran wajib diisi.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Format email tidak valid.';
            } elseif (strlen($username) < 4) {
                $error = 'Username minimal 4 karakter.';
            } elseif (strlen($password) < 6) {
                $error = 'Password minimal 6 karakter.';
            } elseif ($password !== $password2) {
                $error = 'Konfirmasi password tidak sama.';
            } else {
                // Cek username
                $stmt = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, 's', $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $username_exists = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                // Cek email
                $stmt = mysqli_prepare($koneksi, "SELECT id FROM users WHERE email = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $email_exists = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if ($username_exists) {
                    $error = 'Username sudah digunakan. Silakan pilih username lain.';
                } elseif ($email_exists) {
                    $error = 'Email sudah terdaftar. Silakan gunakan email lain.';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    // Pegawai tidak dapat memilih role.
                    // Semua pendaftaran otomatis menjadi operator dan menunggu persetujuan admin.
                    $role = 'operator';
                    $status = 'menunggu';

                    $stmt = mysqli_prepare(
                        $koneksi,
                        "INSERT INTO users
                        (username, password, nama_lengkap, email, role, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
                    );

                    if (!$stmt) {
                        $error = 'Pendaftaran gagal diproses.';
                    } else {
                        mysqli_stmt_bind_param(
                            $stmt,
                            'ssssss',
                            $username,
                            $password_hash,
                            $nama_lengkap,
                            $email,
                            $role,
                            $status
                        );

                        if (mysqli_stmt_execute($stmt)) {
                            $success = 'Pendaftaran berhasil dikirim. Akun Anda menunggu persetujuan Administrator.';
                            $mode = 'login';
                        } else {
                            $error = 'Pendaftaran gagal disimpan. Silakan coba lagi.';
                        }

                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Masuk — ARSIKAS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#0E1524; --panel:#141D30; --panel-2:#182238;
    --line:#26314A; --gold:#B9962A; --gold-soft:#957A30;
    --text:#E9EDF4; --muted:#8C96AC; --red:#D9756B;

    /* Warna kotak abu-abu & teks */
    --box:#E3E6EC; --box-2:#D5DAE3; --box-3:#F1F3F7; --box-line:#C3CAD6;
    --ink:#0F172A; --ink-2:#3F4A66; --ink-3:#4A5672; --cream-dk:#7A5F12;

    box-sizing:border-box; padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px);
  }
  *{box-sizing:inherit}
  html,body{height:100%;margin:0;background:var(--bg);color:var(--text);
    font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased;}
  h1,h2,.brand{font-family:'Space Grotesk',sans-serif;}

  .shell{min-height:100%; display:flex; flex-direction:column; position:relative; isolation:isolate;
    background:url('foto/u.jpg') center/cover no-repeat fixed, var(--bg);}
  .shell::before{content:''; position:absolute; inset:0; z-index:-1;
    background:linear-gradient(180deg,rgba(8,16,32,.72) 0%,rgba(8,16,32,.56) 50%,rgba(8,16,32,.8) 100%);}
  .topbrand{padding:26px 40px;}
  @media (max-width:600px){ .topbrand{padding:20px 20px 0;} }
  .stack{width:100%; max-width:480px; margin:auto; padding:10px 20px 40px; display:flex; flex-direction:column; align-items:stretch;}

  .brand{display:flex; align-items:center; justify-content:flex-start; gap:10px; font-size:19px; font-weight:700; color:#fff; text-shadow:0 1px 8px rgba(0,0,0,.6);}
  .brand .mark{width:36px;height:36px;border-radius:9px;background:linear-gradient(180deg,#B9962A,#957A30);
    display:flex;align-items:center;justify-content:center;color:#1A1300;font-weight:700;font-family:'IBM Plex Mono',monospace;font-size:14px;text-shadow:none;}
  .stack h1{font-size:26px; font-weight:700; line-height:1.3; margin:0 0 12px; text-align:center; color:#fff; text-shadow:0 2px 14px rgba(0,0,0,.7);}
  .desc{margin:0 auto 26px; max-width:430px; text-align:center; color:#FFFFFF; font-size:14px; font-weight:600; line-height:1.65; text-shadow:0 1px 8px rgba(0,0,0,.75);}

  .hero-foot{font-size:12px; font-weight:600; text-align:center; color:#FFFFFF; border-top:1px solid rgba(255,255,255,.4); padding-top:16px; margin-top:22px; text-shadow:0 1px 6px rgba(0,0,0,.75);}

  .card{width:100%; max-width:none; padding:34px 30px; border-radius:18px;
    background:var(--box); border:1px solid var(--box-line); color:var(--ink);
    box-shadow:0 24px 60px rgba(0,0,0,.45);}
  .card h2{font-size:23px; font-weight:700; color:var(--ink); margin-bottom:6px;}
  .card .sub{color:var(--ink-2); font-size:13.5px; font-weight:600; margin-bottom:28px;}


  label{display:block; font-size:12.5px; color:var(--ink-2); margin-bottom:6px; font-weight:700;}
  .field{margin-bottom:18px;}
  .input-wrap{position:relative; display:flex; align-items:center; background:var(--box-3); border:1px solid var(--box-line);
    border-radius:10px; padding:0 12px; transition:border-color .15s;}
  .input-wrap:focus-within{border-color:var(--gold); box-shadow:0 0 0 3px rgba(185,150,42,.25);}
  .input-wrap svg{width:16px;height:16px;color:var(--ink-3);flex:none;}
  .input-wrap input{flex:1; background:transparent; border:none; outline:none; color:var(--ink);
    font-size:14px; font-weight:600; padding:12px 10px; font-family:'Inter',sans-serif;}
  .input-wrap input::placeholder{color:#6B7690; font-weight:500;}
  .toggle-pw{cursor:pointer; color:var(--cream-dk); font-size:12px; font-weight:700; user-select:none;}

  .row-between{display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; font-size:12.5px;}
  .remember{display:flex; align-items:center; gap:7px; color:var(--ink-2); font-weight:600;}
  .row-between a{color:var(--cream-dk); font-weight:700; text-decoration:none;}
  .row-between a:hover{text-decoration:underline;}

  .btn-login{width:100%; padding:12px; border-radius:10px; border:1px solid #957A30;
    background:linear-gradient(180deg,#B9962A,#957A30); color:#1A1300;
    font-size:14.5px; font-weight:800; cursor:pointer; transition:filter .15s;}
  .btn-login:hover{filter:brightness(1.05);}

  .alert{background:#FDE2E0; border:1px solid #F0A9A3; color:#B3382E; font-size:13px; font-weight:600; padding:10px 14px;
    border-radius:9px; margin-bottom:18px; display:flex; align-items:center; gap:8px;}
  .alert svg{width:15px;height:15px;flex:none;}

  .divider{display:flex; align-items:center; gap:10px; color:var(--ink-3); font-size:12px; font-weight:600; margin:22px 0;}
  .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--box-line);}

  .success-alert{background:#DFF5E8; border:1px solid #9ED5B7; color:#1E7A4A; font-size:13px; font-weight:600; padding:10px 14px;
    border-radius:9px; margin-bottom:18px; display:flex; align-items:center; gap:8px;}
  .success-alert svg{width:15px;height:15px;flex:none;}
  .register-info{background:var(--box-2); border:1px solid var(--box-line); border-radius:10px; padding:12px 14px;
    margin:4px 0 18px; display:flex; flex-direction:column; gap:5px; font-size:12px; font-weight:600; line-height:1.5; color:var(--ink-2);}
  .register-info strong{color:var(--ink); font-size:12.5px; font-weight:800;}
  .register-info b{color:var(--cream-dk); font-weight:800;}
  .switch-link{color:var(--cream-dk); text-decoration:none; font-weight:700;}
  .switch-link:hover{text-decoration:underline;}
  .footnote{text-align:center; font-size:12.5px; font-weight:600; color:var(--ink-2); margin-top:6px;}
</style>
</head>
<body>
<div class="shell">

  <header class="topbrand">
    <div class="brand"><div class="mark">√</div>ARSIKAS</div>
  </header>

  <main class="stack">
    <h1>Ukur Tingkat Kematangan Keamanan Siber dan Sandi Organisasi Anda.</h1>

    <p class="desc">Portal internal untuk mengelola instrumen penilaian, memantau progres pengisian, dan menghasilkan rekap Tingkat Kematangan Keamanan Siber &amp; Persandian secara terstruktur.</p>

    <div class="card">
      <h2>Masuk ke akun Anda</h2>
      <p class="sub">
        <?php if ($mode === 'register'): ?>
            Daftarkan akun pegawai. Akun akan dapat digunakan setelah disetujui Administrator.
        <?php else: ?>
            Gunakan username dan password yang terdaftar untuk mengakses dashboard.
        <?php endif; ?>
    </p>

      <?php if ($error): ?>
        <div class="alert">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 8v5M12 16h.01"/>
          </svg>
          <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="success-alert">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9"/>
            <path d="m8 12 2.5 2.5L16 9"/>
          </svg>
          <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <?php if ($mode === 'register'): ?>

        <!-- FORM PENDAFTARAN PEGAWAI -->
        <form method="post" autocomplete="off">
          <input type="hidden" name="form_type" value="register">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <div class="field">
            <label for="nama_lengkap">Nama Lengkap</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="12" cy="8" r="3.5"/>
                <path d="M4.5 20c0-3.6 3.1-6 7.5-6s7.5 2.4 7.5 6"/>
              </svg>
              <input type="text" id="nama_lengkap" name="nama_lengkap"
                     placeholder="Nama lengkap"
                     value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autofocus>
            </div>
          </div>

          <div class="field">
            <label for="username">Username</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="12" cy="8" r="3.5"/>
                <path d="M4.5 20c0-3.6 3.1-6 7.5-6s7.5 2.4 7.5 6"/>
              </svg>
              <input type="text" id="username" name="username"
                     placeholder="Username yang akan digunakan untuk login"
                     value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
          </div>

          <div class="field">
            <label for="email">Email</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <rect x="3.5" y="5" width="17" height="14" rx="2"/>
                <path d="m4 7 8 6 8-6"/>
              </svg>
              <input type="email" id="email" name="email"
                     placeholder="nama@instansi.go.id"
                     value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/>
                <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>
              </svg>
              <input type="password" id="password" name="password"
                     placeholder="Minimal 6 karakter" required>
              <span class="toggle-pw" onclick="togglePw(this, 'password')">Lihat</span>
            </div>
          </div>

          <div class="field">
            <label for="password_confirmation">Konfirmasi Password</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/>
                <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>
              </svg>
              <input type="password" id="password_confirmation" name="password_confirmation"
                     placeholder="Ulangi password" required>
              <span class="toggle-pw" onclick="togglePw(this, 'password_confirmation')">Lihat</span>
            </div>
          </div>

          <div class="register-info">
            <strong>Persetujuan Administrator</strong>
            <span>Akun akan berstatus <b>Menunggu</b> dan belum dapat digunakan sampai Administrator menyetujuinya.</span>
          </div>

          <button type="submit" class="btn-login">Ajukan Pendaftaran</button>
        </form>

        <div class="divider">sudah memiliki akun?</div>
        <p class="footnote">
          <a class="switch-link" href="login.php">Kembali ke halaman login</a>
        </p>

      <?php else: ?>

        <!-- FORM LOGIN -->
        <form method="post" autocomplete="off">
          <input type="hidden" name="form_type" value="login">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <div class="field">
            <label for="username">Username</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="12" cy="8" r="3.5"/>
                <path d="M4.5 20c0-3.6 3.1-6 7.5-6s7.5 2.4 7.5 6"/>
              </svg>
              <input type="text" id="username" name="username" placeholder="Masukkan username"
                     value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autofocus>
            </div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/>
                <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>
              </svg>
              <input type="password" id="password" name="password" placeholder="Masukkan password" required>
              <span class="toggle-pw" onclick="togglePw(this, 'password')">Lihat</span>
            </div>
          </div>

          <div class="row-between">
            <label class="remember">
              <input type="checkbox" name="remember" style="accent-color:var(--gold)"> Ingat saya
            </label>
            <a href="lupa_password.php">Lupa password?</a>
          </div>

          <button type="submit" class="btn-login">Masuk</button>
        </form>

        <div class="divider">akses internal</div>

        <p class="footnote">
          Belum memiliki akun pegawai?
          <a class="switch-link" href="login.php?daftar=1">Daftar akun</a>
        </p>

        <p class="footnote" style="margin-top:12px;">
          Akun baru harus mendapatkan persetujuan Administrator sebelum dapat digunakan.
        </p>

      <?php endif; ?>
    </div>


    <div class="hero-foot">Badan Siber dan Sandi Negara &middot; Instrumen Pengukuran Tingkat Kematangan Keamanan Siber dan Sandi</div>

  </main>

</div>

<script>
function togglePw(el, inputId){
    const pw = document.getElementById(inputId);
    const isPw = pw.type === 'password';
    pw.type = isPw ? 'text' : 'password';
    el.textContent = isPw ? 'Sembunyikan' : 'Lihat';
}
</script>
</body>
</html>