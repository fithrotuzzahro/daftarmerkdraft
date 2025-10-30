<?php
header('Content-Type: application/json');
require_once 'config_db.php';

function sendResponse($success, $message)
{
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

try {
    // Ambil data dari request
    $email = trim($_POST['email'] ?? '');
    $otp = trim($_POST['otp'] ?? '');

    // Validasi input
    if (empty($email) || empty($otp)) {
        sendResponse(false, 'Email dan OTP harus diisi!');
    }

    // Validasi format OTP
    if (strlen($otp) !== 6) {
        sendResponse(false, 'Kode OTP harus 6 digit!');
    }

    if (!ctype_digit($otp)) {
        sendResponse(false, 'Kode OTP hanya boleh berisi angka!');
    }

    // Cari user dengan email dan OTP yang sesuai
    $stmt = $pdo->prepare("
    SELECT NIK_NIP, nama_lengkap, otp, otp_expiry, is_verified 
    FROM user 
    WHERE email = ?
  ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Cek apakah sudah terverifikasi
    if ($user['is_verified'] == 1) {
        sendResponse(false, 'Akun Anda sudah terverifikasi. Silakan login.');
    }

    // Cek apakah OTP sudah kedaluwarsa
    $now = new DateTime();
    $expiry = new DateTime($user['otp_expiry']);

    if ($now > $expiry) {
        sendResponse(false, 'Kode OTP sudah kedaluwarsa! Silakan registrasi ulang atau hubungi administrator.');
    }

    // Cek apakah OTP cocok
    if ($user['otp'] !== $otp) {
        sendResponse(false, 'Kode OTP salah! Silakan periksa kembali kode yang dikirim ke email Anda.');
    }

    // Update status verifikasi
    $stmt = $pdo->prepare("
    UPDATE user 
    SET is_verified = 1, otp = NULL, otp_expiry = NULL 
    WHERE NIK_NIP = ?
  ");

    if (!$stmt->execute([$user['NIK_NIP']])) {
        sendResponse(false, 'Gagal memverifikasi akun! Silakan coba lagi.');
    }

    // Log aktivitas (opsional)
    error_log("User verified successfully: " . $email);

    sendResponse(true, 'Verifikasi berhasil! Akun Anda telah aktif. Anda akan dialihkan ke halaman login...');
} catch (Exception $e) {
    error_log("OTP Verification error: " . $e->getMessage());
    sendResponse(false, 'Terjadi kesalahan sistem: ' . $e->getMessage());
}
if (!$user) {
    sendResponse(false, 'Email tidak ditemukan dalam sistem!');
}

  // Cek apakah user ditemukan