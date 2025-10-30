<?php
header('Content-Type: application/json');
require_once 'config_db.php';
require_once 'config_email.php';
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendError($message, $details = null) {
  echo json_encode([
    'success' => false, 
    'message' => $message,
    'details' => $details
  ]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  sendError('Method tidak diizinkan', 'Hanya menerima POST request');
}

try {
  // Ambil data dari form
  $nik = trim($_POST['nik'] ?? '');
  $nama_lengkap = trim($_POST['namaPemilik'] ?? '');
  $no_wa = trim($_POST['telepon'] ?? '');
  $kel_desa = trim($_POST['kel_desa'] ?? '');
  $rt_rw = trim($_POST['rt_rw'] ?? '');
  $kecamatan = trim($_POST['kecamatan'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password_plain = $_POST['password'] ?? '';

  // Validasi field wajib
  if (empty($nik) || empty($nama_lengkap) || empty($email) || empty($password_plain)) {
    sendError('Semua field wajib diisi!', 'Pastikan tidak ada field yang kosong');
  }

  // Validasi NIK
  if (strlen($nik) !== 16) {
    sendError('NIK tidak valid!', 'NIK harus 16 digit');
  }

  if (!ctype_digit($nik)) {
    sendError('NIK tidak valid!', 'NIK hanya boleh berisi angka');
  }

  // Validasi email
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError('Format email tidak valid!', 'Gunakan format email yang benar (contoh@email.com)');
  }

  // Validasi password
  if (strlen($password_plain) < 6) {
    sendError('Password terlalu pendek!', 'Password minimal 6 karakter');
  }

  // Validasi nomor WA
  if (!ctype_digit($no_wa)) {
    sendError('Nomor WhatsApp tidak valid!', 'Nomor hanya boleh berisi angka');
  }

  if (strlen($no_wa) < 10 || strlen($no_wa) > 13) {
    sendError('Nomor WhatsApp tidak valid!', 'Panjang nomor harus 10-13 digit');
  }

  // Hash password
  $password = password_hash($password_plain, PASSWORD_BCRYPT);

  // Cek email atau NIK sudah terdaftar
  $stmt = $pdo->prepare("SELECT email, NIK_NIP FROM user WHERE email = ? OR NIK_NIP = ?");
  $stmt->execute([$email, $nik]);
  $existing = $stmt->fetch();
  
  if ($existing) {
    if ($existing['email'] === $email) {
      sendError('Email sudah terdaftar!', 'Gunakan email lain atau login jika sudah memiliki akun');
    }
    if ($existing['NIK_NIP'] === $nik) {
      sendError('NIK sudah terdaftar!', 'Setiap NIK hanya dapat didaftarkan satu kali');
    }
  }

  // Validasi dan upload file KTP
  $foto_ktp = '';
  if (!isset($_FILES['fileKTP']) || $_FILES['fileKTP']['error'] !== UPLOAD_ERR_OK) {
    sendError('File KTP belum diunggah!', 'Pastikan Anda memilih file KTP');
  }

  $file_tmp = $_FILES['fileKTP']['tmp_name'];
  $file_size = $_FILES['fileKTP']['size'];
  $file_name_original = $_FILES['fileKTP']['name'];
  $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));

  // Validasi ukuran file
  if ($file_size > 1024 * 1024) {
    $size_mb = round($file_size / 1024 / 1024, 2);
    sendError('Ukuran file terlalu besar!', "Ukuran file: {$size_mb} MB. Maksimal 1 MB");
  }

  // Validasi tipe file
  $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
  if (!in_array($file_ext, $allowed_ext)) {
    sendError('Format file tidak didukung!', 'Hanya menerima file: PDF, JPG, JPEG, PNG');
  }

  // Validasi MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $file_tmp);
  finfo_close($finfo);
  
  $allowed_mime = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!in_array($mime, $allowed_mime)) {
    sendError('Tipe file tidak valid!', 'File yang diunggah bukan PDF atau gambar yang valid');
  }

  // Upload file dengan format ktp_NIK.extension
  $file_name = 'ktp_' . $nik . '.' . $file_ext;
  $target_dir = '../uploads/ktp/';
  
  if (!is_dir($target_dir)) {
    if (!mkdir($target_dir, 0777, true)) {
      sendError('Gagal membuat direktori upload!', 'Hubungi administrator');
    }
  }
  
  $target_file = $target_dir . $file_name;
  
  if (!move_uploaded_file($file_tmp, $target_file)) {
    sendError('Gagal mengunggah file!', 'Terjadi kesalahan saat menyimpan file');
  }
  
  $foto_ktp = 'uploads/ktp/' . $file_name;

  // Generate OTP
  $otp = random_int(100000, 999999);
  $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

  // Insert ke database
  $stmt = $pdo->prepare("
    INSERT INTO user (
      NIK_NIP, nama_lengkap, no_wa, kel_desa, rt_rw, kecamatan, 
      email, password, foto_ktp, otp, otp_expiry, is_verified, role, tanggal_buat
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Pemohon', NOW())
  ");
  
  if (!$stmt->execute([
    $nik, $nama_lengkap, $no_wa, $kel_desa, $rt_rw, $kecamatan, 
    $email, $password, $foto_ktp, $otp, $otp_expiry
  ])) {
    // Hapus file jika gagal insert
    if (file_exists($target_file)) {
      unlink($target_file);
    }
    sendError('Gagal menyimpan data!', 'Terjadi kesalahan database');
  }

  // Kirim OTP via email
  $mail = new PHPMailer(true);
  
  try {
    // Konfigurasi SMTP
    $mail->isSMTP();
    $mail->Host = $mail_host;
    $mail->SMTPAuth = true;
    $mail->Username = $mail_username;
    $mail->Password = $mail_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $mail_port;

    // Pengirim dan penerima
    $mail->setFrom($mail_from, $mail_from_name);
    $mail->addAddress($email, $nama_lengkap);

    // Konten email
    $mail->isHTML(true);
    $mail->Subject = 'Kode Verifikasi OTP - Disperindag Sidoarjo';
    $mail->Body = "
      <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px;'>
          <h2 style='color: #007bff; text-align: center;'>Verifikasi Akun Anda</h2>
          <p>Halo, <strong>$nama_lengkap</strong></p>
          <p>Terima kasih telah mendaftar di <strong>Disperindag Sidoarjo</strong>.</p>
          <p>Berikut adalah kode OTP Anda untuk verifikasi akun:</p>
          <div style='text-align: center; margin: 30px 0;'>
            <div style='background-color: #007bff; color: white; font-size: 32px; font-weight: bold; padding: 20px; border-radius: 8px; letter-spacing: 5px;'>
              $otp
            </div>
          </div>
          <p style='color: #d9534f;'><strong>⚠️ Kode ini berlaku selama 10 menit.</strong></p>
          <p style='color: #666; font-size: 12px; margin-top: 30px;'>
            Jika Anda tidak melakukan registrasi, abaikan email ini.
          </p>
        </div>
      </div>
    ";

    $mail->send();
    
    echo json_encode([
      'success' => true, 
      'email' => $email,
      'message' => 'Registrasi berhasil! Silakan cek email Anda untuk kode OTP.'
    ]);
    
  } catch (Exception $e) {
    error_log("Email error: " . $mail->ErrorInfo);
    
    // Hapus user jika gagal kirim email
    $stmt = $pdo->prepare("DELETE FROM user WHERE email = ?");
    $stmt->execute([$email]);
    
    // Hapus file
    if (file_exists($target_file)) {
      unlink($target_file);
    }
    
    sendError(
      'Gagal mengirim email OTP!', 
      'Pastikan email Anda valid dan coba lagi. Error: ' . $mail->ErrorInfo
    );
  }

} catch (Exception $e) {
  error_log("Registration error: " . $e->getMessage());
  sendError('Terjadi kesalahan sistem!', $e->getMessage());
}