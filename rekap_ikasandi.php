<?php
session_start();
require_once 'koneksi.php';

/* =========================================================
   USER LOGIN
   ========================================================= */
$id_users = (int)($_SESSION['id_users'] ?? $_SESSION['user_id'] ?? 1);
$nama_pengisi = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Nama Pengisi';
$role = $_SESSION['role'] ?? 'pegawai';
$is_admin = strtolower($role) === 'admin';

/* =========================================================
   KATEGORI
   (dipakai untuk menampilkan nama kategori pada
   kolom "Kategori yang Diisi" di CSV)
   ========================================================= */
$kategori = [
    1 => ['kode' => 'I', 'nama' => 'Identifikasi'],
    2 => ['kode' => 'II', 'nama' => 'Proteksi'],
    3 => ['kode' => 'III', 'nama' => 'Deteksi'],
    4 => ['kode' => 'IV', 'nama' => 'Penanggulangan dan Pemulihan'],
    5 => ['kode' => 'V', 'nama' => 'Persandian']
];

/* =========================================================
   NAMA BULAN (untuk filter & label)
   ========================================================= */
$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

/* =========================================================
   SUMBER DATA REKAP
   Gabungan data penilaian (pengisian_ikasandi) dan data responden,
   sehingga data responden langsung muncul di rekap walau
   penilaian belum diisi (jumlah kategori = 0).
   Baris dari tabel responden memakai id = 0 dan topik_id = NULL.
   ========================================================= */
$sumber_rekap = "
    (
        SELECT id_users,
               CONVERT(judul_topik USING utf8mb4) COLLATE utf8mb4_unicode_ci AS judul_topik,
               CONVERT(nama_pengisi USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama_pengisi,
               tanggal_pengisian, topik_id, id
        FROM pengisian_ikasandi
        UNION ALL
        SELECT id_users,
               CONVERT(judul_audit USING utf8mb4) COLLATE utf8mb4_unicode_ci AS judul_topik,
               CONVERT(nama_responden USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama_pengisi,
               tanggal_pengisian, NULL AS topik_id, 0 AS id
        FROM responden
        WHERE judul_audit IS NOT NULL AND judul_audit <> ''
    ) AS sumber
";

/* =========================================================
   HELPER
   ========================================================= */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function jalankan($koneksi, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($koneksi, $sql);

    if (!$stmt) {
        return [null, null];
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);

    return [$stmt, mysqli_stmt_get_result($stmt)];
}

function urlDengan($tambah = [])
{
    $q = array_merge($_GET, $tambah);

    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) {
            unset($q[$k]);
        }
    }

    return '?' . http_build_query($q);
}

/* =========================================================
   CSRF TOKEN
   ========================================================= */
if (empty($_SESSION['csrf_rekap'])) {
    $_SESSION['csrf_rekap'] = bin2hex(random_bytes(32));
}

/* =========================================================
   PROSES HAPUS
   Menghapus data penilaian DAN data responden dengan judul tersebut.
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'hapus') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_rekap'], $token)) {
        die('Token keamanan tidak valid.');
    }

    $hapus_id_users = (int)($_POST['id_users'] ?? 0);
    $hapus_judul = trim($_POST['judul_topik'] ?? '');

    if ($hapus_id_users <= 0 || $hapus_judul === '') {
        die('Data pengisian tidak valid.');
    }

    // Pegawai hanya boleh menghapus data sendiri
    if (!$is_admin && $hapus_id_users !== $id_users) {
        http_response_code(403);
        die('Anda tidak memiliki akses menghapus data ini.');
    }

    mysqli_begin_transaction($koneksi);

    try {
        /* 1) hapus data penilaian */
        $stmt_hapus = mysqli_prepare(
            $koneksi,
            "DELETE FROM pengisian_ikasandi WHERE id_users = ? AND judul_topik = ?"
        );

        if (!$stmt_hapus) {
            throw new Exception('Gagal menyiapkan query hapus.');
        }

        mysqli_stmt_bind_param($stmt_hapus, 'is', $hapus_id_users, $hapus_judul);

        if (!mysqli_stmt_execute($stmt_hapus)) {
            throw new Exception('Gagal menghapus data.');
        }

        $jumlah = mysqli_stmt_affected_rows($stmt_hapus);
        mysqli_stmt_close($stmt_hapus);

        /* 2) hapus data responden dengan judul yang sama */
        $stmt_resp = mysqli_prepare(
            $koneksi,
            "DELETE FROM responden WHERE id_users = ? AND judul_audit = ?"
        );

        if (!$stmt_resp) {
            throw new Exception('Gagal menyiapkan query hapus responden.');
        }

        mysqli_stmt_bind_param($stmt_resp, 'is', $hapus_id_users, $hapus_judul);

        if (!mysqli_stmt_execute($stmt_resp)) {
            throw new Exception('Gagal menghapus data responden.');
        }

        $jumlah += mysqli_stmt_affected_rows($stmt_resp);
        mysqli_stmt_close($stmt_resp);

        if ($jumlah <= 0) {
            throw new Exception('Data tidak ditemukan.');
        }

        mysqli_commit($koneksi);

        /* Jika judul yang dihapus sedang aktif di session, kosongkan */
        if ($hapus_id_users === $id_users
            && ($_SESSION['judul_topik'] ?? '') === $hapus_judul) {
            unset($_SESSION['judul_topik']);
        }

        header('Location: rekap_ikasandi.php?pesan=hapus_berhasil');
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($koneksi);
        die('Proses hapus gagal: ' . e($e->getMessage()));
    }
}

/* =========================================================
   DAFTAR TAHUN YANG TERSEDIA (untuk dropdown filter Tahun)
   ========================================================= */
$tahun_tersedia = [];

$where_tahun = [];
$types_tahun = '';
$params_tahun = [];

if (!$is_admin) {
    $where_tahun[] = 'id_users = ?';
    $types_tahun .= 'i';
    $params_tahun[] = $id_users;
}

$sql_tahun = "
    SELECT DISTINCT YEAR(tanggal_pengisian) AS th
    FROM $sumber_rekap
    " . ($where_tahun ? 'WHERE ' . implode(' AND ', $where_tahun) : '') . "
    ORDER BY th DESC
";

[$stmt_tahun, $res_tahun] = jalankan($koneksi, $sql_tahun, $types_tahun, $params_tahun);

if ($res_tahun) {
    while ($rw = mysqli_fetch_assoc($res_tahun)) {
        if (!empty($rw['th'])) {
            $tahun_tersedia[] = (int)$rw['th'];
        }
    }
    mysqli_stmt_close($stmt_tahun);
}

// Jika belum ada data sama sekali, tetap sediakan tahun berjalan agar dropdown tidak kosong
if (empty($tahun_tersedia)) {
    $tahun_tersedia[] = (int)date('Y');
}

/* =========================================================
   FILTER (Cari, Bulan, Tahun)
   ========================================================= */
$f_cari = trim($_GET['q'] ?? '');
$f_tahun = (int)($_GET['tahun'] ?? 0);
$f_bulan = (int)($_GET['bulan'] ?? 0);

$where = [];
$types = '';
$params = [];

// Pegawai hanya melihat data sendiri
if (!$is_admin) {
    $where[] = 'id_users = ?';
    $types .= 'i';
    $params[] = $id_users;
}

// Filter Tahun (berdasarkan tanggal_pengisian)
if ($f_tahun > 0) {
    $where[] = 'YEAR(tanggal_pengisian) = ?';
    $types .= 'i';
    $params[] = $f_tahun;
}

// Filter Bulan (berdasarkan tanggal_pengisian)
if ($f_bulan >= 1 && $f_bulan <= 12) {
    $where[] = 'MONTH(tanggal_pengisian) = ?';
    $types .= 'i';
    $params[] = $f_bulan;
} else {
    $f_bulan = 0;
}

// Pencarian
if ($f_cari !== '') {
    $where[] = '(judul_topik LIKE ? OR nama_pengisi LIKE ?)';
    $types .= 'ss';

    $like = '%' . $f_cari . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql_where = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

/* =========================================================
   PAGINASI
   ========================================================= */
$per_halaman = 25;
$halaman = max(1, (int)($_GET['hal'] ?? 1));

$sql_count = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT id_users, judul_topik
        FROM $sumber_rekap
        $sql_where
        GROUP BY id_users, judul_topik
    ) AS rekap
";

$total = 0;

[$stmt, $res] = jalankan(
    $koneksi,
    $sql_count,
    $types,
    $params
);

if ($res) {
    $row = mysqli_fetch_assoc($res);
    $total = (int)($row['total'] ?? 0);
    mysqli_stmt_close($stmt);
}

$total_hal = max(1, (int)ceil($total / $per_halaman));
$halaman = min($halaman, $total_hal);
$offset = ($halaman - 1) * $per_halaman;

/* =========================================================
   DATA REKAP
   ========================================================= */
$sql_data = "
    SELECT
        id_users,
        judul_topik,
        COALESCE(
            MAX(CASE WHEN id > 0 THEN nama_pengisi END),
            MAX(nama_pengisi)
        ) AS nama_pengisi,
        MAX(tanggal_pengisian) AS tanggal_pengisian,
        COUNT(DISTINCT topik_id) AS jumlah_topik,
        GROUP_CONCAT(
            DISTINCT topik_id
            ORDER BY topik_id
            SEPARATOR ','
        ) AS daftar_topik,
        MAX(id) AS id_terakhir
    FROM $sumber_rekap
    $sql_where
    GROUP BY id_users, judul_topik
    ORDER BY MAX(tanggal_pengisian) DESC, MAX(id) DESC
    LIMIT $per_halaman OFFSET $offset
";

$data = [];

[$stmt, $res] = jalankan(
    $koneksi,
    $sql_data,
    $types,
    $params
);

$error_rekap = '';

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $data[] = $r;
    }

    mysqli_stmt_close($stmt);
} else {
    $error_rekap = mysqli_error($koneksi);
}

/* =========================================================
   DOWNLOAD CSV
   ========================================================= */
if (($_GET['aksi'] ?? '') === 'download') {

    $filename = 'Rekap_IKASANDI_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Judul Pengisian',
        'Nama Pengisi',
        'Tanggal Pengisian',
        'Jumlah Kategori',
        'Kategori yang Diisi'
    ], ';');

    $sql_export = "
        SELECT
            id_users,
            judul_topik,
            COALESCE(
                MAX(CASE WHEN id > 0 THEN nama_pengisi END),
                MAX(nama_pengisi)
            ) AS nama_pengisi,
            MAX(tanggal_pengisian) AS tanggal_pengisian,
            COUNT(DISTINCT topik_id) AS jumlah_topik,
            GROUP_CONCAT(
                DISTINCT topik_id
                ORDER BY topik_id
                SEPARATOR ','
            ) AS daftar_topik
        FROM $sumber_rekap
        $sql_where
        GROUP BY id_users, judul_topik
        ORDER BY MAX(tanggal_pengisian) DESC, MAX(id) DESC
    ";

    [$stmt, $res] = jalankan(
        $koneksi,
        $sql_export,
        $types,
        $params
    );

    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {

            $nama_topik = [];

            if (!empty($r['daftar_topik'])) {
                foreach (explode(',', $r['daftar_topik']) as $tid) {
                    $tid = (int)$tid;

                    if (isset($kategori[$tid])) {
                        $nama_topik[] =
                            $kategori[$tid]['kode'] . '. ' .
                            $kategori[$tid]['nama'];
                    }
                }
            }

            fputcsv($out, [
                $r['judul_topik'],
                $r['nama_pengisi'],
                $r['tanggal_pengisian'],
                $r['jumlah_topik'],
                implode(', ', $nama_topik)
            ], ';');
        }

        mysqli_stmt_close($stmt);
    }

    fclose($out);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Pengisian IKASANDI</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    background: #F4F7FB;
    color: #172033;
}

.header {
    min-height: 70px;
    background: #0B2545;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 30px;
    flex-wrap: wrap;
    gap: 10px;
}

.logo {
    font-size: 20px;
    font-weight: 800;
}

.header-kanan {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-left: auto;
    flex-wrap: wrap;
}

.nav-utama {
    display: flex;
    align-items: center;
    gap: 6px;
}

.nav-utama a {
    color: rgba(255,255,255,.75);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    padding: 9px 14px;
    border-radius: 8px;
    white-space: nowrap;
}

.nav-utama a:hover,
.nav-utama a.active {
    background: rgba(255,255,255,.14);
    color: #fff;
}

.user {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
}

.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #D4AF37;
    color: #0B2545;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: 800;
}

main {
    max-width: 1450px;
    margin: auto;
    padding: 28px 24px 60px;
}

.title {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.title h1 {
    margin: 0;
    font-size: 25px;
}

.title p {
    margin: 6px 0 0;
    color: #667085;
    font-size: 13px;
}

.btn {
    display: inline-block;
    border: 1px solid #D0D5DD;
    background: #fff;
    color: #172033;
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    font-family: inherit;
}

.btn.primary {
    background: #155EEF;
    border-color: #155EEF;
    color: #fff;
}

.btn.dark {
    background: #0B2545;
    border-color: #0B2545;
    color: #fff;
}

.filter {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 18px;
    margin-bottom: 18px;
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter label {
    font-size: 12px;
    font-weight: 700;
    color: #475467;
}

.filter input,
.filter select {
    height: 40px;
    border: 1px solid #D0D5DD;
    border-radius: 8px;
    padding: 0 12px;
    font-family: inherit;
    font-size: 13px;
    min-width: 190px;
    background: #fff;
}

.filter input[type="text"] {
    min-width: 250px;
}

.filter select.filter-periode {
    min-width: 150px;
}

.card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    overflow: hidden;
}

.scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

th {
    background: #0B2545;
    color: #fff;
    text-align: left;
    padding: 15px 18px;
    font-size: 12px;
    white-space: nowrap;
}

td {
    padding: 15px 18px;
    border-bottom: 1px solid #EEF1F5;
    vertical-align: middle;
}

tbody tr:hover td {
    background: #F8FAFC;
}

td.judul {
    min-width: 250px;
    font-weight: 600;
}

td.nama {
    min-width: 180px;
}

td.tanggal {
    min-width: 150px;
    white-space: nowrap;
    color: #475467;
}

.link-detail {
    color: #155EEF;
    font-weight: 700;
    text-decoration: none;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    background: #EAF2FF;
    color: #155EEF;
    white-space: nowrap;
}

.badge.kosong {
    background: #FEF3C7;
    color: #92400E;
}

/* TOMBOL UBAH DAN HAPUS */
td.aksi {
    min-width: 190px;
    white-space: nowrap;
}

.form-hapus {
    display: inline-block;
    margin: 0;
}

.btn-ubah,
.btn-hapus {
    display: inline-block;
    padding: 8px 11px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    border: none;
    cursor: pointer;
    margin: 2px;
}

.btn-ubah {
    background: #FFF4D6;
    color: #925F00;
}

.btn-hapus {
    background: #FEE4E2;
    color: #B42318;
}

.btn-ubah:hover {
    background: #FFE6A3;
}

.btn-hapus:hover {
    background: #FECDCA;
}

.empty {
    padding: 50px 20px;
    text-align: center;
    color: #667085;
    font-size: 14px;
}

.pager {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 16px 18px;
    font-size: 13px;
    color: #667085;
}

.pager .links {
    display: flex;
    align-items: center;
    gap: 6px;
}

.pager a,
.pager span {
    padding: 8px 12px;
    border: 1px solid #D0D5DD;
    border-radius: 8px;
    text-decoration: none;
    color: #344054;
    font-weight: 700;
    background: #fff;
}

.pager span.cur {
    background: #155EEF;
    border-color: #155EEF;
    color: #fff;
}

.pager span.off {
    color: #C4CAD4;
    border-color: #EEF1F5;
}

.notif {
    padding: 12px 16px;
    margin-bottom: 15px;
    border-radius: 8px;
    background: #D1FAE5;
    color: #065F46;
    font-size: 13px;
}

@media (max-width: 900px) {
    .nav-utama {
        display: none;
    }
}

@media (max-width: 700px) {
    .header {
        padding: 14px;
    }

    main {
        padding: 20px 12px 40px;
    }

    .filter {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-group,
    .filter input,
    .filter select {
        width: 100%;
        min-width: 100%;
    }

    .title h1 {
        font-size: 22px;
    }

    .pager {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media print {
    .header,
    .filter,
    .title .actions,
    .pager .links,
    .aksi {
        display: none !important;
    }

    body {
        background: #fff;
    }

    main {
        max-width: 100%;
        padding: 0;
    }

    th {
        background: #eee !important;
        color: #000 !important;
    }
}
</style>
</head>

<body>

<header class="header">

    <div class="logo">ARSIKAS</div>

    <div class="header-kanan">

        <nav class="nav-utama">
            <a href="index.php">Dashboard</a>
            <a href="instrumen_ikasandi.php">Instrumen IKASANDI</a>
            <a href="pengisian_ikasandi.php">Pengisian IKASANDI</a>
            <a href="rekap_ikasandi.php" class="active">Rekap IKASANDI</a>
            <?php if ($is_admin): ?>
                <a href="log_pengguna.php">Log Pengguna</a>
            <?php endif; ?>
        </nav>

        <div class="user">
            <?= e($nama_pengisi) ?>
            <div class="avatar">
                <?= e(strtoupper(substr($nama_pengisi, 0, 1))) ?>
            </div>
        </div>

    </div>

</header>

<main>

    <div class="title">

        <div>
            <h1>Rekap Data Pengisian</h1>

            <p>
                <?= $is_admin
                    ? 'Rekap pengisian IKASANDI seluruh pengguna.'
                    : 'Rekap pengisian IKASANDI milik Anda.' ?>

                Total
                <b><?= number_format($total, 0, ',', '.') ?></b>
                data pengisian
                <?php if ($f_tahun > 0 || $f_bulan > 0): ?>
                    untuk periode
                    <b>
                        <?= $f_bulan > 0 ? e($nama_bulan[$f_bulan]) . ' ' : '' ?><?= $f_tahun > 0 ? $f_tahun : 'semua tahun' ?>
                    </b>
                <?php endif; ?>.
            </p>
        </div>

        <div class="actions"
             style="display:flex;gap:8px;flex-wrap:wrap;">

            <a class="btn primary"
               href="pengisian_ikasandi.php?baru=1">
                + Pengisian
            </a>

        </div>

    </div>

    <?php if (($_GET['pesan'] ?? '') === 'hapus_berhasil'): ?>
        <div class="notif">
            Data pengisian IKASANDI berhasil dihapus.
        </div>
    <?php endif; ?>

    <?php if ($error_rekap !== ''): ?>
        <div class="notif" style="background:#FEE4E2;color:#B42318;">
            Data rekap gagal dimuat: <?= e($error_rekap) ?>
            <br>Pastikan kolom <b>judul_audit</b> sudah ada di tabel <b>responden</b> (lihat migrasi database).
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['debug'])): ?>
        <div class="notif" style="background:#EFF6FF;color:#1E3A8A;line-height:1.7;">
            <b>Diagnosa Rekap</b><br>
            ID pengguna di session: <b><?= (int)$id_users ?></b> &middot;
            Role: <b><?= e($role) ?></b> &middot;
            Baris tampil di tabel: <b><?= count($data) ?></b> &middot;
            Total hasil query: <b><?= (int)$total ?></b><br>
            <?php
            try {
                $sql_dbg = "SELECT id_users, judul_audit, nama_responden, tanggal_pengisian
                            FROM responden "
                         . ($is_admin ? '' : 'WHERE id_users = ' . (int)$id_users . ' ')
                         . "ORDER BY tanggal_pengisian DESC LIMIT 20";
                $q_dbg = mysqli_query($koneksi, $sql_dbg);
                if (!$q_dbg) {
                    echo 'Error tabel responden: ' . e(mysqli_error($koneksi));
                } else {
                    echo 'Isi tabel <b>responden</b> (maks. 20 baris): <b>' . mysqli_num_rows($q_dbg) . '</b> baris<br>';
                    while ($d = mysqli_fetch_assoc($q_dbg)) {
                        echo '&bull; id_users=' . (int)$d['id_users']
                           . ' | judul_audit="' . e($d['judul_audit'] ?? 'NULL') . '"'
                           . ' | nama=' . e($d['nama_responden'] ?? '')
                           . ' | tanggal=' . e($d['tanggal_pengisian'] ?? '') . '<br>';
                    }
                }
            } catch (Throwable $ex) {
                echo 'Error: ' . e($ex->getMessage());
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- FILTER -->
    <form class="filter" method="get">

        <div class="filter-group">
            <label for="q">Cari</label>
            <input
                type="text"
                id="q"
                name="q"
                value="<?= e($f_cari) ?>"
                placeholder="Cari judul atau nama pengisi..."
            >
        </div>

        <div class="filter-group">
            <label for="bulan">Bulan</label>

            <select name="bulan" id="bulan" class="filter-periode">
                <option value="0">Semua bulan</option>

                <?php foreach ($nama_bulan as $bnum => $bnama): ?>
                    <option
                        value="<?= $bnum ?>"
                        <?= $f_bulan === $bnum ? 'selected' : '' ?>
                    >
                        <?= e($bnama) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="tahun">Tahun</label>

            <select name="tahun" id="tahun" class="filter-periode">
                <option value="0">Semua tahun</option>

                <?php foreach ($tahun_tersedia as $th): ?>
                    <option
                        value="<?= $th ?>"
                        <?= $f_tahun === $th ? 'selected' : '' ?>
                    >
                        <?= $th ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions"
             style="display:flex;gap:8px;">

            <button class="btn dark" type="submit">
                Terapkan
            </button>

            <?php if ($f_cari !== '' || $f_tahun > 0 || $f_bulan > 0): ?>
                <a class="btn" href="rekap_ikasandi.php">
                    Reset
                </a>
            <?php endif; ?>

        </div>

    </form>

    <!-- TABEL -->
    <div class="card">

        <?php if (empty($data)): ?>

            <div class="empty">
                <div style="font-size:35px;margin-bottom:10px;">
                    &#128203;
                </div>

                <b>Belum ada data pengisian</b>

                <p>
                    Tidak ada data yang sesuai dengan pencarian atau filter.
                </p>
            </div>

        <?php else: ?>

            <div class="scroll">

                <table>

                    <thead>
                        <tr>
                            <th>Judul Pengisian</th>
                            <th>Nama Pengisi</th>
                            <th>Tanggal Pengisian</th>
                            <th>Jumlah Kategori</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($data as $r): ?>

                        <?php
                        $tanggal_tampil = '-';

                        if (!empty($r['tanggal_pengisian'])) {
                            $tanggal = strtotime($r['tanggal_pengisian']);

                            $tanggal_tampil = $tanggal
                                ? date('d-m-Y', $tanggal)
                                : $r['tanggal_pengisian'];
                        }
                        ?>

                        <tr>

                            <td class="judul">
                                <a
                                    class="link-detail"
                                    href="detail_rekap_ikasandi.php?id_users=<?= (int)$r['id_users'] ?>&judul_topik=<?= urlencode($r['judul_topik']) ?>"
                                >
                                    <?= e($r['judul_topik'] ?: 'Rekap Pengisian IKASANDI') ?>
                                </a>
                            </td>

                            <td class="nama">
                                <?= e($r['nama_pengisi'] ?: '-') ?>
                            </td>

                            <td class="tanggal">
                                <?= e($tanggal_tampil) ?>
                            </td>

                            <td>
                                <?php if ((int)$r['jumlah_topik'] > 0): ?>
                                    <span class="badge">
                                        <?= (int)$r['jumlah_topik'] ?>
                                        dari 5 kategori
                                    </span>
                                <?php else: ?>
                                    <span class="badge kosong">
                                        Belum dinilai
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="aksi">

                                <!-- UBAH (data responden dengan judul ini) -->
                                <a
                                    class="btn-ubah"
                                    href="pengisian_ikasandi.php?view=responden&aksi=ubah&id_users=<?= (int)$r['id_users'] ?>&judul_topik=<?= urlencode($r['judul_topik']) ?>"
                                >
                                    ✏️ Ubah
                                </a>

                                <!-- HAPUS -->
                                <form
                                    method="post"
                                    class="form-hapus"
                                    onsubmit="return confirm('Yakin ingin menghapus seluruh pengisian IKASANDI ini beserta data responden dan semua kategori terkait?');"
                                >

                                    <input
                                        type="hidden"
                                        name="aksi"
                                        value="hapus"
                                    >

                                    <input
                                        type="hidden"
                                        name="id_users"
                                        value="<?= (int)$r['id_users'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="judul_topik"
                                        value="<?= e($r['judul_topik']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_rekap']) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-hapus"
                                    >
                                        🗑 Hapus
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <!-- PAGINASI -->
            <div class="pager">

                <div>
                    Menampilkan
                    <?= $total > 0 ? $offset + 1 : 0 ?>
                    –
                    <?= $offset + count($data) ?>
                    dari
                    <?= number_format($total, 0, ',', '.') ?>
                    data pengisian
                </div>

                <div class="links">

                    <?php if ($halaman > 1): ?>

                        <a href="<?= e(urlDengan(['hal' => 1])) ?>">«</a>

                        <a href="<?= e(urlDengan(['hal' => $halaman - 1])) ?>">
                            ‹ Sebelumnya
                        </a>

                    <?php else: ?>

                        <span class="off">«</span>
                        <span class="off">‹ Sebelumnya</span>

                    <?php endif; ?>

                    <span class="cur">
                        <?= $halaman ?> / <?= $total_hal ?>
                    </span>

                    <?php if ($halaman < $total_hal): ?>

                        <a href="<?= e(urlDengan(['hal' => $halaman + 1])) ?>">
                            Berikutnya ›
                        </a>

                        <a href="<?= e(urlDengan(['hal' => $total_hal])) ?>">
                            »
                        </a>

                    <?php else: ?>

                        <span class="off">Berikutnya ›</span>
                        <span class="off">»</span>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</main>

</body>
</html>