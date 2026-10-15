<?php
session_start();

/* ==========================================================================
   KONEKSI DATABASE
   ========================================================================== */
require_once 'koneksi.php';


/* ==========================================================================
   USER (tidak diarahkan ke login; jika session belum ada, pakai data sementara)
   ========================================================================== */
$id_users = (int) ($_SESSION['id_users'] ?? $_SESSION['user_id'] ?? 1);
$nama_pengisi = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Nama Pengisi';
$role = $_SESSION['role'] ?? 'pegawai';
$is_admin = strtolower($role) === 'admin';

/* Pengguna yang datanya sedang dilihat/diubah.
   Pegawai selalu dirinya sendiri; admin boleh mengubah data pengguna lain
   (dari tombol Ubah di Rekap, lewat ?id_users=...). */
$id_target = $id_users;

if ($is_admin) {
    $id_target_req = (int) ($_GET['id_users'] ?? $_POST['id_target'] ?? 0);
    if ($id_target_req > 0) {
        $id_target = $id_target_req;
    }
}


/* ==========================================================================
   CSRF TOKEN
   ========================================================================== */
if (empty($_SESSION['csrf_pengisian'])) {
    $_SESSION['csrf_pengisian'] = bin2hex(random_bytes(32));
}


/* ==========================================================================
   FUNGSI UMUM
   ========================================================================== */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


/* ==========================================================================
   ALAT BANTU PENETAPAN KATEGORI SISTEM ELEKTRONIK (SE)
   Mengacu pada sheet "Responden" B16:R29 pada instrumen Excel IKASANDI.
   Setiap pertanyaan dijawab A/B/C; skor A=5, B=2, C=1.
   Total skor (10-50) dipetakan ke Kategori SE.
   Setiap pertanyaan dilengkapi Nomor Dokumen & Nama Dokumen sebagai
   referensi bukti dukung atas jawaban yang dipilih.
   ========================================================================== */
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

$batas_kategori_se = [
    ['min' => 10, 'max' => 15, 'label' => 'Rendah'],
    ['min' => 16, 'max' => 34, 'label' => 'Tinggi'],
    ['min' => 35, 'max' => 50, 'label' => 'Strategis'],
];

function tentukanKategoriSE($skor, $batas_kategori_se)
{
    foreach ($batas_kategori_se as $b) {
        if ($skor >= $b['min'] && $skor <= $b['max']) {
            return $b['label'];
        }
    }
    return null;
}


/* ==========================================================================
   SIMPAN DATA RESPONDEN
   Satu pengguna bisa punya BANYAK data responden (1 baris per judul topik)
   di tabel `responden`.

   CATATAN MIGRASI DATABASE (jalankan sekali, backup dulu):

     -- kolom tambahan (jika belum ada)
     ALTER TABLE responden
       ADD COLUMN skor_kategori_se INT NULL AFTER kategori_se,
       ADD COLUMN jawaban_alat_bantu_se TEXT NULL AFTER skor_kategori_se,
       ADD COLUMN dokumen_alat_bantu_se TEXT NULL AFTER jawaban_alat_bantu_se,
       ADD COLUMN judul_audit VARCHAR(255) NULL AFTER dokumen_alat_bantu_se;

     -- kunci unik per (id_users, judul_audit)
     SHOW INDEX FROM responden;
     UPDATE responden SET judul_audit = CONCAT('Pengisian ', id_users)
       WHERE judul_audit IS NULL OR judul_audit = '';
     ALTER TABLE responden
       MODIFY judul_audit VARCHAR(255) NOT NULL,
       ADD UNIQUE KEY uq_responden_user_judul (id_users, judul_audit);
     ALTER TABLE responden DROP INDEX id_users;  -- sesuaikan nama index lama

   Kolom `dokumen_alat_bantu_se` menyimpan JSON per pertanyaan Alat Bantu SE:
   { "1_1": {"nomor": "...", "nama": "..."}, "1_2": {...}, ... }

   Mode simpan:
   - judul_lama kosong  -> INSERT (pengisian baru); judul kembar ditolak
   - judul_lama terisi  -> UPDATE baris dengan judul lama (mode ubah)
   ========================================================================== */
$error_responden = '';
$sukses_responden = '';
$info_responden = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_responden') {

    $token_responden = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_pengisian'], $token_responden)) {
        $error_responden = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {

        $r_organisasi   = trim($_POST['organisasi_pse'] ?? '');
        $r_alamat       = trim($_POST['alamat'] ?? '');
        $r_email        = trim($_POST['email'] ?? '');
        $r_telepon      = trim($_POST['no_telepon'] ?? '');
        $r_nama         = trim($_POST['nama_responden'] ?? '');
        $r_jabatan      = trim($_POST['jabatan_responden'] ?? '');
        $r_tahun        = (int) ($_POST['tahun_pengukuran'] ?? date('Y'));
        $r_tanggal      = trim($_POST['tanggal_pengisian_responden'] ?? date('Y-m-d'));
        $r_target_level = trim($_POST['target_level_kematangan'] ?? '');
        $r_target_nilai = (float) str_replace(',', '.', $_POST['target_nilai'] ?? '0');
        $r_judul_audit  = mb_substr(trim($_POST['judul_audit'] ?? ''), 0, 255);

        /* --- Alat Bantu Kategori SE: dihitung ulang di server, bukan dipercaya dari klien --- */
        $ab_se_post     = $_POST['ab_se'] ?? [];
        $ab_se_jawaban  = [];
        $skor_se_total  = 0;
        $ab_se_terisi   = 0;

        foreach ($alat_bantu_se as $kode => $item) {
            $jawab = $ab_se_post[$kode] ?? '';
            if (in_array($jawab, ['A', 'B', 'C'], true)) {
                $ab_se_jawaban[$kode] = $jawab;
                $skor_se_total += $skor_opsi_se[$jawab];
                $ab_se_terisi++;
            }
        }

        $ab_se_lengkap = ($ab_se_terisi === count($alat_bantu_se));

        $r_kategori_se = $ab_se_lengkap ? tentukanKategoriSE($skor_se_total, $batas_kategori_se) : null;
        $r_skor_se     = $ab_se_terisi > 0 ? $skor_se_total : null;
        $r_jawaban_ab_se_json = !empty($ab_se_jawaban) ? json_encode($ab_se_jawaban, JSON_UNESCAPED_UNICODE) : null;

        /* --- Nomor Dokumen & Nama Dokumen per pertanyaan Alat Bantu SE --- */
        $ab_se_dok_post = $_POST['ab_se_dok'] ?? [];
        $ab_se_dokumen  = [];

        foreach ($alat_bantu_se as $kode => $item) {
            $nomor_dok = trim($ab_se_dok_post[$kode]['nomor'] ?? '');
            $nama_dok  = trim($ab_se_dok_post[$kode]['nama'] ?? '');

            if ($nomor_dok !== '' || $nama_dok !== '') {
                $ab_se_dokumen[$kode] = [
                    'nomor' => $nomor_dok,
                    'nama'  => $nama_dok,
                ];
            }
        }

        $r_dokumen_ab_se_json = !empty($ab_se_dokumen) ? json_encode($ab_se_dokumen, JSON_UNESCAPED_UNICODE) : null;

        if ($r_judul_audit === '') {
            $error_responden = 'Judul Topik wajib diisi.';
        } elseif ($r_email !== '' && !filter_var($r_email, FILTER_VALIDATE_EMAIL)) {
            $error_responden = 'Format alamat surel (email) tidak valid.';
        } else {

            /* judul_lama hanya terisi saat form dibuka dari data yang sudah ada (mode UBAH) */
            $judul_lama = trim($_POST['judul_lama'] ?? '');
            $mode_ubah_simpan = ($judul_lama !== '');

            if ($mode_ubah_simpan) {
                /* UBAH: perbarui baris responden dengan judul lama (judul boleh ikut diganti) */
                $sql_simpan = "
                    UPDATE responden SET
                        organisasi_pse = ?, alamat = ?, email = ?, no_telepon = ?,
                        nama_responden = ?, jabatan_responden = ?, tahun_pengukuran = ?,
                        tanggal_pengisian = ?, target_level_kematangan = ?, target_nilai = ?,
                        kategori_se = ?, skor_kategori_se = ?, jawaban_alat_bantu_se = ?,
                        dokumen_alat_bantu_se = ?, judul_audit = ?
                    WHERE id_users = ? AND judul_audit = ?
                ";
                $tipe_simpan = 'ssssssissdsisssis';
                $param_simpan = [
                    $r_organisasi, $r_alamat, $r_email, $r_telepon,
                    $r_nama, $r_jabatan, $r_tahun,
                    $r_tanggal, $r_target_level, $r_target_nilai,
                    $r_kategori_se, $r_skor_se, $r_jawaban_ab_se_json,
                    $r_dokumen_ab_se_json, $r_judul_audit,
                    $id_target, $judul_lama,
                ];
            } else {
                /* BARU: tambah baris responden baru */
                $sql_simpan = "
                    INSERT INTO responden
                        (id_users, organisasi_pse, alamat, email, no_telepon,
                         nama_responden, jabatan_responden, tahun_pengukuran,
                         tanggal_pengisian, target_level_kematangan, target_nilai,
                         kategori_se, skor_kategori_se, jawaban_alat_bantu_se,
                         dokumen_alat_bantu_se, judul_audit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $tipe_simpan = 'issssssissdsisss';
                $param_simpan = [
                    $id_target, $r_organisasi, $r_alamat, $r_email, $r_telepon,
                    $r_nama, $r_jabatan, $r_tahun,
                    $r_tanggal, $r_target_level, $r_target_nilai,
                    $r_kategori_se, $r_skor_se, $r_jawaban_ab_se_json,
                    $r_dokumen_ab_se_json, $r_judul_audit,
                ];
            }

            try {
                $stmt_responden = mysqli_prepare($koneksi, $sql_simpan);

                if (!$stmt_responden) {
                    throw new Exception('Gagal menyiapkan query data responden.');
                }

                mysqli_stmt_bind_param($stmt_responden, $tipe_simpan, ...$param_simpan);

                if (!mysqli_stmt_execute($stmt_responden)) {
                    $kode_err = mysqli_stmt_errno($stmt_responden);
                    $pesan_err = mysqli_stmt_error($stmt_responden);
                    mysqli_stmt_close($stmt_responden);
                    throw new Exception($pesan_err, $kode_err);
                }

                $baris_terpengaruh = mysqli_stmt_affected_rows($stmt_responden);
                mysqli_stmt_close($stmt_responden);

                /* Mode UBAH tetapi baris tidak ditemukan (mis. sudah dihapus) */
                if ($mode_ubah_simpan && $baris_terpengaruh < 0) {
                    throw new Exception('Data responden tidak ditemukan.');
                }

                /* Judul diganti saat UBAH: ikutkan data penilaian lama agar tidak terputus */
                if ($mode_ubah_simpan && $judul_lama !== $r_judul_audit) {
                    $stmt_ren = mysqli_prepare(
                        $koneksi,
                        "UPDATE pengisian_ikasandi SET judul_topik = ? WHERE id_users = ? AND judul_topik = ?"
                    );
                    if ($stmt_ren) {
                        mysqli_stmt_bind_param($stmt_ren, 'sis', $r_judul_audit, $id_target, $judul_lama);
                        mysqli_stmt_execute($stmt_ren);
                        mysqli_stmt_close($stmt_ren);
                    }
                }

                if ($id_target === $id_users) {
                    $_SESSION['judul_topik'] = $r_judul_audit;
                    $_SESSION['tanggal_pengisian'] = $r_tanggal;
                }

                $tambahan  = ($ab_se_terisi > 0 && !$ab_se_lengkap) ? '&se_terisi=' . $ab_se_terisi : '';
                $tambahan .= ($id_target !== $id_users) ? '&id_users=' . (int) $id_target : '';
                $tambahan .= '&judul_topik=' . urlencode($r_judul_audit);

                header('Location: pengisian_ikasandi.php?view=responden&responden=tersimpan' . $tambahan);
                exit;

            } catch (Throwable $ex) {
                if ((int) $ex->getCode() === 1062) {
                    if (strpos($ex->getMessage(), 'uq_responden_user_judul') !== false) {
                        $error_responden = 'Judul Topik "' . $r_judul_audit . '" sudah ada. '
                            . 'Gunakan judul lain, atau ubah data lama lewat tombol Ubah di Rekap.';
                    } else {
                        $error_responden = 'Database belum dimigrasi: tabel responden masih membatasi 1 data per pengguna '
                            . '(' . $ex->getMessage() . '). Jalankan migrasi database (kunci unik id_users + judul_audit) terlebih dahulu.';
                    }
                } else {
                    $error_responden = 'Gagal menyimpan data responden: ' . $ex->getMessage();
                }
            }
        }
    }
}

if (($_GET['responden'] ?? '') === 'tersimpan') {
    $sukses_responden = 'Data responden berhasil disimpan dan sudah tampil di Rekap IKASANDI.';

    if (isset($_GET['se_terisi'])) {
        $info_responden = 'Alat Bantu Kategori SE baru terisi ' . (int) $_GET['se_terisi']
            . ' dari ' . count($alat_bantu_se)
            . ' pertanyaan. Kategori SE belum bisa dihitung sebelum semua pertanyaan dijawab.';
    }
}


/* ==========================================================================
   AMBIL DATA RESPONDEN
   Satu pengguna bisa punya banyak responden (1 per judul topik).
   Dipilih lewat ?judul_topik=... ; jika tidak ada, pakai judul aktif di session;
   jika tidak ditemukan, ambil yang terbaru.
   ========================================================================== */
$responden = null;

$judul_req = trim((string) ($_GET['judul_topik'] ?? ''));
if ($judul_req === '' && $id_target === $id_users) {
    $judul_req = trim((string) ($_SESSION['judul_topik'] ?? ''));
}

if ($judul_req !== '') {
    $stmt_get_responden = mysqli_prepare(
        $koneksi,
        "SELECT * FROM responden WHERE id_users = ? AND judul_audit = ? LIMIT 1"
    );

    if ($stmt_get_responden) {
        mysqli_stmt_bind_param($stmt_get_responden, 'is', $id_target, $judul_req);
        mysqli_stmt_execute($stmt_get_responden);
        $res_responden = mysqli_stmt_get_result($stmt_get_responden);
        $responden = mysqli_fetch_assoc($res_responden) ?: null;
        mysqli_stmt_close($stmt_get_responden);
    }
}

/* Cadangan: judul tidak ditentukan / tidak ditemukan -> ambil yang terbaru */
if ($responden === null) {
    $stmt_get_responden = mysqli_prepare(
        $koneksi,
        "SELECT * FROM responden WHERE id_users = ? ORDER BY tanggal_pengisian DESC LIMIT 1"
    );

    if ($stmt_get_responden) {
        mysqli_stmt_bind_param($stmt_get_responden, 'i', $id_target);
        mysqli_stmt_execute($stmt_get_responden);
        $res_responden = mysqli_stmt_get_result($stmt_get_responden);
        $responden = mysqli_fetch_assoc($res_responden) ?: null;
        mysqli_stmt_close($stmt_get_responden);
    }
}

/* --------------------------------------------------------------------------
   MODE KOSONG: dibuka lewat tombol "+ Pengisian" (tanpa parameter apa pun).
   Halaman tampil kosong sehingga data lama tidak ikut terisi, dan proses
   simpan menjadi INSERT (responden baru). Data yang sudah ada tetap bisa
   dibuka lewat menu "Data Responden" (?view=responden) atau tombol Ubah
   di Rekap (?aksi=ubah).
   -------------------------------------------------------------------------- */
$mode_kosong = !isset($_GET['view']) && !isset($_GET['aksi']) && !isset($_GET['responden'])
    && !isset($_GET['judul_topik'])
    && $_SERVER['REQUEST_METHOD'] !== 'POST';

if ($mode_kosong) {
    $responden = null;
}

/* Judul asli di database untuk form ini (dikirim sebagai judul_lama; kosong = pengisian baru) */
$judul_lama_form = $responden ? (string) $responden['judul_audit'] : '';

/* Jika simpan gagal, pertahankan isian yang sudah diketik pengguna */
if ($error_responden !== '' && isset($r_judul_audit)) {
    $judul_lama_form = trim($_POST['judul_lama'] ?? '');

    $responden = array_merge($responden ?? [], [
        'organisasi_pse'          => $r_organisasi,
        'alamat'                  => $r_alamat,
        'email'                   => $r_email,
        'no_telepon'              => $r_telepon,
        'nama_responden'          => $r_nama,
        'jabatan_responden'       => $r_jabatan,
        'tahun_pengukuran'        => $r_tahun,
        'tanggal_pengisian'       => $r_tanggal,
        'target_level_kematangan' => $r_target_level,
        'target_nilai'            => $r_target_nilai,
        'kategori_se'             => $r_kategori_se,
        'skor_kategori_se'        => $r_skor_se,
        'jawaban_alat_bantu_se'   => $r_jawaban_ab_se_json,
        'dokumen_alat_bantu_se'   => $r_dokumen_ab_se_json,
        'judul_audit'             => $r_judul_audit,
    ]);
}

/* Jadikan responden yang sedang dibuka sebagai judul aktif (untuk halaman pengisian) */
if ($responden && $id_target === $id_users && $error_responden === '' && !$mode_kosong
    && !empty($responden['judul_audit'])) {
    $_SESSION['judul_topik'] = (string) $responden['judul_audit'];
}

/* Jawaban Alat Bantu Kategori SE yang tersimpan, untuk pre-fill form */
$jawaban_ab_se_tersimpan = [];
if ($responden && !empty($responden['jawaban_alat_bantu_se'])) {
    $decoded = json_decode($responden['jawaban_alat_bantu_se'], true);
    if (is_array($decoded)) {
        $jawaban_ab_se_tersimpan = $decoded;
    }
}

/* Nomor Dokumen & Nama Dokumen yang tersimpan, untuk pre-fill form */
$dokumen_ab_se_tersimpan = [];
if ($responden && !empty($responden['dokumen_alat_bantu_se'])) {
    $decoded_dok = json_decode($responden['dokumen_alat_bantu_se'], true);
    if (is_array($decoded_dok)) {
        $dokumen_ab_se_tersimpan = $decoded_dok;
    }
}

/* Nilai ringkasan badge (aman jika responden belum ada) */
$skor_tampil     = ($responden && isset($responden['skor_kategori_se']) && $responden['skor_kategori_se'] !== null)
    ? (int) $responden['skor_kategori_se'] : null;
$kategori_tampil = (string) ($responden['kategori_se'] ?? '');

/* Judul topik/audit yang diketik pengguna (mis. "website am"); kosong = judul default */
$judul_audit = trim((string) ($responden['judul_audit'] ?? ''));


/* ==========================================================================
   DAFTAR TOPIK IKASANDI
   Topik 1-4 = domain Keamanan Siber (Kamsiber). Topik 5 = Persandian (kode V).
   ========================================================================== */
$kategori = [
    1 => ['kode' => 'I',   'nama' => 'Identifikasi'],
    2 => ['kode' => 'II',  'nama' => 'Proteksi'],
    3 => ['kode' => 'III', 'nama' => 'Deteksi'],
    4 => ['kode' => 'IV',  'nama' => 'Penanggulangan dan Pemulihan'],
    5 => ['kode' => 'V',   'nama' => 'Persandian'],
];


/* ==========================================================================
   TOPIK AKTIF
   ========================================================================== */
$topik_id = isset($_GET['kategori']) ? (int) $_GET['kategori'] : 1;

if (!isset($kategori[$topik_id])) {
    $topik_id = 1;
}

$topik_aktif = $kategori[$topik_id];


/* ==========================================================================
   FLAG: MULAI PENGISIAN BARU (kosong)
   ========================================================================== */
$halaman_baru = isset($_GET['baru']) && $_GET['baru'] == '1';


/* ==========================================================================
   DATA FORM
   Judul pengisian diambil dari Data Responden (judul_audit). Jika kosong,
   dipakai cadangan dari session. Nilai ini dikirim ke simpan_pengisian.php
   lewat input hidden agar validasi "Judul pengisian wajib diisi" terpenuhi.
   ========================================================================== */
$judul_topik = $judul_audit !== '' ? $judul_audit : trim((string) ($_SESSION['judul_topik'] ?? ''));

$tanggal_pengisian = !empty($responden['tanggal_pengisian'])
    ? $responden['tanggal_pengisian']
    : ($_SESSION['tanggal_pengisian'] ?? date('Y-m-d'));


/* ==========================================================================
   PESAN PENGISIAN
   ========================================================================== */
$success = '';
$error = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'sementara') {
        $success = 'Pengisian berhasil disimpan sementara.';
    }
    if ($_GET['success'] === 'selesai') {
        $success = 'Penilaian berhasil disimpan dan menunggu verifikasi.';
    }
}


/* ==========================================================================
   FUNGSI
   ========================================================================== */
function statusPengisian($terisi, $total)
{
    if ($total <= 0) return 'Belum Diisi';
    if ($terisi <= 0) return 'Belum Diisi';
    if ($terisi < $total) return 'Disimpan Sementara';
    return 'Selesai';
}

/* Sama persis dengan fungsi di rekap_ikasandi.php */
function levelKematangan($nilai)
{
    if ($nilai <= 1.50) return ['Level 1 - Awal', '#DC2626'];
    if ($nilai <= 2.50) return ['Level 2 - Berulang', '#EA580C'];
    if ($nilai <= 3.50) return ['Level 3 - Ditetapkan', '#D97706'];
    if ($nilai <= 4.50) return ['Level 4 - Terkelola dan Terukur', '#16A34A'];
    return ['Level 5 - Optimalisasi', '#2563EB'];
}

/*
 * Rumus sama dengan rekap_ikasandi.php:
 *   Nilai Kategori = rata-rata indeks_nilai pertanyaan dalam kategori (kosong = 0)
 *   Nilai Topik    = rata-rata Nilai Kategori
 */
function hitungNilaiTopikAktif($pertanyaan, $jawaban_tersimpan)
{
    $kategori_map = [];

    foreach ($pertanyaan as $p) {
        $kat = ($p['kategori'] !== null && $p['kategori'] !== '') ? $p['kategori'] : '(Tanpa kategori)';

        if (!isset($kategori_map[$kat])) {
            $kategori_map[$kat] = ['n' => 0, 'jumlah' => 0, 'terisi' => 0];
        }

        $kategori_map[$kat]['n']++;

        $id = (int) $p['id'];
        $j = $jawaban_tersimpan[$id] ?? null;

        if ($j !== null && $j['indeks_nilai'] !== null && $j['indeks_nilai'] !== '') {
            $kategori_map[$kat]['jumlah'] += (float) $j['indeks_nilai'];
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

    $nilai_topik = $n_kategori > 0 ? $sum_kategori / $n_kategori : 0;

    return ['kategori' => $kategori_hasil, 'nilai' => $nilai_topik];
}

/* tab rekap: topik 1-4 = Kamsiber, topik 5 = Persandian */
$tab_rekap = ($topik_id === 5) ? 'persandian' : 'kamsiber';

/* tab aktif & topik yang termasuk di dalamnya */
$tab_aktif    = $tab_rekap;
$topik_di_tab = [1, 2, 3, 4, 5];


/* ==========================================================================
   MODE HALAMAN
   - Default (tanpa parameter)  : responden  (Data Responden + Alat Bantu SE)
   - ?view=pengisian&kategori=.. : pengisian
   - ?view=kamsiber / ?view=persandian : Tingkat Kematangan
   ========================================================================== */
$view = $_GET['view'] ?? 'responden';
if (!in_array($view, ['responden', 'pengisian', 'kamsiber', 'persandian'], true)) {
    $view = 'responden';
}

/* ==========================================================================
   PENGUNCIAN AKSES KE HALAMAN PENGISIAN IKASANDI
   Pengguna hanya boleh membuka halaman pengisian jika seluruh Data Responden
   dan 10 jawaban Alat Bantu Kategori SE sudah tersimpan lengkap.
   Validasi dilakukan di server agar tidak bisa dilewati dengan mengetik URL.
   ========================================================================== */
if ($view === 'pengisian') {
    $kolom_responden_wajib = [
        'organisasi_pse',
        'alamat',
        'email',
        'no_telepon',
        'nama_responden',
        'jabatan_responden',
        'tahun_pengukuran',
        'tanggal_pengisian',
        'target_level_kematangan',
        'target_nilai',
        'judul_audit'
    ];

    $data_responden_lengkap = ($responden !== null);
    if ($data_responden_lengkap) {
        foreach ($kolom_responden_wajib as $kolom) {
            if (!isset($responden[$kolom]) || trim((string) $responden[$kolom]) === '') {
                $data_responden_lengkap = false;
                break;
            }
        }
    }

    $jumlah_jawaban_se_lengkap = 0;
    foreach ($alat_bantu_se as $kode_se => $item_se) {
        if (isset($jawaban_ab_se_tersimpan[$kode_se])
            && in_array($jawaban_ab_se_tersimpan[$kode_se], ['A', 'B', 'C'], true)) {
            $jumlah_jawaban_se_lengkap++;
        }
    }

    if (!$data_responden_lengkap || $jumlah_jawaban_se_lengkap !== count($alat_bantu_se)) {
        header('Location: pengisian_ikasandi.php?view=responden&lengkapi=1');
        exit;
    }
}

$topik_kamsiber = [
    1 => ['kode' => 'I',   'nama' => 'IDENTIFIKASI',                'warna' => '#2E75B6'],
    2 => ['kode' => 'II',  'nama' => 'PROTEKSI',                    'warna' => '#7030A0'],
    3 => ['kode' => 'III', 'nama' => 'DETEKSI',                     'warna' => '#ED7D31'],
    4 => ['kode' => 'IV',  'nama' => 'PENANGGULANGAN DAN PEMULIHAN', 'warna' => '#70AD47'],
];
$topik_persandian = [5 => ['kode' => 'V', 'nama' => 'PERSANDIAN', 'warna' => '#6B7280']];


/* Hitung nilai berjenjang untuk satu kelompok domain (dipakai Rekap) */
function hitungKelompok($koneksi, $id_users, $daftar_topik)
{
    $hasil = [];
    $jumlah_nilai_total = 0;
    $jumlah_domain = 0;

    foreach ($daftar_topik as $topik_id => $info) {
        $stmt = mysqli_prepare($koneksi, "SELECT id, kategori FROM pertanyaan WHERE topik_id = ? ORDER BY id ASC");
        mysqli_stmt_bind_param($stmt, 'i', $topik_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $daftar_pertanyaan = [];
        while ($row = mysqli_fetch_assoc($res)) $daftar_pertanyaan[] = $row;
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($koneksi, "SELECT pertanyaan_id, indeks_nilai FROM pengisian_ikasandi WHERE id_users = ? AND topik_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id_users, $topik_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $jawaban = [];
        while ($row = mysqli_fetch_assoc($res)) {
            if ($row['indeks_nilai'] !== null && $row['indeks_nilai'] !== '') {
                $jawaban[(int) $row['pertanyaan_id']] = (int) $row['indeks_nilai'];
            }
        }
        mysqli_stmt_close($stmt);

        $kategori_map = [];
        foreach ($daftar_pertanyaan as $p) {
            $kat = ($p['kategori'] !== null && $p['kategori'] !== '') ? $p['kategori'] : '(Tanpa kategori)';
            if (!isset($kategori_map[$kat])) $kategori_map[$kat] = ['n' => 0, 'jumlah' => 0, 'terisi' => 0];
            $kategori_map[$kat]['n']++;
            if (isset($jawaban[(int) $p['id']])) {
                $kategori_map[$kat]['jumlah'] += $jawaban[(int) $p['id']];
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

        $hasil[$topik_id] = [
            'kode' => $info['kode'], 'nama' => $info['nama'], 'warna' => $info['warna'],
            'kategori' => $kategori_hasil, 'nilai' => $nilai_domain,
            'total_soal' => count($daftar_pertanyaan),
        ];
        if (count($daftar_pertanyaan) > 0) { $jumlah_nilai_total += $nilai_domain; $jumlah_domain++; }
    }

    $nilai_total = $jumlah_domain > 0 ? $jumlah_nilai_total / $jumlah_domain : 0;
    return ['domain' => $hasil, 'nilai_total' => $nilai_total];
}


/* Grafik radar (SVG): Target vs Nilai */
function radarChart($labels, $target_vals, $nilai_vals, $max = 5, $size = 300)
{
    $n = count($labels);
    $cx = $size / 2; $cy = $size / 2; $r = $size * 0.36;
    $rings = 4;
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
        $svg .= "<text x='" . round($lx, 1) . "' y='" . round($ly, 1) . "' font-size='10.5' fill='#475467' text-anchor='$anchor' font-family='Segoe UI,Arial,sans-serif'>" . e($labels[$i]) . "</text>";
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


/* Render 1 blok laporan (Keamanan Siber / Persandian) */
function renderBlok($judul, $kategori_se_label, $tahun, $target_nilai, $kelompok)
{
    $domain = $kelompok['domain'];
    $nilai_total = $kelompok['nilai_total'];
    [$level_label, $level_warna] = levelKematangan($nilai_total);

    /* Kelompok dengan 1 domain (Persandian): radar & matriks memakai rincian per-KATEGORI */
    $is_single_domain = count($domain) === 1;

    $labels = []; $target_vals = []; $nilai_vals = [];
    if ($is_single_domain) {
        $satu_domain = reset($domain);
        foreach ($satu_domain['kategori'] as $k) { $labels[] = $k['nama']; $target_vals[] = $target_nilai; $nilai_vals[] = round($k['nilai'], 2); }
        $judul_grafik = 'Grafik Kematangan Per Kategori';
    } else {
        foreach ($domain as $d) { $labels[] = $d['nama']; $target_vals[] = $target_nilai; $nilai_vals[] = round($d['nilai'], 2); }
        $judul_grafik = 'Grafik Kematangan Per Domain';
    }

    ob_start();
    ?>
    <div class="lap-wrap">
      <div class="lap-tabel">
        <table class="lap">
          <thead>
            <tr>
              <th class="judul" rowspan="2">
                <?= e($judul) ?><?= $kategori_se_label ? '<br><span class="sub">Kategori SE: ' . e($kategori_se_label) . '</span>' : '' ?>
              </th>
              <th colspan="2"><?= (int) $tahun ?></th>
              <th rowspan="2">Nilai Kematangan<br>per-Domain</th>
              <th rowspan="2">Kategori Tingkat<br>Kematangan per-Domain</th>
            </tr>
            <tr><th>Target Nilai Kematangan</th><th>Nilai Kematangan</th></tr>
          </thead>
          <tbody>
            <tr class="total-row">
              <td>Total</td>
              <td class="mono"><?= number_format($target_nilai, 2, ',', '.') ?></td>
              <td class="mono nilai-total"><b><?= number_format($nilai_total, 2, ',', '.') ?></b></td>
              <td colspan="2"></td>
            </tr>
            <?php foreach ($domain as $d):
                $jml_kat = count($d['kategori']);
                if ($jml_kat === 0) continue;
                [$lvl_d, $warna_d] = levelKematangan($d['nilai']);
                $first = true;
                foreach ($d['kategori'] as $k):
                    $cellclass = $k['nilai'] >= $target_nilai ? 'ok' : ($k['nilai'] >= 1.51 ? 'mid' : 'low');
            ?>
              <tr>
                <td class="strip" style="border-left:6px solid <?= $d['warna'] ?>"><?= e($k['nama']) ?></td>
                <td class="mono"><?= number_format($target_nilai, 2, ',', '.') ?></td>
                <td class="mono cell-<?= $cellclass ?>"><?= number_format($k['nilai'], 2, ',', '.') ?></td>
                <?php if ($first): ?>
                  <td class="mono cell-<?= $d['nilai'] >= $target_nilai ? 'ok' : 'low' ?>" rowspan="<?= $jml_kat ?>"><?= number_format($d['nilai'], 2, ',', '.') ?></td>
                  <td rowspan="<?= $jml_kat ?>"><span class="lvl-chip" style="background:<?= $warna_d ?>"><?= e($lvl_d) ?></span></td>
                <?php endif; ?>
              </tr>
            <?php $first = false; endforeach; endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="lap-level">
        <div class="lap-level-lbl">Kategori Tingkat Kematangan<br><?= e($judul) ?></div>
        <div class="lap-level-badge" style="color:<?= $level_warna ?>"><?= e($level_label) ?></div>
      </div>
    </div>

    <div class="lap-wrap" style="margin-top:16px;">
      <div class="lap-radar">
        <div class="card-title"><?= e($judul_grafik) ?></div>
        <?= radarChart($labels, $target_vals, $nilai_vals) ?>
        <div class="legend"><span class="dot" style="background:#94A3B8"></span>Target Nilai Kematangan &nbsp;&nbsp;<span class="dot" style="background:#0B2545"></span>Nilai Kematangan</div>
      </div>

      <div class="lap-matriks">
        <div class="card-title">Nilai Kematangan menurut Kategori SE</div>
        <table class="matriks">
          <thead><tr><th></th><th>Rendah</th><th>Tinggi</th><th>Strategis</th></tr></thead>
          <tbody>
            <?php if ($is_single_domain):
                $satu_domain = reset($domain);
                foreach ($satu_domain['kategori'] as $k): ?>
              <tr>
                <td class="strip" style="border-left:6px solid <?= $satu_domain['warna'] ?>"><?= e($k['nama']) ?></td>
                <td class="mono"><?= number_format($k['nilai'], 2, ',', '.') ?></td>
                <td class="mono"><?= number_format($k['nilai'], 2, ',', '.') ?></td>
                <td class="mono"><?= number_format($k['nilai'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; else:
              foreach ($domain as $d): if (count($d['kategori']) === 0) continue; ?>
              <tr>
                <td class="strip" style="border-left:6px solid <?= $d['warna'] ?>"><?= e($d['nama']) ?></td>
                <td class="mono"><?= number_format($d['nilai'], 2, ',', '.') ?></td>
                <td class="mono"><?= number_format($d['nilai'], 2, ',', '.') ?></td>
                <td class="mono"><?= number_format($d['nilai'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        <div class="note">* Kolom Rendah/Tinggi/Strategis masih menampilkan nilai yang sama (dihitung dari seluruh pertanyaan) karena tabel <code>pertanyaan</code> belum punya penanda kategori SE per kontrol.</div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}


/* Data halaman Tingkat Kematangan (hanya saat ?view=kamsiber / ?view=persandian) */
$kelompok = null;
$judul_view = '';
$tahun = (int) date('Y');
$target_nilai = 2.51;
$kategori_se_label = '';
$daftar_pengguna = [];
$nama_target = $nama_pengisi;
$id_users_rekap = $id_users;

if (in_array($view, ['kamsiber', 'persandian'], true)) {

    /* pengguna yang ditampilkan (admin bisa memilih) */
    if ($is_admin) {
        $q = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM users ORDER BY nama_lengkap");
        if ($q) { while ($r = mysqli_fetch_assoc($q)) $daftar_pengguna[] = $r; }
        if (isset($_GET['user_id']) && (int) $_GET['user_id'] > 0) $id_users_rekap = (int) $_GET['user_id'];
    }
    foreach ($daftar_pengguna as $u) {
        if ((int) $u['id'] === $id_users_rekap) { $nama_target = $u['nama_lengkap']; break; }
    }

    $tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
    $target_nilai = isset($_GET['target']) ? (float) str_replace(',', '.', $_GET['target']) : 2.51;
    $kategori_se_label = $_GET['se'] ?? '';

    if ($view === 'persandian') {
        $judul_view = 'Tingkat Kematangan Persandian';
        $ada_persandian = false;
        $q = mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pertanyaan WHERE topik_id = 5");
        if ($q) $ada_persandian = ((int) mysqli_fetch_assoc($q)['n']) > 0;
        $kelompok = $ada_persandian ? hitungKelompok($koneksi, $id_users_rekap, $topik_persandian) : null;
    } else {
        $judul_view = 'Tingkat Kematangan Keamanan Siber';
        $kelompok = hitungKelompok($koneksi, $id_users_rekap, $topik_kamsiber);
    }

}


/* ==========================================================================
   WARNA INDEKS
   ========================================================================== */
$warna_indeks = [
    0 => '#DC2626',
    1 => '#EA580C',
    2 => '#D97706',
    3 => '#CA8A04',
    4 => '#16A34A',
    5 => '#2563EB',
];


/* ==========================================================================
   DATA PERTANYAAN (topik aktif)
   ========================================================================== */
$pertanyaan = [];

$sql = "
    SELECT
        id, kode, kategori, pertanyaan,
        indeks_0, indeks_1, indeks_2, indeks_3, indeks_4, indeks_5,
        panduan_indeks_5, keterangan_validasi_sumber
    FROM pertanyaan
    WHERE topik_id = ?
    ORDER BY id ASC
";

$stmt = mysqli_prepare($koneksi, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $topik_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $pertanyaan[] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* ==========================================================================
   JAWABAN YANG SUDAH TERSIMPAN (topik aktif)
   ========================================================================== */
$jawaban_tersimpan = [];

$sql_jawaban = "
    SELECT
        id, id_users, topik_id, pertanyaan_id, judul_topik, nama_pengisi,
        tanggal_pengisian, indeks_nilai, keterangan_validasi, bukti_dukung,
        status, created_at, updated_at
    FROM pengisian_ikasandi
    WHERE id_users = ?
    AND topik_id = ?
    AND judul_topik = ?
";

/* Jawaban yang sudah tersimpan untuk judul pengisian ini dimuat kembali ke form
   dan di-highlight (seperti halaman Data Responden). Tanpa judul = tidak ada data. */
if ($judul_topik !== '') {
    $stmt_jawaban = mysqli_prepare($koneksi, $sql_jawaban);

    if ($stmt_jawaban) {
        mysqli_stmt_bind_param($stmt_jawaban, 'iis', $id_users, $topik_id, $judul_topik);
        mysqli_stmt_execute($stmt_jawaban);
        $result_jawaban = mysqli_stmt_get_result($stmt_jawaban);

        while ($row = mysqli_fetch_assoc($result_jawaban)) {
            $jawaban_tersimpan[$row['pertanyaan_id']] = $row;
        }

        mysqli_stmt_close($stmt_jawaban);
    }
}


/* ============================================================
   EXPORT CSV UNTUK SEMUA HALAMAN
   ============================================================ */
if (($_GET['aksi'] ?? '') === 'download') {

    $filename = 'IKASANDI_' . $topik_aktif['kode'] . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // BOM agar karakter Indonesia terbaca di Excel
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    if ($view === 'pengisian') {

        fputcsv($out, [
            'Kode Indikator',
            'Topik',
            'Kategori',
            'Pertanyaan',
            'Indeks Nilai',
            'Keterangan Validasi',
            'Bukti Dukung',
            'Status'
        ], ';');

        foreach ($pertanyaan as $p) {

            $id = (int) $p['id'];
            $jawaban = $jawaban_tersimpan[$id] ?? [];

            fputcsv($out, [
                $p['kode'],
                $topik_aktif['nama'],
                $p['kategori'],
                $p['pertanyaan'],
                $jawaban['indeks_nilai'] ?? '',
                $jawaban['keterangan_validasi'] ?? '',
                $jawaban['bukti_dukung'] ?? '',
                $jawaban['status'] ?? 'Belum Diisi'
            ], ';');
        }

    } elseif ($view === 'responden') {

        fputcsv($out, [
            'Organisasi/PSE',
            'Nama Responden',
            'Jabatan',
            'Tahun Pengukuran',
            'Target Level',
            'Target Nilai',
            'Kategori SE',
            'Skor Kategori SE'
        ], ';');

        if ($responden) {
            fputcsv($out, [
                $responden['organisasi_pse'] ?? '',
                $responden['nama_responden'] ?? '',
                $responden['jabatan_responden'] ?? '',
                $responden['tahun_pengukuran'] ?? '',
                $responden['target_level_kematangan'] ?? '',
                $responden['target_nilai'] ?? '',
                $responden['kategori_se'] ?? '',
                $responden['skor_kategori_se'] ?? ''
            ], ';');
        }

    } else {

        fputcsv($out, [
            'Kelompok',
            'Domain',
            'Kategori',
            'Target',
            'Nilai'
        ], ';');

        if (!empty($kelompok['domain'])) {

            foreach ($kelompok['domain'] as $d) {

                foreach ($d['kategori'] as $k) {

                    fputcsv($out, [
                        $judul_view,
                        $d['kode'] . ' ' . $d['nama'],
                        $k['nama'],
                        number_format($target_nilai, 2, ',', ''),
                        number_format($k['nilai'], 2, ',', '')
                    ], ';');

                }
            }
        }
    }

    fclose($out);
    exit;
}


/* ==========================================================================
   HITUNG PROGRESS (topik aktif)
   ========================================================================== */
$total_pertanyaan = count($pertanyaan);
$total_terisi = 0;

foreach ($pertanyaan as $p) {
    $id = (int) $p['id'];

    if (
        isset($jawaban_tersimpan[$id])
        && $jawaban_tersimpan[$id]['indeks_nilai'] !== null
        && $jawaban_tersimpan[$id]['indeks_nilai'] !== ''
    ) {
        $total_terisi++;
    }
}

$progress = $total_pertanyaan > 0
    ? round(($total_terisi / $total_pertanyaan) * 100)
    : 0;

$status = statusPengisian($total_terisi, $total_pertanyaan);

?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Pengisian IKASANDI</title>

<style>

/* RESET */
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    background: #F4F7FB;
    color: #172033;
}

/* HEADER */
.header {
    height: 70px;
    background: #0B2545;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.header-kanan {
    display: flex;
    align-items: center;
    gap: 20px;
}

.logo { font-size: 20px; font-weight: 800; }
.logo span { opacity: .65; font-size: 14px; font-weight: 400; }

.nav-utama { display: flex; align-items: center; gap: 6px; }

.nav-utama a {
    color: rgba(255,255,255,.75);
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    padding: 9px 14px;
    border-radius: 8px;
    transition: .2s;
    white-space: nowrap;
}

.nav-utama a:hover { background: rgba(255,255,255,.08); color: #fff; }

.nav-utama a.active {
    background: rgba(255,255,255,.14);
    color: #fff;
}

.nav-utama .pemisah {
    width: 1px;
    height: 20px;
    background: rgba(255,255,255,.2);
    margin: 0 6px;
}

.user { display: flex; align-items: center; gap: 10px; font-size: 13px; }

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

/* LAYOUT */
.layout {
    display: grid;
    grid-template-columns: 250px 1fr;
    min-height: calc(100vh - 70px);
}

/* SIDEBAR */
.sidebar {
    background: white;
    border-right: 1px solid #E2E8F0;
    padding: 22px 15px;
}

.sidebar-title {
    font-size: 12px;
    text-transform: uppercase;
    color: #667085;
    font-weight: 800;
    margin: 0 10px 12px;
}

.sidebar-sep { height: 1px; background: #E2E8F0; margin: 14px 10px; }

.kategori {
    display: block;
    text-decoration: none;
    color: #344054;
    padding: 13px;
    margin-bottom: 7px;
    border-radius: 10px;
    transition: .2s;
}

.kategori:hover { background: #EFF6FF; }

.kategori.active {
    background: #E8F1FF;
    color: #155EEF;
    border-left: 4px solid #155EEF;
}

.kategori-kode { font-size: 12px; font-weight: 800; }
.kategori-nama { display: block; margin-top: 4px; font-size: 13px; }

/* DATA RESPONDEN (SIDEBAR) */
.responden-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 18px;
}

.responden-summary-title {
    font-size: 11px;
    text-transform: uppercase;
    color: #667085;
    font-weight: 800;
    margin-bottom: 8px;
}

.responden-summary-org {
    font-size: 13px;
    font-weight: 700;
    color: #172033;
    margin-bottom: 3px;
}

.responden-summary-meta {
    font-size: 11px;
    color: #667085;
    line-height: 1.5;
}

.responden-badge-se {
    display: inline-block;
    margin-top: 7px;
    padding: 4px 9px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 700;
    background: #E8F1FF;
    color: #155EEF;
}

.responden-badge-rendah { background: #FEF3C7; color: #92400E; }
.responden-badge-tinggi { background: #DBEAFE; color: #1E40AF; }
.responden-badge-strategis { background: #FEE2E2; color: #991B1B; }

.responden-edit-link {
    display: inline-block;
    margin-top: 10px;
    font-size: 12px;
    font-weight: 700;
    color: #155EEF;
    text-decoration: none;
}

.responden-edit-link:hover { text-decoration: underline; }

/* MAIN */
.content {
    width: 100%;
    max-width: 1400px;
    margin: auto;
    padding: 30px;
}

/* JUDUL */
.page-title {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.page-title h1 { margin: 0; font-size: 27px; }
.page-title p { margin: 7px 0 0; color: #667085; font-size: 13px; }

/* STATUS */
.status {
    min-width: 250px;
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 15px;
}

.status-label { color: #667085; font-size: 12px; }
.status-value { margin-top: 5px; color: #155EEF; font-size: 14px; font-weight: 800; }

.progress {
    margin-top: 12px;
    height: 8px;
    background: #E4E7EC;
    border-radius: 20px;
    overflow: hidden;
}

.progress-bar { height: 100%; background: #155EEF; }

/* ALERT */
.alert {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 18px;
    font-size: 13px;
}

.alert a { color: inherit; font-weight: 700; }

.alert-success { background: #ECFDF3; border: 1px solid #ABEFC6; color: #027A48; }
.alert-error { background: #FEE4E2; border: 1px solid #FDA29B; color: #B42318; }
.alert-info { background: #FFFAEB; border: 1px solid #FEDF89; color: #93370D; }
.alert-mode { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E3A8A; }

/* HEADER TOPIK */
.kategori-header {
    background: linear-gradient(135deg, #0B2545, #123D70);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.kategori-header small { opacity: .7; font-size: 11px; }
.kategori-header h2 { margin: 5px 0 0; font-size: 21px; }
.kategori-header .judul-pengisian { margin-top: 8px; font-size: 12.5px; opacity: .85; }

/* QUESTION */
.question {
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 15px;
    padding: 23px;
    margin-bottom: 18px;
}

.question-head { display: flex; gap: 12px; align-items: flex-start; }

.number {
    min-width: 105px;
    height: 38px;
    background: #E8F1FF;
    color: #155EEF;
    border-radius: 8px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: 800;
    font-size: 13px;
}

.kode { color: #155EEF; font-size: 13px; font-weight: 800; }

.pertanyaan {
    margin-top: 15px;
    font-size: 15px;
    line-height: 1.7;
    white-space: pre-line;
}

/* INDEKS */
.section-label { margin: 22px 0 10px; font-size: 13px; font-weight: 800; }

.indeks-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.indeks { position: relative; }
.indeks input { position: absolute; opacity: 0; }

.indeks label {
    display: block;
    border: 1px solid #D0D5DD;
    border-radius: 10px;
    padding: 13px;
    cursor: pointer;
    height: 100%;
}

.indeks label:hover { background: #F8FAFC; }

.indeks input:checked + label {
    border: 2px solid #155EEF;
    background: #EFF6FF;
    box-shadow: 0 0 0 3px rgba(21,94,239,.12);
}

/* HIGHLIGHT INDIKATOR YANG SUDAH TERISI */
.question { position: relative; transition: .2s; }

.question.terjawab { background: #F0FDF4; border-color: #86EFAC; }

.question.terjawab .number { background: #D1FADF; color: #027A48; }

.question.terjawab::after {
    content: "✓ Terisi: Indeks " attr(data-jawaban);
    position: absolute; top: 16px; right: 18px;
    font-size: 11px; font-weight: 800; color: #027A48;
    background: #D1FADF; padding: 4px 10px; border-radius: 20px;
}

.question textarea.terisi,
.question .file input.terisi { background: #F0FDF4; border-color: #86EFAC; }

.bukti-tersimpan {
    margin-top: 8px; padding: 8px 11px;
    background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 8px;
    font-size: 12px; font-weight: 700; color: #027A48;
    overflow-wrap: anywhere;
}
.bukti-tersimpan a { color: #027A48; }

.indeks-title { font-size: 13px; font-weight: 800; margin-bottom: 5px; }
.indeks-text { font-size: 11px; line-height: 1.55; color: #667085; }

/* VALIDASI */
.validasi { border-top: 1px solid #E2E8F0; margin-top: 20px; padding-top: 20px; }

textarea {
    width: 100%;
    min-height: 100px;
    resize: vertical;
    border: 1px solid #D0D5DD;
    border-radius: 9px;
    padding: 12px;
    font-family: inherit;
    font-size: 13px;
    outline: none;
}

textarea:focus { border-color: #155EEF; }

/* PANDUAN */
.panduan {
    margin-top: 5px;
    padding: 10px 13px;
    background: #FFFAEB;
    border-left: 4px solid #D4AF37;
    border-radius: 3px;
    font-size: 11px;
    line-height: 1.5;
    white-space: normal;
}

/* FILE */
.file { margin-top: 15px; }

.file input {
    width: 100%;
    padding: 11px;
    border: 1px dashed #98A2B3;
    border-radius: 9px;
    background: #F8FAFC;
    font-size: 12px;
}

/* ACTION */
.action {
    position: sticky;
    bottom: 0;
    background: rgba(255,255,255,.96);
    border-top: 1px solid #E2E8F0;
    padding: 14px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.action-info { font-size: 12px; color: #667085; }
.buttons { display: flex; gap: 9px; }

.btn {
    display: inline-block;
    border: 0;
    border-radius: 8px;
    padding: 11px 15px;
    cursor: pointer;
    font-family: inherit;
    font-weight: 700;
    font-size: 12px;
    text-decoration: none;
}

.btn-secondary { background: #EEF2F6; color: #344054; }
.btn-primary { background: #155EEF; color: white; }
.btn-success { background: #12B76A; color: white; }

/* EMPTY */
.empty {
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 15px;
    padding: 50px;
    text-align: center;
    color: #667085;
}

/* ===== REKAP ===== */
.rbtn { display: inline-block; padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; text-decoration: none; border: 1px solid #D0D5DD; background: #fff; color: #172033; cursor: pointer; font-family: inherit; }

.lap-wrap { display: flex; gap: 16px; align-items: stretch; flex-wrap: wrap; }
.lap-tabel { flex: 2; min-width: 420px; background: #fff; border: 1px solid #E4E9F0; border-radius: 14px; padding: 14px; overflow-x: auto; }
.lap-level { flex: 1; min-width: 220px; background: #fff; border: 1px solid #E4E9F0; border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px; }
.lap-level-lbl { font-size: 13px; font-weight: 700; color: #172033; margin-bottom: 10px; }
.lap-level-badge { font-size: 26px; font-weight: 800; line-height: 1.25; }
.lap-radar { flex: 1; min-width: 320px; background: #fff; border: 1px solid #E4E9F0; border-radius: 14px; padding: 16px; text-align: center; }
.lap-matriks { flex: 1; min-width: 320px; background: #fff; border: 1px solid #E4E9F0; border-radius: 14px; padding: 16px; }
.card-title { font-size: 13px; font-weight: 700; margin-bottom: 10px; color: #0B2545; }

table.lap { width: 100%; border-collapse: collapse; font-size: 12px; }
table.lap th { background: #0B2545; color: #fff; padding: 8px 6px; font-size: 11px; text-align: center; }
table.lap th.judul { background: #FFF2CC; color: #7F6000; text-align: left; padding: 10px; font-size: 13px; }
table.lap th.judul .sub { font-weight: 400; font-size: 11px; }
table.lap td { padding: 7px 8px; border-bottom: 1px solid #EEF1F5; }
table.lap td.strip { padding-left: 10px; }
table.lap tr.total-row td { background: #111827; color: #fff; font-weight: 700; }
table.lap tr.total-row td.nilai-total { background: #DC2626; }
td.mono { font-family: "Consolas", monospace; text-align: center; }
td.cell-ok { background: #D8F3DC; color: #166534; font-weight: 700; }
td.cell-mid { background: #FEF9C3; color: #854D0E; font-weight: 700; }
td.cell-low { background: #FADBD8; color: #991B1B; font-weight: 700; }
.lvl-chip { display: inline-block; padding: 4px 10px; border-radius: 14px; color: #fff; font-size: 11px; font-weight: 700; }

table.matriks { width: 100%; border-collapse: collapse; font-size: 12.5px; }
table.matriks th { text-align: center; color: #667085; font-size: 11px; padding: 6px; border-bottom: 2px solid #E4E9F0; }
table.matriks td { padding: 8px 8px; border-bottom: 1px solid #F0F2F6; }
table.matriks td.strip { font-weight: 600; padding-left: 10px; }
.note { font-size: 10.5px; color: #98A2B3; margin-top: 10px; }
.legend { font-size: 11px; color: #475467; margin-top: 6px; }
.dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 4px; }
.info-box { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E3A8A; padding: 14px 18px; border-radius: 10px; font-size: 13px; }

/* ===== DATA RESPONDEN (FORM) ===== */
.se-badge-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 20px;
}

.se-badge-item { font-size: 12px; color: #667085; }
.se-badge-item b { font-size: 14px; color: #172033; display: block; }

.se-badge {
    margin-left: auto;
    padding: 7px 14px;
    border-radius: 20px;
    font-weight: 800;
    font-size: 13px;
    background: #E8F1FF;
    color: #155EEF;
}

.se-badge.rendah { background: #FEF3C7; color: #92400E; }
.se-badge.tinggi { background: #DBEAFE; color: #1E40AF; }
.se-badge.strategis { background: #FEE2E2; color: #991B1B; }

.rcard {
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 23px;
    margin-bottom: 20px;
}

.rcard-title { margin: 0 0 4px; font-size: 17px; color: #0B2545; }
.rcard-desc { margin: 0 0 18px; font-size: 12.5px; color: #667085; line-height: 1.6; }

.responden-form {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px 16px;
}

.responden-form .full { grid-column: 1 / -1; }

.responden-form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 700;
    color: #344054;
}

.responden-form input,
.responden-form select,
.responden-form textarea {
    font-family: inherit;
    font-size: 13px;
    padding: 10px 11px;
    border: 1px solid #D0D5DD;
    border-radius: 8px;
    outline: none;
    resize: vertical;
}

.responden-form textarea { min-height: 60px; }

.responden-form input:focus,
.responden-form select:focus,
.responden-form textarea:focus { border-color: #155EEF; }

.responden-btn-simpan {
    padding: 12px 20px;
    border: none;
    border-radius: 9px;
    background: #155EEF;
    color: #fff;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
}

.responden-btn-simpan:hover { background: #0B4ACB; }

/* ALAT BANTU KATEGORI SE */
.abse-item {
    position: relative;
    transition: .2s;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 13px;
    padding: 16px;
    margin-bottom: 12px;
}

.abse-item-no {
    display: inline-block;
    min-width: 40px;
    background: #E8F1FF;
    color: #155EEF;
    font-weight: 800;
    font-size: 12px;
    border-radius: 6px;
    padding: 3px 8px;
    margin-right: 8px;
}

.abse-item-judul { font-size: 13.5px; font-weight: 700; color: #172033; display: inline; }

.abse-opsi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 12px;
}

.abse-opsi { position: relative; }
.abse-opsi input { position: absolute; opacity: 0; }

.abse-opsi label {
    display: block;
    background: #fff;
    border: 1px solid #D0D5DD;
    border-radius: 10px;
    padding: 11px;
    cursor: pointer;
    height: 100%;
    font-size: 11.5px;
    line-height: 1.5;
}

.abse-opsi label:hover { background: #F0F6FF; }

.abse-opsi input:checked + label {
    border: 2px solid #155EEF;
    background: #EFF6FF;
    box-shadow: 0 0 0 3px rgba(21,94,239,.12);
}

.abse-opsi-title { font-size: 12px; font-weight: 800; margin-bottom: 4px; color: #344054; }
.abse-opsi-skor { font-weight: 400; color: #98A2B3; }

/* NOMOR DOKUMEN / NAMA DOKUMEN */
.abse-dok-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #D0D5DD;
}

.abse-dok-grid label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 700;
    color: #344054;
}

.abse-dok-grid input {
    font-family: inherit;
    font-size: 12.5px;
    padding: 9px 10px;
    border: 1px solid #D0D5DD;
    border-radius: 8px;
    outline: none;
    background: #fff;
}

.abse-dok-grid input:focus { border-color: #155EEF; }

/* HIGHLIGHT ISIAN YANG SUDAH TERISI */
.responden-form input.terisi,
.responden-form textarea.terisi,
.responden-form select.terisi,
.abse-dok-grid input.terisi {
    background: #F0FDF4;
    border-color: #86EFAC;
}

.abse-item.terjawab { background: #F0FDF4; border-color: #86EFAC; }
.abse-item.terjawab::after {
    content: "✓ Jawaban: [" attr(data-jawaban) "]";
    position: absolute; top: 12px; right: 14px;
    font-size: 11px; font-weight: 800; color: #027A48;
    background: #D1FADF; padding: 3px 9px; border-radius: 20px;
}

/* JUDUL TOPIK YANG BISA DIKETIK */
.judul-audit-field {
    display: flex; flex-direction: column; gap: 6px;
    font-size: 12.5px; font-weight: 700; color: #344054;
    margin-bottom: 16px;
}
.judul-audit-field input {
    font-family: inherit; font-size: 14px; padding: 10px 11px;
    border: 1px solid #D0D5DD; border-radius: 8px; outline: none;
}
.judul-audit-field input:focus { border-color: #155EEF; }
.judul-audit-field input.terisi { background: #F0FDF4; border-color: #86EFAC; }
.judul-audit-hint { font-weight: 400; font-size: 11px; color: #98A2B3; }

.form-actions {
    position: sticky;
    bottom: 0;
    background: rgba(244,247,251,.96);
    border-top: 1px solid #E2E8F0;
    padding: 14px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.link-lanjut {
    font-size: 12.5px;
    font-weight: 700;
    color: #155EEF;
    text-decoration: none;
}

.link-lanjut:hover { text-decoration: underline; }

@media print {
    .header, .sidebar, .rbtn, .form-actions { display: none !important; }
    .layout { display: block; }
    .content { padding: 0; }
}

/* RESPONSIVE */
@media (max-width: 1000px) {
    .layout { grid-template-columns: 1fr; }
    .sidebar { border-right: 0; border-bottom: 1px solid #E2E8F0; }
    .indeks-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 900px) {
    .nav-utama { display: none; }
}

@media (max-width: 800px) {
    .responden-form { grid-template-columns: 1fr 1fr; }
    .abse-opsi-grid { grid-template-columns: 1fr; }
    .abse-dok-grid { grid-template-columns: 1fr; }
}

@media (max-width: 650px) {
    .header { padding: 0 15px; }
    .content { padding: 15px; }
    .page-title { flex-direction: column; }
    .status { width: 100%; }
    .indeks-grid { grid-template-columns: 1fr; }
    .responden-form { grid-template-columns: 1fr; }
    .action { flex-direction: column; align-items: stretch; }
    .buttons { flex-direction: column; }
    .form-actions { flex-direction: column; align-items: stretch; }
}

</style>

</head>


<body>

<!-- HEADER -->
<header class="header">

    <div class="logo">
        ARSIKAS
    </div>

    <div class="header-kanan">
        <nav class="nav-utama">
            <a href="index.php">Dashboard</a>
            <a href="instrumen_ikasandi.php">Instrumen IKASANDI</a>
            <a href="pengisian_ikasandi.php" class="active">Pengisian IKASANDI</a>
            <a href="rekap_ikasandi.php">Rekap IKASANDI</a>
            <?php if ($is_admin): ?>
                <span class="pemisah"></span>
                <a href="log_pengguna.php">Log Pengguna</a>
            <?php endif; ?>
        </nav>

        <div class="user">
            <?= e($nama_pengisi); ?>
            <div class="avatar">
                <?= e(strtoupper(substr($nama_pengisi, 0, 1))); ?>
            </div>
        </div>
    </div>

</header>


<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">

    <!-- MENU DATA RESPONDEN (sama seperti domain lain) -->
    <div class="sidebar-title">DATA AWAL</div>

    <a
        href="pengisian_ikasandi.php?view=responden"
        class="kategori <?= ($view === 'responden' ? 'active' : ''); ?>"
    >
        <span class="kategori-kode">👤 Data Responden</span>
        <span class="kategori-nama">Identitas &amp; Kategori SE</span>
    </a>

    <div class="sidebar-sep"></div>


    <div class="sidebar-title">TOPIK IKASANDI</div>

    <?php foreach ($kategori as $id => $item): ?>

        <a
            href="pengisian_ikasandi.php?view=pengisian&kategori=<?= (int) $id; ?>"
            class="kategori <?= ($view === 'pengisian' && $id === $topik_id ? 'active' : ''); ?>"
        >
            <span class="kategori-kode"><?= e($item['kode']); ?></span>
            <span class="kategori-nama"><?= e($item['nama']); ?></span>
        </a>

    <?php endforeach; ?>


    <!-- HALAMAN TINGKAT KEMATANGAN -->
    <a
        href="pengisian_ikasandi.php?view=kamsiber"
        class="kategori <?= ($view === 'kamsiber' ? 'active' : ''); ?>"
    >
        <span class="kategori-kode">📊 Tingkat Kematangan</span>
        <span class="kategori-nama">Keamanan Siber</span>
    </a>

    <a
        href="pengisian_ikasandi.php?view=persandian"
        class="kategori <?= ($view === 'persandian' ? 'active' : ''); ?>"
    >
        <span class="kategori-kode">📊 Tingkat Kematangan</span>
        <span class="kategori-nama">Persandian</span>
    </a>


    <!-- STATUS TOPIK AKTIF (tidak ditampilkan di halaman responden) -->
    <?php if ($view !== 'responden'): ?>
    <div style="margin-top:20px;background:#F8FAFC;padding:14px;border-radius:10px;">

        <div style="color:#667085;font-size:11px;">
            Status
        </div>

        <strong style="color:#155EEF;font-size:13px;">
            <?= e($status); ?>
        </strong>

        <?php if ($view === 'pengisian'): ?>

            <div style="margin-top:8px;font-size:11px;color:#667085;">
                <?= (int) $total_terisi; ?> dari
                <?= (int) $total_pertanyaan; ?> indikator
            </div>

        <?php endif; ?>

    </div>
    <?php endif; ?>

</aside>


<!-- CONTENT -->
<?php if ($view === 'responden'): ?>

<!-- ======================= DATA RESPONDEN (FORM) ======================= -->
<main class="content" style="max-width:1100px;">

    <div class="page-title">
        <div>
            <h1>Data Responden</h1>
        </div>
    </div>

    <?php if (isset($_GET['lengkapi']) && $_GET['lengkapi'] === '1'): ?>
        <div class="alert alert-error">
            Data Responden belum lengkap. Lengkapi seluruh kolom Data Responden,
            isi semua 10 pertanyaan Alat Bantu Kategori SE, lalu klik
            <strong>Simpan Data Responden</strong> sebelum melanjutkan ke Pengisian IKASANDI.
        </div>
    <?php endif; ?>

    <?php if ($info_responden): ?>
        <div class="alert alert-info"><?= e($info_responden); ?></div>
    <?php endif; ?>

    <?php if ($error_responden): ?>
        <div class="alert alert-error"><?= e($error_responden); ?></div>
    <?php endif; ?>

    <div class="se-badge-wrap">
        <div class="se-badge-item">
            Total Skor
            <b id="abseSkorTampil"><?= $skor_tampil !== null ? $skor_tampil : '-'; ?> / 50</b>
        </div>
        <div class="se-badge-item">
            Pertanyaan Terisi
            <b id="abseTerisiTampil"><?= count($jawaban_ab_se_tersimpan); ?> / <?= count($alat_bantu_se); ?></b>
        </div>
        <div class="se-badge <?= e(strtolower($kategori_tampil)); ?>" id="abseKategoriTampil">
            <?= e($kategori_tampil !== '' ? $kategori_tampil : 'Belum dihitung'); ?>
        </div>
    </div>

    <!-- ==================== JUDUL TOPIK ==================== -->
    <div class="rcard">
        <h2 class="rcard-title" id="judulAuditTampil"><?= e($judul_audit !== '' ? $judul_audit : 'Topik IKASANDI'); ?></h2>

        <label class="judul-audit-field">
            Judul Topik
            <input
                type="text"
                id="judulAudit"
                required
                name="judul_audit"
                form="formResponden"
                maxlength="255"
                value="<?= e($judul_audit); ?>"
                placeholder="Ketik judul, misalnya: website am"
            >
            <span class="judul-audit-hint">Judul ini wajib diisi sebelum pengisian IKASANDI.</span>
        </label>
    </div>

    <form method="post" id="formResponden"
          action="pengisian_ikasandi.php?view=responden<?= $id_target !== $id_users ? '&id_users=' . (int) $id_target : ''; ?>">

        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_pengisian']); ?>">
        <input type="hidden" name="aksi" value="simpan_responden">
        <input type="hidden" name="id_target" value="<?= (int) $id_target; ?>">
        <!-- Terisi hanya jika data lama dimuat (mode ubah); kosong saat pengisian baru -->
        <input type="hidden" name="judul_lama" value="<?= e($judul_lama_form); ?>">

        <!-- ==================== INFORMASI RESPONDEN ==================== -->
        <div class="rcard">

            <h2 class="rcard-title">Informasi Responden</h2>
            <p class="rcard-desc">Data organisasi/PSE dan penanggung jawab pengisian instrumen IKASANDI.</p>

            <div class="responden-form">

                <label>
                    Organisasi/PSE
                    <input type="text" name="organisasi_pse" required value="<?= e($responden['organisasi_pse'] ?? ''); ?>">
                </label>

                <label class="full">
                    Alamat
                    <textarea name="alamat" rows="2" required><?= e($responden['alamat'] ?? ''); ?></textarea>
                </label>

                <label>
                    Alamat Surel (Email)
                    <input type="email" name="email" required value="<?= e($responden['email'] ?? ''); ?>">
                </label>

                <label>
                    Nomor Telepon
                    <input type="text" name="no_telepon" required value="<?= e($responden['no_telepon'] ?? ''); ?>">
                </label>

                <label>
                    Nama Responden
                    <input type="text" name="nama_responden" required value="<?= e($responden['nama_responden'] ?? ''); ?>">
                </label>

                <label>
                    Jabatan Responden
                    <input type="text" name="jabatan_responden" required value="<?= e($responden['jabatan_responden'] ?? ''); ?>">
                </label>

                <label>
                    Tahun Pengukuran
                    <input type="number" name="tahun_pengukuran" required min="2000" max="2100"
                           value="<?= e($responden['tahun_pengukuran'] ?? date('Y')); ?>">
                </label>

                <label>
                    Tanggal Pengisian
                    <input type="date" name="tanggal_pengisian_responden" required
                           value="<?= e($responden['tanggal_pengisian'] ?? date('Y-m-d')); ?>">
                </label>

                <label>
                    Target Level Kematangan
                    <select name="target_level_kematangan" required>
                        <?php foreach (['Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5'] as $lvl): ?>
                            <option value="<?= e($lvl); ?>"
                                <?= (($responden['target_level_kematangan'] ?? 'Level 3') === $lvl) ? 'selected' : ''; ?>>
                                <?= e($lvl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Target Nilai
                    <input type="number" step="0.01" min="0" max="5" name="target_nilai" required
                           value="<?= e($responden['target_nilai'] ?? '2.51'); ?>">
                </label>

            </div>

        </div>

        <!-- ==================== ALAT BANTU KATEGORI SE ==================== -->
        <div class="rcard">

            <h2 class="rcard-title">Alat Bantu Penetapan Kategori Sistem Elektronik (SE)</h2>
            <p class="rcard-desc">
                Bagian ini merupakan alat bantu guna mengidentifikasi/mengevaluasi tingkat atau kategori sistem elektronik
                yang digunakan [Kategori Sistem Elektronik: Rendah; Tinggi; Strategis]. Jawab seluruh 10 pertanyaan berikut
                (pilih A, B, atau C); Kategori SE dihitung otomatis dari total skor (A = 5, B = 2, C = 1). Lengkapi juga
                Nomor Dokumen dan Nama Dokumen sebagai referensi bukti dukung atas jawaban yang dipilih.
            </p>

            <?php foreach ($alat_bantu_se as $kode => $item):
                $jawab_tersimpan = $jawaban_ab_se_tersimpan[$kode] ?? '';
                $dok_item  = $dokumen_ab_se_tersimpan[$kode] ?? [];
                $dok_nomor = $dok_item['nomor'] ?? '';
                $dok_nama  = $dok_item['nama'] ?? '';
            ?>
                <div class="abse-item" data-jawaban="<?= e($jawab_tersimpan); ?>">
                    <span class="abse-item-no"><?= e($item['no']); ?></span>
                    <span class="abse-item-judul"><?= e($item['pertanyaan']); ?></span>

                    <div class="abse-opsi-grid">
                        <?php foreach ($item['opsi'] as $huruf => $teks): ?>
                            <div class="abse-opsi">
                                <input
                                    type="radio"
                                    id="abse_<?= e($kode); ?>_<?= e($huruf); ?>"
                                    name="ab_se[<?= e($kode); ?>]"
                                    value="<?= e($huruf); ?>"
                                    data-skor="<?= (int) $skor_opsi_se[$huruf]; ?>"
                                    class="abse-radio"
                                    <?= $huruf === 'A' ? 'required' : ''; ?>
                                    <?= ($jawab_tersimpan === $huruf) ? 'checked' : ''; ?>
                                >
                                <label for="abse_<?= e($kode); ?>_<?= e($huruf); ?>">
                                    <div class="abse-opsi-title">
                                        [<?= e($huruf); ?>] <span class="abse-opsi-skor">(skor <?= (int) $skor_opsi_se[$huruf]; ?>)</span>
                                    </div>
                                    <?= e($teks); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="abse-dok-grid">
                        <label>
                            Nomor Dokumen
                            <input
                                type="text"
                                name="ab_se_dok[<?= e($kode); ?>][nomor]"
                                value="<?= e($dok_nomor); ?>"
                                placeholder="Nomor dokumen bukti dukung"
                            >
                        </label>
                        <label>
                            Nama Dokumen
                            <input
                                type="text"
                                name="ab_se_dok[<?= e($kode); ?>][nama]"
                                value="<?= e($dok_nama); ?>"
                                placeholder="Nama dokumen bukti dukung"
                            >
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <div class="form-actions">
            <div class="action-info">
                <strong id="abseTerisiBawah"><?= count($jawaban_ab_se_tersimpan); ?></strong>
                dari
                <strong><?= count($alat_bantu_se); ?></strong>
                pertanyaan Alat Bantu SE telah diisi.
                &nbsp;&middot;&nbsp;
                <a href="pengisian_ikasandi.php?view=pengisian&kategori=1" class="link-lanjut" title="Data Responden dan seluruh jawaban Alat Bantu SE harus lengkap dan tersimpan">Lanjut ke Pengisian IKASANDI &rarr;</a>
            </div>

            <div class="buttons">
                <button
                    type="submit"
                    class="btn btn-success"
                    onclick="return konfirmasiResponden();"
                >
                    ✓ Simpan Data Responden
                </button>
            </div>
        </div>

    </form>

</main>

<?php elseif ($view !== 'pengisian'): ?>

<!-- ======================= TINGKAT KEMATANGAN ======================= -->
<main class="content">

  <div class="page-title">
    <div>
      <h1><?= e($judul_view); ?></h1>
      <p>Pengguna: <b><?= e($nama_target); ?></b> · Tahun <?= (int) $tahun; ?></p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;">
      <?php if ($is_admin && $daftar_pengguna): ?>
        <form method="get" style="display:inline">
          <input type="hidden" name="view" value="<?= e($view); ?>">
          <select class="rbtn" name="user_id" onchange="this.form.submit()">
            <?php foreach ($daftar_pengguna as $u): ?>
              <option value="<?= (int) $u['id']; ?>" <?= (int) $u['id'] === $id_users_rekap ? 'selected' : ''; ?>><?= e($u['nama_lengkap']); ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($kelompok): ?>
    <?= renderBlok($judul_view, $kategori_se_label, $tahun, $target_nilai, $kelompok); ?>
  <?php else: ?>
    <div class="info-box">Data pertanyaan untuk domain Persandian (topik_id = 5) belum tersedia. Lengkapi dulu data pertanyaannya di tabel <code>pertanyaan</code> agar halaman ini bisa tampil.</div>
  <?php endif; ?>

</main>

<?php else: ?>

<!-- ======================= PENGISIAN ======================= -->
<main class="content">

<!-- TITLE -->
<div class="page-title">

    <div>
        <h1>Pengisian IKASANDI</h1>
        <p>
            Judul pengisian diambil dari Data Responden. Pilih indeks kematangan secara manual pada setiap indikator.
        </p>
    </div>


    <div class="status">

        <div class="status-label">Progress Pengisian</div>

        <div class="status-value">
            <?= $total_terisi; ?> / <?= $total_pertanyaan; ?>
        </div>

        <div class="progress">
            <div class="progress-bar" style="width:<?= $progress; ?>%;"></div>
        </div>

        <div style="margin-top:7px;font-size:11px;color:#667085;">
            <?= $progress; ?>%
        </div>

    </div>

</div>


<!-- PESAN -->
<?php if ($success): ?>
    <div class="alert alert-success">
        ✓ <?= e($success); ?>
    </div>
<?php endif; ?>

<?php if ($judul_topik === ''): ?>
    <div class="alert alert-error">
        Judul pengisian belum diisi. Silakan isi <b>Judul Topik</b> terlebih dahulu di halaman
        <a href="pengisian_ikasandi.php?view=responden">Data Responden</a>
        agar penilaian bisa disimpan.
    </div>
<?php endif; ?>


<!-- FORM -->
<form
    action="simpan_pengisian.php"
    method="POST"
    enctype="multipart/form-data"
    id="formPengisian"
>

<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_pengisian']); ?>">
<input type="hidden" name="topik_id" value="<?= (int) $topik_id; ?>">

<!-- Data yang dibutuhkan simpan_pengisian.php (diambil dari Data Responden) -->
<input type="hidden" name="judul_topik" id="judulTopikHidden" value="<?= e($judul_topik); ?>">
<input type="hidden" name="nama_pengisi" value="<?= e($nama_pengisi); ?>">
<input type="hidden" name="tanggal_pengisian" value="<?= e($tanggal_pengisian); ?>">


<!-- TOPIK AKTIF -->
<div class="kategori-header">
    <small>Topik IKASANDI</small>
    <h2><?= e($topik_aktif['kode']); ?>. <?= e($topik_aktif['nama']); ?></h2>
    <?php if ($judul_topik !== ''): ?>
        <div class="judul-pengisian">Judul pengisian: <b><?= e($judul_topik); ?></b></div>
    <?php endif; ?>
</div>


<!-- PERTANYAAN -->
<?php if (empty($pertanyaan)): ?>

    <div class="empty">
        <h3>Data indikator belum tersedia</h3>
        <p>Belum ada indikator untuk kategori ini pada database.</p>
    </div>

<?php else: ?>

    <?php foreach ($pertanyaan as $no => $p): ?>

        <?php
        $id = (int) $p['id'];

        $jawaban = $jawaban_tersimpan[$id] ?? null;

        /* Nilai indeks hanya dari pilihan manual pengguna. */
        $nilai = '';
        if (
            isset($jawaban['indeks_nilai'])
            && $jawaban['indeks_nilai'] !== null
            && $jawaban['indeks_nilai'] !== ''
        ) {
            $nilai = $jawaban['indeks_nilai'];
        }

        $validasi = $jawaban['keterangan_validasi'] ?? '';
        ?>

        <div
            class="question<?= ($nilai !== '') ? ' terjawab' : ''; ?>"
            id="pertanyaan-<?= $id; ?>"
            data-jawaban="<?= e($nilai); ?>"
        >

            <div class="question-head">
                <div class="number">Pertanyaan <?= $no + 1; ?></div>
                <div>
                    <div class="kode"><?= e($p['kode']); ?></div>
                </div>
            </div>

            <div class="pertanyaan">
                <?= e($p['kode']); ?> - <?= e($p['pertanyaan']); ?>
            </div>

            <div class="section-label">Pilihan Indeks Nilai Kematangan</div>

            <div class="indeks-grid">

                <?php for ($i = 0; $i <= 5; $i++): ?>

                    <div class="indeks">

                        <input
                            type="radio"
                            id="indeks_<?= $id; ?>_<?= $i; ?>"
                            name="jawaban[<?= $id; ?>][indeks]"
                            value="<?= $i; ?>"
                            <?= ((string) $nilai === (string) $i) ? 'checked' : ''; ?>
                        >

                        <label for="indeks_<?= $id; ?>_<?= $i; ?>">

                            <div class="indeks-title" style="color:<?= $warna_indeks[$i]; ?>;">
                                Indeks <?= $i; ?>
                            </div>

                            <div class="indeks-text">
                                <?= e($p['indeks_' . $i]); ?>
                            </div>

                        </label>

                    </div>

                <?php endfor; ?>

            </div>

            <?php if (!empty($p['panduan_indeks_5'])): ?>

                <div class="panduan">
                    <strong>Panduan Pemenuhan untuk Indeks 5</strong>
                    <br><br>
                    <?= e($p['panduan_indeks_5']); ?>
                </div>

            <?php endif; ?>

            <div class="validasi">

                <div class="section-label">Keterangan / Validasi</div>

                <textarea
                    name="jawaban[<?= $id; ?>][validasi]"
                    placeholder="Tuliskan keterangan hasil validasi berdasarkan kondisi sebenarnya..."
                ><?= e($validasi); ?></textarea>

                <div class="file">

                    <div class="section-label">Bukti Dukung</div>

                    <input
                        type="file"
                        name="bukti[<?= $id; ?>]"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt,.csv"
                    >

                    <?php if (!empty($jawaban['bukti_dukung'])): ?>
                        <div class="bukti-tersimpan">
                            ✓ Bukti tersimpan:
                            <a href="<?= e($jawaban['bukti_dukung']); ?>" target="_blank" rel="noopener noreferrer">
                                <?= e(basename($jawaban['bukti_dukung'])); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:6px;font-size:10px;color:#98A2B3;">
                        PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, TXT, CSV.
                        Maksimal 10 MB. Pilih indeks kematangan secara manual sesuai hasil pemeriksaan bukti.
                    </div>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>


<!-- ACTION -->
<?php if (!empty($pertanyaan)): ?>

<div class="action">

    <div class="action-info">
        <strong><?= $total_terisi; ?></strong>
        dari
        <strong><?= $total_pertanyaan; ?></strong>
        indikator telah diisi.
    </div>

    <div class="buttons">

        <button
            type="submit"
            name="aksi"
            value="submit"
            class="btn btn-success"
            onclick="return konfirmasiPenilaian();"
        >
            ✓ Simpan Penilaian
        </button>

    </div>

</div>

<?php endif; ?>

</form>

</main>
<?php endif; ?>

</div>


<script>

/* ===================== HALAMAN PENGISIAN ===================== */

/* KONFIRMASI */
function konfirmasiPenilaian()
{
    /* Judul pengisian wajib ada (diambil dari Data Responden) */
    const judulEl = document.getElementById('judulTopikHidden');
    if (judulEl && judulEl.value.trim() === '') {
        if (confirm('Judul pengisian belum diisi di Data Responden. Buka halaman Data Responden sekarang?')) {
            window.location.href = 'pengisian_ikasandi.php?view=responden';
        }
        return false;
    }

    const total = <?= (int) $total_pertanyaan; ?>;

    const terisi = document.querySelectorAll(
        '.question input[type="radio"]:checked'
    ).length;

    if (total === 0) {
        alert('Belum ada indikator.');
        return false;
    }

    if (terisi < total) {
        return confirm(
            'Masih ada ' + (total - terisi) +
            ' indikator yang belum diisi. Apakah penilaian tetap disimpan?'
        );
    }

    return confirm(
        'Semua indikator telah diisi. Apakah Anda yakin ingin menyimpan penilaian?'
    );
}


/* KONFIRMASI SIMPAN DATA RESPONDEN (langsung tersimpan & tampil di Rekap) */
function konfirmasiResponden()
{
    const total = <?= count($alat_bantu_se); ?>;

    const terisi = document.querySelectorAll(
        '.abse-radio:checked'
    ).length;

    if (terisi < total) {
        return confirm(
            'Masih ada ' + (total - terisi) +
            ' pertanyaan Alat Bantu SE yang belum diisi (Kategori SE belum bisa dihitung). ' +
            'Apakah data responden tetap disimpan?'
        );
    }

    return confirm(
        'Semua pertanyaan Alat Bantu SE telah diisi. Apakah Anda yakin ingin menyimpan data responden?'
    );
}


/* UPDATE PROGRESS */
document.addEventListener('change', function (event) {
    if (event.target.matches('.question input[type="radio"]')) {
        updateProgress();
    }

    if (event.target.closest && event.target.closest('.question')) {
        tandaiPengisian();
    }
});

document.addEventListener('input', function (event) {
    if (event.target.closest && event.target.closest('.question')) {
        tandaiPengisian();
    }
});

/* HIGHLIGHT indikator, keterangan, dan bukti dukung yang sudah terisi */
function tandaiPengisian()
{
    document.querySelectorAll('.question').forEach(function (q) {
        const dipilih = q.querySelector('input[type="radio"]:checked');

        q.classList.toggle('terjawab', !!dipilih);

        if (dipilih) {
            q.setAttribute('data-jawaban', dipilih.value);
        }

        const ket = q.querySelector('textarea');
        if (ket) {
            ket.classList.toggle('terisi', ket.value.trim() !== '');
        }

        const berkas = q.querySelector('.file input[type="file"]');
        if (berkas) {
            berkas.classList.toggle('terisi', berkas.files && berkas.files.length > 0);
        }
    });
}

document.addEventListener('DOMContentLoaded', tandaiPengisian);

function updateProgress()
{
    const total = document.querySelectorAll('.question').length;

    if (total === 0) {
        return;
    }

    const terisi = document.querySelectorAll(
        '.question input[type="radio"]:checked'
    ).length;

    const persen = Math.round((terisi / total) * 100);

    const bar = document.querySelector('.progress-bar');
    const status = document.querySelector('.status-value');

    if (bar) {
        bar.style.width = persen + '%';
    }

    if (status) {
        status.innerHTML = terisi + ' / ' + total + ' indikator';
    }
}


/* ===================== HALAMAN DATA RESPONDEN ===================== */
(function () {

    var TOTAL_SOAL_SE = <?= count($alat_bantu_se); ?>;

    var BATAS_SE = [
        { min: 10, max: 15, label: 'Rendah' },
        { min: 16, max: 34, label: 'Tinggi' },
        { min: 35, max: 50, label: 'Strategis' }
    ];

    function tentukanKategoriSE(skor) {
        for (var i = 0; i < BATAS_SE.length; i++) {
            if (skor >= BATAS_SE[i].min && skor <= BATAS_SE[i].max) {
                return BATAS_SE[i].label;
            }
        }
        return null;
    }

    function hitungAlatBantuSE() {
        var elSkor = document.getElementById('abseSkorTampil');
        if (!elSkor) return; /* bukan halaman Data Responden */

        var radios = document.querySelectorAll('.abse-radio:checked');
        var skor = 0;
        radios.forEach(function (r) {
            skor += parseInt(r.dataset.skor, 10) || 0;
        });

        var terisi = radios.length;

        var elTerisi = document.getElementById('abseTerisiTampil');
        var elKategori = document.getElementById('abseKategoriTampil');

        elSkor.textContent = skor + ' / 50';
        if (elTerisi) elTerisi.textContent = terisi + ' / ' + TOTAL_SOAL_SE;

        var elTerisiBawah = document.getElementById('abseTerisiBawah');
        if (elTerisiBawah) elTerisiBawah.textContent = terisi;

        if (elKategori) {
            elKategori.className = 'se-badge';
            if (terisi === TOTAL_SOAL_SE) {
                var kategori = tentukanKategoriSE(skor);
                elKategori.textContent = kategori || 'Belum dihitung';
                if (kategori) elKategori.classList.add(kategori.toLowerCase());
            } else {
                elKategori.textContent = 'Belum dihitung';
            }
        }
    }

    /* Highlight isian & jawaban yang sudah terisi */
    function tandaiTerisi() {
        var form = document.getElementById('formResponden');
        if (!form) return; /* bukan halaman Data Responden */

        form.querySelectorAll('input[type="text"], input[type="email"], input[type="number"], input[type="date"], textarea').forEach(function (el) {
            el.classList.toggle('terisi', el.value.trim() !== '');
        });

        form.querySelectorAll('.abse-item').forEach(function (item) {
            var dipilih = item.querySelector('.abse-radio:checked');
            item.classList.toggle('terjawab', !!dipilih);
            if (dipilih) item.setAttribute('data-jawaban', dipilih.value);
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches('.abse-radio')) {
            hitungAlatBantuSE();
        }
        tandaiTerisi();
    });

    document.addEventListener('input', function (event) {
        if (event.target.closest && event.target.closest('#formResponden')) {
            tandaiTerisi();
        }
    });

    /* Judul topik: judul kartu ikut berubah saat diketik */
    function updateJudulAudit() {
        var inp = document.getElementById('judulAudit');
        var h = document.getElementById('judulAuditTampil');
        if (!inp || !h) return;
        var v = inp.value.trim();
        h.textContent = v !== '' ? v : 'Topik IKASANDI';
        inp.classList.toggle('terisi', v !== '');
    }

    document.addEventListener('input', function (event) {
        if (event.target.id === 'judulAudit') updateJudulAudit();
    });

    document.addEventListener('DOMContentLoaded', function () {
        hitungAlatBantuSE();
        tandaiTerisi();
        updateJudulAudit();
    });

})();

</script>

</body>

</html>