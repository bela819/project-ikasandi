<?php
/**
 * Koneksi Database IKASANDI
 *
 * Database : ikasandi
 * Host     : localhost
 * User     : root
 * Password : kosong (default XAMPP)
 */

$host = "localhost";
$user = "root";
$password = "";
$database = "ikasandi";

/*
 * Membuat koneksi menggunakan MySQLi
 */
$koneksi = new mysqli(
    $host,
    $user,
    $password,
    $database
);

/*
 * Cek koneksi
 */
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

/*
 * Menggunakan UTF-8
 */
$koneksi->set_charset("utf8mb4");
?>
