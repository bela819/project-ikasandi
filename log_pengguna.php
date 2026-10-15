<?php
session_start();
require_once 'koneksi.php';

/* =========================================================
   1. AUTENTIKASI DAN HAK AKSES
   ========================================================= */

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_login = (int) $_SESSION['user_id'];

if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    die('Akses ditolak. Halaman ini hanya dapat diakses oleh Administrator.');
}

/* =========================================================
   2. FUNGSI BANTU
   ========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($type, $message)
{
    header('Location: log_pengguna.php?' . $type . '=' . urlencode($message));
    exit;
}

/* =========================================================
   3. TOKEN CSRF
   ========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_token'];

/* =========================================================
   4. DATA AKUN LOGIN UNTUK SIDEBAR
   ========================================================= */

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id, username, nama_lengkap, email, role, status
     FROM users
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'i', $id_login);
mysqli_stmt_execute($stmt);

$result_login = mysqli_stmt_get_result($stmt);
$akun_login = mysqli_fetch_assoc($result_login);

mysqli_stmt_close($stmt);

/* Pastikan akun masih aktif */

if (!$akun_login || $akun_login['status'] !== 'aktif') {
    session_unset();
    session_destroy();

    header('Location: login.php');
    exit;
}

/* Nama pengguna */

$nama_sidebar = trim($akun_login['nama_lengkap'] ?? '');

if ($nama_sidebar === '') {
    $nama_sidebar = $akun_login['username'] ?? 'Pengguna';
}

/* Role pengguna */

$role_sidebar = strtolower($akun_login['role'] ?? '');

$role_label_sidebar = ($role_sidebar === 'admin')
    ? 'Administrator'
    : (($role_sidebar === 'operator')
        ? 'Operator'
        : ucfirst($role_sidebar));

/* Inisial avatar otomatis */

$nama_parts = preg_split('/\s+/', $nama_sidebar);
$inisial_sidebar = '';

foreach (array_slice($nama_parts, 0, 2) as $part) {
    if ($part !== '') {
        $inisial_sidebar .= mb_strtoupper(
            mb_substr($part, 0, 1, 'UTF-8'),
            'UTF-8'
        );
    }
}

if ($inisial_sidebar === '') {
    $inisial_sidebar = 'U';
}

/* =========================================================
   5. PESAN NOTIFIKASI
   ========================================================= */

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

/* =========================================================
   6. PROSES AKSI ADMIN
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted_csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf, $posted_csrf)) {
        redirectWithMessage(
            'error',
            'Token keamanan tidak valid. Silakan coba lagi.'
        );
    }

    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0 && $aksi !== 'tambah') {
        redirectWithMessage('error', 'ID pengguna tidak valid.');
    }

    /* -----------------------------------------------------
       TAMBAH PENGGUNA
       ----------------------------------------------------- */

    if ($aksi === 'tambah') {

        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'operator');
        $status = trim($_POST['status'] ?? 'aktif');

        $allowed_roles = ['admin', 'operator'];
        $allowed_status = [
            'aktif', 'nonaktif', 'menunggu', 'ditolak'
        ];

        if (
            $username === '' ||
            $password === '' ||
            $nama === '' ||
            $email === ''
        ) {
            redirectWithMessage(
                'error',
                'Username, password, nama lengkap, dan email wajib diisi.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage('error', 'Format email tidak valid.');
        }

        if (strlen($password) < 6) {
            redirectWithMessage('error', 'Password minimal 6 karakter.');
        }

        if (!in_array($role, $allowed_roles, true)) {
            redirectWithMessage('error', 'Role tidak valid.');
        }

        if (!in_array($status, $allowed_status, true)) {
            redirectWithMessage('error', 'Status tidak valid.');
        }

        /* Cek username */

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id FROM users WHERE username = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);

        $exists = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if ($exists) {
            redirectWithMessage('error', 'Username sudah digunakan.');
        }

        /* Cek email */

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);

        $exists = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if ($exists) {
            redirectWithMessage('error', 'Email sudah digunakan.');
        }

        /* Simpan pengguna */

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO users
             (username, password, nama_lengkap, email, role,
              status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );

        mysqli_stmt_bind_param(
            $stmt,
            'ssssss',
            $username,
            $hash,
            $nama,
            $email,
            $role,
            $status
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            redirectWithMessage(
                'success',
                'Pengguna berhasil ditambahkan.'
            );
        }

        mysqli_stmt_close($stmt);

        redirectWithMessage(
            'error',
            'Gagal menambahkan pengguna.'
        );
    }

    /* -----------------------------------------------------
       SETUJUI PENDAFTARAN
       ----------------------------------------------------- */

    if ($aksi === 'setujui') {

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE users
             SET status = 'aktif', updated_at = NOW()
             WHERE id = ? AND status = 'menunggu'"
        );

        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        redirectWithMessage(
            $affected > 0 ? 'success' : 'error',
            $affected > 0
                ? 'Pendaftaran akun berhasil disetujui.'
                : 'Akun tidak ditemukan atau sudah diproses.'
        );
    }

    /* -----------------------------------------------------
       TOLAK PENDAFTARAN
       ----------------------------------------------------- */

    if ($aksi === 'tolak') {

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE users
             SET status = 'ditolak', updated_at = NOW()
             WHERE id = ? AND status = 'menunggu'"
        );

        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        redirectWithMessage(
            $affected > 0 ? 'success' : 'error',
            $affected > 0
                ? 'Pendaftaran akun ditolak.'
                : 'Akun tidak ditemukan atau sudah diproses.'
        );
    }

    /* -----------------------------------------------------
       AKTIFKAN AKUN
       ----------------------------------------------------- */

    if ($aksi === 'aktifkan') {

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE users
             SET status = 'aktif', updated_at = NOW()
             WHERE id = ?
             AND status IN ('nonaktif', 'ditolak')"
        );

        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        redirectWithMessage(
            $affected > 0 ? 'success' : 'error',
            $affected > 0
                ? 'Akun berhasil diaktifkan.'
                : 'Status akun tidak dapat diubah.'
        );
    }

    /* -----------------------------------------------------
       NONAKTIFKAN AKUN
       ----------------------------------------------------- */

    if ($aksi === 'nonaktifkan') {

        if ($id === $id_login) {
            redirectWithMessage(
                'error',
                'Anda tidak dapat menonaktifkan akun yang sedang digunakan.'
            );
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE users
             SET status = 'nonaktif', updated_at = NOW()
             WHERE id = ? AND status = 'aktif'"
        );

        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        redirectWithMessage(
            $affected > 0 ? 'success' : 'error',
            $affected > 0
                ? 'Akun berhasil dinonaktifkan.'
                : 'Status akun tidak dapat diubah.'
        );
    }

    /* -----------------------------------------------------
       HAPUS AKUN
       ----------------------------------------------------- */

    if ($aksi === 'hapus') {

        if ($id === $id_login) {
            redirectWithMessage(
                'error',
                'Anda tidak dapat menghapus akun yang sedang digunakan.'
            );
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "DELETE FROM users WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        redirectWithMessage(
            $affected > 0 ? 'success' : 'error',
            $affected > 0
                ? 'Pengguna berhasil dihapus.'
                : 'Pengguna tidak ditemukan.'
        );
    }

    /* -----------------------------------------------------
       EDIT PENGGUNA
       ----------------------------------------------------- */

    if ($aksi === 'edit') {

        $username = trim($_POST['username'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'operator');
        $status = trim($_POST['status'] ?? 'nonaktif');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $nama === '' || $email === '') {
            redirectWithMessage(
                'error',
                'Username, nama lengkap, dan email wajib diisi.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage('error', 'Format email tidak valid.');
        }

        if (!in_array($role, ['admin', 'operator'], true)) {
            redirectWithMessage('error', 'Role tidak valid.');
        }

        if (!in_array(
            $status,
            ['aktif', 'nonaktif', 'menunggu', 'ditolak'],
            true
        )) {
            redirectWithMessage('error', 'Status tidak valid.');
        }

        /* Cek username */

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id FROM users
             WHERE username = ? AND id <> ? LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            'si',
            $username,
            $id
        );

        mysqli_stmt_execute($stmt);

        $exists_username = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if ($exists_username) {
            redirectWithMessage(
                'error',
                'Username sudah digunakan pengguna lain.'
            );
        }

        /* Cek email */

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id FROM users
             WHERE email = ? AND id <> ? LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            'si',
            $email,
            $id
        );

        mysqli_stmt_execute($stmt);

        $exists_email = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);

        if ($exists_email) {
            redirectWithMessage(
                'error',
                'Email sudah digunakan pengguna lain.'
            );
        }

        /* Update password jika diisi */

        if ($password !== '') {

            if (strlen($password) < 6) {
                redirectWithMessage(
                    'error',
                    'Password baru minimal 6 karakter.'
                );
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE users
                 SET username = ?, password = ?,
                     nama_lengkap = ?, email = ?,
                     role = ?, status = ?, updated_at = NOW()
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                'ssssssi',
                $username,
                $hash,
                $nama,
                $email,
                $role,
                $status,
                $id
            );

        } else {

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE users
                 SET username = ?, nama_lengkap = ?,
                     email = ?, role = ?, status = ?,
                     updated_at = NOW()
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                'sssssi',
                $username,
                $nama,
                $email,
                $role,
                $status,
                $id
            );
        }

        if (mysqli_stmt_execute($stmt)) {

            mysqli_stmt_close($stmt);

            /*
             * Jika akun yang diedit adalah akun login,
             * perbarui data sesi agar konsisten.
             */

            if ($id === $id_login) {
                $_SESSION['nama_lengkap'] = $nama;
                $_SESSION['nama'] = $nama;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
            }

            redirectWithMessage(
                'success',
                'Data pengguna berhasil diperbarui.'
            );
        }

        mysqli_stmt_close($stmt);

        redirectWithMessage(
            'error',
            'Gagal memperbarui data pengguna.'
        );
    }
}

/* =========================================================
   7. STATISTIK PENGGUNA
   ========================================================= */

$count_query = "
    SELECT
        SUM(status = 'menunggu') AS menunggu,
        SUM(status = 'aktif') AS aktif,
        SUM(status = 'ditolak') AS ditolak,
        SUM(status = 'nonaktif') AS nonaktif
    FROM users
";

$count_result = mysqli_query($koneksi, $count_query);
$counts = mysqli_fetch_assoc($count_result) ?: [];

/* =========================================================
   8. FILTER DAN PENCARIAN
   ========================================================= */

$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = '';

if (
    $filter_status !== '' &&
    in_array(
        $filter_status,
        ['aktif', 'nonaktif', 'menunggu', 'ditolak'],
        true
    )
) {
    $where[] = 'status = ?';
    $params[] = $filter_status;
    $types .= 's';
}

if ($search !== '') {

    $where[] = "
        (username LIKE ?
         OR nama_lengkap LIKE ?
         OR email LIKE ?
         OR role LIKE ?)
    ";

    $like = '%' . $search . '%';

    array_push(
        $params,
        $like,
        $like,
        $like,
        $like
    );

    $types .= 'ssss';
}

/* Query pengguna */

$sql = "
    SELECT id, username, nama_lengkap, email,
           role, status, created_at, updated_at
    FROM users
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY
        CASE status
            WHEN 'menunggu' THEN 1
            WHEN 'aktif' THEN 2
            WHEN 'nonaktif' THEN 3
            WHEN 'ditolak' THEN 4
            ELSE 5
        END,
        id DESC
";

$stmt = mysqli_prepare($koneksi, $sql);

if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$users = [];

while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

mysqli_stmt_close($stmt);

/* =========================================================
   9. DATA PENGGUNA YANG AKAN DIEDIT
   ========================================================= */

$edit_user = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    if ($edit_id > 0) {

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id, username, nama_lengkap,
                    email, role, status
             FROM users
             WHERE id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 'i', $edit_id);
        mysqli_stmt_execute($stmt);

        $edit_user = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmt)
        );

        mysqli_stmt_close($stmt);
    }

    if (!$edit_user) {
        $error = 'Data pengguna tidak ditemukan.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Manajemen Pengguna — IKASANDI</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">

<style>
:root {
    --bg: #0E1524;
    --panel: #141D30;
    --panel-2: #182238;
    --line: #26314A;
    --gold: #C9A227;
    --gold-soft: #8D7431;
    --text: #E9EDF4;
    --muted: #8C96AC;
    --green: #5FBE8A;
    --red: #D9756B;
}

* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    min-height: 100%;
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', sans-serif;
    -webkit-font-smoothing: antialiased;
}

a {
    color: inherit;
    text-decoration: none;
}

button, input, select {
    font: inherit;
}

h1, h2, h3, .brand {
    font-family: 'Space Grotesk', sans-serif;
}

/* LAYOUT */

.layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.side {
    width: 240px;
    flex: 0 0 240px;
    background: var(--panel);
    border-right: 1px solid var(--line);
    padding: 28px 20px;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 19px;
    font-weight: 700;
    margin-bottom: 30px;
}

.brand .mark {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(
        155deg,
        var(--gold),
        var(--gold-soft)
    );
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1A1300;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 14px;
    font-weight: 700;
}

nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.navlabel {
    color: var(--muted);
    font-size: 10px;
    letter-spacing: .08em;
    margin: 20px 4px 7px;
}

.navitem {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 12px;
    border-radius: 9px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 500;
    transition: .2s;
}

.navitem svg {
    width: 17px;
    height: 17px;
    flex: none;
    opacity: .85;
}

.navitem:hover {
    background: var(--panel-2);
    color: var(--text);
}

.navitem.active {
    background: var(--panel-2);
    color: var(--text);
    box-shadow: inset 2px 0 0 var(--gold);
}

/* PROFIL AKUN LOGIN */

.side-foot {
    margin-top: auto;
    border-top: 1px solid var(--line);
    padding-top: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.avatar {
    width: 34px;
    height: 34px;
    min-width: 34px;
    border-radius: 50%;
    background: #233252;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: var(--gold);
}

.profile-info {
    flex: 1;
    min-width: 0;
}

.who {
    color: var(--text);
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 155px;
}

.role {
    color: var(--muted);
    font-size: 12px;
    margin-top: 2px;
}

/* MAIN */

.main {
    flex: 1;
    min-width: 0;
    padding: 32px;
}

.page-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
    margin-bottom: 24px;
}

.page-head h1 {
    font-size: 26px;
    margin: 0 0 6px;
}

.page-head p {
    color: var(--muted);
    font-size: 13px;
    margin: 0;
    line-height: 1.5;
}

/* BUTTON */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 12px;
    border-radius: 9px;
    text-decoration: none;
    border: 1px solid var(--line);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    background: var(--panel-2);
    color: var(--text);
    white-space: nowrap;
    transition: .2s;
}

.btn:hover {
    filter: brightness(1.15);
}

.btn-primary {
    background: var(--gold);
    border-color: var(--gold);
    color: #1A1300;
}

.btn-success {
    background: #173527;
    border-color: #2d6d4d;
    color: #7DD3A8;
}

.btn-danger {
    background: #351919;
    border-color: #6A2A2A;
    color: #F19A91;
}

.btn-warning {
    background: #382f16;
    border-color: #6f5b24;
    color: #E7C86C;
}

.btn-muted {
    background: var(--panel);
    color: var(--muted);
}

/* CARD */

.card {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 18px;
}

.section-title {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 18px;
    margin: 0 0 15px;
}

/* STATISTIK */

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.stat {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 11px;
    padding: 17px;
}

.stat .label {
    color: var(--muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
}

.stat .value {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 25px;
    margin-top: 8px;
}

/* FILTER */

.toolbar {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.search {
    flex: 1;
    min-width: 220px;
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 9px;
    color: var(--text);
    padding: 11px 12px;
    outline: none;
}

.search:focus,
.select:focus,
.form-control:focus {
    border-color: var(--gold);
}

.select {
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 9px;
    color: var(--text);
    padding: 11px 12px;
    min-width: 150px;
    cursor: pointer;
    outline: none;
}

/* TABEL */

.table-wrap {
    overflow-x: auto;
    border: 1px solid var(--line);
    border-radius: 10px;
}

table {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
}

th, td {
    padding: 13px 11px;
    border-bottom: 1px solid var(--line);
    text-align: left;
    font-size: 12px;
    vertical-align: middle;
}

th {
    background: var(--panel-2);
    color: var(--muted);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

tr:last-child td {
    border-bottom: 0;
}

td .name {
    font-weight: 600;
    color: var(--text);
}

td .sub {
    display: block;
    color: var(--muted);
    font-size: 11px;
    margin-top: 4px;
}

.badge {
    display: inline-flex;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: capitalize;
    white-space: nowrap;
}

.badge-aktif {
    background: #173527;
    color: #7DD3A8;
}

.badge-menunggu {
    background: #382f16;
    color: #E7C86C;
}

.badge-ditolak {
    background: #351919;
    color: #F19A91;
}

.badge-nonaktif {
    background: #272D39;
    color: #9AA4B7;
}

.badge-role {
    background: #202A40;
    color: #B8C3D8;
}

.actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}

.actions form {
    margin: 0;
}

.empty {
    text-align: center;
    color: var(--muted);
    padding: 30px;
}

/* ALERT */

.alert {
    padding: 12px 14px;
    border-radius: 9px;
    margin-bottom: 15px;
    font-size: 12px;
}

.alert-success {
    background: #102A20;
    border: 1px solid #2E6A50;
    color: #7DD3A8;
}

.alert-error {
    background: #331515;
    border: 1px solid #6A2A2A;
    color: var(--red);
}

.notice {
    background: #172038;
    border: 1px solid #30405f;
    color: #AFC0DD;
    padding: 12px 14px;
    border-radius: 9px;
    font-size: 12px;
    margin-bottom: 16px;
}

/* FORM */

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 12px;
    color: var(--muted);
}

.form-control {
    background: var(--panel-2);
    border: 1px solid var(--line);
    color: var(--text);
    border-radius: 9px;
    padding: 11px 12px;
    outline: none;
    width: 100%;
}

.form-control option {
    background: var(--panel-2);
    color: var(--text);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 20px;
}

.help {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.6;
}

/* RESPONSIVE */

@media(max-width:1000px) {
    .side {
        width: 220px;
        flex-basis: 220px;
        padding: 24px 14px;
    }

    .main {
        padding: 24px 20px;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width:700px) {
    .layout {
        display: block;
    }

    .side {
        width: 100%;
        min-height: auto;
        border-right: 0;
        border-bottom: 1px solid var(--line);
        padding: 18px;
    }

    .side nav {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .side-foot {
        margin-top: 20px;
    }

    .main {
        padding: 22px 15px 40px;
    }

    .page-head {
        display: block;
    }

    .page-head .btn {
        margin-top: 15px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }
}

@media(max-width:420px) {
    .stats {
        grid-template-columns: 1fr 1fr;
    }

    .stat {
        padding: 13px;
    }

    .stat .value {
        font-size: 21px;
    }

    .side nav {
        grid-template-columns: 1fr;
    }
}

/* =========================================================
   KOTAK PUTIH (statistik, tabel, form)
   ========================================================= */

.stat,
.card {
    --panel: #FFFFFF;
    --panel-2: #F3F5F9;
    --line: #DDE2EC;
    --text: #1B2437;
    --muted: #6B7690;

    background: #FFFFFF;
    border-color: #DDE2EC;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .12);
}

/* Input & select agar jelas terlihat di atas putih */
.card .search,
.card .select,
.card .form-control {
    background: #F3F5F9;
    border-color: #DDE2EC;
    color: #1B2437;
}

.card .search::placeholder {
    color: #8C96AC;
}

.card .form-control option {
    background: #FFFFFF;
    color: #1B2437;
}

/* Tabel */
.card .table-wrap {
    border-color: #DDE2EC;
}

.card th {
    background: #F3F5F9;
    color: #6B7690;
}

.card td {
    color: #1B2437;
}

/* Badge versi terang */
.card .badge-aktif    { background: #DFF5E8; color: #1E7A4A; }
.card .badge-menunggu { background: #FFF1CC; color: #8A6A00; }
.card .badge-ditolak  { background: #FDE2E0; color: #B3382E; }
.card .badge-nonaktif { background: #E8EBF1; color: #566079; }
.card .badge-role     { background: #E8ECF5; color: #3A4764; }

/* Tombol versi terang */
.card .btn-success { background: #DFF5E8; border-color: #9ED5B7; color: #1E7A4A; }
.card .btn-danger  { background: #FDE2E0; border-color: #F0A9A3; color: #B3382E; }
.card .btn-warning { background: #FFF1CC; border-color: #E6C766; color: #8A6A00; }
.card .btn-muted   { background: #FFFFFF; border-color: #DDE2EC; color: #4A5672; }
.card .btn-primary { color: #1A1300; }

.card .btn:hover {
    filter: brightness(.96);
}

/* =========================================================
   TULISAN LEBIH TEBAL
   ========================================================= */

.stat .label {
    color: #4A5672;
    font-weight: 700;
}

.stat .value {
    color: #0F172A;
    font-weight: 700;
}

.card th {
    color: #3F4A66;
    font-weight: 800;
}

.card td {
    color: #0F172A;
    font-weight: 600;
}

.card td .name {
    color: #0F172A;
    font-weight: 800;
}

.card td .sub {
    color: #4A5672;
    font-weight: 600;
}

.card .badge {
    font-weight: 800;
}

.card .btn {
    font-weight: 700;
}

.card .search,
.card .select,
.card .form-control {
    color: #0F172A;
    font-weight: 600;
}

.card .search::placeholder {
    color: #6B7690;
    font-weight: 500;
}

.card .form-group label {
    color: #3F4A66;
    font-weight: 700;
}

.card .section-title {
    color: #0F172A;
    font-weight: 700;
}

.card .help {
    color: #4A5672;
    font-weight: 600;
}

.card .empty {
    color: #4A5672;
    font-weight: 600;
}

/* =========================================================
   WARNA KOTAK ABU-ABU
   ========================================================= */

.stat,
.card {
    --panel: #E3E6EC;
    --panel-2: #D5DAE3;
    --line: #C3CAD6;

    background: #E3E6EC;
    border-color: #C3CAD6;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .18);
}

.card .search,
.card .select,
.card .form-control {
    background: #F1F3F7;
    border-color: #C3CAD6;
}

.card .form-control option {
    background: #F1F3F7;
}

.card .table-wrap {
    border-color: #C3CAD6;
}

.card th {
    background: #D5DAE3;
}

.card th,
.card td {
    border-bottom-color: #C3CAD6;
}

.card .btn-muted {
    background: #F1F3F7;
    border-color: #C3CAD6;
}

.card .badge-role {
    background: #CBD2E0;
}

.card .badge-nonaktif {
    background: #CDD2DC;
}
</style>
</head>

<body>

<div class="layout">

    <!-- =================================================
         SIDEBAR
         ================================================= -->

    <aside class="side">

        <div class="brand">
            <div class="mark">√</div>
            IKASANDI
        </div>

        <nav>

            <a class="navitem" href="index.php">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.6">
                    <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                    <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                    <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                    <rect x="3" y="16" width="7" height="5" rx="1.5"/>
                </svg>
                Dashboard
            </a>

            <a class="navitem" href="instrumen_ikasandi.php">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.6">
                    <path d="M4 19.5V6a2 2 0 0 1 2-2h8.5L20 8.5V19.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19.5Z"/>
                    <path d="M14 4v4.5H20"/>
                </svg>
                Instrumen IKASANDI
            </a>

            <a class="navitem" href="pengisian_ikasandi.php">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.6">
                    <path d="M4 4h16v16H4z"/>
                    <path d="M8 8h8"/>
                    <path d="M8 12h8"/>
                    <path d="M8 16h5"/>
                    <path d="M16 16h2"/>
                </svg>
                Pengisian IKASANDI
            </a>

            <a class="navitem" href="rekap_ikasandi.php">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.6">
                    <path d="M9 11.5l2 2 4-4.5"/>
                    <rect x="3" y="3" width="18" height="18" rx="3"/>
                </svg>
                Rekap IKASANDI
            </a>

            <div class="navlabel">MANAJEMEN</div>

            <a class="navitem active" href="log_pengguna.php">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.6">
                    <circle cx="9" cy="8" r="3.2"/>
                    <path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/>
                    <path d="M16.5 4.8a3.2 3.2 0 0 1 0 6.4"/>
                    <path d="M20 20c0-2.6-1.6-4.6-3.8-5.3"/>
                </svg>
                Log Pengguna
            </a>

            <div class="navlabel">SESI</div>

            <a class="navitem" href="logout.php">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.6">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <path d="M16 17l5-5-5-5"/>
                    <path d="M21 12H9"/>
                </svg>
                Keluar
            </a>

        </nav>

        <!-- PROFIL AKUN LOGIN OTOMATIS -->

        <div class="side-foot">

            <div class="avatar">
                <?= e($inisial_sidebar) ?>
            </div>

            <div class="profile-info">

                <div class="who"
                     title="<?= e($nama_sidebar) ?>">
                    <?= e($nama_sidebar) ?>
                </div>

                <div class="role">
                    <?= e($role_label_sidebar) ?>
                </div>

            </div>

        </div>

    </aside>

    <!-- =================================================
         KONTEN UTAMA
         ================================================= -->

    <main class="main">

        <div class="page-head">

            <div>
                <h1>Manajemen Pengguna</h1>
                <p>
                    Kelola akun pegawai dan persetujuan
                    pendaftaran pengguna IKASANDI.
                </p>
            </div>

            <a class="btn btn-primary"
               href="log_pengguna.php?tambah=1">
                + Tambah Pengguna
            </a>

        </div>

        <!-- NOTIFIKASI -->

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- =================================================
             FORM TAMBAH PENGGUNA
             ================================================= -->

        <?php if (isset($_GET['tambah'])): ?>

            <div class="card">

                <h2 class="section-title">Tambah Pengguna</h2>

                <p class="help">
                    Admin dapat membuat akun secara langsung.
                    Pendaftaran pegawai dapat menggunakan status
                    Menunggu untuk memerlukan persetujuan admin.
                </p>

                <form method="post">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($csrf) ?>">

                    <input type="hidden"
                           name="aksi"
                           value="tambah">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input class="form-control"
                                   type="text"
                                   name="nama_lengkap"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Username</label>
                            <input class="form-control"
                                   type="text"
                                   name="username"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input class="form-control"
                                   type="email"
                                   name="email"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input class="form-control"
                                   type="password"
                                   name="password"
                                   minlength="6"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <select class="form-control" name="role">
                                <option value="operator">Operator</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="aktif">Aktif</option>
                                <option value="menunggu">Menunggu</option>
                                <option value="nonaktif">Nonaktif</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-actions">

                        <a class="btn btn-muted"
                           href="log_pengguna.php">
                            Batal
                        </a>

                        <button class="btn btn-primary"
                                type="submit">
                            Simpan Pengguna
                        </button>

                    </div>

                </form>

            </div>

        <!-- =================================================
             FORM EDIT PENGGUNA
             ================================================= -->

        <?php elseif ($edit_user): ?>

            <div class="card">

                <h2 class="section-title">
                    Edit Pengguna #<?= (int)$edit_user['id'] ?>
                </h2>

                <form method="post">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($csrf) ?>">

                    <input type="hidden"
                           name="aksi"
                           value="edit">

                    <input type="hidden"
                           name="id"
                           value="<?= (int)$edit_user['id'] ?>">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input class="form-control"
                                   type="text"
                                   name="nama_lengkap"
                                   value="<?= e($edit_user['nama_lengkap']) ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Username</label>
                            <input class="form-control"
                                   type="text"
                                   name="username"
                                   value="<?= e($edit_user['username']) ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input class="form-control"
                                   type="email"
                                   name="email"
                                   value="<?= e($edit_user['email']) ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>
                                Password Baru
                                <span class="help">
                                    (Kosongkan jika tidak diubah)
                                </span>
                            </label>

                            <input class="form-control"
                                   type="password"
                                   name="password"
                                   minlength="6">
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <select class="form-control" name="role">

                                <option value="operator"
                                    <?= $edit_user['role'] === 'operator'
                                        ? 'selected' : '' ?>>
                                    Operator
                                </option>

                                <option value="admin"
                                    <?= $edit_user['role'] === 'admin'
                                        ? 'selected' : '' ?>>
                                    Administrator
                                </option>

                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">

                                <?php foreach (
                                    ['aktif', 'menunggu', 'nonaktif', 'ditolak']
                                    as $s
                                ): ?>

                                    <option value="<?= e($s) ?>"
                                        <?= $edit_user['status'] === $s
                                            ? 'selected' : '' ?>>
                                        <?= e(ucfirst($s)) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </div>

                    </div>

                    <div class="form-actions">

                        <a class="btn btn-muted"
                           href="log_pengguna.php">
                            Batal
                        </a>

                        <button class="btn btn-primary"
                                type="submit">
                            Simpan Perubahan
                        </button>

                    </div>

                </form>

            </div>

        <!-- =================================================
             TABEL MANAJEMEN PENGGUNA
             ================================================= -->

        <?php else: ?>

            <?php if ((int)($counts['menunggu'] ?? 0) > 0): ?>

                <div class="notice">
                    Ada
                    <strong>
                        <?= (int)$counts['menunggu'] ?>
                    </strong>
                    pendaftaran akun yang menunggu persetujuan
                    Administrator. Gunakan tombol Setujui atau Tolak.
                </div>

            <?php endif; ?>

            <!-- STATISTIK -->

            <div class="stats">

                <div class="stat">
                    <div class="label">Menunggu</div>
                    <div class="value">
                        <?= (int)($counts['menunggu'] ?? 0) ?>
                    </div>
                </div>

                <div class="stat">
                    <div class="label">Aktif</div>
                    <div class="value">
                        <?= (int)($counts['aktif'] ?? 0) ?>
                    </div>
                </div>

                <div class="stat">
                    <div class="label">Nonaktif</div>
                    <div class="value">
                        <?= (int)($counts['nonaktif'] ?? 0) ?>
                    </div>
                </div>

                <div class="stat">
                    <div class="label">Ditolak</div>
                    <div class="value">
                        <?= (int)($counts['ditolak'] ?? 0) ?>
                    </div>
                </div>

            </div>

            <!-- TABEL -->

            <div class="card">

                <form class="toolbar"
                      method="get"
                      id="filterForm">

                    <input class="search"
                           type="search"
                           name="q"
                           placeholder="Cari username, nama, email, atau role..."
                           value="<?= e($search) ?>">

                    <select class="select"
                            name="status"
                            id="statusFilter">

                        <option value="">Semua Status</option>

                        <option value="menunggu"
                            <?= $filter_status === 'menunggu'
                                ? 'selected' : '' ?>>
                            Menunggu
                        </option>

                        <option value="aktif"
                            <?= $filter_status === 'aktif'
                                ? 'selected' : '' ?>>
                            Aktif
                        </option>

                        <option value="nonaktif"
                            <?= $filter_status === 'nonaktif'
                                ? 'selected' : '' ?>>
                            Nonaktif
                        </option>

                        <option value="ditolak"
                            <?= $filter_status === 'ditolak'
                                ? 'selected' : '' ?>>
                            Ditolak
                        </option>

                    </select>

                    <button class="btn btn-primary" type="submit">
                        Cari
                    </button>

                    <a class="btn btn-muted"
                       href="log_pengguna.php">
                        Reset
                    </a>

                </form>

                <div class="table-wrap">

                    <table>

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pengguna</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Terdaftar</th>
                                <th>Diperbarui</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (!$users): ?>

                            <tr>
                                <td colspan="8" class="empty">
                                    Belum ada data pengguna.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($users as $u): ?>

                                <?php
                                $status_class = strtolower($u['status']);

                                $role_label = $u['role'] === 'admin'
                                    ? 'Administrator'
                                    : 'Operator';
                                ?>

                                <tr>

                                    <td>
                                        <?= (int)$u['id'] ?>
                                    </td>

                                    <td>
                                        <span class="name">
                                            <?= e($u['nama_lengkap']) ?>
                                        </span>

                                        <span class="sub">
                                            @<?= e($u['username']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e($u['email']) ?>
                                    </td>

                                    <td>
                                        <span class="badge badge-role">
                                            <?= e($role_label) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge badge-<?= e($status_class) ?>">
                                            <?= e(ucfirst($u['status'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e($u['created_at']) ?>
                                    </td>

                                    <td>
                                        <?= e($u['updated_at']) ?>
                                    </td>

                                    <td>

                                        <div class="actions">

                                            <?php if ($u['status'] === 'menunggu'): ?>

                                                <!-- SETUJUI -->

                                                <form method="post"
                                                      onsubmit="return confirm('Setujui pendaftaran akun ini?');">

                                                    <input type="hidden"
                                                           name="csrf_token"
                                                           value="<?= e($csrf) ?>">

                                                    <input type="hidden"
                                                           name="aksi"
                                                           value="setujui">

                                                    <input type="hidden"
                                                           name="id"
                                                           value="<?= (int)$u['id'] ?>">

                                                    <button class="btn btn-success"
                                                            type="submit">
                                                        Setujui
                                                    </button>

                                                </form>

                                                <!-- TOLAK -->

                                                <form method="post"
                                                      onsubmit="return confirm('Tolak pendaftaran akun ini?');">

                                                    <input type="hidden"
                                                           name="csrf_token"
                                                           value="<?= e($csrf) ?>">

                                                    <input type="hidden"
                                                           name="aksi"
                                                           value="tolak">

                                                    <input type="hidden"
                                                           name="id"
                                                           value="<?= (int)$u['id'] ?>">

                                                    <button class="btn btn-danger"
                                                            type="submit">
                                                        Tolak
                                                    </button>

                                                </form>

                                            <?php elseif ($u['status'] === 'aktif'): ?>

                                                <?php if ((int)$u['id'] !== $id_login): ?>

                                                    <!-- NONAKTIFKAN -->

                                                    <form method="post"
                                                          onsubmit="return confirm('Nonaktifkan akun ini?');">

                                                        <input type="hidden"
                                                               name="csrf_token"
                                                               value="<?= e($csrf) ?>">

                                                        <input type="hidden"
                                                               name="aksi"
                                                               value="nonaktifkan">

                                                        <input type="hidden"
                                                               name="id"
                                                               value="<?= (int)$u['id'] ?>">

                                                        <button class="btn btn-warning"
                                                                type="submit">
                                                            Nonaktifkan
                                                        </button>

                                                    </form>

                                                <?php endif; ?>

                                            <?php elseif (
                                                in_array(
                                                    $u['status'],
                                                    ['nonaktif', 'ditolak'],
                                                    true
                                                )
                                            ): ?>

                                                <!-- AKTIFKAN -->

                                                <form method="post"
                                                      onsubmit="return confirm('Aktifkan akun ini?');">

                                                    <input type="hidden"
                                                           name="csrf_token"
                                                           value="<?= e($csrf) ?>">

                                                    <input type="hidden"
                                                           name="aksi"
                                                           value="aktifkan">

                                                    <input type="hidden"
                                                           name="id"
                                                           value="<?= (int)$u['id'] ?>">

                                                    <button class="btn btn-success"
                                                            type="submit">
                                                        Aktifkan
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                            <!-- EDIT -->

                                            <a class="btn btn-muted"
                                               href="log_pengguna.php?edit=<?= (int)$u['id'] ?>">
                                                Edit
                                            </a>

                                            <!-- HAPUS -->

                                            <?php if ((int)$u['id'] !== $id_login): ?>

                                                <form method="post"
                                                      onsubmit="return confirm('Hapus pengguna ini secara permanen?');">

                                                    <input type="hidden"
                                                           name="csrf_token"
                                                           value="<?= e($csrf) ?>">

                                                    <input type="hidden"
                                                           name="aksi"
                                                           value="hapus">

                                                    <input type="hidden"
                                                           name="id"
                                                           value="<?= (int)$u['id'] ?>">

                                                    <button class="btn btn-danger"
                                                            type="submit">
                                                        Hapus
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<script>
/* =========================================================
   FILTER STATUS
   ========================================================= */

const statusFilter = document.getElementById('statusFilter');
const filterForm = document.getElementById('filterForm');

if (statusFilter && filterForm) {
    statusFilter.addEventListener('change', function () {
        filterForm.submit();
    });
}

/* =========================================================
   KONFIRMASI FORM DAN PENCEGAHAN DOUBLE SUBMIT
   ========================================================= */

document.querySelectorAll('form').forEach(function(form) {

    form.addEventListener('submit', function() {

        /*
         * Jangan menonaktifkan tombol jika konfirmasi
         * pengguna dibatalkan.
         */

        const button = form.querySelector('button[type="submit"]');

        if (button) {
            setTimeout(function() {
                if (form.checkValidity()) {
                    button.disabled = true;
                    button.style.opacity = '0.65';
                }
            }, 0);
        }
    });

});
</script>

</body>
</html>