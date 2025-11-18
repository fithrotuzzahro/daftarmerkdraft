<?php
session_start();
require_once 'config_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $identifier = trim($_POST['identifier'] ?? '');
    $otp = trim($_POST['otp'] ?? '');

    // Validasi input
    if (empty($identifier)) {
        throw new Exception("Identifier tidak ditemukan.");
    }

    if (empty($otp)) {
        throw new Exception("Kode OTP wajib diisi.");
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
        throw new Exception("Kode OTP harus 6 digit angka.");
    }

    // Cari user berdasarkan identifier (email atau nomor WA)
    $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
    
    if ($is_email) {
        $stmt = $pdo->prepare("
            SELECT NIK_NIP, nama_lengkap, email, no_wa, role, otp, otp_expiry 
            FROM user 
            WHERE email = ?
        ");
        $stmt->execute([$identifier]);
    } else {
        // Normalize nomor telepon
        $phone = preg_replace('/\D/', '', $identifier);
        if (substr($phone, 0, 1) == '0') {
            $phone_08 = $phone;
        } elseif (substr($phone, 0, 2) == '62') {
            $phone_08 = '0' . substr($phone, 2);
        } else {
            $phone_08 = '0' . $phone;
        }
        
        $stmt = $pdo->prepare("
            SELECT NIK_NIP, nama_lengkap, email, no_wa, role, otp, otp_expiry 
            FROM user 
            WHERE no_wa = ?
        ");
        $stmt->execute([$phone_08]);
    }

    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("Data pengguna tidak ditemukan.");
    }

    // Cek apakah OTP ada
    if (empty($user['otp'])) {
        throw new Exception("Kode OTP tidak ditemukan. Silakan request OTP terlebih dahulu.");
    }

    // Cek apakah OTP sudah expired
    $now = new DateTime();
    $expiry = new DateTime($user['otp_expiry']);
    
    if ($now > $expiry) {
        throw new Exception("Kode OTP telah kedaluwarsa. Silakan request OTP baru.");
    }

    // Verifikasi OTP
    if (!password_verify($otp, $user['otp'])) {
        throw new Exception("Kode OTP salah. Silakan cek kembali WhatsApp Anda.");
    }

    // OTP BENAR - Hapus OTP dan login
    $stmt = $pdo->prepare("
        UPDATE user 
        SET otp = NULL, 
            otp_expiry = NULL,
            updated_at = NOW()
        WHERE NIK_NIP = ?
    ");
    $stmt->execute([$user['NIK_NIP']]);

    // Set session
    $_SESSION['NIK_NIP'] = $user['NIK_NIP'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['no_wa'] = $user['no_wa'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();

    // Tentukan redirect berdasarkan role
    $redirect = 'home.php';
    if ($user['role'] === 'Admin') {
        $redirect = 'dashboard-admin.php';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil!',
        'nama' => $user['nama_lengkap'],
        'role' => $user['role'],
        'redirect' => $redirect
    ]);

} catch (Exception $e) {
    error_log("OTP Login Verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}