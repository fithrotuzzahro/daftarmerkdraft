<?php
session_start();
require_once 'config_db.php'; // koneksi PDO

header('Content-Type: application/json');

function sendResponse($success, $message)
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if (!isset($_SESSION['NIK_NIP'])) {
    sendResponse(false, 'Akses ditolak! Anda belum login.');
}

$nik_session = $_SESSION['NIK_NIP'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Metode tidak valid.');
}

$namaPemilik = trim($_POST['namaPemilik'] ?? '');
$nik = trim($_POST['NIK_NIP'] ?? '');
$rt_rw = trim($_POST['rt_rw'] ?? '');
$kel_desa = trim($_POST['kel_desa'] ?? '');
$kecamatan = trim($_POST['kecamatan'] ?? '');
$telepon = trim($_POST['telepon'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($namaPemilik) || empty($nik) || empty($telepon) || empty($email)) {
    sendResponse(false, 'Harap lengkapi semua field wajib.');
}

if (!preg_match('/^[0-9]{16}$/', $nik)) {
    sendResponse(false, 'Format NIK tidak valid. Harus 16 digit angka.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Format email tidak valid.');
}

$upload_dir = '../uploads/ktp/';
$foto_ktp = null;

if (isset($_FILES['fileKTP']) && $_FILES['fileKTP']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['fileKTP'];
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

    if (!in_array($file['type'], $allowed_types)) {
        sendResponse(false, 'Format file KTP tidak diperbolehkan. Gunakan JPG, PNG, atau PDF.');
    }

    if ($file['size'] > 1024 * 1024) {
        sendResponse(false, 'Ukuran file KTP terlalu besar. Maksimal 1 MB.');
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'ktp_' . $nik . '.' . $ext;
    $target_path = $upload_dir . $new_filename;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        sendResponse(false, 'Gagal mengunggah file KTP.');
    }

    $foto_ktp = 'uploads/ktp/' . $new_filename;
}

try {
    $update_fields = "
        nama_lengkap = :nama_lengkap,
        NIK_NIP = :nik,
        no_wa = :telepon,
        kel_desa = :kel_desa,
        kecamatan = :kecamatan,
        rt_rw = :rt_rw,
        email = :email,
        updated_at = NOW()
    ";

    $params = [
        ':nama_lengkap' => $namaPemilik,
        ':nik' => $nik,
        ':telepon' => $telepon,
        ':kel_desa' => $kel_desa,
        ':kecamatan' => $kecamatan,
        ':rt_rw' => $rt_rw,
        ':email' => $email,
        ':nik_session' => $nik_session
    ];

    if (!empty($password)) {
        $update_fields .= ", password = :password";
        $params[':password'] = password_hash($password, PASSWORD_BCRYPT);
    }

    if ($foto_ktp) {
        $update_fields .= ", foto_ktp = :foto_ktp";
        $params[':foto_ktp'] = $foto_ktp;
    }

    $stmt = $pdo->prepare("UPDATE user SET $update_fields WHERE NIK_NIP = :nik_session");
    $stmt->execute($params);

    // Setelah update berhasil:
if ($nik !== $nik_session) {
    $_SESSION['NIK_NIP'] = $nik;
}

session_destroy();
sendResponse(true, 'Profil berhasil diperbarui. Silakan login kembali.', [
    'redirect' => '../logout.php'
]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Gagal memperbarui profil: ' . $e->getMessage());
}