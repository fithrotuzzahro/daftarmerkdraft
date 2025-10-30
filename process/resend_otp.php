<?php
header('Content-Type: application/json');
require_once 'config_db.php';
require_once 'config_email.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        sendResponse(false, 'Email harus diisi!');
    }

    // Cari user berdasarkan email
    $stmt = $pdo->prepare("
    SELECT NIK_NIP, nama_lengkap, email, is_verified 
    FROM user 
    WHERE email = ?
  ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(false, 'Email tidak ditemukan!');
    }

    if ($user['is_verified'] == 1) {
        sendResponse(false, 'Akun Anda sudah terverifikasi. Silakan login.');
    }

    // Generate OTP baru
    $otp = random_int(100000, 999999);
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Update OTP di database
    $stmt = $pdo->prepare("UPDATE user SET otp = ?, otp_expiry = ? WHERE NIK_NIP = ?");
    $stmt->execute([$otp, $otp_expiry, $user['NIK_NIP']]);

    // Kirim OTP via email
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $mail_host;
        $mail->SMTPAuth = true;
        $mail->Username = $mail_username;
        $mail->Password = $mail_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $mail_port;

        $mail->setFrom($mail_from, $mail_from_name);
        $mail->addAddress($email, $user['nama_lengkap']);

        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi OTP Baru - Disperindag Sidoarjo';
        $mail->Body = "
      <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px;'>
          <h2 style='color: #007bff; text-align: center;'>Verifikasi Akun Anda</h2>
          <p>Halo, <strong>{$user['nama_lengkap']}</strong></p>
          <p>Anda meminta kode OTP baru untuk verifikasi akun.</p>
          <p>Berikut adalah kode OTP baru Anda:</p>
          <div style='text-align: center; margin: 30px 0;'>
            <div style='background-color: #007bff; color: white; font-size: 32px; font-weight: bold; padding: 20px; border-radius: 8px; letter-spacing: 5px;'>
              $otp
            </div>
          </div>
          <p style='color: #d9534f;'><strong>⚠️ Kode ini berlaku selama 10 menit.</strong></p>
          <p style='color: #666; font-size: 12px; margin-top: 30px;'>
            Jika Anda tidak meminta kode ini, abaikan email ini.
          </p>
        </div>
      </div>
    ";

        $mail->send();

        sendResponse(true, 'Kode OTP baru telah dikirim ke email Anda!');
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        sendResponse(false, 'Gagal mengirim email OTP: ' . $mail->ErrorInfo);
    }
} catch (Exception $e) {
    error_log("Resend OTP error: " . $e->getMessage());
    sendResponse(false, 'Terjadi kesalahan sistem: ' . $e->getMessage());
}
