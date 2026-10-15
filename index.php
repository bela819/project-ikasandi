<?php
session_start();

require_once 'koneksi.php';

/* ------------------------------------------------------------------
   AUTENTIKASI
------------------------------------------------------------------ */

if (empty($_SESSION['user_id']) && empty($_SESSION['id_users'])) {
    header('Location: login.php');
    exit;
}

$id_users = (int) ($_SESSION['user_id'] ?? $_SESSION['id_users'] ?? 0);

$nama_pengguna = trim(
    $_SESSION['nama_lengkap']
    ?? $_SESSION['nama']
    ?? $_SESSION['username']
    ?? 'Pengguna'
);

$role     = strtolower(trim($_SESSION['role'] ?? 'pegawai'));
$is_admin = ($role === 'admin');


/* ------------------------------------------------------------------
   HELPER
------------------------------------------------------------------ */

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatTanggal($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $time = strtotime($tanggal);

    return $time ? date('d/m/Y H:i', $time) : (string) $tanggal;
}

function namaRole($role)
{
    $role = strtolower(trim($role));

    switch ($role) {
        case 'admin':    return 'Administrator';
        case 'operator': return 'Operator';
        case 'pegawai':  return 'Pegawai';
        default:         return ucfirst($role);
    }
}

function inisial($nama)
{
    $nama = trim($nama);

    if ($nama === '') {
        return 'U';
    }

    $kata = preg_split('/\s+/', $nama);

    if (count($kata) === 1) {
        return strtoupper(substr($kata[0], 0, 2));
    }

    return strtoupper(
        substr($kata[0], 0, 1) . substr($kata[count($kata) - 1], 0, 1)
    );
}

function queryOne($koneksi, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($koneksi, $sql);

    if (!$stmt) {
        return null;
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data   = $result ? mysqli_fetch_assoc($result) : null;

    mysqli_stmt_close($stmt);

    return $data;
}

function queryAll($koneksi, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($koneksi, $sql);

    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data   = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }

    mysqli_stmt_close($stmt);

    return $data;
}


/* ------------------------------------------------------------------
   DOMAIN IKASANDI
------------------------------------------------------------------ */

$kategori = [
    1 => ['kode' => 'I',   'nama' => 'Identifikasi'],
    2 => ['kode' => 'II',  'nama' => 'Proteksi'],
    3 => ['kode' => 'III', 'nama' => 'Deteksi'],
    4 => ['kode' => 'IV',  'nama' => 'Penanggulangan dan Pemulihan'],
    5 => ['kode' => 'V',   'nama' => 'Persandian'],
];

$total_domain = count($kategori);


/* ------------------------------------------------------------------
   TOTAL INSTRUMEN
------------------------------------------------------------------ */

$row = queryOne($koneksi, "SELECT COUNT(*) AS total FROM pertanyaan");
$total_instrumen = (int) ($row['total'] ?? 0);


/* ------------------------------------------------------------------
   TOTAL PENGGUNA (khusus Admin)
------------------------------------------------------------------ */

$total_pengguna = 0;

if ($is_admin) {
    $row = queryOne($koneksi, "SELECT COUNT(*) AS total FROM users");
    $total_pengguna = (int) ($row['total'] ?? 0);
}


/* ------------------------------------------------------------------
   TOTAL PENGISIAN
   1 pengisian = id_users + judul_topik
------------------------------------------------------------------ */

if ($is_admin) {

    $row = queryOne(
        $koneksi,
        "SELECT COUNT(*) AS total
         FROM (
             SELECT id_users, judul_topik
             FROM pengisian_ikasandi
             WHERE judul_topik IS NOT NULL
               AND TRIM(judul_topik) <> ''
             GROUP BY id_users, judul_topik
         ) AS x"
    );

} else {

    $row = queryOne(
        $koneksi,
        "SELECT COUNT(*) AS total
         FROM (
             SELECT id_users, judul_topik
             FROM pengisian_ikasandi
             WHERE id_users = ?
               AND judul_topik IS NOT NULL
               AND TRIM(judul_topik) <> ''
             GROUP BY id_users, judul_topik
         ) AS x",
        "i",
        [$id_users]
    );
}

$total_pengisian = (int) ($row['total'] ?? 0);


/* ------------------------------------------------------------------
   PENGISIAN PER JUDUL TOPIK
------------------------------------------------------------------ */

if ($is_admin) {

    $data_topik = queryAll(
        $koneksi,
        "SELECT
            judul_topik,
            COUNT(DISTINCT id_users)  AS total_pengisian,
            COUNT(*)                  AS total_jawaban,
            COUNT(DISTINCT topik_id)  AS jumlah_domain,
            MAX(tanggal_pengisian)    AS terakhir
         FROM pengisian_ikasandi
         WHERE judul_topik IS NOT NULL
           AND TRIM(judul_topik) <> ''
         GROUP BY judul_topik
         ORDER BY MAX(id) DESC"
    );

} else {

    $data_topik = queryAll(
        $koneksi,
        "SELECT
            judul_topik,
            COUNT(DISTINCT id_users)  AS total_pengisian,
            COUNT(*)                  AS total_jawaban,
            COUNT(DISTINCT topik_id)  AS jumlah_domain,
            MAX(tanggal_pengisian)    AS terakhir
         FROM pengisian_ikasandi
         WHERE id_users = ?
           AND judul_topik IS NOT NULL
           AND TRIM(judul_topik) <> ''
         GROUP BY judul_topik
         ORDER BY MAX(id) DESC",
        "i",
        [$id_users]
    );
}


/* ------------------------------------------------------------------
   AKTIVITAS PENGISIAN TERBARU
   1 judul topik = 1 baris (data terakhir)
------------------------------------------------------------------ */

if ($is_admin) {

    $aktivitas = queryAll(
        $koneksi,
        "SELECT
            p.id, p.id_users, p.judul_topik, p.topik_id,
            p.nama_pengisi, p.tanggal_pengisian,
            u.nama_lengkap, u.username, u.role
         FROM pengisian_ikasandi p
         LEFT JOIN users u ON u.id = p.id_users
         INNER JOIN (
             SELECT judul_topik, MAX(id) AS max_id
             FROM pengisian_ikasandi
             WHERE judul_topik IS NOT NULL
               AND TRIM(judul_topik) <> ''
             GROUP BY judul_topik
         ) terakhir ON terakhir.max_id = p.id
         ORDER BY p.id DESC
         LIMIT 10"
    );

} else {

    $aktivitas = queryAll(
        $koneksi,
        "SELECT
            p.id, p.id_users, p.judul_topik, p.topik_id,
            p.nama_pengisi, p.tanggal_pengisian,
            u.nama_lengkap, u.username, u.role
         FROM pengisian_ikasandi p
         LEFT JOIN users u ON u.id = p.id_users
         INNER JOIN (
             SELECT judul_topik, MAX(id) AS max_id
             FROM pengisian_ikasandi
             WHERE id_users = ?
               AND judul_topik IS NOT NULL
               AND TRIM(judul_topik) <> ''
             GROUP BY judul_topik
         ) terakhir ON terakhir.max_id = p.id
         WHERE p.id_users = ?
         ORDER BY p.id DESC
         LIMIT 10",
        "ii",
        [$id_users, $id_users]
    );
}


/* ------------------------------------------------------------------
   GRAFIK PENGISIAN PER TAHUN
------------------------------------------------------------------ */

$grafik = [];

$tahun_sekarang           = (int) date('Y');
$jumlah_tahun_ditampilkan = 5;

for ($i = $jumlah_tahun_ditampilkan - 1; $i >= 0; $i--) {
    $tahun = $tahun_sekarang - $i;
    $grafik[$tahun] = ['label' => (string) $tahun, 'total' => 0];
}

$tahun_awal = $tahun_sekarang - ($jumlah_tahun_ditampilkan - 1);

if ($is_admin) {

    $data_grafik = queryAll(
        $koneksi,
        "SELECT YEAR(tanggal_pengisian) AS tahun, COUNT(*) AS total
         FROM (
             SELECT id_users, judul_topik, MAX(tanggal_pengisian) AS tanggal_pengisian
             FROM pengisian_ikasandi
             WHERE tanggal_pengisian IS NOT NULL
               AND judul_topik IS NOT NULL
               AND TRIM(judul_topik) <> ''
             GROUP BY id_users, judul_topik
         ) AS x
         WHERE YEAR(tanggal_pengisian) >= ?
         GROUP BY YEAR(tanggal_pengisian)
         ORDER BY tahun ASC",
        "i",
        [$tahun_awal]
    );

} else {

    $data_grafik = queryAll(
        $koneksi,
        "SELECT YEAR(tanggal_pengisian) AS tahun, COUNT(*) AS total
         FROM (
             SELECT id_users, judul_topik, MAX(tanggal_pengisian) AS tanggal_pengisian
             FROM pengisian_ikasandi
             WHERE id_users = ?
               AND tanggal_pengisian IS NOT NULL
               AND judul_topik IS NOT NULL
               AND TRIM(judul_topik) <> ''
             GROUP BY id_users, judul_topik
         ) AS x
         WHERE YEAR(tanggal_pengisian) >= ?
         GROUP BY YEAR(tanggal_pengisian)
         ORDER BY tahun ASC",
        "ii",
        [$id_users, $tahun_awal]
    );
}

foreach ($data_grafik as $item) {
    $tahun = (int) $item['tahun'];

    if (isset($grafik[$tahun])) {
        $grafik[$tahun]['total'] = (int) $item['total'];
    }
}

$nilai_grafik = array_column($grafik, 'total');
$max_grafik   = max(1, ...$nilai_grafik);


/* ------------------------------------------------------------------
   DATA DOMAIN
------------------------------------------------------------------ */

$domain_data = queryAll(
    $koneksi,
    "SELECT
        p.topik_id,
        COUNT(DISTINCT p.id)  AS jumlah_pertanyaan,
        COUNT(DISTINCT pi.id) AS jumlah_jawaban
     FROM pertanyaan p
     LEFT JOIN pengisian_ikasandi pi ON pi.pertanyaan_id = p.id
     GROUP BY p.topik_id
     ORDER BY p.topik_id ASC"
);

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Arsikas — Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">

<style>

/* ==================================================================
   PALET — disamakan dengan halaman Rekap IKASANDI
================================================================== */

:root {
    --bg:        #F4F7FB;
    --panel:     #FFFFFF;
    --panel-2:   #F8FAFC;
    --line:      #E2E8F0;
    --line-soft: #EEF1F5;

    --navy:      #0B2545;
    --gold:      #D4AF37;

    --accent:      #155EEF;
    --accent-tint: #EAF2FF;

    --text:      #172033;
    --muted:     #667085;
}

* { box-sizing: border-box; }

html, body {
    margin: 0;
    min-height: 100%;
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', sans-serif;
    -webkit-font-smoothing: antialiased;
}

body { overflow-x: hidden; }

h1, h2, h3 { font-family: 'Space Grotesk', sans-serif; }

a { color: inherit; text-decoration: none; }

button, input, select { font: inherit; }

:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
}


/* ---------- NAVBAR ---------- */

.header {
    min-height: 70px;
    background: var(--navy);
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 30px;
    flex-wrap: wrap;
    gap: 10px;
    position: sticky;
    top: 0;
    z-index: 50;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 20px;
    font-weight: 800;
}

.logo .mark {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--navy);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 14px;
    font-weight: 700;
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
    color: rgba(255, 255, 255, .75);
    font-size: 13px;
    font-weight: 600;
    padding: 9px 14px;
    border-radius: 8px;
    white-space: nowrap;
}

.nav-utama a:hover,
.nav-utama a.active {
    background: rgba(255, 255, 255, .14);
    color: #fff;
}

.user {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 700;
}

.avatar,
.mini-avatar {
    border-radius: 50%;
    background: var(--gold);
    color: var(--navy);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    flex: none;
}

.avatar      { width: 38px; height: 38px; font-size: 13px; }

.mini-avatar {
    width: 30px;
    height: 30px;
    font-size: 10.5px;
    background: var(--accent-tint);
    color: var(--accent);
}

@media (max-width: 900px) {
    .nav-utama { display: none; }
}

@media (max-width: 700px) {
    .header { padding: 14px; }
}


/* ---------- MAIN ---------- */

main {
    max-width: 1450px;
    margin: auto;
    padding: 28px 24px 60px;
}

@media (max-width: 700px) {
    main { padding: 20px 12px 40px; }
}


/* ---------- TOPBAR ---------- */

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.topbar h1 { margin: 0 0 6px; font-size: 26px; font-weight: 700; }
.topbar p  { margin: 0; color: var(--muted); font-size: 13px; font-weight: 500; line-height: 1.5; }

.search {
    background: var(--panel);
    border: 1px solid #D0D5DD;
    border-radius: 9px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 260px;
    transition: .2s;
}

.search:focus-within { border-color: var(--accent); }

.search svg { width: 15px; height: 15px; color: var(--muted); flex: none; }

.search input {
    background: transparent;
    border: none;
    outline: none;
    color: var(--text);
    font-size: 13px;
    width: 100%;
}

.search input::placeholder { color: var(--muted); }

@media (max-width: 600px) {
    .search { width: 100%; }
}


/* ---------- STATISTIK ---------- */

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.stat {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 17px;
}

.stat .lbl { font-size: 12.5px; font-weight: 600; color: var(--muted); margin-bottom: 10px; }

.stat .val {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 25px;
    font-weight: 600;
}

.stat .sub { margin-top: 8px; color: var(--muted); font-size: 12px; font-weight: 500; }


/* ---------- GRID & PANEL ---------- */

.grid2 {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 18px;
    margin-bottom: 18px;
}

.grid2 .panel { margin-bottom: 0; }

@media (max-width: 950px) {
    .grid2 { grid-template-columns: 1fr; }
}

.panel {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 18px;
    min-width: 0;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.panel-head h3 { font-size: 16px; font-weight: 700; margin: 0; }

.more {
    font-size: 12px;
    font-weight: 700;
    color: var(--accent);
    white-space: nowrap;
}

.more:hover { text-decoration: underline; }


/* ---------- GRAFIK ---------- */

.chart {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    height: 190px;
    padding-top: 15px;
}

.bar-col {
    flex: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}

.bar-value {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10.5px;
    font-weight: 600;
    color: var(--muted);
}

.bar {
    width: 100%;
    max-width: 40px;
    min-height: 2px;
    border-radius: 6px 6px 3px 3px;
    background: linear-gradient(180deg, var(--accent), var(--navy));
}

.year { font-size: 11px; font-weight: 600; color: var(--muted); }


/* ---------- DAFTAR TOPIK ---------- */

.topik-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 270px;
    overflow-y: auto;
    padding-right: 4px;
}

.topik-list::-webkit-scrollbar { width: 6px; }
.topik-list::-webkit-scrollbar-thumb {
    background: #D0D5DD;
    border-radius: 6px;
}

.topik-item {
    padding: 12px 14px;
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 10px;
}

.topik-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.topik-title { font-size: 13px; font-weight: 700; line-height: 1.4; }

.topik-count {
    font-family: 'IBM Plex Mono', monospace;
    color: var(--accent);
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}

.topik-meta { margin-top: 6px; color: var(--muted); font-size: 11.5px; font-weight: 500; }

.empty {
    padding: 28px 15px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
}


/* ---------- TABEL ---------- */

.table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--line);
    border-radius: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 700px;
}

th {
    background: var(--navy);
    text-align: left;
    color: #fff;
    font-weight: 700;
    font-size: 12px;
    white-space: nowrap;
    padding: 15px 18px;
}

td {
    padding: 15px 18px;
    border-bottom: 1px solid var(--line-soft);
    vertical-align: middle;
    font-size: 12.5px;
    font-weight: 500;
}

tbody tr:hover td { background: var(--panel-2); }
tr:last-child td  { border-bottom: none; }

.who-cell { display: flex; align-items: center; gap: 10px; }

.topic-cell { max-width: 350px; line-height: 1.4; }

.mono {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--muted);
}

.badge {
    display: inline-flex;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.badge.domain {
    background: var(--accent-tint);
    color: var(--accent);
}

.cell-empty {
    text-align: center;
    color: var(--muted);
    padding: 30px;
}


/* ---------- TOMBOL ---------- */

.footer-actions {
    display: flex;
    gap: 10px;
    margin-top: 18px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    border: 1px solid #D0D5DD;
    background: #fff;
    color: var(--text);
    cursor: pointer;
    white-space: nowrap;
}

.btn:hover { filter: brightness(.96); }

.btn.primary {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}

</style>
</head>

<body>

<!-- ===================== NAVBAR ===================== -->

<header class="header">

    <div class="logo">
        <div class="mark">√</div>
        ARSIKAS
    </div>

    <div class="header-kanan">

        <nav class="nav-utama">
            <a href="index.php" class="active">Dashboard</a>
            <a href="instrumen_ikasandi.php">Instrumen IKASANDI</a>
            <a href="pengisian_ikasandi.php">Pengisian IKASANDI</a>
            <a href="rekap_ikasandi.php">Rekap IKASANDI</a>
            <?php if ($is_admin): ?>
                <a href="log_pengguna.php">Log Pengguna</a>
            <?php endif; ?>
            <a href="logout.php">Keluar</a>
        </nav>

        <div class="user">
            <?= e($nama_pengguna); ?>
            <div class="avatar"><?= e(inisial($nama_pengguna)); ?></div>
        </div>

    </div>

</header>


<!-- ======================= MAIN ======================= -->

<main>

    <div class="topbar">

        <div>
            <h1>Selamat datang, <?= e($nama_pengguna); ?></h1>
            <p>Dashboard IKASANDI — <?= e(date('d/m/Y')); ?> · <?= e(namaRole($role)); ?></p>
        </div>

        <div class="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7"/>
                <path d="m21 21-4.3-4.3"/>
            </svg>

            <input
                type="text"
                id="searchDashboard"
                placeholder="Cari judul topik..."
                autocomplete="off"
            >
        </div>

    </div>


    <!-- STATISTIK -->

    <div class="stats">

        <div class="stat">
            <div class="lbl">Total Instrumen IKASANDI</div>
            <div class="val"><?= number_format($total_instrumen, 0, ',', '.'); ?></div>
            <div class="sub">Jumlah pertanyaan dalam instrumen</div>
        </div>

        <?php if ($is_admin): ?>
        <div class="stat">
            <div class="lbl">Total Pengguna</div>
            <div class="val"><?= number_format($total_pengguna, 0, ',', '.'); ?></div>
            <div class="sub">Pengguna terdaftar</div>
        </div>
        <?php endif; ?>

        <div class="stat">
            <div class="lbl">Total Pengisian</div>
            <div class="val"><?= number_format($total_pengisian, 0, ',', '.'); ?></div>
            <div class="sub">Berdasarkan judul topik</div>
        </div>

        <div class="stat">
            <div class="lbl">Total Domain IKASANDI</div>
            <div class="val"><?= number_format($total_domain, 0, ',', '.'); ?></div>
            <div class="sub">Domain penilaian IKASANDI</div>
        </div>

    </div>


    <!-- GRAFIK + PENGISIAN PER JUDUL -->

    <div class="grid2">

        <div class="panel">

            <div class="panel-head">
                <h3>Total Pengisian per Tahun</h3>
                <a class="more" href="rekap_ikasandi.php">Lihat rekap →</a>
            </div>

            <div class="chart">

                <?php foreach ($grafik as $item): ?>

                    <?php
                    $persentase = $item['total'] > 0
                        ? ($item['total'] / $max_grafik) * 100
                        : 2;
                    ?>

                    <div class="bar-col">
                        <div class="bar-value"><?= (int) $item['total']; ?></div>

                        <div
                            class="bar"
                            style="height: <?= max(2, min(100, $persentase)); ?>%;"
                            title="<?= (int) $item['total']; ?> pengisian"
                        ></div>

                        <div class="year"><?= e($item['label']); ?></div>
                    </div>

                <?php endforeach; ?>

            </div>

        </div>


        <div class="panel">

            <div class="panel-head">
                <h3>Pengisian per Judul Topik</h3>
            </div>

            <div class="topik-list" id="topikList">

                <?php if (empty($data_topik)): ?>

                    <div class="empty">Belum ada data pengisian IKASANDI.</div>

                <?php else: ?>

                    <?php foreach ($data_topik as $item): ?>

                        <div
                            class="topik-item"
                            data-search="<?= e(strtolower($item['judul_topik'])); ?>"
                        >

                            <div class="topik-row">
                                <div class="topik-title"><?= e($item['judul_topik']); ?></div>

                                <div class="topik-count">
                                    <?= number_format((int) $item['total_pengisian'], 0, ',', '.'); ?> ×
                                </div>
                            </div>

                            <div class="topik-meta">
                                <?= (int) $item['jumlah_domain']; ?> domain ·
                                <?= number_format((int) $item['total_jawaban'], 0, ',', '.'); ?> jawaban
                                <?php if (!empty($item['terakhir'])): ?>
                                    · terakhir <?= e(formatTanggal($item['terakhir'])); ?>
                                <?php endif; ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- AKTIVITAS TERBARU (1 judul topik = 1 data) -->

    <div class="panel">

        <div class="panel-head">
            <h3>Aktivitas Pengisian IKASANDI Terbaru</h3>
            <a class="more" href="rekap_ikasandi.php">Lihat rekap →</a>
        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Judul Topik</th>
                        <th>Pengisi Terakhir</th>
                        <th>Domain</th>
                        <th>Pengisian Terakhir</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($aktivitas)): ?>

                    <tr>
                        <td colspan="4" class="cell-empty">Belum ada pengisian IKASANDI.</td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($aktivitas as $item): ?>

                        <?php
                        $nama = $item['nama_lengkap']
                            ?: $item['nama_pengisi']
                            ?: $item['username']
                            ?: 'Pengguna';

                        $domain = $kategori[(int) $item['topik_id']]['nama']
                            ?? ('Topik #' . (int) $item['topik_id']);
                        ?>

                        <tr>

                            <td class="topic-cell">
                                <strong><?= e($item['judul_topik']); ?></strong>
                            </td>

                            <td>
                                <div class="who-cell">
                                    <div class="mini-avatar"><?= e(inisial($nama)); ?></div>
                                    <?= e($nama); ?>
                                </div>
                            </td>

                            <td>
                                <span class="badge domain"><?= e($domain); ?></span>
                            </td>

                            <td class="mono">
                                <?= e(formatTanggal($item['tanggal_pengisian'])); ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="footer-actions">
            <a href="pengisian_ikasandi.php" class="btn primary">+ Mulai Pengisian IKASANDI</a>
            <a href="rekap_ikasandi.php" class="btn">Lihat Rekap IKASANDI</a>
        </div>

    </div>


    <!-- DOMAIN IKASANDI -->

    <div class="panel">

        <div class="panel-head">
            <h3>Domain IKASANDI</h3>
            <a class="more" href="instrumen_ikasandi.php">Lihat instrumen →</a>
        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Domain</th>
                        <th>Jumlah Pertanyaan</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($kategori as $id => $info): ?>

                        <?php
                        $domain_item = ['jumlah_pertanyaan' => 0, 'jumlah_jawaban' => 0];

                        foreach ($domain_data as $d) {
                            if ((int) $d['topik_id'] === (int) $id) {
                                $domain_item = $d;
                                break;
                            }
                        }
                        ?>

                        <tr>
                            <td><span class="badge domain"><?= e($info['kode']); ?></span></td>
                            <td><?= e($info['nama']); ?></td>
                            <td class="mono">
                                <?= number_format((int) $domain_item['jumlah_pertanyaan'], 0, ',', '.'); ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>


<script>

/* Pencarian judul topik */

const searchInput = document.getElementById('searchDashboard');
const topikItems  = document.querySelectorAll('.topik-item');

if (searchInput) {

    searchInput.addEventListener('input', function () {

        const keyword = this.value.toLowerCase().trim();

        topikItems.forEach(function (item) {
            const text = item.dataset.search || '';
            item.style.display = text.includes(keyword) ? '' : 'none';
        });

    });

}

</script>

</body>
</html>