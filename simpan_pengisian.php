<?php

session_start();

require_once 'koneksi.php';


/*
|--------------------------------------------------------------------------
| CEK REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: pengisian_ikasandi.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrf_session =
    $_SESSION['csrf_pengisian']
    ?? '';

$csrf_post =
    $_POST['csrf_token']
    ?? '';

if (
    empty($csrf_session) ||
    empty($csrf_post) ||
    !hash_equals(
        $csrf_session,
        $csrf_post
    )
) {

    die(
        'Token keamanan tidak valid.'
    );

}


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

$id_users = (int) (
    $_SESSION['id_users']
    ?? $_SESSION['user_id']
    ?? 1
);


$nama_session =
    $_SESSION['nama_lengkap']
    ?? $_SESSION['nama']
    ?? 'Nama Pengisi';


/*
|--------------------------------------------------------------------------
| DATA FORM
|--------------------------------------------------------------------------
*/

$topik_id =
    isset($_POST['topik_id'])
    ? (int) $_POST['topik_id']
    : 0;


$judul_topik =
    trim(
        $_POST['judul_topik']
        ?? ''
    );


$nama_pengisi =
    trim(
        $_POST['nama_pengisi']
        ?? $nama_session
    );


$tanggal_pengisian =
    $_POST['tanggal_pengisian']
    ?? date('Y-m-d');


$aksi =
    $_POST['aksi']
    ?? 'sementara';


$jawaban =
    $_POST['jawaban']
    ?? [];


$bukti =
    $_FILES['bukti']
    ?? [];


/*
|--------------------------------------------------------------------------
| VALIDASI
|--------------------------------------------------------------------------
*/

if ($topik_id <= 0) {

    die(
        'Topik IKASANDI tidak valid.'
    );

}


if ($judul_topik === '') {

    die(
        'Judul pengisian wajib diisi.'
    );

}


if ($nama_pengisi === '') {

    die(
        'Nama pengisi wajib diisi.'
    );

}


if (
    !in_array(
        $aksi,
        [
            'sementara',
            'submit'
        ],
        true
    )
) {

    $aksi = 'sementara';

}


/*
|--------------------------------------------------------------------------
| SIMPAN SESSION FORM
|--------------------------------------------------------------------------
*/

$_SESSION['judul_topik'] =
    $judul_topik;

$_SESSION['tanggal_pengisian'] =
    $tanggal_pengisian;


/*
|--------------------------------------------------------------------------
| FOLDER UPLOAD
|--------------------------------------------------------------------------
*/

$folder_upload =
    __DIR__
    . DIRECTORY_SEPARATOR
    . 'uploads'
    . DIRECTORY_SEPARATOR
    . 'ikasandi';


if (
    !is_dir(
        $folder_upload
    )
) {

    mkdir(
        $folder_upload,
        0755,
        true
    );

}


/*
|--------------------------------------------------------------------------
| TRANSACTION
|--------------------------------------------------------------------------
*/

mysqli_begin_transaction(
    $koneksi
);


try {


    /*
    |--------------------------------------------------------------------------
    | LOOP JAWABAN
    |--------------------------------------------------------------------------
    */

    foreach (
        $jawaban
        as $pertanyaan_id => $data
    ) {


        $pertanyaan_id =
            (int) $pertanyaan_id;


        if (
            $pertanyaan_id <= 0
        ) {

            continue;

        }


        /*
        |--------------------------------------------------------------------------
        | INDEKS
        |--------------------------------------------------------------------------
        */

        $indeks_nilai = null;


        if (
            isset(
                $data['indeks']
            )
            &&
            $data['indeks'] !== ''
        ) {

            $indeks_nilai =
                (int) $data['indeks'];


            if (
                $indeks_nilai < 0
                ||
                $indeks_nilai > 5
            ) {

                throw new Exception(
                    'Nilai indeks harus antara 0 sampai 5.'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDASI
        |--------------------------------------------------------------------------
        */

        $keterangan_validasi =
            trim(
                $data['validasi']
                ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | CEK DATA LAMA
        |--------------------------------------------------------------------------
        */

        $sql_cek = "
            SELECT
                id,
                bukti_dukung

            FROM pengisian_ikasandi

            WHERE id_users = ?

            AND topik_id = ?

            AND pertanyaan_id = ?

            LIMIT 1
        ";


        $stmt_cek =
            mysqli_prepare(
                $koneksi,
                $sql_cek
            );


        if (!$stmt_cek) {

            throw new Exception(
                mysqli_error($koneksi)
            );

        }


        mysqli_stmt_bind_param(
            $stmt_cek,
            'iii',
            $id_users,
            $topik_id,
            $pertanyaan_id
        );


        mysqli_stmt_execute(
            $stmt_cek
        );


        $result_cek =
            mysqli_stmt_get_result(
                $stmt_cek
            );


        $data_lama =
            mysqli_fetch_assoc(
                $result_cek
            );


        mysqli_stmt_close(
            $stmt_cek
        );


        $bukti_dukung =
            $data_lama['bukti_dukung']
            ?? null;


        /*
        |--------------------------------------------------------------------------
        | UPLOAD BUKTI
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $bukti['name'][$pertanyaan_id]
            )
            &&
            $bukti['error'][$pertanyaan_id]
            === UPLOAD_ERR_OK
        ) {


            $nama_file_asli =
                $bukti['name'][$pertanyaan_id];


            $tmp_file =
                $bukti['tmp_name'][$pertanyaan_id];


            $ukuran_file =
                (int)
                $bukti['size'][$pertanyaan_id];


            /*
            | Maksimal 10 MB
            */

            if (
                $ukuran_file
                >
                10 * 1024 * 1024
            ) {

                throw new Exception(
                    'Ukuran file bukti dukung maksimal 10 MB.'
                );

            }


            /*
            | Ambil ekstensi
            */

            $ekstensi =
                strtolower(
                    pathinfo(
                        $nama_file_asli,
                        PATHINFO_EXTENSION
                    )
                );


            $ekstensi_diizinkan = [

                'pdf',
                'jpg',
                'jpeg',
                'png',
                'doc',
                'docx',
                'xls',
                'xlsx',
                'txt',
                'csv'

            ];


            if (
                !in_array(
                    $ekstensi,
                    $ekstensi_diizinkan,
                    true
                )
            ) {

                throw new Exception(
                    'Format file bukti dukung tidak diizinkan.'
                );

            }


            /*
            | Buat nama file unik
            */

            $nama_file_baru =
                'ikasandi_'
                . $id_users
                . '_'
                . $topik_id
                . '_'
                . $pertanyaan_id
                . '_'
                . date('YmdHis')
                . '_'
                . bin2hex(
                    random_bytes(4)
                )
                . '.'
                . $ekstensi;


            $tujuan_file =
                $folder_upload
                . DIRECTORY_SEPARATOR
                . $nama_file_baru;


            if (
                !move_uploaded_file(
                    $tmp_file,
                    $tujuan_file
                )
            ) {

                throw new Exception(
                    'Gagal menyimpan file bukti dukung.'
                );

            }


            /*
            | Simpan nama file saja ke database
            */

            $bukti_dukung =
                $nama_file_baru;

        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        if (
            $aksi === 'submit'
        ) {

            $status =
                'menunggu_verifikasi';

        } else {

            $status =
                'sementara';

        }


        /*
        |--------------------------------------------------------------------------
        | INSERT / UPDATE
        |--------------------------------------------------------------------------
        */

        if ($data_lama) {


            /*
            | UPDATE
            */

            $sql_update = "

                UPDATE pengisian_ikasandi

                SET
                    judul_topik = ?,
                    nama_pengisi = ?,
                    tanggal_pengisian = ?,
                    indeks_nilai = ?,
                    keterangan_validasi = ?,
                    bukti_dukung = ?,
                    status = ?,
                    updated_at = NOW()

                WHERE id = ?

            ";


            $stmt =
                mysqli_prepare(
                    $koneksi,
                    $sql_update
                );


            if (!$stmt) {

                throw new Exception(
                    mysqli_error($koneksi)
                );

            }


            $id_pengisian =
                (int) $data_lama['id'];


            mysqli_stmt_bind_param(
                $stmt,
                'sssisssi',
                $judul_topik,
                $nama_pengisi,
                $tanggal_pengisian,
                $indeks_nilai,
                $keterangan_validasi,
                $bukti_dukung,
                $status,
                $id_pengisian
            );


        } else {


            /*
            | INSERT
            */

            $sql_insert = "

                INSERT INTO pengisian_ikasandi
                (
                    id_users,
                    topik_id,
                    pertanyaan_id,
                    judul_topik,
                    nama_pengisi,
                    tanggal_pengisian,
                    indeks_nilai,
                    keterangan_validasi,
                    bukti_dukung,
                    status,
                    created_at,
                    updated_at
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW(),
                    NOW()
                )

            ";


            $stmt =
                mysqli_prepare(
                    $koneksi,
                    $sql_insert
                );


            if (!$stmt) {

                throw new Exception(
                    mysqli_error($koneksi)
                );

            }


            mysqli_stmt_bind_param(
                $stmt,
                'iiisssisss',
                $id_users,
                $topik_id,
                $pertanyaan_id,
                $judul_topik,
                $nama_pengisi,
                $tanggal_pengisian,
                $indeks_nilai,
                $keterangan_validasi,
                $bukti_dukung,
                $status
            );

        }


        /*
        |--------------------------------------------------------------------------
        | EXECUTE
        |--------------------------------------------------------------------------
        */

        if (
            !mysqli_stmt_execute(
                $stmt
            )
        ) {

            throw new Exception(
                mysqli_stmt_error($stmt)
            );

        }


        mysqli_stmt_close(
            $stmt
        );

    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    mysqli_commit(
        $koneksi
    );


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    if (
        $aksi === 'submit'
    ) {

        header(
            'Location: pengisian_ikasandi.php'
            . '?kategori='
            . $topik_id
            . '&success=selesai'
        );

    } else {

        header(
            'Location: pengisian_ikasandi.php'
            . '?kategori='
            . $topik_id
            . '&success=sementara'
        );

    }


    exit;


} catch (
    Exception $e
) {


    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    mysqli_rollback(
        $koneksi
    );


    echo '

    <!DOCTYPE html>

    <html lang="id">

    <head>

        <meta charset="UTF-8">

        <title>
            Gagal Menyimpan
        </title>

        <style>

            body {
                font-family: Arial;
                background: #F4F7FB;
                padding: 40px;
            }

            .box {
                max-width: 600px;
                margin: auto;
                background: white;
                padding: 30px;
                border-radius: 15px;
                border: 1px solid #E2E8F0;
            }

            .error {
                color: #B42318;
                background: #FEF3F2;
                padding: 15px;
                border-radius: 8px;
            }

            a {
                display: inline-block;
                margin-top: 15px;
                padding: 10px 15px;
                background: #155EEF;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <h2>
                Data gagal disimpan
            </h2>

            <div class="error">
                '
                . htmlspecialchars(
                    $e->getMessage(),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '
            </div>

            <a href="pengisian_ikasandi.php?kategori='
                . $topik_id
                . '">
                Kembali ke Pengisian IKASANDI
            </a>

        </div>

    </body>

    </html>

    ';

}