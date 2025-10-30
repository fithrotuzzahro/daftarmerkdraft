<?php
session_start();
include 'config_db.php';

header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['NIK'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Insert Data Usaha
    $stmt = $pdo->prepare("INSERT INTO datausaha (
        nama_usaha, kel_desa, kecamatan, no_telp_perusahaan, 
        hasil_produk, jml_tenaga_kerja, kapasitas_produk, 
        omset_perbulan, wilayah_pemasaran
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $_POST['nama_usaha'],
        $_POST['kel_desa'],
        $_POST['kecamatan'],
        $_POST['no_telp_perusahaan'] ?? null,
        $_POST['hasil_produk'],
        $_POST['jml_tenaga_kerja'],
        $_POST['kapasitas_produk'],
        $_POST['omset_perbulan'],
        $_POST['wilayah_pemasaran']
    ]);
    
    $id_usaha = $pdo->lastInsertId();
    
    // 2. Insert Pendaftaran
    $stmt = $pdo->prepare("INSERT INTO pendaftaran (
        NIK, id_usaha, tgl_daftar, status_validasi, jenis_permohonan
    ) VALUES (?, ?, NOW(), 'Pengecekan Berkas', 'Pendaftaran Baru')