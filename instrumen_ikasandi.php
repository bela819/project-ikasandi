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


/* ==========================================================================
   FUNGSI
   ========================================================================== */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Instrumen IKASANDI — Pengantar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#FFFFFF; --canvas:#EEF1F5; --navy:#0B2545; --navy-2:#123159;
    --gold:#B8862E; --gold-soft:#E7D6AE; --line:#DFE4EB; --text:#1D2733; --muted:#5C6779;
    box-sizing:border-box; padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px);
  }
  *{box-sizing:inherit}
  html,body{margin:0;background:var(--canvas);color:var(--text);font-family:'Inter',sans-serif;}
  h1,h2,h3{font-family:'Lora',serif; color:var(--navy); margin:0;}
  p{line-height:1.65; margin:0 0 12px;}
  .wrap{max-width:900px;margin:0 auto;padding-bottom:90px;}

  /* ==================== HEADER APLIKASI (ARSIKAS) ==================== */
  .header {
      height: 70px;
      background: #0B2545;
      color: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 30px;
      font-family: 'Inter', sans-serif;
  }

  .header-kanan { display: flex; align-items: center; gap: 20px; }

  .logo { font-size: 20px; font-weight: 800; color: #fff; }
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

  .user { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #fff; }

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

  @media (max-width: 900px) { .nav-utama { display: none; } }
  @media (max-width: 650px) { .header { padding: 0 15px; } }
  /* ==================== /HEADER APLIKASI ==================== */

  header.band{background:linear-gradient(180deg,var(--navy),var(--navy-2)); padding:28px 26px 34px;}
  .head-row{display:flex; align-items:center; justify-content:center; gap:22px; max-width:860px; margin:0 auto;}
  .badge{width:66px;height:66px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;
    background:radial-gradient(circle at 32% 28%,#1B3A66,#0B2545); border:2px solid var(--gold-soft);}
  .badge.shield{background:radial-gradient(circle at 32% 28%,#F4E9CE,#D9BE80); border-color:var(--navy-2);}
  h1.title{color:#fff; font-size:23px; text-align:center; line-height:1.35;}
  h1.title .sub{display:block; font-size:14px; font-weight:500; color:var(--gold-soft); font-family:'Inter',sans-serif; margin-top:6px; letter-spacing:.02em;}

  main{background:var(--paper); margin:-18px 20px 0; border-radius:14px; box-shadow:0 10px 30px rgba(11,37,69,.08); border:1px solid var(--line); padding:36px 40px 10px; position:relative;}
  @media (max-width:640px){ main{margin:-18px 10px 0; padding:26px 20px 10px;} }

  section{padding:22px 0; border-bottom:1px solid var(--line);}
  section:last-of-type{border-bottom:none;}
  section h2{font-size:18px; margin-bottom:14px;}
  section h3{font-size:15px; color:var(--text); margin:16px 0 6px;}

  .lead p{font-size:15px;}
  .lead b{color:var(--navy);}

  .kontrol{margin:14px 0; padding-left:16px; border-left:3px solid var(--gold);}
  .kontrol b{color:var(--navy); font-family:'Lora',serif; font-size:15px;}

  table{width:100%; border-collapse:collapse; font-size:13px; margin-top:6px;}
  th{background:var(--navy); color:#fff; font-weight:600; text-align:left; padding:9px 10px; font-size:12.5px;}
  td{padding:9px 10px; border-bottom:1px solid var(--line); vertical-align:top;}
  tbody tr:nth-child(even){background:#F7F9FB;}
  .lvl-table td:first-child{font-family:'Lora',serif; font-weight:600; color:var(--navy); white-space:nowrap;}
  .scroll-x{overflow-x:auto; border:1px solid var(--line); border-radius:8px;}

  ol.petunjuk{margin:0; padding-left:20px;}
  ol.petunjuk li{margin-bottom:10px; line-height:1.6; font-size:14.5px;}

  .disclaimer{background:#FBF7EC; border:1px solid var(--gold-soft); border-radius:10px; padding:16px 18px; font-size:13.5px; line-height:1.65; color:#4A4028;}
  .disclaimer p{margin-bottom:10px;}

  .changelog{font-size:13.5px;}
  .ver{margin-bottom:14px;}
  .ver .tag{font-family:'IBM Plex Mono',monospace; background:var(--navy); color:#fff; font-size:11.5px; padding:2px 8px; border-radius:5px; margin-right:8px;}
  .ver ul{margin:6px 0 0 18px; padding:0; color:var(--muted);}
  .ver ul li{margin-bottom:3px;}

  .count-table td, .count-table th{text-align:center;}
  .count-table td:first-child, .count-table th:first-child{text-align:left;}
  .count-table .indent{padding-left:22px; color:var(--muted); font-weight:400;}
  .count-table .strong{font-weight:700; color:var(--navy);}
  .rendah{color:#3A8A54;font-weight:600} .tinggi{color:#B8862E;font-weight:600} .strategis{color:#B33A3A;font-weight:600}

  /* Excel-style sheet tabs */
  .tabbar{position:fixed; left:0; right:0; bottom:0; background:#1B2530; border-top:1px solid #2B3644;
    display:flex; gap:6px; padding:9px 16px; overflow-x:auto; z-index:20;}
  .tab{flex:none; font-size:12.5px; font-weight:600; padding:7px 14px; border-radius:6px 6px 0 0; cursor:pointer; color:#0B1017; white-space:nowrap;}
  .tab.active{background:#fff; color:var(--navy); box-shadow:0 -2px 0 var(--gold) inset;}
  .tab.pengantar{background:#DDE3EA;color:#1D2733;}
  .tab.responden{background:#F5D949;}
  .tab.ident{background:#4C8DE0;color:#fff;}
  .tab.prot{background:#8B4FCC;color:#fff;}
  .tab.det{background:#6E2740;color:#fff;}
  .tab.gulih{background:#C7712E;color:#fff;}
  .tab.sandi{background:#5A6472;color:#fff;}
  .tab.tkk{background:#1F5C3B;color:#fff;}
  .tab.tkp{background:#3FA857;color:#fff;}

  .toast{position:fixed; bottom:58px; left:50%; transform:translateX(-50%) translateY(8px); background:var(--navy); color:#fff;
    font-size:13px; padding:9px 16px; border-radius:8px; opacity:0; pointer-events:none; transition:opacity .2s, transform .2s;}
  .toast.show{opacity:1; transform:translateX(-50%) translateY(0);}
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
    <a href="instrumen_ikasandi.php" class="active">Instrumen IKASANDI</a>
    <a href="pengisian_ikasandi.php">Pengisian IKASANDI</a>
    <a href="data_responden.php">Data Responden</a>
    <a href="rekap_ikasandi.php">Rekap IKASANDI</a>
    <?php if ($role === 'admin'): ?>
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

<header class="band">
  <div class="head-row">
    <div class="badge" aria-hidden="true">
      <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#E7D6AE" stroke-width="1.5"><path d="M12 2l8 3v6c0 5-3.5 8.5-8 11-4.5-2.5-8-6-8-11V5l8-3Z"/><path d="M9 12l2 2 4-4.5"/></svg>
    </div>
    <h1 class="title">Instrumen Pengukuran Tingkat Kematangan<br>Keamanan Siber dan Sandi
      <span class="sub">IKASANDI · Badan Siber dan Sandi Negara</span>
    </h1>
    <div class="badge shield" aria-hidden="true">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0B2545" stroke-width="1.6"><path d="M12 2.5l7.5 2.7v6c0 5.3-3.2 9-7.5 10.3-4.3-1.3-7.5-5-7.5-10.3v-6L12 2.5Z"/><path d="M12 8v6M9 11h6"/></svg>
    </div>
  </div>
</header>

<div class="wrap">
<main>

  <section class="lead">
    <p>Merujuk pada pasal 15 UU ITE dan Pasal 3 PP 71 tahun 2019, dimana menyebutkan bahwa setiap Penyelenggara Sistem Elektronik (PSE) harus menyelenggarakan Sistem Elektronik (SE) secara andal dan aman serta bertanggung jawab terhadap beroperasinya SE. Guna memberikan panduan bagi PSE dalam memenuhi aspek operasi yang andal dan aman tersebut, BSSN memberikan panduan dalam bentuk kontrol kematangan keamanan siber dan sandi.</p>
    <p><b>IKASANDI</b> merupakan instrumen yang digunakan untuk mengukur Tingkat Kematangan Keamanan Siber dan Sandi sebuah organisasi atau Penyelenggara PSE. Instrumen IKASANDI memiliki dua bagian pengukuran, yaitu Pengukuran Tingkat Kematangan Keamanan Siber dan Pengukuran Tingkat Kematangan Persandian.</p>
  </section>

  <section>
    <h2>A. Pengukuran Tingkat Kematangan Keamanan Siber</h2>
    <p>IKASANDI telah diselaraskan dengan mengacu pada Perban Nomor 10 Tahun 2023 tentang Pengukuran Tingkat Kematangan Keamanan Siber dengan penyesuaian kontrol keamanan sesuai kebutuhan. Selain itu, IKASANDI juga merupakan hasil pemetaan dari berbagai instrumen/alat bantu yaitu Indeks Keamanan Informasi (Indeks KAMI), Cyber Security Maturity (CSM), Tingkat Maturitas Penanganan Insiden Siber (TMPI), dan CIS Critical Security Controls versi 8 (CIS Controls v8). IKASANDI dapat digunakan baik untuk PSE yang menyelenggarakan IIV sesuai Perban 8 Tahun 2023 (kategori Strategis) maupun PSE non-IIV (kategori non-Strategis: Tinggi dan Rendah), dengan penerapan Kontrol Keamanan meliputi:</p>

    <div class="kontrol"><b>Identifikasi</b> — mencakup inventarisasi aset, penilaian risiko, dan analisis ancaman; merupakan tahap awal yang sangat penting untuk mengetahui apa yang perlu dilindungi dan memahami potensi risiko. Tanpa pemahaman yang kuat tentang aset dan risiko, langkah perlindungan dan deteksi tidak akan efektif. Identifikasi digunakan untuk perencanaan strategis.</div>
    <div class="kontrol"><b>Proteksi</b> — berfokus pada implementasi kontrol untuk melindungi sistem dan data dari ancaman, mencakup kontrol akses, enkripsi, dan kebijakan keamanan. Alokasi yang signifikan untuk proteksi penting karena merupakan lini pertama pertahanan terhadap ancaman dan berfungsi mengurangi kemungkinan terjadinya insiden.</div>
    <div class="kontrol"><b>Deteksi</b> — melibatkan pemantauan, analisis log, dan penggunaan alat untuk mengidentifikasi aktivitas mencurigakan atau pelanggaran. Deteksi yang efektif adalah kunci untuk mengetahui dan merespons insiden keamanan dengan cepat, sebelum menyebabkan kerusakan signifikan.</div>
    <div class="kontrol"><b>Penanggulangan dan Pemulihan</b> — mencakup tanggapan dan mitigasi dampak dari insiden yang telah terdeteksi, meliputi isolasi sistem yang terpengaruh, perbaikan, dan pemulihan, agar dampak insiden dapat diminimalkan dan operasional pulih secara cepat.</div>
  </section>

  <section>
    <h2>B. Pengukuran Tingkat Kematangan Persandian</h2>
    <p>Untuk bagian pengukuran Tingkat Kematangan Persandian, IKASANDI diselaraskan dengan mengacu pada Perka Lemsaneg Nomor 6 Tahun 2016 dan Perka BSSN Nomor 1 Tahun 2023, dengan penerapan kontrol evaluasi persandian pada 5 area evaluasi meliputi:</p>
    <div class="kontrol"><b>Kebijakan Pengamanan Informasi</b> — mengukur tingkat kepatuhan dan pemenuhan kondisi terhadap penyusunan kebijakan terkait rencana strategis Pengamanan Informasi, arsitektur Keamanan Informasi, dan tata kelola Keamanan Informasi.</div>
    <div class="kontrol"><b>Pengelolaan Sumber Daya Keamanan Informasi</b> — mengukur kepatuhan dalam pelaksanaan pengelolaan sumber daya Keamanan Informasi, meliputi aset teknologi informasi dan komunikasi serta materiil sandi, pengelolaan sumber daya manusia, dan manajemen pengetahuan.</div>
    <div class="kontrol"><b>Pengamanan Sistem Elektronik dan Pengamanan Informasi Nonelektronik</b> — mengukur tingkat kepatuhan terhadap pengamanan Sistem Elektronik dan pengamanan informasi nonelektronik.</div>
    <div class="kontrol"><b>Layanan Keamanan Informasi</b> — mengukur kepatuhan terhadap penetapan dan pelaksanaan evaluasi program kerja Layanan Keamanan Informasi.</div>
    <div class="kontrol"><b>Pola Hubungan Komunikasi Sandi</b> — mengukur kepatuhan terhadap pelaksanaan identifikasi dan analisis pola hubungan komunikasi sandi.</div>
    <p style="margin-top:14px">Hasil penilaian menggunakan instrumen ini diharapkan dapat menggambarkan kondisi pengelolaan keamanan siber dan sandi yang saat ini terjadi pada organisasi atau PSE responden, guna melakukan peningkatan tingkat kematangan keamanan siber dan sandi secara terstruktur dan terarah.</p>
  </section>

  <section>
    <h2>Indikator Evaluasi Penerapan Domain Kerangka Kerja dan Kategori Kegiatan</h2>
    <div class="scroll-x">
    <table class="lvl-table">
      <thead><tr><th>Level</th><th>Kategori</th><th>Rentang Indeks</th><th>Deskripsi Umum</th><th>Organisasi &amp; Konsistensi</th><th>Dokumen</th></tr></thead>
      <tbody>
        <tr><td>Level 1</td><td>Level Awal</td><td>0 – 1,50</td><td>Implementasi awal; Keamanan Siber dan Sandi bersifat informal dan belum terorganisir</td><td>Tidak dilakukan secara konsisten dan berkelanjutan</td><td>Belum disusun</td></tr>
        <tr><td>Level 2</td><td>Level Berulang</td><td>1,51 – 2,50</td><td>Implementasi berulang; prosedur sudah terorganisir namun masih bersifat informal</td><td>Dilakukan berulang, namun belum konsisten dan belum berkelanjutan</td><td>Sudah disusun, belum ditetapkan</td></tr>
        <tr><td>Level 3</td><td>Level Terdefinisi</td><td>2,51 – 3,50</td><td>Implementasi terdefinisi dengan baik dan terorganisir jelas; bersifat formal</td><td>Dilakukan berulang dan konsisten, direviu secara berkala</td><td>Sudah disusun dan ditetapkan</td></tr>
        <tr><td>Level 4</td><td>Level Terkelola</td><td>3,51 – 4,50</td><td>Implementasi terkelola dengan baik, namun belum terotomatisasi; bersifat formal</td><td>Dilakukan berulang; perbaikan dilakukan berkelanjutan</td><td>Sudah disusun dan ditetapkan</td></tr>
        <tr><td>Level 5</td><td>Level Inovatif</td><td>4,51 – 5,0</td><td>Implementasi optimal; terorganisir baik dan telah terotomatisasi; bersifat formal</td><td>Berulang, konsisten, terintegrasi, dan menjadi budaya organisasi</td><td>Sudah ditetapkan</td></tr>
      </tbody>
    </table>
    </div>
  </section>

  <section>
    <h2>Petunjuk Penggunaan Instrumen</h2>
    <ol class="petunjuk">
      <li>Instrumen ini dapat digunakan oleh institusi atau PSE untuk mendapatkan gambaran mengenai kematangan keamanan siber dan sandi yang dijalankannya.</li>
      <li>Instrumen ini dianjurkan diisi oleh pejabat yang secara langsung bertanggung jawab dan berwenang mengelola keamanan siber dan sandi di seluruh cakupan organisasinya.</li>
      <li>Proses pengukuran dilakukan melalui evaluasi kondisi penerapan kontrol kerangka kerja pada masing-masing domain.</li>
      <li>Kondisi penerapan kontrol dibagi menjadi rentang indeks Nilai Kematangan dari indeks 0 sampai dengan indeks 5 pada masing-masing kontrol.</li>
      <li>Responden diminta menentukan Target Level Kematangan, Target Nilai Kematangan, dan Kategori SE. Kategori SE menentukan kontrol mana yang perlu dijawab, direpresentasikan dengan warna: Hijau (Rendah), Kuning (Tinggi), Merah (Strategis). Kontrol tanpa warna tidak perlu dijawab.</li>
      <li>Responden selanjutnya mengisi Indeks Nilai Kematangan sesuai kondisi penerapan kontrol pada cakupan organisasinya.</li>
      <li>Kontrol dibagi ke dalam domain Kamsiber: Identifikasi (sheet "I Identifikasi"), Proteksi ("II Proteksi"), Deteksi ("III Deteksi"), Penanggulangan &amp; Pemulihan ("IV Gulih"), dan Area Persandian ("Persandian").</li>
      <li>Hasil pengisian responden ditampilkan pada sheet "Tingkat Kematangan KamSiber" dan "Tingkat Kematangan Persandian".</li>
    </ol>
  </section>

  <section>
    <h2>Disclaimer</h2>
    <div class="disclaimer">
      <p>IKASANDI disediakan sebagai alat bantu untuk mengukur dan mengevaluasi tingkat kematangan keamanan siber dan sandi organisasi Anda. Harap diperhatikan hal-hal berikut:</p>
      <p><b>a) Tidak ada jaminan keamanan</b> — pemenuhan seluruh kontrol tidak menghilangkan risiko celah keamanan, baik dari sisi konsistensi kepatuhan operasional maupun serangan 0-day, namun diharapkan meminimalkan risiko dan mempersingkat waktu mitigasi.</p>
      <p><b>b) Tidak ada jaminan bebas kesalahan</b> — hasil penilaian sebaiknya digunakan sebagai panduan umum, bukan saran profesional atau keputusan akhir.</p>
      <p><b>c) Tidak bertanggung jawab atas kerugian</b> — pengguna bertanggung jawab penuh atas penerapan hasil penilaian dan keputusan yang diambil berdasarkannya.</p>
      <p><b>d) Kustomisasi dan penyesuaian</b> — instrumen mungkin memerlukan penyesuaian lebih lanjut sesuai konteks organisasi; disarankan berkonsultasi dengan profesional keamanan siber.</p>
      <p><b>e) Kepatuhan dan regulasi</b> — penggunaan instrumen ini tidak menjamin kepatuhan terhadap standar keamanan siber yang berlaku.</p>
      <p><b>f) Kerja sama tim</b> — penilaian kematangan keamanan siber adalah upaya tim lintas pemangku kepentingan organisasi.</p>
      <p><b>g) Kerahasiaan &amp; penggunaan terbatas</b> — data yang dikumpulkan bersifat sensitif dan tidak akan dibagikan ke pihak ketiga tanpa persetujuan eksplisit, kecuali diwajibkan oleh hukum.</p>
      <p style="margin-bottom:0">Dengan menggunakan instrumen ini, Anda mengakui telah membaca, memahami, dan menyetujui syarat dan ketentuan dalam disclaimer ini.</p>
    </div>
  </section>

  <section>
    <h2>Catatan Perubahan</h2>
    <div class="changelog">
      <div class="ver"><span class="tag">v1.0</span>Rilis IKAS+
        <ul>
          <li>Merupakan kajian gabungan tools IKAMI, CSM, CIS v8.0, TMPI untuk mengukur kematangan keamanan siber</li>
          <li>Menyesuaikan kerangka kerja Perban 8 Tahun 2023</li>
        </ul>
      </div>
      <div class="ver"><span class="tag">v2.0</span>Perubahan nama menjadi IKASANDI
        <ul>
          <li>Pengukuran diperluas untuk Kematangan Keamanan Siber dan Sandi</li>
          <li>Dilakukan pemetaan dengan area persandian (evaluasi penerapan kontrol pada 5 area evaluasi)</li>
        </ul>
      </div>
      <div class="ver"><span class="tag">v2.0.1</span>20 Mei 2026
        <ul><li>Bug fix pada formula kalkulasi skor dengan level tingkat kematangan</li></ul>
      </div>
    </div>
  </section>

  <section>
    <h2>Rekapitulasi Jumlah Kontrol</h2>
    <div class="scroll-x">
    <table class="count-table">
      <thead><tr><th>Kontrol</th><th class="rendah">Rendah</th><th class="tinggi">Tinggi</th><th class="strategis">Strategis</th></tr></thead>
      <tbody>
        <tr><td>Kontrol Kamsiber</td><td>96</td><td>163</td><td>177</td></tr>
        <tr><td class="indent">Kontrol Identifikasi</td><td>31</td><td>56</td><td>59</td></tr>
        <tr><td class="indent">Kontrol Proteksi</td><td>52</td><td>75</td><td>79</td></tr>
        <tr><td class="indent">Kontrol Deteksi</td><td>4</td><td>9</td><td>12</td></tr>
        <tr><td class="indent">Kontrol Gulih</td><td>9</td><td>23</td><td>27</td></tr>
        <tr><td>Kontrol Persandian</td><td>39</td><td>39</td><td>39</td></tr>
        <tr><td class="indent">Irisan Kontrol Keamanan Siber &amp; Persandian</td><td>23</td><td>23</td><td>23</td></tr>
        <tr><td class="strong">TOTAL</td><td class="strong">112</td><td class="strong">179</td><td class="strong">193</td></tr>
      </tbody>
    </table>
    </div>
  </section>

</main>
</div>

</body>
</html>