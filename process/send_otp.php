<?php
// PHPMailer-based OTP sender
// Requirements: PHPMailer via Composer (vendor/autoload.php) dan kredensial SMTP via environment variables.
// Env yang dibutuhkan: SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE (tls/ssl), SMTP_FROM_EMAIL, SMTP_FROM_NAME

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
header('Content-Type: application/json');

// Rate limit sederhana per session
if (!isset($_SESSION['otp_send_count'])) {
  $_SESSION['otp_send_count'] = 0;
}
if ($_SESSION['otp_send_count'] > 20) {
  echo json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan OTP. Coba nanti.']);
  exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
if (!$email) {
  echo json_encode(['success' => false, 'message' => 'Email tidak valid.']);
  exit;
}

// Pastikan autoload PHPMailer tersedia
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
  require $autoloadPath;
} else {
  // Fallback tanpa Composer: pastikan Anda menaruh folder "phpmailer/src" berisi PHPMailer.php, SMTP.php, Exception.php
  $phpMailerBase = __DIR__ . '/../phpmailer/src';
  if (file_exists($phpMailerBase . '/PHPMailer.php')) {
    require $phpMailerBase . '/PHPMailer.php';
    require $phpMailerBase . '/SMTP.php';
    require $phpMailerBase . '/Exception.php';
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'PHPMailer belum terpasang. Tambahkan vendor/autoload.php (Composer) atau letakkan folder phpmailer/src bersebelahan dengan folder process/.'
    ]);
    exit;
  }
}

$config = [];
$configPath = __DIR__ . '/mail_config.php';
if (file_exists($configPath)) {
  $loaded = require $configPath;
  if (is_array($loaded)) {
    $config = $loaded;
  }
}

$host     = $config['host']        ?? (getenv('SMTP_HOST') ?: '');
$port     = $config['port']        ?? (getenv('SMTP_PORT') ?: '');
$user     = $config['username']    ?? (getenv('SMTP_USER') ?: '');
$pass     = $config['password']    ?? (getenv('SMTP_PASS') ?: '');
$secure   = $config['secure']      ?? (getenv('SMTP_SECURE') ?: 'tls'); // tls atau ssl
$from     = $config['from_email']  ?? (getenv('SMTP_FROM_EMAIL') ?: 'no-reply@example.com');
$fromName = $config['from_name']   ?? (getenv('SMTP_FROM_NAME') ?: 'Disperindag Sidoarjo');

// Validasi konfigurasi dasar
if (!$host || !$port || !$user || !$pass) {
  echo json_encode([
    'success' => false,
    'message' => 'Konfigurasi SMTP belum lengkap. Set di process/mail_config.php atau ENV: SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS.'
  ]);
  exit;
}

// Generate OTP 6 digit
$otp = random_int(100000, 999999);

// Simpan ke session (hash + kedaluwarsa 5 menit)
$_SESSION['otp_email']       = $email;
$_SESSION['otp_hash']        = password_hash((string)$otp, PASSWORD_DEFAULT);
$_SESSION['otp_expires_at']  = time() + 5 * 60; // 5 menit
$_SESSION['otp_send_count'] += 1;

try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = $host;
  $mail->SMTPAuth   = true;
  $mail->Username   = $user;
  $mail->Password   = $pass;
  $mail->SMTPSecure = $secure; // 'tls' atau 'ssl'
  $mail->Port       = (int)$port;

  $mail->setFrom($from, $fromName);
  $mail->addAddress($email);

  $mail->isHTML(false);
  $mail->Subject = 'Kode OTP Registrasi';
  $mail->Body    = "Halo,\n\nKode OTP Anda adalah: {$otp}\nKode berlaku 5 menit.\n\nTerima kasih.";

  $mail->send();

  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'Gagal mengirim email OTP: ' . $e->getMessage(),
  ]);
}
