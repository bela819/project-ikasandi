<?php
session_start();
require_once 'koneksi.php';

/*
|--------------------------------------------------------------------------
| FUNGSI UMUM
|--------------------------------------------------------------------------
*/
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Warna badge indeks per pertanyaan (skala conditional formatting Excel IKASANDI). */
function warnaIndeks($nilai)
{
    if ($nilai === null || $nilai === '') {
        return ['#e9ecef', '#6c757d'];
    }
    $n = (float)$nilai;
    if ($n <= 1.50)  return ['#f8d7da', '#842029'];
    if ($n <= 2.50)  return ['#ffe5b4', '#8a5a00'];
    if ($n <= 3.50)  return ['#fff3cd', '#7a6300'];
    if ($n <= 4.50)  return ['#d1e7dd', '#0f5132'];
    return ['#badbcc', '#0f5132'];
}

function labelLevel($nilai)
{
    if ($nilai === null || $nilai === '') return '-';
    $n = (float)$nilai;
    if ($n <= 1.50)  return 'Level 1 - Awal';
    if ($n <= 2.50)  return 'Level 2 - Berulang';
    if ($n <= 3.50)  return 'Level 3 - Terdefinisi';
    if ($n <= 4.50)  return 'Level 4 - Terkelola';
    return 'Level 5 - Inovatif';
}

/** Label + warna level untuk blok Tingkat Kematangan. */
function levelKematangan($nilai)
{
    $n = (float)$nilai;
    if ($n <= 1.50) $warna = '#DC2626';
    elseif ($n <= 2.50) $warna = '#EA580C';
    elseif ($n <= 3.50) $warna = '#D97706';
    elseif ($n <= 4.50) $warna = '#16A34A';
    else $warna = '#2563EB';
    return [labelLevel($n), $warna];
}


/*
|--------------------------------------------------------------------------
| BUKTI DUKUNG
| File bukti tidak dibuka lewat URL tebakan (yang menyebabkan "Not Found"),
| tetapi lewat handler ini: server mencari file-nya sendiri (di folder
| aplikasi, folder induk, dan subfolder umum), lalu menampilkannya.
| Jika tidak ketemu, halaman penjelasan menampilkan nilai di database dan
| lokasi yang sudah dicari, sehingga penyebabnya langsung terlihat.
|--------------------------------------------------------------------------
*/

/** Tipe file yang boleh ditampilkan/diunduh (file .php dsb. sengaja tidak diizinkan). */
function tipeBuktiDiizinkan()
{
    return [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif'  => 'image/gif',  'webp' => 'image/webp', 'txt' => 'text/plain; charset=utf-8',
        'csv'  => 'text/csv',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip'  => 'application/zip', 'rar' => 'application/vnd.rar',
    ];
}

/** Pecah isi kolom bukti_dukung menjadi daftar file (mendukung 1 file, JSON array, atau dipisah ; | baris baru). */
function daftarBukti($nilai)
{
    $nilai = trim((string)$nilai);
    if ($nilai === '') return [];

    if ($nilai[0] === '[') {
        $j = json_decode($nilai, true);
        if (is_array($j)) {
            $out = [];
            foreach ($j as $x) {
                if (is_array($x)) $x = $x['path'] ?? ($x['file'] ?? ($x['nama'] ?? ''));
                if (is_string($x) && trim($x) !== '') $out[] = trim($x);
            }
            return $out;
        }
    }
    $parts = preg_split('/[;\r\n|]+/', $nilai);
    return array_values(array_filter(array_map('trim', $parts), 'strlen'));
}

/** Link untuk satu bukti: URL penuh dipakai langsung, selain itu lewat handler. */
function linkBukti($berkas, $id_baris, $id_users, $indeks)
{
    if (preg_match('#^https?://#i', $berkas)) return $berkas;
    return basename($_SERVER['SCRIPT_NAME']) . '?lihat_bukti=' . (int)$id_baris
         . '&id_users=' . (int)$id_users . '&f=' . (int)$indeks;
}

/**
 * Cari file bukti di server. Return ['file' => path-nyata|null, 'dicari' => [...], 'catatan' => '...'].
 */
function cariFileBukti($nilai)
{
    $dicari = [];
    $asal = trim((string)$nilai);
    if ($asal === '') return ['file' => null, 'dicari' => $dicari, 'catatan' => 'Nilai bukti kosong.'];

    // Folder yang boleh dibaca: folder aplikasi, folder induknya, dan document root.
    $roots = [];
    foreach ([__DIR__, dirname(__DIR__), $_SERVER['DOCUMENT_ROOT'] ?? ''] as $r) {
        $rr = ($r !== '') ? realpath($r) : false;
        if ($rr) $roots[$rr] = true;
    }
    $roots = array_keys($roots);

    $boleh = function ($f) use ($roots) {
        $rp = realpath($f);
        if (!$rp || !is_file($rp)) return null;
        $rp2 = str_replace('\\', '/', $rp);
        foreach ($roots as $r) {
            $r2 = rtrim(str_replace('\\', '/', $r), '/') . '/';
            if (stripos($rp2, $r2) === 0) return $rp;
        }
        return null;
    };

    $izin = tipeBuktiDiizinkan();
    $cekExt = function ($f) use ($izin) {
        return isset($izin[strtolower(pathinfo($f, PATHINFO_EXTENSION))]);
    };

    $namaNama = array_unique([$asal, rawurldecode($asal)]);
    $kandidat = [];
    $bases = array_unique([__DIR__, dirname(__DIR__), $_SERVER['DOCUMENT_ROOT'] ?? '']);
    $subs  = ['uploads/ikasandi', '', 'uploads', 'upload', 'bukti', 'bukti_dukung', 'file', 'files',
              'assets/uploads', 'uploads/bukti', 'uploads/bukti_dukung'];

    foreach ($namaNama as $n) {
        $p = str_replace('\\', '/', $n);
        $p = preg_replace('#^file:///?#i', '', $p);

        // Path absolut (mis. C:/xampp/htdocs/app/uploads/a.pdf atau /var/www/...)
        if (preg_match('#^([A-Za-z]:)?/#', $p)) $kandidat[] = $p;

        $rel  = preg_replace('#^(\./)+#', '', ltrim($p, '/'));
        $rel2 = preg_replace('#^(\.\./)+#', '', $rel);
        // Bagian setelah "htdocs/" bila path absolut Windows
        if (preg_match('#/htdocs/(.+)$#i', $p, $m)) $rel3 = $m[1]; else $rel3 = $rel2;

        foreach ($bases as $b) {
            if ($b === '') continue;
            $b = rtrim(str_replace('\\', '/', $b), '/');
            foreach ($subs as $s) {
                $pre = $b . ($s !== '' ? '/' . $s : '');
                foreach (array_unique([$rel, $rel2, $rel3, basename($p)]) as $r) {
                    if ($r !== '') $kandidat[] = $pre . '/' . $r;
                }
            }
        }
    }

    foreach (array_unique($kandidat) as $k) {
        $dicari[] = $k;
        $ok = $boleh($k);
        if ($ok) {
            if (!$cekExt($ok)) return ['file' => null, 'dicari' => $dicari, 'catatan' => 'File ditemukan tetapi jenis file tidak diizinkan untuk ditampilkan.'];
            return ['file' => $ok, 'dicari' => $dicari, 'catatan' => ''];
        }
    }

    // Cadangan: cari berdasarkan nama file di seluruh folder aplikasi (lalu folder induk).
    $target = strtolower(basename(str_replace('\\', '/', $asal)));
    $lewati = ['vendor', 'node_modules', '.git', 'phpmyadmin'];
    foreach ([__DIR__, dirname(__DIR__)] as $base) {
        $dicari[] = '[cari nama file "' . $target . '" secara rekursif di] ' . $base;
        try {
            $dir = new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS);
            $flt = new RecursiveCallbackFilterIterator($dir, function ($cur) use ($lewati) {
                return !($cur->isDir() && in_array($cur->getFilename(), $lewati, true));
            });
            $it = new RecursiveIteratorIterator($flt, RecursiveIteratorIterator::LEAVES_ONLY);
            $it->setMaxDepth(6);
            foreach ($it as $f) {
                if (strtolower($f->getFilename()) === $target) {
                    $ok = $boleh($f->getPathname());
                    if ($ok && $cekExt($ok)) return ['file' => $ok, 'dicari' => $dicari, 'catatan' => ''];
                }
            }
        } catch (Throwable $ex) {
            // abaikan folder yang tidak bisa dibaca
        }
    }

    return ['file' => null, 'dicari' => $dicari, 'catatan' => 'File tidak ditemukan di server.'];
}

/** Daftar file di folder uploads yang namanya memuat id user ini (untuk diagnosa). */
function fileUploadUser($id_users)
{
    $hasil = [];
    foreach ([__DIR__ . '/uploads/ikasandi', __DIR__ . '/uploads'] as $dir) {
        if (!is_dir($dir)) continue;
        foreach (glob($dir . '/*_' . (int)$id_users . '_*') ?: [] as $f) {
            if (is_file($f)) $hasil[basename($f)] = filemtime($f);
        }
    }
    arsort($hasil);
    return array_slice(array_keys($hasil), 0, 20);
}

/* Handler: ?lihat_bukti=<id baris pengisian>&id_users=<id>&f=<urutan file> */
if (isset($_GET['lihat_bukti'])) {
    $id_baris = (int)$_GET['lihat_bukti'];
    $uid      = (int)($_GET['id_users'] ?? 0);
    $urutan   = max(0, (int)($_GET['f'] ?? 0));

    $stB = $koneksi->prepare("SELECT bukti_dukung FROM pengisian_ikasandi WHERE id = ? AND id_users = ? LIMIT 1");
    $stB->bind_param('ii', $id_baris, $uid);
    $stB->execute();
    $rowB = $stB->get_result()->fetch_assoc();
    $stB->close();

    $nilaiBukti = $rowB['bukti_dukung'] ?? '';
    $berkas     = daftarBukti($nilaiBukti)[$urutan] ?? '';
    $hasil      = cariFileBukti($berkas);

    if ($hasil['file']) {
        $file = $hasil['file'];
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $tipe = tipeBuktiDiizinkan()[$ext];
        $inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'], true);

        while (ob_get_level() > 0) ob_end_clean();
        session_write_close();
        header('Content-Type: ' . $tipe);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
             . "; filename*=UTF-8''" . rawurlencode(basename($file)));
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    $adaDiDisk = fileUploadUser($uid);
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="id"><head><meta charset="UTF-8"><title>Bukti dukung tidak ditemukan</title>
    <style>body{font-family:Arial,sans-serif;max-width:900px;margin:40px auto;padding:0 16px;color:#17365d}
    code{background:#f1f3f5;padding:2px 6px;border-radius:4px;word-break:break-all}
    li{margin-bottom:4px;font-size:13px;word-break:break-all}</style></head><body>
    <h2>Bukti dukung tidak dapat ditampilkan</h2>
    <p><?= e($hasil['catatan']) ?></p>
    <p>Nilai yang tersimpan di database: <code><?= e($berkas !== '' ? $berkas : $nilaiBukti) ?></code></p>
    <?php if ($adaDiDisk): ?>
        <p>File milik user ini yang saat ini ada di folder <code>uploads</code> (terbaru di atas):</p>
        <ul><?php foreach ($adaDiDisk as $nf): ?><li><code><?= e($nf) ?></code></li><?php endforeach; ?></ul>
        <p>Nama di database tidak sama dengan satupun file di atas. Upload ulang bukti dukung dari halaman pengisian.</p>
    <?php else: ?>
        <p>Tidak ada satupun file milik user ini (id <?= (int)$uid ?>) di folder <code>uploads</code>.
           File belum tersimpan atau sudah terhapus. Upload ulang bukti dukung dari halaman pengisian.</p>
    <?php endif; ?>
    <details><summary>Lokasi yang sudah dicari (<?= count($hasil['dicari']) ?>)</summary>
    <ul><?php foreach ($hasil['dicari'] as $d): ?><li><?= e($d) ?></li><?php endforeach; ?></ul></details>
    </body></html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| PARAMETER PENILAIAN
|--------------------------------------------------------------------------
*/
$id_users    = isset($_GET['id_users']) ? (int)$_GET['id_users'] : 0;
$judul_topik = trim($_GET['judul_topik'] ?? '');
$mode        = $_GET['mode'] ?? 'kategori'; // 'kategori' = satu domain, 'all' = seluruh domain

if ($id_users <= 0 || $judul_topik === '') {
    die("Data penilaian tidak ditemukan.");
}

/*
|--------------------------------------------------------------------------
| KATEGORI / DOMAIN PENILAIAN
|--------------------------------------------------------------------------
*/
$kategori = [
    'identifikasi'   => ['judul' => 'Identifikasi',                 'topik_id' => 1, 'icon' => 'bi-search',       'kode' => 'I'],
    'proteksi'       => ['judul' => 'Proteksi',                     'topik_id' => 2, 'icon' => 'bi-shield-check', 'kode' => 'II'],
    'deteksi'        => ['judul' => 'Deteksi',                      'topik_id' => 3, 'icon' => 'bi-radar',        'kode' => 'III'],
    'penanggulangan' => ['judul' => 'Penanggulangan dan Pemulihan', 'topik_id' => 4, 'icon' => 'bi-arrow-repeat', 'kode' => 'IV'],
    'persandian'     => ['judul' => 'Persandian',                   'topik_id' => 5, 'icon' => 'bi-lock',         'kode' => 'V'],
];

/* Pengelompokan untuk blok Tingkat Kematangan (topik 1-4 = Keamanan Siber, topik 5 = Persandian) */
$topik_kamsiber = [
    1 => ['kode' => 'I',   'nama' => 'IDENTIFIKASI',                 'warna' => '#2E75B6'],
    2 => ['kode' => 'II',  'nama' => 'PROTEKSI',                     'warna' => '#7030A0'],
    3 => ['kode' => 'III', 'nama' => 'DETEKSI',                      'warna' => '#ED7D31'],
    4 => ['kode' => 'IV',  'nama' => 'PENANGGULANGAN DAN PEMULIHAN', 'warna' => '#70AD47'],
];
$topik_persandian = [
    5 => ['kode' => 'V', 'nama' => 'PERSANDIAN', 'warna' => '#6B7280'],
];

/*
|--------------------------------------------------------------------------
| LEGENDA TINGKAT KEMATANGAN (sheet "Pengantar")
|--------------------------------------------------------------------------
*/
$legenda = [
    ['Level 1', 'Level Awal',        '0,00 - 1,50', 'Penerapan belum terorganisir, bersifat informal, tidak konsisten. Dokumen manajemen risiko & kontrol belum disusun.'],
    ['Level 2', 'Level Berulang',    '1,51 - 2,50', 'Sudah memiliki prosedur namun masih informal, berulang tapi belum konsisten. Dokumen sudah disusun namun belum ditetapkan.'],
    ['Level 3', 'Level Terdefinisi', '2,51 - 3,50', 'Terorganisir dengan jelas dan bersifat formal, dilakukan berulang, konsisten, dan direviu berkala. Dokumen sudah ditetapkan.'],
    ['Level 4', 'Level Terkelola',   '3,51 - 4,50', 'Terorganisir dengan baik namun belum otomatis. Dilakukan berulang dan perbaikan berkelanjutan. Dokumen sudah ditetapkan.'],
    ['Level 5', 'Level Inovatif',    '4,51 - 5,00', 'Terorganisir baik dan sudah terotomatisasi, konsisten, terintegrasi, dan menjadi bagian budaya Organisasi.'],
];

/*
|--------------------------------------------------------------------------
| ALAT BANTU PENETAPAN KATEGORI SISTEM ELEKTRONIK (SE)
| Sama persis dengan definisi di data_responden.php, dipakai di sini
| hanya untuk MENAMPILKAN hasil (pertanyaan, opsi, skor) yang sudah
| tersimpan pada tabel `responden` (jawaban_alat_bantu_se,
| dokumen_alat_bantu_se).
|--------------------------------------------------------------------------
*/
$alat_bantu_se = [
    '1_1' => [
        'no' => '1.1',
        'pertanyaan' => 'Nilai investasi sistem elektronik yang terpasang',
        'opsi' => [
            'A' => 'Lebih dari Rp.30 Miliar',
            'B' => 'Lebih dari Rp.3 Miliar s/d Rp.30 Miliar',
            'C' => 'Kurang dari Rp.3 Miliar',
        ],
    ],
    '1_2' => [
        'no' => '1.2',
        'pertanyaan' => 'Total anggaran operasional tahunan yang dialokasikan untuk pengelolaan Sistem Elektronik',
        'opsi' => [
            'A' => 'Lebih dari Rp.10 Miliar',
            'B' => 'Lebih dari Rp.1 Miliar s/d Rp.10 Miliar',
            'C' => 'Kurang dari Rp.1 Miliar',
        ],
    ],
    '1_3' => [
        'no' => '1.3',
        'pertanyaan' => 'Memiliki kewajiban kepatuhan terhadap Peraturan atau Standar tertentu',
        'opsi' => [
            'A' => 'Peraturan atau Standar nasional dan internasional',
            'B' => 'Peraturan atau Standar nasional',
            'C' => 'Tidak ada Peraturan khusus',
        ],
    ],
    '1_4' => [
        'no' => '1.4',
        'pertanyaan' => 'Menggunakan teknik kriptografi khusus untuk keamanan informasi dalam Sistem Elektronik',
        'opsi' => [
            'A' => 'Teknik kriptografi khusus yang disertifikasi oleh Negara',
            'B' => 'Teknik kriptografi sesuai standar industri, tersedia secara publik atau dikembangkan sendiri',
            'C' => 'Tidak ada penggunaan teknik kriptografi',
        ],
    ],
    '1_5' => [
        'no' => '1.5',
        'pertanyaan' => 'Jumlah pengguna Sistem Elektronik',
        'opsi' => [
            'A' => 'Lebih dari 5.000 pengguna',
            'B' => '1.000 sampai dengan 5.000 pengguna',
            'C' => 'Kurang dari 1.000 pengguna',
        ],
    ],
    '1_6' => [
        'no' => '1.6',
        'pertanyaan' => 'Data pribadi yang dikelola Sistem Elektronik',
        'opsi' => [
            'A' => 'Data pribadi yang memiliki hubungan dengan Data Pribadi lainnya',
            'B' => 'Data pribadi yang bersifat individu dan/atau data pribadi yang terkait dengan kepemilikan badan usaha',
            'C' => 'Tidak ada data pribadi',
        ],
    ],
    '1_7' => [
        'no' => '1.7',
        'pertanyaan' => 'Tingkat klasifikasi/kekritisan Data yang ada dalam Sistem Elektronik, relatif terhadap ancaman upaya penyerangan atau penerobosan keamanan informasi',
        'opsi' => [
            'A' => 'Sangat Rahasia',
            'B' => 'Rahasia dan/atau Terbatas',
            'C' => 'Biasa',
        ],
    ],
    '1_8' => [
        'no' => '1.8',
        'pertanyaan' => 'Tingkat kekritisan proses yang ada dalam Sistem Elektronik, relatif terhadap ancaman upaya penyerangan atau penerobosan keamanan informasi',
        'opsi' => [
            'A' => 'Proses yang berisiko mengganggu hajat hidup orang banyak dan memberi dampak langsung pada layanan publik',
            'B' => 'Proses yang berisiko mengganggu hajat hidup orang banyak dan memberi dampak tidak langsung',
            'C' => 'Proses yang hanya berdampak pada bisnis Organisasi',
        ],
    ],
    '1_9' => [
        'no' => '1.9',
        'pertanyaan' => 'Dampak dari kegagalan Sistem Elektronik',
        'opsi' => [
            'A' => 'Membahayakan pertahanan keamanan negara',
            'B' => 'Tidak tersedianya layanan publik berskala nasional atau berdampak pada layanan di sektor lain',
            'C' => 'Tidak tersedianya layanan publik dalam 1 propinsi atau internal 1 Organisasi',
        ],
    ],
    '1_10' => [
        'no' => '1.10',
        'pertanyaan' => 'Potensi kerugian atau dampak negatif dari insiden ditembusnya keamanan informasi Sistem Elektronik (sabotase, terorisme)',
        'opsi' => [
            'A' => 'Menimbulkan korban jiwa',
            'B' => 'Terbatas pada kerugian finansial',
            'C' => 'Mengakibatkan gangguan operasional sementara (tidak membahayakan dan mengakibatkan kerugian finansial)',
        ],
    ],
];

$skor_opsi_se = ['A' => 5, 'B' => 2, 'C' => 1];

/*
|--------------------------------------------------------------------------
| VALIDASI VIEW / MODE
|--------------------------------------------------------------------------
*/
/* Tampilan awal = Hasil Alat Bantu SE ('alatbantu'); setelah itu lanjut ke Identifikasi dst. */
$view = $_GET['view'] ?? 'alatbantu';
if ($view !== 'alatbantu' && !isset($kategori[$view])) {
    $view = 'alatbantu';
}
$isAll      = ($mode === 'all');
$viewAlat   = (!$isAll && $view === 'alatbantu');
$tampilAbse = ($isAll || $viewAlat);   /* hasil alat bantu tampil di tampilan awal & mode "semua" */

/*
|--------------------------------------------------------------------------
| INFORMASI PENILAIAN
|--------------------------------------------------------------------------
*/
$stmtInfo = $koneksi->prepare("
    SELECT
        p.nama_pengisi,
        p.tanggal_pengisian,
        p.judul_topik,
        r.organisasi_pse,
        r.nama_responden,
        r.jabatan_responden,
        r.tahun_pengukuran,
        r.target_level_kematangan,
        r.target_nilai,
        r.kategori_se,
        r.skor_kategori_se,
        r.jawaban_alat_bantu_se,
        r.dokumen_alat_bantu_se
    FROM pengisian_ikasandi p
    LEFT JOIN responden r ON r.id_users = p.id_users
    WHERE p.id_users = ? AND p.judul_topik = ?
    LIMIT 1
");
$stmtInfo->bind_param("is", $id_users, $judul_topik);
$stmtInfo->execute();
$info = $stmtInfo->get_result()->fetch_assoc();
$stmtInfo->close();

if (!$info) {
    die("Data penilaian tidak ditemukan.");
}

$nama_pengisi      = $info['nama_pengisi'];
$tanggal_pengisian = $info['tanggal_pengisian'];

/* Data responden yang terkait dengan pengguna/pengisian ini.
 * Jika data responden belum diisi, halaman tetap dapat dibuka dan
 * menampilkan tanda "-" pada kolom yang kosong.
 */
$organisasi_pse          = $info['organisasi_pse'] ?? '';
$nama_responden          = $info['nama_responden'] ?? '';
$jabatan_responden       = $info['jabatan_responden'] ?? '';
$tahun_pengukuran       = $info['tahun_pengukuran'] ?? '';
$target_level_kematangan = $info['target_level_kematangan'] ?? '';
$target_nilai_responden  = $info['target_nilai'] ?? '';
$kategori_se             = $info['kategori_se'] ?? '';
$skor_kategori_se        = $info['skor_kategori_se'] ?? '';

/* Jawaban Alat Bantu Kategori SE (A/B/C per pertanyaan) yang tersimpan */
$jawaban_ab_se_tersimpan = [];
if (!empty($info['jawaban_alat_bantu_se'])) {
    $decoded = json_decode($info['jawaban_alat_bantu_se'], true);
    if (is_array($decoded)) {
        $jawaban_ab_se_tersimpan = $decoded;
    }
}

/* Nomor Dokumen & Nama Dokumen per pertanyaan Alat Bantu SE yang tersimpan */
$dokumen_ab_se_tersimpan = [];
if (!empty($info['dokumen_alat_bantu_se'])) {
    $decoded_dok = json_decode($info['dokumen_alat_bantu_se'], true);
    if (is_array($decoded_dok)) {
        $dokumen_ab_se_tersimpan = $decoded_dok;
    }
}

/*
|--------------------------------------------------------------------------
| FUNGSI DATA PER DOMAIN
|--------------------------------------------------------------------------
*/
function ambilDataDomain($koneksi, $id_users, $judul_topik, $topik_id)
{
    $sql = "
        SELECT
            p.id, p.id_users, p.topik_id, p.pertanyaan_id, p.judul_topik,
            p.nama_pengisi, p.tanggal_pengisian, p.indeks_nilai,
            p.keterangan_validasi, p.bukti_dukung,
            p.created_at, p.updated_at,
            q.kode, q.kategori, q.pertanyaan
        FROM pengisian_ikasandi p
        LEFT JOIN pertanyaan q ON p.pertanyaan_id = q.id
        WHERE p.id_users = ? AND p.judul_topik = ? AND p.topik_id = ?
        ORDER BY p.pertanyaan_id ASC, p.id ASC
    ";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("isi", $id_users, $judul_topik, $topik_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function kelompokkanPerKategori(array $rows)
{
    $grup = [];
    foreach ($rows as $row) {
        $key = ($row['kategori'] !== null && $row['kategori'] !== '') ? $row['kategori'] : 'Lainnya';
        if (!isset($grup[$key])) $grup[$key] = [];
        $grup[$key][] = $row;
    }
    return $grup;
}

function hitungRingkasan(array $rows)
{
    $total_indikator = count($rows);
    $total_terisi = 0;
    $total_nilai  = 0;

    foreach ($rows as $item) {
        if ($item['indeks_nilai'] !== null && $item['indeks_nilai'] !== '') {
            $total_terisi++;
            $total_nilai += (float)$item['indeks_nilai'];
        }
    }
    $rata_rata = $total_terisi > 0 ? round($total_nilai / $total_terisi, 2) : 0;
    return [$total_indikator, $total_terisi, $rata_rata];
}

/*
|--------------------------------------------------------------------------
| FUNGSI TINGKAT KEMATANGAN (relasi ke pengisian_ikasandi + pertanyaan)
|   Nilai Kategori = rata-rata indeks pertanyaan dalam kategori (kosong = 0)
|   Nilai Domain   = rata-rata Nilai Kategori
|   Nilai Total    = rata-rata Nilai Domain
| Difilter per judul_topik agar hanya penilaian yang sedang dibuka.
|--------------------------------------------------------------------------
*/
function hitungKelompok($koneksi, $id_users, $judul_topik, $daftar_topik)
{
    $hasil = [];
    $jumlah_nilai_total = 0;
    $jumlah_domain = 0;

    foreach ($daftar_topik as $tid => $info) {

        $stmt = $koneksi->prepare("SELECT id, kategori FROM pertanyaan WHERE topik_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $res = $stmt->get_result();
        $daftar_pertanyaan = [];
        while ($row = $res->fetch_assoc()) $daftar_pertanyaan[] = $row;
        $stmt->close();

        $stmt = $koneksi->prepare("
            SELECT pertanyaan_id, indeks_nilai
            FROM pengisian_ikasandi
            WHERE id_users = ? AND judul_topik = ? AND topik_id = ?
        ");
        $stmt->bind_param('isi', $id_users, $judul_topik, $tid);
        $stmt->execute();
        $res = $stmt->get_result();
        $jawaban = [];
        while ($row = $res->fetch_assoc()) {
            if ($row['indeks_nilai'] !== null && $row['indeks_nilai'] !== '') {
                $jawaban[(int)$row['pertanyaan_id']] = (float)$row['indeks_nilai'];
            }
        }
        $stmt->close();

        $kategori_map = [];
        foreach ($daftar_pertanyaan as $p) {
            $kat = ($p['kategori'] !== null && $p['kategori'] !== '') ? $p['kategori'] : '(Tanpa kategori)';
            if (!isset($kategori_map[$kat])) $kategori_map[$kat] = ['n' => 0, 'jumlah' => 0, 'terisi' => 0];
            $kategori_map[$kat]['n']++;
            if (isset($jawaban[(int)$p['id']])) {
                $kategori_map[$kat]['jumlah'] += $jawaban[(int)$p['id']];
                $kategori_map[$kat]['terisi']++;
            }
        }

        $kategori_hasil = [];
        $sum_kategori = 0;
        $n_kategori = 0;
        foreach ($kategori_map as $nama => $k) {
            $nilai = $k['n'] > 0 ? $k['jumlah'] / $k['n'] : 0;
            $kategori_hasil[] = ['nama' => $nama, 'nilai' => $nilai, 'n' => $k['n'], 'terisi' => $k['terisi']];
            $sum_kategori += $nilai;
            $n_kategori++;
        }
        $nilai_domain = $n_kategori > 0 ? $sum_kategori / $n_kategori : 0;

        $hasil[$tid] = [
            'kode' => $info['kode'], 'nama' => $info['nama'], 'warna' => $info['warna'],
            'kategori' => $kategori_hasil, 'nilai' => $nilai_domain,
            'total_soal' => count($daftar_pertanyaan),
        ];
        if (count($daftar_pertanyaan) > 0) {
            $jumlah_nilai_total += $nilai_domain;
            $jumlah_domain++;
        }
    }

    $nilai_total = $jumlah_domain > 0 ? $jumlah_nilai_total / $jumlah_domain : 0;
    return ['domain' => $hasil, 'nilai_total' => $nilai_total];
}

/* Grafik radar SVG: Target vs Nilai */
function radarChart($labels, $target_vals, $nilai_vals, $max = 5, $size = 300)
{
    $n = count($labels);
    if ($n < 3) {
        return '<div class="text-muted small">Grafik radar membutuhkan minimal 3 kategori.</div>';
    }
    $cx = $size / 2; $cy = $size / 2; $r = $size * 0.36; $rings = 4;
    $svg = "<svg viewBox='0 0 $size $size' width='100%' height='$size' style='max-width:{$size}px'>";

    for ($g = 1; $g <= $rings; $g++) {
        $rr = $r * $g / $rings;
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $ang = -M_PI / 2 + $i * (2 * M_PI / $n);
            $pts[] = round($cx + $rr * cos($ang), 1) . ',' . round($cy + $rr * sin($ang), 1);
        }
        $svg .= "<polygon points='" . implode(' ', $pts) . "' fill='none' stroke='#E2E8F0' stroke-width='1'/>";
    }
    for ($i = 0; $i < $n; $i++) {
        $ang = -M_PI / 2 + $i * (2 * M_PI / $n);
        $x = $cx + $r * cos($ang); $y = $cy + $r * sin($ang);
        $svg .= "<line x1='$cx' y1='$cy' x2='" . round($x, 1) . "' y2='" . round($y, 1) . "' stroke='#E2E8F0' stroke-width='1'/>";
        $lx = $cx + ($r + 26) * cos($ang); $ly = $cy + ($r + 14) * sin($ang);
        $anchor = abs(cos($ang)) < 0.3 ? 'middle' : (cos($ang) > 0 ? 'start' : 'end');
        $svg .= "<text x='" . round($lx, 1) . "' y='" . round($ly, 1) . "' font-size='10' fill='#475467' text-anchor='$anchor' font-family='Arial,sans-serif'>" . e($labels[$i]) . "</text>";
    }

    $poly = function ($vals) use ($n, $cx, $cy, $r, $max) {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $ang = -M_PI / 2 + $i * (2 * M_PI / $n);
            $rr = $r * min($vals[$i], $max) / $max;
            $pts[] = round($cx + $rr * cos($ang), 1) . ',' . round($cy + $rr * sin($ang), 1);
        }
        return implode(' ', $pts);
    };

    $svg .= "<polygon points='" . $poly($target_vals) . "' fill='none' stroke='#94A3B8' stroke-width='1.6' stroke-dasharray='4 3'/>";
    $svg .= "<polygon points='" . $poly($nilai_vals) . "' fill='#0B254522' stroke='#0B2545' stroke-width='2'/>";
    $svg .= "</svg>";
    return $svg;
}

/* Render 1 blok Tingkat Kematangan (tabel + level + radar) */
function renderBlok($judul, $tahun, $target_nilai, $kelompok)
{
    $domain      = $kelompok['domain'];
    $nilai_total = $kelompok['nilai_total'];
    [$level_label, $level_warna] = levelKematangan($nilai_total);
    $is_single = count($domain) === 1;

    $labels = []; $target_vals = []; $nilai_vals = [];
    if ($is_single) {
        $sd = reset($domain);
        foreach ($sd['kategori'] as $k) {
            $labels[] = $k['nama'];
            $target_vals[] = $target_nilai;
            $nilai_vals[] = round($k['nilai'], 2);
        }
        $judul_grafik = 'Grafik Kematangan Per Kategori';
    } else {
        foreach ($domain as $d) {
            $labels[] = $d['nama'];
            $target_vals[] = $target_nilai;
            $nilai_vals[] = round($d['nilai'], 2);
        }
        $judul_grafik = 'Grafik Kematangan Per Domain';
    }

    ob_start(); ?>
    <div class="lap-wrap">
      <div class="lap-tabel">
        <table class="lap">
          <thead>
            <tr>
              <th class="judul" rowspan="2"><?= e($judul) ?></th>
              <th colspan="2"><?= (int)$tahun ?></th>
              <th rowspan="2">Nilai Kematangan<br>per-Domain</th>
              <th rowspan="2">Kategori Tingkat<br>Kematangan per-Domain</th>
            </tr>
            <tr><th>Target</th><th>Nilai</th></tr>
          </thead>
          <tbody>
            <tr class="total-row">
              <td>Total</td>
              <td class="mono"><?= number_format($target_nilai, 2, ',', '.') ?></td>
              <td class="mono nilai-total"><b><?= number_format($nilai_total, 2, ',', '.') ?></b></td>
              <td colspan="2"></td>
            </tr>
            <?php foreach ($domain as $d):
                $jml = count($d['kategori']);
                if ($jml === 0) continue;
                [$lvl_d, $warna_d] = levelKematangan($d['nilai']);
                $first = true;
                foreach ($d['kategori'] as $k):
                    $cc = $k['nilai'] >= $target_nilai ? 'ok' : ($k['nilai'] >= 1.51 ? 'mid' : 'low'); ?>
              <tr>
                <td class="strip" style="border-left:6px solid <?= e($d['warna']) ?>"><?= e($k['nama']) ?></td>
                <td class="mono"><?= number_format($target_nilai, 2, ',', '.') ?></td>
                <td class="mono cell-<?= $cc ?>"><?= number_format($k['nilai'], 2, ',', '.') ?></td>
                <?php if ($first): ?>
                  <td class="mono cell-<?= $d['nilai'] >= $target_nilai ? 'ok' : 'low' ?>" rowspan="<?= $jml ?>"><?= number_format($d['nilai'], 2, ',', '.') ?></td>
                  <td rowspan="<?= $jml ?>"><span class="lvl-chip" style="background:<?= e($warna_d) ?>"><?= e($lvl_d) ?></span></td>
                <?php endif; ?>
              </tr>
            <?php $first = false; endforeach; endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="lap-level">
        <div class="lap-level-lbl">Kategori Tingkat Kematangan<br><?= e($judul) ?></div>
        <div class="lap-level-badge" style="color:<?= e($level_warna) ?>"><?= e($level_label) ?></div>
      </div>

      <div class="lap-radar">
        <div class="card-title"><?= e($judul_grafik) ?></div>
        <?= radarChart($labels, $target_vals, $nilai_vals) ?>
        <div class="legend"><span class="dot" style="background:#94A3B8"></span>Target &nbsp;&nbsp;<span class="dot" style="background:#0B2545"></span>Nilai</div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA DETAIL (per domain / semua domain)
|--------------------------------------------------------------------------
*/
$domainData = [];

if ($isAll) {
    foreach ($kategori as $key => $item) {
        $rows = ambilDataDomain($koneksi, $id_users, $judul_topik, $item['topik_id']);
        [$total, $terisi, $rata] = hitungRingkasan($rows);
        $domainData[$key] = [
            'judul' => $item['judul'], 'kode' => $item['kode'], 'icon' => $item['icon'],
            'rows' => $rows, 'total' => $total, 'terisi' => $terisi, 'rata' => $rata,
        ];
    }
} elseif (!$viewAlat) {
    $item = $kategori[$view];
    $rows = ambilDataDomain($koneksi, $id_users, $judul_topik, $item['topik_id']);
    [$total, $terisi, $rata] = hitungRingkasan($rows);
    $domainData[$view] = [
        'judul' => $item['judul'], 'kode' => $item['kode'], 'icon' => $item['icon'],
        'rows' => $rows, 'total' => $total, 'terisi' => $terisi, 'rata' => $rata,
    ];
}

/*
|--------------------------------------------------------------------------
| DATA TINGKAT KEMATANGAN (langsung tampil, tanpa pindah halaman)
|--------------------------------------------------------------------------
*/
// Prioritaskan parameter URL bila diberikan; jika tidak, gunakan data responden.
$tahun = isset($_GET['tahun']) && (int)$_GET['tahun'] > 0
    ? (int)$_GET['tahun']
    : ((int)$tahun_pengukuran > 0 ? (int)$tahun_pengukuran : (int)date('Y', strtotime($tanggal_pengisian ?: 'now')));

$target_nilai = isset($_GET['target']) && $_GET['target'] !== ''
    ? (float)str_replace(',', '.', $_GET['target'])
    : (($target_nilai_responden !== '' && $target_nilai_responden !== null)
        ? (float)$target_nilai_responden
        : 2.51);


$kelompok_kamsiber = hitungKelompok($koneksi, $id_users, $judul_topik, $topik_kamsiber);

$ada_persandian = false;
$qp = mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pertanyaan WHERE topik_id = 5");
if ($qp) {
    $ada_persandian = ((int)mysqli_fetch_assoc($qp)['n']) > 0;
}
$kelompok_persandian = $ada_persandian
    ? hitungKelompok($koneksi, $id_users, $judul_topik, $topik_persandian)
    : null;

$qs_dasar = 'id_users=' . urlencode($id_users) . '&judul_topik=' . urlencode($judul_topik)
          . '&target=' . urlencode(number_format($target_nilai, 2, ',', '')) . '&tahun=' . (int)$tahun;

/* URL mode "Semua Kategori" (tombol "Semua Kategori") */
$url_semua = 'detail_rekap_ikasandi.php?' . $qs_dasar . '&mode=all';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Untuk tombol "Unduh PDF" (jsPDF + AutoTable = PDF teks, cepat) dan "Unduh Excel" -->
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>

    <style>
        body { background: #f4f6f9; font-family: Arial, sans-serif; }
        .main-content { padding: 30px; }
        .page-title { color: #17365d; font-weight: 700; }

        .card { border: none; border-radius: 12px; box-shadow: 0 3px 15px rgba(0,0,0,.05); }

        .category-card {
            display: block; text-decoration: none; border: 1px solid #dee2e6;
            border-radius: 10px; padding: 16px; height: 100%;
            color: #17365d; background: white; transition: .2s;
        }
        .category-card:hover { background: #eef4ff; border-color: #17365d; transform: translateY(-2px); }
        .category-card.active { background: #17365d; color: white; border-color: #17365d; }
        .nav-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .nav-row .category-card {
            flex: 1 1 0;
            min-width: 150px;
            font-size: 13px;
        }
        html { scroll-behavior: smooth; }
        .panel-kem { display: none; }
        .panel-kem.open { display: block; }

        .category-card .icon { font-size: 23px; margin-bottom: 10px; display: block; }

        /* ==== TABEL BERGAYA EXCEL ==== */
        .excel-table { border-collapse: collapse; width: 100%; font-size: 12.5px; }
        .excel-table th, .excel-table td { border: 1px solid #b9c2cf; padding: 6px 8px; vertical-align: top; }
        .excel-table thead th { background: #17365d; color: #fff; font-size: 12px; text-align: center; white-space: nowrap; }
        .excel-domain-title {
            background: #17365d; color: #fff; font-weight: 700; font-size: 15px;
            padding: 10px 14px; border-radius: 6px 6px 0 0;
        }
        .excel-kategori-row td { background: #dbe5f1; color: #17365d; font-weight: 700; font-size: 12.5px; }
        .excel-table td.col-kode { width: 70px; font-weight: 600; color: #17365d; white-space: nowrap; }
        .excel-table td.col-pertanyaan { min-width: 320px; overflow-wrap: anywhere; }
        .excel-table td.col-keterangan { min-width: 200px; overflow-wrap: anywhere; }

        .badge-indeks { display: inline-block; min-width: 34px; padding: 4px 8px; border-radius: 6px; font-weight: 700; text-align: center; }
        .level-note { font-size: 11px; display: block; margin-top: 2px; }

        .legend-table th, .legend-table td { font-size: 12.5px; vertical-align: middle; }
        .legend-table thead th { background: #17365d; color: #fff; }

        .info-label { font-size: 13px; color: #6c757d; }
        .info-value { font-weight: 600; color: #17365d; overflow-wrap: anywhere; }
        .table-responsive { border-radius: 8px; }
        .summary-card h3 { margin-bottom: 0; }

        /* ==== TINGKAT KEMATANGAN ==== */
        .lap-wrap { display: flex; gap: 16px; align-items: stretch; flex-wrap: wrap; }
        .lap-tabel { flex: 2; min-width: 420px; overflow-x: auto; }
        .lap-level { flex: 1; min-width: 200px; border: 1px solid #E4E9F0; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; }
        .lap-level-lbl { font-size: 13px; font-weight: 700; margin-bottom: 10px; }
        .lap-level-badge { font-size: 24px; font-weight: 800; line-height: 1.25; }
        .lap-radar { flex: 1; min-width: 300px; border: 1px solid #E4E9F0; border-radius: 12px; padding: 16px; text-align: center; }
        .card-title { font-size: 13px; font-weight: 700; margin-bottom: 10px; color: #17365d; }
        table.lap { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.lap th { background: #17365d; color: #fff; padding: 8px 6px; font-size: 11px; text-align: center; border: 1px solid #b9c2cf; }
        table.lap th.judul { background: #FFF2CC; color: #7F6000; text-align: left; padding: 10px; font-size: 13px; }
        table.lap td { padding: 7px 8px; border: 1px solid #EEF1F5; }
        table.lap tr.total-row td { background: #111827; color: #fff; font-weight: 700; }
        table.lap tr.total-row td.nilai-total { background: #DC2626; }
        td.mono { font-family: Consolas, monospace; text-align: center; }
        td.cell-ok  { background: #D8F3DC; color: #166534; font-weight: 700; }
        td.cell-mid { background: #FEF9C3; color: #854D0E; font-weight: 700; }
        td.cell-low { background: #FADBD8; color: #991B1B; font-weight: 700; }
        .lvl-chip { display: inline-block; padding: 4px 10px; border-radius: 14px; color: #fff; font-size: 11px; font-weight: 700; }
        .legend { font-size: 11px; color: #475467; margin-top: 6px; }
        .dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 4px; }

        /* ==== HASIL ALAT BANTU KATEGORI SE ==== */
        .abse-item {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 16px;
            background: #fff;
            margin-bottom: 14px;
        }
        .abse-item-head { margin-bottom: 4px; }
        .abse-item-no {
            display: inline-block;
            min-width: 44px;
            background: #dbe5f1;
            color: #17365d;
            font-weight: 800;
            font-size: 12px;
            border-radius: 6px;
            padding: 3px 8px;
            margin-right: 8px;
        }
        .abse-item-judul { font-weight: 700; font-size: 14px; color: #17365d; }
        .abse-opsi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 12px;
        }
        .abse-opsi {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            font-size: 12px;
            background: #f8f9fa;
            line-height: 1.5;
        }
        .abse-opsi.selected {
            border: 2px solid #17365d;
            background: #eef4ff;
        }
        .abse-opsi-title { font-weight: 700; margin-bottom: 4px; color: #17365d; }
        .abse-opsi-skor { font-weight: 400; color: #6c757d; }
        .abse-dok-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #dee2e6;
        }
        .dok-label { font-size: 11px; color: #6c757d; display: block; }
        .dok-value { font-weight: 600; color: #17365d; }

        /* ==== PRINT ==== */
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .main-content { padding: 0; }
            .card { box-shadow: none; border-radius: 0; }
            .domain-section { page-break-before: always; }
            .domain-section:first-child { page-break-before: auto; }
            .excel-table tr, table.lap tr { page-break-inside: avoid; }
            .lap-wrap { page-break-inside: avoid; }
            .abse-item { page-break-inside: avoid; }
            @page { size: A4 landscape; margin: 12mm; }
        }

        @media (max-width: 800px) {
            .abse-opsi-grid { grid-template-columns: 1fr; }
            .abse-dok-row { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

<div class="container-fluid main-content" id="laporan-area">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

        <div>
            <h3 class="page-title">
                <i class="bi bi-clipboard-data me-2"></i>
                Detail Rekap IKASANDI
            </h3>
        </div>

        <div class="d-flex gap-2 flex-wrap no-print">

            <a href="rekap_ikasandi.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            <?php if ($isAll): ?>
                <a href="detail_rekap_ikasandi.php?<?= $qs_dasar ?>&view=<?= urlencode($view) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-collection me-1"></i> Kembali ke Per Kategori
                </a>
            <?php endif; ?>

            <?php if (!$isAll): ?>
                <a href="<?= e($url_semua) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-collection me-1"></i> Semua Kategori
                </a>
            <?php endif; ?>

            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>

            <button id="btn-excel" onclick="unduhExcel(this)" class="btn btn-success">
                <i class="bi bi-file-earmark-excel me-1"></i> Unduh Excel
            </button>

            <button id="btn-pdf" onclick="unduhPDF(this)" class="btn btn-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i> Unduh PDF
            </button>

        </div>
    </div>

    <!-- INFORMASI PENILAIAN (Data Responden ada di dalam panel Hasil Alat Bantu SE) -->
    <div class="card mb-4" id="info-penilaian">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Informasi Penilaian</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="info-label">Judul Penilaian</div>
                    <div class="info-value"><?= e($judul_topik) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="info-label">Nama Pengisi</div>
                    <div class="info-value"><?= e($nama_pengisi ?: '-') ?></div>
                </div>
                <div class="col-md-4">
                    <div class="info-label">Tanggal Pengisian</div>
                    <div class="info-value"><?= e($tanggal_pengisian ?: '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- NAVIGASI KATEGORI -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <h5 class="fw-bold mb-3">
                <i class="bi bi-grid me-2"></i>
                Kategori Penilaian IKASANDI
            </h5>
            <div class="nav-row">
                <a href="detail_rekap_ikasandi.php?<?= $qs_dasar ?>&view=alatbantu"
                   class="category-card <?= $viewAlat ? 'active' : '' ?>">
                    <i class="bi bi-clipboard-check icon"></i>
                    <strong>Hasil Alat Bantu Kategori SE</strong>
                    <div class="small mt-2">
                        Lihat hasil alat bantu <i class="bi bi-arrow-right"></i>
                    </div>
                </a>

                <?php foreach ($kategori as $key => $item): ?>
                    <a href="detail_rekap_ikasandi.php?<?= $qs_dasar ?>&view=<?= urlencode($key) ?>"
                       class="category-card <?= (!$isAll && $view === $key) ? 'active' : '' ?>">
                        <i class="bi <?= e($item['icon']) ?> icon"></i>
                        <strong><?= e($item['judul']) ?></strong>
                        <div class="small mt-2">
                            Lihat detail indikator <i class="bi bi-arrow-right"></i>
                        </div>
                    </a>
                <?php endforeach; ?>

                <a href="#kamsiber" class="category-card kem-card" data-panel="sec-kamsiber" onclick="bukaPanel('sec-kamsiber', event)">
                    <i class="bi bi-bar-chart-line icon"></i>
                    <strong>Tingkat Kematangan Keamanan Siber</strong>
                    <div class="small mt-2">
                        Lihat nilai kematangan <i class="bi bi-arrow-right"></i>
                    </div>
                </a>

                <a href="#persandian" class="category-card kem-card" data-panel="sec-persandian" onclick="bukaPanel('sec-persandian', event)">
                    <i class="bi bi-lock icon"></i>
                    <strong>Tingkat Kematangan Persandian</strong>
                    <div class="small mt-2">
                        Lihat nilai kematangan <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- HASIL ALAT BANTU PENETAPAN KATEGORI SISTEM ELEKTRONIK (SE) -->
    <div class="card mb-4" id="abse-hasil" <?= $tampilAbse ? '' : 'style="display:none"' ?>>
        <div class="card-body">
            <div class="mb-4">
                <h5 class="fw-bold mb-3">Data Responden</h5>

                <div class="table-responsive">
                    <table class="lap respondent-export-table mb-0">
                        <thead>
                            <tr>
                                <th colspan="4" class="judul">Data Responden</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Organisasi / PSE</strong></td>
                                <td><?= e($organisasi_pse ?: '-') ?></td>
                                <td><strong>Nama Responden</strong></td>
                                <td><?= e($nama_responden ?: '-') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Jabatan Responden</strong></td>
                                <td><?= e($jabatan_responden ?: '-') ?></td>
                                <td><strong>Tahun Pengukuran</strong></td>
                                <td><?= e($tahun_pengukuran ?: '-') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Target Level Kematangan</strong></td>
                                <td><?= e($target_level_kematangan ?: '-') ?></td>
                                <td><strong>Target Nilai</strong></td>
                                <td><?= ($target_nilai_responden !== '' && $target_nilai_responden !== null) ? number_format((float)$target_nilai_responden, 2, ',', '.') : '-' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Kategori SE</strong></td>
                                <td><?= e($kategori_se ?: '-') ?></td>
                                <td><strong>Skor Kategori SE</strong></td>
                                <td><?= ($skor_kategori_se !== '' && $skor_kategori_se !== null) ? e($skor_kategori_se) : '-' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <h5 class="fw-bold mb-3">
                <i class="bi bi-clipboard-check me-2"></i>
                Hasil Alat Bantu Penetapan Kategori Sistem Elektronik (SE)
            </h5>

            <?php foreach ($alat_bantu_se as $kode => $item):
                $jawab_terpilih = $jawaban_ab_se_tersimpan[$kode] ?? '';
                $dok        = $dokumen_ab_se_tersimpan[$kode] ?? [];
                $dok_nomor  = $dok['nomor'] ?? '-';
                $dok_nama   = $dok['nama'] ?? '-';
            ?>
                <div class="abse-item">
                    <div class="abse-item-head">
                        <span class="abse-item-no"><?= e($item['no']) ?></span>
                        <span class="abse-item-judul"><?= e($item['pertanyaan']) ?></span>
                    </div>

                    <div class="abse-opsi-grid">
                        <?php foreach ($item['opsi'] as $huruf => $teks): ?>
                            <div class="abse-opsi <?= ($jawab_terpilih === $huruf) ? 'selected' : '' ?>">
                                <div class="abse-opsi-title">
                                    [<?= e($huruf) ?>] <span class="abse-opsi-skor">(skor <?= (int)$skor_opsi_se[$huruf] ?>)</span>
                                </div>
                                <div><?= e($teks) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="abse-dok-row">
                        <div>
                            <span class="dok-label">Nomor Dokumen</span>
                            <div class="dok-value"><?= e($dok_nomor) ?></div>
                        </div>
                        <div>
                            <span class="dok-label">Nama Dokumen</span>
                            <div class="dok-value"><?= e($dok_nama) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PANEL TINGKAT KEMATANGAN (dibuka dari kartu navigasi) -->
    <div id="sec-kamsiber" class="panel-kem <?= $isAll ? 'open' : '' ?> card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-bar-chart-line me-2"></i>
                    Tingkat Kematangan Keamanan Siber
                </h5>
                <button type="button" class="btn btn-sm btn-outline-secondary no-print" onclick="tutupPanel()">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
            </div>
            <?= renderBlok('Tingkat Kematangan Keamanan Siber', $tahun, $target_nilai, $kelompok_kamsiber); ?>
        </div>
    </div>

    <div id="sec-persandian" class="panel-kem <?= $isAll ? 'open' : '' ?> card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-lock me-2"></i>
                    Tingkat Kematangan Persandian
                </h5>
                <button type="button" class="btn btn-sm btn-outline-secondary no-print" onclick="tutupPanel()">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
            </div>
            <?php if ($kelompok_persandian): ?>
                <?= renderBlok('Tingkat Kematangan Persandian', $tahun, $target_nilai, $kelompok_persandian); ?>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Data pertanyaan Persandian (topik_id = 5) belum tersedia.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DETAIL INDIKATOR PER DOMAIN -->
    <div id="detail-indikator">
    <?php foreach ($domainData as $domKey => $dom): ?>

        <div class="domain-section mb-4">

            <div class="excel-domain-title">
                DOMAIN <?= e($dom['kode']) ?>: <?= mb_strtoupper(e($dom['judul'])) ?>
            </div>

            <div class="card" style="border-radius:0 0 12px 12px;">
                <div class="card-body">

                    <!-- RINGKASAN -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card summary-card"><div class="card-body">
                                <div class="text-muted">Total Indikator</div>
                                <h3 class="fw-bold text-primary"><?= (int)$dom['total'] ?></h3>
                            </div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="card summary-card"><div class="card-body">
                                <div class="text-muted">Indikator Terisi</div>
                                <h3 class="fw-bold text-success"><?= (int)$dom['terisi'] ?></h3>
                            </div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="card summary-card"><div class="card-body">
                                <div class="text-muted">Rata-rata Indeks (indikator terisi)</div>
                                <h3 class="fw-bold text-dark"><?= number_format($dom['rata'], 2) ?></h3>
                                <small class="text-muted"><?= e(labelLevel($dom['rata'])) ?></small>
                            </div></div>
                        </div>
                    </div>

                    <?php if (count($dom['rows']) > 0): ?>

                        <div class="table-responsive">
                            <table class="excel-table" id="tabel-<?= e($domKey) ?>">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">No</th>
                                        <th style="width:70px;">Kode</th>
                                        <th style="min-width:320px;">Pertanyaan / Indikator</th>
                                        <th style="width:110px;">Indeks Nilai<br>Kematangan</th>
                                        <th style="min-width:200px;">Keterangan Validasi</th>
                                        <th style="width:110px;">Bukti Dukung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $grup = kelompokkanPerKategori($dom['rows']);
                                    $no = 1;
                                    foreach ($grup as $namaKategori => $baris):
                                    ?>
                                        <tr class="excel-kategori-row">
                                            <td colspan="6"><?= e($namaKategori) ?></td>
                                        </tr>

                                        <?php foreach ($baris as $row): ?>
                                            <tr>
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td class="col-kode"><?= e($row['kode'] ?? '-') ?></td>
                                                <td class="col-pertanyaan"><?= nl2br(e($row['pertanyaan'] ?? '-')) ?></td>

                                                <td class="text-center">
                                                    <?php
                                                    [$bg, $fg] = warnaIndeks($row['indeks_nilai']);
                                                    $terisiRow = ($row['indeks_nilai'] !== null && $row['indeks_nilai'] !== '');
                                                    $tampil = $terisiRow ? e($row['indeks_nilai']) : 'Belum diisi';
                                                    ?>
                                                    <span class="badge-indeks" style="background:<?= $bg ?>; color:<?= $fg ?>;">
                                                        <?= $tampil ?>
                                                    </span>
                                                    <?php if ($terisiRow): ?>
                                                        <span class="level-note text-muted"><?= e(labelLevel($row['indeks_nilai'])) ?></span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="col-keterangan"><?= nl2br(e($row['keterangan_validasi'] ?? '-')) ?></td>

                                                <td class="text-center">
                                                    <?php $bukti = $row['bukti_dukung'] ?? ''; ?>
                                                    <?php if ($bukti !== ''): ?>
                                                        <?php $daftarB = daftarBukti($bukti); foreach ($daftarB as $ib => $fb): ?>
                                                            <div><a href="<?= e(linkBukti($fb, (int)$row['id'], (int)$id_users, $ib)) ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                                                <i class="bi bi-paperclip"></i> Lihat<?= count($daftarB) > 1 ? ' ' . ($ib + 1) : '' ?>
                                                            </a></div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Tidak ada</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: ?>

                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Belum terdapat data pengisian untuk domain
                            <strong><?= e($dom['judul']) ?></strong> pada penilaian ini.
                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </div>

    <?php endforeach; ?>
    </div>

</div>

<script>
/* Hanya satu tampilan sekaligus: detail kategori ATAU satu panel Tingkat Kematangan */
const MODE_SEMUA = <?= $isAll ? 'true' : 'false' ?>;
const ABSE_TAMPIL = <?= $tampilAbse ? 'true' : 'false' ?>;
function bukaPanel(id, ev) {
    if (ev) ev.preventDefault();
    const panel = document.getElementById(id);

    if (MODE_SEMUA) {            /* mode "Semua Kategori": semua tampil, cukup gulir */
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
    }

    const sudahTerbuka = panel.classList.contains('open');
    tutupPanel();
    if (sudahTerbuka) return;

    panel.classList.add('open');
    document.getElementById('detail-indikator').style.display = 'none';
    const abse = document.getElementById('abse-hasil');
    if (abse) abse.style.display = 'none';
    document.querySelectorAll('.category-card.active').forEach(el => {
        el.dataset.semula = '1';
        el.classList.remove('active');
    });
    document.querySelector('.kem-card[data-panel="' + id + '"]').classList.add('active');
    history.replaceState(null, '', '#' + id.replace('sec-', ''));
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function tutupPanel() {
    if (MODE_SEMUA) return;
    document.querySelectorAll('.panel-kem').forEach(el => el.classList.remove('open'));
    document.querySelectorAll('.kem-card').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.category-card[data-semula]').forEach(el => {
        el.classList.add('active');
        delete el.dataset.semula;
    });
    document.getElementById('detail-indikator').style.display = '';
    const abse = document.getElementById('abse-hasil');
    if (abse) abse.style.display = ABSE_TAMPIL ? '' : 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    const h = location.hash.replace('#', '');
    if (!MODE_SEMUA && (h === 'kamsiber' || h === 'persandian')) bukaPanel('sec-' + h);
});

const JUDUL_LAPORAN = <?= json_encode($judul_topik, JSON_UNESCAPED_UNICODE) ?>;
const NAMA_FILE     = <?= json_encode(preg_replace('/[^A-Za-z0-9_-]/', '_', $judul_topik)) ?>;

/* =====================================================================
 * ALAT BANTU UMUM UNTUK UNDUH (dipakai Excel & PDF)
 * ===================================================================== */
let sedangProses = false;

/* Tombol berubah jadi "Memproses..." dan terkunci agar tidak terklik dua kali. */
function mulaiProses(btn, label) {
    sedangProses = true;
    let asal = null;
    if (btn) {
        asal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + label;
    }
    document.body.style.cursor = 'progress';
    return function selesai() {
        sedangProses = false;
        document.body.style.cursor = '';
        if (btn) { btn.disabled = false; btn.innerHTML = asal; }
    };
}

/* beri browser kesempatan menggambar tombol "Memproses..." sebelum kerja berat */
const tungguGambar = () => new Promise(r => requestAnimationFrame(() => setTimeout(r, 0)));

const teks = el => {
    const c = el.cloneNode(true);
    c.querySelectorAll('br').forEach(b => b.replaceWith('\n'));
    return c.textContent.replace(/[ \t]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim();
};

const rgbArr = css => {
    const m = (css || '').match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?/);
    if (!m) return null;
    if (m[4] !== undefined && parseFloat(m[4]) === 0) return null;
    return [+m[1], +m[2], +m[3]];
};
const argb = css => {
    const c = rgbArr(css);
    return c ? 'FF' + c.map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase() : null;
};

const namaSheet = s => s.replace(/[\\\/\?\*\[\]:]/g, '').slice(0, 31);

/* Grafik radar (SVG) -> PNG.
 * opsi.pad  : ruang tambahan kiri/kanan agar label tidak terpotong
 * opsi.wrap : label panjang dipecah menjadi beberapa baris
 * Tanpa opsi, perilakunya sama seperti sebelumnya (dipakai Excel). */
function svgKePng(svg, skala, opsi) {
    skala = skala || 2;
    opsi = opsi || {};
    const pad = opsi.pad || 0;
    return new Promise((resolve, reject) => {
        const vb = svg.viewBox && svg.viewBox.baseVal;
        const w0 = vb && vb.width ? vb.width : 300;
        const h0 = vb && vb.height ? vb.height : 300;
        const w = w0 + 2 * pad;
        const h = pad ? h0 + 20 : h0;

        const klon = svg.cloneNode(true);
        klon.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        klon.setAttribute('width', w);
        klon.setAttribute('height', h);
        klon.removeAttribute('style');
        if (pad) klon.setAttribute('viewBox', (-pad) + ' -10 ' + w + ' ' + h);

        if (opsi.wrap) {
            klon.querySelectorAll('text').forEach(t => {
                const kata = t.textContent.split(' ');
                const baris = [];
                let cur = '';
                kata.forEach(k => {
                    if (cur && (cur + ' ' + k).length > 18) { baris.push(cur); cur = k; }
                    else cur = (cur + ' ' + k).trim();
                });
                if (cur) baris.push(cur);
                if (baris.length < 2) return;
                const x = t.getAttribute('x');
                t.textContent = '';
                baris.forEach((ln, i) => {
                    const s = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
                    s.setAttribute('x', x);
                    s.setAttribute('dy', i === 0 ? (-(baris.length - 1) * 0.55) + 'em' : '1.1em');
                    s.textContent = ln;
                    t.appendChild(s);
                });
            });
        }

        const xml = new XMLSerializer().serializeToString(klon);
        const img = new Image();
        img.onload = () => {
            const cv = document.createElement('canvas');
            cv.width = w * skala; cv.height = h * skala;
            const g = cv.getContext('2d');
            g.fillStyle = '#ffffff';
            g.fillRect(0, 0, cv.width, cv.height);
            g.drawImage(img, 0, 0, cv.width, cv.height);
            resolve({ data: cv.toDataURL('image/png'), w: w, h: h });
        };
        img.onerror = reject;
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
    });
}

/* Data Alat Bantu SE dibaca sekali dari halaman */
function ambilAlatBantu() {
    return Array.from(document.querySelectorAll('#abse-hasil .abse-item')).map(it => {
        const sel = it.querySelector('.abse-opsi.selected');
        let jawab = 'Belum dipilih', skor = '';
        if (sel) {
            const t = teks(sel.querySelector('.abse-opsi-title'));
            const huruf = (t.match(/\[(\w)\]/) || [])[1] || '';
            skor = parseInt((t.match(/skor (\d+)/) || [])[1]) || '';
            jawab = '[' + huruf + '] ' + teks(sel.lastElementChild);
        }
        const dok = it.querySelectorAll('.dok-value');
        return {
            no: teks(it.querySelector('.abse-item-no')),
            judul: teks(it.querySelector('.abse-item-judul')),
            jawab: jawab, skor: skor, terpilih: !!sel,
            nomor: dok[0] ? teks(dok[0]) : '-',
            nama: dok[1] ? teks(dok[1]) : '-'
        };
    });
}

function ringkasanDomain(sec) {
    return Array.from(sec.querySelectorAll('.summary-card')).map(c =>
        teks(c.querySelector('.text-muted')) + ': ' + teks(c.querySelector('h3'))).join('     |     ');
}

/* =====================================================================
 * UNDUH EXCEL (.xlsx) — banyak sheet, warna, merge cell, lebar kolom,
 * grafik radar sebagai gambar, siap cetak landscape.
 * ===================================================================== */
async function unduhExcel(btn) {
    if (sedangProses) return;
    const selesai = mulaiProses(btn, 'Membuat Excel...');
    try {
        await tungguGambar();

        const wb = new ExcelJS.Workbook();
        wb.creator = 'IKASANDI';

        const B = { style: 'thin', color: { argb: 'FFB9C2CF' } };
        const border = { top: B, left: B, bottom: B, right: B };

        const setup = (ws, widths, freezeRow) => {
            widths.forEach((w, i) => ws.getColumn(i + 1).width = w);
            ws.pageSetup = { orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0,
                             margins: { left: .4, right: .4, top: .5, bottom: .5, header: .2, footer: .2 } };
            ws.views = [{ showGridLines: false, state: freezeRow ? 'frozen' : 'normal', ySplit: freezeRow || 0 }];
        };

        const judulBar = (ws, row, text, ncol) => {
            ws.mergeCells(row, 1, row, ncol);
            const c = ws.getCell(row, 1);
            c.value = text;
            c.font = { bold: true, size: 13, color: { argb: 'FFFFFFFF' }, name: 'Arial' };
            c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF17365D' } };
            c.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
            ws.getRow(row).height = 26;
        };

        /* Salin tabel HTML ke worksheet (rowspan/colspan, warna, rata teks, link) */
        const tulisTabel = (ws, startRow, table) => {
            const occ = {};
            Array.from(table.rows).forEach((tr, ri) => {
                const R = startRow + ri;
                let c = 1;
                Array.from(tr.cells).forEach(td => {
                    while (occ[R + ',' + c]) c++;
                    const rs = td.rowSpan || 1, cs = td.colSpan || 1;
                    for (let i = 0; i < rs; i++) for (let j = 0; j < cs; j++) occ[(R + i) + ',' + (c + j)] = 1;

                    const src = td.querySelector('.lvl-chip, .badge-indeks') || td;
                    const txt = teks(src);
                    const link = td.querySelector('a[href]');

                    let val = txt, numFmt = null;
                    if (/^\d+([.,]\d+)?$/.test(txt) && !td.classList.contains('col-kode')) {
                        val = parseFloat(txt.replace(',', '.'));
                        if (/[.,]/.test(txt)) numFmt = '0.00';
                    }
                    const cell = ws.getCell(R, c);
                    cell.value = link ? { text: txt, hyperlink: new URL(link.getAttribute('href'), location.href).href } : val;
                    if (numFmt) cell.numFmt = numFmt;

                    const st = getComputedStyle(src);
                    const bg = argb(st.backgroundColor) || (src === td ? null : argb(getComputedStyle(td).backgroundColor));
                    const fg = link ? 'FF0563C1' : (argb(st.color) || 'FF000000');
                    cell.font = { name: 'Arial', size: 10, bold: parseInt(st.fontWeight) >= 600, color: { argb: fg } };
                    if (bg) cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } };
                    const ta = getComputedStyle(td).textAlign;
                    cell.alignment = {
                        vertical: 'middle', wrapText: true,
                        horizontal: (ta === 'center' || td.tagName === 'TH') ? 'center' : 'left'
                    };

                    for (let i = 0; i < rs; i++) for (let j = 0; j < cs; j++) ws.getCell(R + i, c + j).border = border;
                    if (rs > 1 || cs > 1) ws.mergeCells(R, c, R + rs - 1, c + cs - 1);
                    c += cs;
                });
            });
            return startRow + table.rows.length;
        };

        /* ---------- Sheet 1: Data Responden ---------- */
        const wsR = wb.addWorksheet('Data Responden');
        setup(wsR, [28, 42, 28, 42]);
        judulBar(wsR, 1, 'Detail Rekap IKASANDI', 4);
        let r = 3;
        document.querySelectorAll('#info-penilaian .info-label').forEach(lbl => {
            const val = lbl.nextElementSibling;
            wsR.getCell(r, 1).value = teks(lbl);
            wsR.getCell(r, 1).font = { bold: true, name: 'Arial', size: 10 };
            wsR.mergeCells(r, 2, r, 4);
            wsR.getCell(r, 2).value = val ? teks(val) : '-';
            wsR.getCell(r, 2).font = { name: 'Arial', size: 10 };
            wsR.getCell(r, 2).alignment = { horizontal: 'left' };
            r++;
        });
        const tblResp = document.querySelector('.respondent-export-table');
        if (tblResp) tulisTabel(wsR, r + 1, tblResp);

        /* ---------- Sheet 2: Alat Bantu SE ---------- */
        const dataAB = ambilAlatBantu();
        if (dataAB.length) {
            const ws = wb.addWorksheet('Alat Bantu SE');
            setup(ws, [8, 70, 62, 8, 24, 40], 3);
            judulBar(ws, 1, 'Hasil Alat Bantu Penetapan Kategori Sistem Elektronik (SE)', 6);
            ['No', 'Pertanyaan', 'Jawaban Terpilih', 'Skor', 'Nomor Dokumen', 'Nama Dokumen'].forEach((h, i) => {
                const c = ws.getCell(3, i + 1);
                c.value = h; c.border = border;
                c.font = { bold: true, color: { argb: 'FFFFFFFF' }, name: 'Arial', size: 10 };
                c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF17365D' } };
                c.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
            });
            let row = 4;
            dataAB.forEach(d => {
                [d.no, d.judul, d.jawab, d.skor, d.nomor, d.nama].forEach((v, i) => {
                    const c = ws.getCell(row, i + 1);
                    c.value = v; c.border = border;
                    c.font = { name: 'Arial', size: 10, bold: i === 0 };
                    c.alignment = { vertical: 'middle', wrapText: true, horizontal: (i === 0 || i === 3) ? 'center' : 'left' };
                    if (i === 2 && d.terpilih) c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEEF4FF' } };
                });
                row++;
            });
        }

        /* ---------- Sheet Tingkat Kematangan (+ grafik radar) ---------- */
        for (const [id, nama] of [['sec-kamsiber', 'Kematangan Keamanan Siber'], ['sec-persandian', 'Kematangan Persandian']]) {
            const panel = document.getElementById(id);
            const tbl = panel && panel.querySelector('table.lap');
            if (!tbl) continue;

            const ws = wb.addWorksheet(namaSheet(nama));
            setup(ws, [46, 12, 12, 22, 30, 3]);
            judulBar(ws, 1, teks(panel.querySelector('h5')), 5);
            const akhir = tulisTabel(ws, 3, tbl);

            const lvlLbl = panel.querySelector('.lap-level-lbl');
            const lvlBadge = panel.querySelector('.lap-level-badge');
            if (lvlBadge) {
                ws.getCell(akhir + 1, 1).value = teks(lvlLbl).replace(/\n/g, ' ');
                ws.getCell(akhir + 1, 1).font = { bold: true, name: 'Arial', size: 10 };
                ws.mergeCells(akhir + 1, 2, akhir + 1, 5);
                const c = ws.getCell(akhir + 1, 2);
                c.value = teks(lvlBadge);
                c.font = { bold: true, size: 14, name: 'Arial', color: { argb: argb(getComputedStyle(lvlBadge).color) || 'FF000000' } };
                c.alignment = { horizontal: 'left', vertical: 'middle' };
                ws.getRow(akhir + 1).height = 24;
            }

            const svg = panel.querySelector('.lap-radar svg');
            if (svg) {
                const p = await svgKePng(svg, 2);
                const imgId = wb.addImage({ base64: p.data, extension: 'png' });
                ws.addImage(imgId, { tl: { col: 6, row: 2 }, ext: { width: p.w, height: p.h } });
            }
        }

        /* ---------- Sheet per Domain (detail indikator) ---------- */
        document.querySelectorAll('.domain-section').forEach(sec => {
            const tbl = sec.querySelector('.excel-table');
            if (!tbl) return;
            const judul = teks(sec.querySelector('.excel-domain-title'));
            const ws = wb.addWorksheet(namaSheet(judul.split(':')[0]));
            setup(ws, [6, 10, 62, 16, 46, 16], 5);
            judulBar(ws, 1, judul, 6);

            ws.mergeCells(3, 1, 3, 6);
            ws.getCell(3, 1).value = ringkasanDomain(sec);
            ws.getCell(3, 1).font = { bold: true, name: 'Arial', size: 10 };

            tulisTabel(ws, 5, tbl);
        });

        const buf = await wb.xlsx.writeBuffer();
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
        link.download = 'Rekap_IKASANDI_' + NAMA_FILE + '.xlsx';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(link.href), 10000);
    } catch (err) {
        console.error(err);
        alert('Gagal membuat file Excel. Silakan coba lagi.');
    } finally {
        selesai();
    }
}

/* =====================================================================
 * UNDUH PDF (versi dirapikan)
 * ===================================================================== */
async function unduhPDF(btn) {
    if (sedangProses) return;
    const selesai = mulaiProses(btn, 'Membuat PDF...');
    try {
        await tungguGambar();

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

        const PW = 297, PH = 210;
        const M = 12;          // margin kiri/kanan
        const TOP = 20;        // ruang header di setiap halaman
        const BAWAH = 16;      // ruang footer
        const LEBAR = PW - 2 * M;
        const BIRU = [23, 54, 93];
        const ABU = [108, 117, 125];
        let y = TOP;

        /* Hilangkan karakter yang tidak didukung font bawaan PDF */
        const bersih = s => String(s == null ? '' : s)
            .replace(/[\u2018\u2019]/g, "'")
            .replace(/[\u201C\u201D]/g, '"')
            .replace(/[\u2013\u2014]/g, '-')
            .replace(/\u2026/g, '...')
            .replace(/[\u00A0\u200B\u200C\u200D\uFEFF]/g, ' ')
            .replace(/[^\x09\x0A\x20-\x7E\u00A1-\u00FF]/g, '');
        const tx = el => bersih(teks(el));

        const halamanBaru = () => { doc.addPage(); y = TOP; };
        const perluRuang = h => { if (y + h > PH - BAWAH) halamanBaru(); };

        /* Bar judul bagian */
        const bar = text => {
            perluRuang(16);
            doc.setFillColor(BIRU[0], BIRU[1], BIRU[2]);
            doc.rect(M, y, LEBAR, 9, 'F');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.setTextColor(255, 255, 255);
            doc.text(bersih(text), M + 3, y + 6);
            doc.setTextColor(0, 0, 0);
            y += 13;
        };

        /* Deretan kartu kecil: {l: label, v: nilai, s: catatan kanan (opsional)} */
        const kartu = items => {
            if (!items.length) return;
            const gap = 4, n = items.length;
            const w = (LEBAR - gap * (n - 1)) / n;
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            const baris = items.map(it => doc.splitTextToSize(bersih(it.v || '-'), w - 6).slice(0, 3));
            const h = 10 + Math.max.apply(null, baris.map(b => b.length)) * 4.6;
            perluRuang(h + 2);
            items.forEach((it, i) => {
                const x = M + i * (w + gap);
                doc.setFillColor(244, 246, 249);
                doc.setDrawColor(222, 226, 230);
                doc.setLineWidth(0.2);
                doc.roundedRect(x, y, w, h, 1.5, 1.5, 'FD');
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7.5);
                doc.setTextColor(ABU[0], ABU[1], ABU[2]);
                doc.text(bersih(it.l), x + 3, y + 4.5);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(10);
                doc.setTextColor(BIRU[0], BIRU[1], BIRU[2]);
                doc.text(baris[i], x + 3, y + 10);
                if (it.s) {
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(7.5);
                    doc.setTextColor(ABU[0], ABU[1], ABU[2]);
                    doc.text(bersih(it.s), x + w - 3, y + 4.5, { align: 'right' });
                }
            });
            doc.setTextColor(0, 0, 0);
            y += h + 4;
        };

        /* Gaya sel: teks bersih, font seragam, warna badge/chip */
        const gayaSel = data => {
            const td = data.cell.raw;
            if (!td || !td.tagName) return;
            const c = data.cell;
            c.styles.font = 'helvetica';
            c.styles.fontSize = 7.5;
            c.styles.cellPadding = { top: 1.8, bottom: 1.8, left: 2, right: 2 };
            c.text = tx(td).split('\n');
            if (data.section !== 'body') return;

            const chip = td.querySelector('.badge-indeks, .lvl-chip');
            if (chip) {
                const cs = getComputedStyle(chip);
                const bg = rgbArr(cs.backgroundColor), fg = rgbArr(cs.color);
                if (bg) c.styles.fillColor = bg;
                if (fg) c.styles.textColor = fg;
                c.styles.fontStyle = 'bold';
            }
            if (td.querySelector('a[href]')) c.styles.textColor = [5, 99, 193];
            if (td.querySelector('strong') || td.classList.contains('col-kode')) c.styles.fontStyle = 'bold';
            if (td.closest('.respondent-export-table') && td.cellIndex % 2 === 0) {
                c.styles.fillColor = [244, 246, 249];
            }
        };

        /* Tautan "Lihat" bukti dukung agar bisa diklik */
        const tautan = data => {
            if (data.section !== 'body') return;
            const a = data.cell.raw && data.cell.raw.querySelector && data.cell.raw.querySelector('a[href]');
            if (a) {
                doc.link(data.cell.x, data.cell.y, data.cell.width, data.cell.height,
                         { url: new URL(a.getAttribute('href'), location.href).href });
            }
        };

        const tabel = (el, tambahan) => {
            doc.autoTable(Object.assign({
                html: el,
                startY: y,
                useCSS: true,
                theme: 'grid',
                margin: { top: TOP, left: M, right: M, bottom: BAWAH },
                styles: { overflow: 'linebreak', valign: 'middle', lineColor: [185, 194, 207], lineWidth: 0.1 },
                showHead: 'everyPage',
                rowPageBreak: 'avoid',
                didParseCell: gayaSel,
                didDrawCell: tautan
            }, tambahan || {}));
            y = doc.lastAutoTable.finalY + 4;
        };

        /* ---------- 1. Informasi Penilaian ---------- */
        bar('Detail Rekap IKASANDI');
        kartu(Array.from(document.querySelectorAll('#info-penilaian .info-label')).map(l => ({
            l: tx(l),
            v: tx(l.nextElementSibling)
        })));

        /* ---------- 2. Data Responden + Alat Bantu SE ---------- */
        const abse = document.getElementById('abse-hasil');
        if (abse && abse.offsetParent !== null) {
            const tblResp = document.querySelector('.respondent-export-table');
            if (tblResp) {
                bar('Data Responden');
                tabel(tblResp, {
                    showHead: 'never',
                    columnStyles: { 0: { cellWidth: 50 }, 1: { cellWidth: 'auto' }, 2: { cellWidth: 50 }, 3: { cellWidth: 'auto' } }
                });
            }

            const dataAB = ambilAlatBantu();
            if (dataAB.length) {
                perluRuang(40);
                bar('Hasil Alat Bantu Penetapan Kategori Sistem Elektronik (SE)');
                doc.autoTable({
                    startY: y,
                    head: [['No', 'Pertanyaan', 'Jawaban Terpilih', 'Skor', 'Nomor Dokumen', 'Nama Dokumen']],
                    body: dataAB.map(d => [d.no, bersih(d.judul), bersih(d.jawab), d.skor, bersih(d.nomor), bersih(d.nama)]),
                    theme: 'grid',
                    margin: { top: TOP, left: M, right: M, bottom: BAWAH },
                    showHead: 'everyPage',
                    rowPageBreak: 'avoid',
                    styles: { font: 'helvetica', fontSize: 7.5, cellPadding: 2, overflow: 'linebreak', valign: 'middle',
                              lineColor: [185, 194, 207], lineWidth: 0.1 },
                    headStyles: { fillColor: BIRU, textColor: [255, 255, 255], halign: 'center', fontStyle: 'bold' },
                    alternateRowStyles: { fillColor: [250, 251, 253] },
                    columnStyles: {
                        0: { cellWidth: 12, halign: 'center', fontStyle: 'bold' },
                        1: { cellWidth: 92 },
                        2: { cellWidth: 82 },
                        3: { cellWidth: 12, halign: 'center' },
                        4: { cellWidth: 34 },
                        5: { cellWidth: 41 }
                    },
                    didParseCell: data => {
                        if (data.section === 'body' && data.column.index === 2 && dataAB[data.row.index].terpilih) {
                            data.cell.styles.fillColor = [238, 244, 255];
                            data.cell.styles.fontStyle = 'bold';
                        }
                    }
                });
                y = doc.lastAutoTable.finalY + 4;
            }
        }

        /* ---------- 3. Tingkat Kematangan (tabel kiri, level + radar kanan) ---------- */
        const LEBAR_TABEL = 150, GAP = 11;
        const LEBAR_KANAN = LEBAR - LEBAR_TABEL - GAP;   // 112 mm

        for (const panel of document.querySelectorAll('.panel-kem.open')) {
            const tbl = panel.querySelector('table.lap');
            if (!tbl) continue;

            if (y > TOP) halamanBaru();
            bar(tx(panel.querySelector('h5')));

            const halamanMulai = doc.getNumberOfPages();
            const xK = M + LEBAR_TABEL + GAP;
            let yK = y;

            /* Kartu level */
            const lbl = panel.querySelector('.lap-level-lbl');
            const badge = panel.querySelector('.lap-level-badge');
            if (badge) {
                doc.setDrawColor(228, 233, 240);
                doc.setLineWidth(0.3);
                doc.roundedRect(xK, yK, LEBAR_KANAN, 26, 2, 2, 'S');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8.5);
                doc.setTextColor(BIRU[0], BIRU[1], BIRU[2]);
                doc.text(tx(lbl).split('\n'), xK + LEBAR_KANAN / 2, yK + 7, { align: 'center' });
                const w = rgbArr(getComputedStyle(badge).color) || [0, 0, 0];
                doc.setFontSize(15);
                doc.setTextColor(w[0], w[1], w[2]);
                doc.text(tx(badge), xK + LEBAR_KANAN / 2, yK + 21, { align: 'center' });
                doc.setTextColor(0, 0, 0);
                yK += 30;
            }

            /* Grafik radar */
            const svg = panel.querySelector('.lap-radar svg');
            if (svg) {
                const judulGrafik = panel.querySelector('.lap-radar .card-title');
                const p = await svgKePng(svg, 3, { pad: 90, wrap: true });
                const tinggi = LEBAR_KANAN * p.h / p.w;
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(9);
                doc.setTextColor(BIRU[0], BIRU[1], BIRU[2]);
                if (judulGrafik) doc.text(tx(judulGrafik), xK + LEBAR_KANAN / 2, yK + 3, { align: 'center' });
                doc.addImage(p.data, 'PNG', xK, yK + 5, LEBAR_KANAN, tinggi);
                yK += 5 + tinggi + 3;

                /* legenda */
                const cx = xK + LEBAR_KANAN / 2;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.setTextColor(71, 84, 103);
                doc.setFillColor(148, 163, 184); doc.circle(cx - 16, yK, 1.2, 'F');
                doc.text('Target', cx - 13.5, yK + 1);
                doc.setFillColor(11, 37, 69);    doc.circle(cx + 4, yK, 1.2, 'F');
                doc.text('Nilai', cx + 6.5, yK + 1);
                doc.setTextColor(0, 0, 0);
                yK += 6;
            }

            /* Tabel di kiri */
            tabel(tbl, {
                margin: { top: TOP, left: M, right: PW - M - LEBAR_TABEL, bottom: BAWAH },
                columnStyles: {
                    0: { cellWidth: 56 },
                    1: { cellWidth: 16, halign: 'center' },
                    2: { cellWidth: 16, halign: 'center' },
                    3: { cellWidth: 28, halign: 'center' },
                    4: { cellWidth: 34, halign: 'center' }
                }
            });
            if (doc.getNumberOfPages() === halamanMulai) y = Math.max(y, yK);
        }

        /* ---------- 4. Detail Indikator per Domain ---------- */
        for (const sec of document.querySelectorAll('.domain-section')) {
            if (sec.offsetParent === null) continue;
            if (y > TOP) halamanBaru();
            bar(tx(sec.querySelector('.excel-domain-title')));

            kartu(Array.from(sec.querySelectorAll('.summary-card')).map(c => ({
                l: tx(c.querySelector('.text-muted')),
                v: tx(c.querySelector('h3')),
                s: c.querySelector('small') ? tx(c.querySelector('small')) : ''
            })));

            const tbl = sec.querySelector('.excel-table');
            if (tbl) {
                tabel(tbl, {
                    columnStyles: {
                        0: { cellWidth: 10, halign: 'center' },
                        1: { cellWidth: 16 },
                        3: { cellWidth: 28, halign: 'center' },
                        4: { cellWidth: 62 },
                        5: { cellWidth: 22, halign: 'center' }
                    }
                });
            } else {
                const al = sec.querySelector('.alert');
                doc.setFont('helvetica', 'italic');
                doc.setFontSize(9);
                doc.text(bersih(al ? teks(al) : 'Belum terdapat data pengisian.'), M, y + 4);
                y += 10;
            }
        }

        /* ---------- 5. Header + footer di setiap halaman ---------- */
        const n = doc.getNumberOfPages();
        const tglCetak = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
        for (let p = 1; p <= n; p++) {
            doc.setPage(p);

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(9);
            doc.setTextColor(BIRU[0], BIRU[1], BIRU[2]);
            doc.text('IKASANDI - Detail Rekap', M, 9);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(ABU[0], ABU[1], ABU[2]);
            doc.text(bersih(JUDUL_LAPORAN), PW - M, 9, { align: 'right' });
            doc.setDrawColor(BIRU[0], BIRU[1], BIRU[2]);
            doc.setLineWidth(0.4);
            doc.line(M, 12, PW - M, 12);

            doc.setDrawColor(200);
            doc.setLineWidth(0.2);
            doc.line(M, PH - 11, PW - M, PH - 11);
            doc.setFontSize(8);
            doc.setTextColor(120);
            doc.text('Dicetak: ' + tglCetak, M, PH - 7);
            doc.text('Halaman ' + p + ' dari ' + n, PW - M, PH - 7, { align: 'right' });
        }

        doc.save('Rekap_IKASANDI_' + NAMA_FILE + '.pdf');
    } catch (err) {
        console.error(err);
        alert('Gagal membuat PDF. Silakan gunakan tombol Cetak sebagai alternatif.');
    } finally {
        selesai();
    }
}
</script>

</body>
</html>