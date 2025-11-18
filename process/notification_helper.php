<?php

require_once 'config_db.php';

// ===== KONFIGURASI FONNTE =====
define('FONNTE_TOKEN', 'RVwjvMqkCEySULGE92BM'); // Ganti dengan token Fonnte Anda
define('FONNTE_API_URL', 'https://api.fonnte.com/send');
define('ENABLE_WHATSAPP', true); // Set false untuk disable notifikasi WA sementara

function sendWhatsApp($target, $message) {
    if (!ENABLE_WHATSAPP) {
        return ['status' => 'disabled', 'message' => 'WhatsApp notification disabled'];
    }
    
    // Format nomor WhatsApp - Fonnte hanya terima format 08xxx
    $original_target = $target;
    $target = preg_replace('/[^0-9]/', '', $target);
    
    // Konversi dari format 62xxx ke 08xxx (WAJIB untuk Fonnte)
    if (substr($target, 0, 2) === '62') {
        $target = '0' . substr($target, 2);
    } 
    // Jika tidak ada awalan sama sekali, tambahkan 0
    elseif (substr($target, 0, 1) !== '0') {
        $target = '0' . $target;
    }
    
    // Validasi format nomor Indonesia (harus dimulai dengan 08)
    if (substr($target, 0, 2) !== '08') {
        error_log("❌ INVALID PHONE FORMAT");
        error_log("   Original: " . $original_target);
        error_log("   Converted: " . $target);
        return ['status' => false, 'message' => 'Nomor telepon harus format Indonesia (08xxx)'];
    }
    
    // Logging detail untuk debugging
    error_log("========================================");
    error_log("📤 SENDING WHATSAPP MESSAGE");
    error_log("========================================");
    error_log("Original Number: " . $original_target);
    error_log("Formatted Number: " . $target);
    error_log("Message Length: " . strlen($message) . " chars");
    error_log("Message Preview: " . substr($message, 0, 100) . "...");
    error_log("Fonnte Token: " . substr(FONNTE_TOKEN, 0, 10) . "...");
    error_log("API URL: " . FONNTE_API_URL);
    error_log("----------------------------------------");
    
    $curl = curl_init();
    
    // POST Data sesuai format Fonnte terbaru
    $postData = array(
        'target' => $target,
        'message' => $message,
        'countryCode' => '62'
    );
    
    error_log("POST Data: " . json_encode($postData));
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => FONNTE_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . FONNTE_TOKEN
        ),
        // Temporary SSL fix - GANTI dengan cacert.pem untuk production!
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_info = curl_getinfo($curl);
    
    // Check for cURL errors
    if (curl_errno($curl)) {
        $error_msg = curl_error($curl);
        error_log("----------------------------------------");
        error_log("❌ cURL ERROR");
        error_log("----------------------------------------");
        error_log("Error: " . $error_msg);
        error_log("========================================\n");
        
        curl_close($curl);
        
        return [
            'status' => false, 
            'message' => $error_msg,
            'debug' => [
                'original_number' => $original_target,
                'formatted_number' => $target,
                'http_code' => $httpcode,
                'error_type' => 'curl_error'
            ]
        ];
    }
    
    curl_close($curl);
    
    // Log response lengkap dengan detail
    error_log("----------------------------------------");
    error_log("📥 RESPONSE FROM FONNTE");
    error_log("----------------------------------------");
    error_log("HTTP Code: " . $httpcode);
    error_log("Response Body: " . ($response ?: '(empty)'));
    error_log("Connection Info:");
    error_log("  - Total Time: " . $curl_info['total_time'] . "s");
    error_log("  - Size Download: " . $curl_info['size_download'] . " bytes");
    error_log("========================================\n");
    
    if (!$response) {
        return [
            'status' => false, 
            'message' => 'Empty response from Fonnte',
            'debug' => [
                'original_number' => $original_target,
                'formatted_number' => $target,
                'http_code' => $httpcode,
                'curl_info' => $curl_info
            ]
        ];
    }
    
    $result = json_decode($response, true);
    
    if ($result === null) {
        error_log("❌ JSON DECODE ERROR: " . json_last_error_msg());
        return [
            'status' => false, 
            'message' => 'Invalid JSON response', 
            'raw' => $response,
            'json_error' => json_last_error_msg(),
            'debug' => [
                'original_number' => $original_target,
                'formatted_number' => $target,
                'http_code' => $httpcode
            ]
        ];
    }
    
    // Log hasil parsing
    error_log("Parsed Response: " . json_encode($result, JSON_PRETTY_PRINT));
    
    // Check if Fonnte returned success
    // Fonnte bisa return status true atau detail array
    $is_success = false;
    
    if (isset($result['status'])) {
        // Response format: {"status": true/false, "detail": [...]}
        $is_success = ($result['status'] === true || $result['status'] === 'true');
    } elseif (isset($result['detail']) && is_array($result['detail'])) {
        // Check detail array untuk status individual
        foreach ($result['detail'] as $detail) {
            if (isset($detail['status']) && ($detail['status'] === 'success' || $detail['status'] === 'sent')) {
                $is_success = true;
                break;
            }
        }
    }
    
    if ($is_success) {
        error_log("✅ WhatsApp SENT SUCCESSFULLY");
        return ['status' => true, 'data' => $result];
    } else {
        error_log("❌ WhatsApp FAILED");
        error_log("Reason: " . json_encode($result));
        
        // Extract error reason
        $error_reason = 'Unknown error';
        if (isset($result['reason'])) {
            $error_reason = $result['reason'];
        } elseif (isset($result['detail'][0]['message'])) {
            $error_reason = $result['detail'][0]['message'];
        } elseif (isset($result['message'])) {
            $error_reason = $result['message'];
        }
        
        return [
            'status' => false, 
            'data' => $result,
            'debug' => [
                'original_number' => $original_target,
                'formatted_number' => $target,
                'http_code' => $httpcode,
                'message' => $error_reason
            ]
        ];
    }
}

/**
 * Fungsi untuk menambahkan notifikasi ke database DAN kirim WhatsApp
 */
function sendNotification($nik_nip, $id_pendaftaran, $email, $deskripsi, $no_wa = null) {
    global $pdo;
    
    error_log("=== sendNotification Called ===");
    error_log("NIK: " . $nik_nip);
    error_log("ID Pendaftaran: " . $id_pendaftaran);
    error_log("Email: " . $email);
    error_log("No WA: " . ($no_wa ?? 'NULL'));
    error_log("Deskripsi: " . substr($deskripsi, 0, 50) . "...");
    
    $result = [
        'db' => false,
        'wa' => null
    ];
    
    try {
        // 1. Simpan ke database
        $query = "INSERT INTO notifikasi (NIK_NIP, id_pendaftaran, email, deskripsi, tgl_notif, is_read) 
                  VALUES (:nik_nip, :id_pendaftaran, :email, :deskripsi, NOW(), 0)";
        
        $stmt = $pdo->prepare($query);
        $result['db'] = $stmt->execute([
            'nik_nip' => $nik_nip,
            'id_pendaftaran' => $id_pendaftaran,
            'email' => $email,
            'deskripsi' => $deskripsi
        ]);
        
        error_log("Database insert: " . ($result['db'] ? 'SUCCESS' : 'FAILED'));
        
        // 2. Kirim WhatsApp jika nomor tersedia
        if ($no_wa && ENABLE_WHATSAPP) {
            error_log("Attempting to send WhatsApp to: " . $no_wa);
            
            // Format pesan WhatsApp - Lebih ringkas dan jelas
            $wa_message = "*INFORMASI STATUS PENDAFTARAN MEREK*\n\n";
            $wa_message .= "ID Pendaftaran: #" . $id_pendaftaran . "\n\n";
            $wa_message .= $deskripsi . "\n\n";
            $wa_message .= "Silakan login ke sistem untuk informasi lebih lanjut.\n\n";
            $wa_message .= "_Pesan otomatis dari Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo_";
            
            $result['wa'] = sendWhatsApp($no_wa, $wa_message);
            error_log("WhatsApp send result: " . json_encode($result['wa']));
        } else {
            if (!$no_wa) {
                error_log("WhatsApp SKIPPED: No phone number provided");
                $result['wa'] = ['status' => 'skipped', 'reason' => 'No phone number'];
            } elseif (!ENABLE_WHATSAPP) {
                error_log("WhatsApp SKIPPED: Feature disabled");
                $result['wa'] = ['status' => 'disabled', 'reason' => 'WhatsApp feature is disabled'];
            }
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error adding notification: " . $e->getMessage());
        return $result;
    }
}

/**
 * Fungsi untuk mendapatkan data user (NIK, Email, dan No WA) dari id_pendaftaran
 */
function getUserByPendaftaran($id_pendaftaran) {
    global $pdo;
    
    try {
        $query = "SELECT p.NIK as NIK_NIP, u.email, u.nama_lengkap, u.no_wa 
                  FROM pendaftaran p 
                  INNER JOIN user u ON p.NIK = u.NIK_NIP 
                  WHERE p.id_pendaftaran = :id_pendaftaran";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id_pendaftaran' => $id_pendaftaran]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log untuk debugging
        if ($user) {
            error_log("getUserByPendaftaran - Found user:");
            error_log("  - Nama: " . $user['nama_lengkap']);
            error_log("  - Email: " . $user['email']);
            error_log("  - No WA: " . ($user['no_wa'] ?? 'NULL'));
        } else {
            error_log("getUserByPendaftaran - User not found for ID: " . $id_pendaftaran);
        }
        
        return $user;
    } catch (PDOException $e) {
        error_log("Error getting user info: " . $e->getMessage());
        return null;
    }
}

/**
 * Fungsi untuk mendapatkan semua admin beserta nomor WhatsApp
 */
function getAllAdmins() {
    global $pdo;
    
    try {
        $query = "SELECT NIK_NIP, email, nama_lengkap, no_wa FROM user WHERE role = 'Admin'";
        $stmt = $pdo->query($query);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting admins: " . $e->getMessage());
        return [];
    }
}

/**
 * Kirim notifikasi ke pemohon berdasarkan id_pendaftaran (DB + WhatsApp)
 */
function notifyPemohon($id_pendaftaran, $deskripsi) {
    $user = getUserByPendaftaran($id_pendaftaran);
    if (!$user) return ['db' => false, 'wa' => null];
    
    return sendNotification(
        $user['NIK_NIP'],
        $id_pendaftaran,
        $user['email'],
        $deskripsi,
        $user['no_wa'] ?? null
    );
}

/** Kirim notifikasi ke semua admin (DB + WhatsApp) */
function notifyAllAdmins($id_pendaftaran, $deskripsi) {
    $admins = getAllAdmins();
    $db_success = 0;
    $wa_success = 0;
    
    foreach ($admins as $admin) {
        $result = sendNotification(
            $admin['NIK_NIP'], 
            $id_pendaftaran, 
            $admin['email'], 
            $deskripsi,
            $admin['no_wa'] ?? null
        );
        
        if ($result['db']) $db_success++;
        if ($result['wa'] && isset($result['wa']['status']) && $result['wa']['status'] === true) {
            $wa_success++;
        }
    }
    
    return [
        'db_count' => $db_success,
        'wa_count' => $wa_success,
        'total_admins' => count($admins)
    ];
}

// ===== TEMPLATE NOTIFIKASI =====

class NotificationTemplates {
    
    // Untuk Pemohon
    public static function tidakBisaDifasilitasi($alasan) {
        return "Maaf, pendaftaran merek Anda tidak bisa difasilitasi.\n\nAlasan: {$alasan}";
    }
    
    public static function konfirmasiMerekAlternatif($merek_no, $alasan) {
        return "Merek Alternatif {$merek_no} Anda telah dipilih untuk difasilitasi.\n\nAlasan: {$alasan}\n\nMohon konfirmasi jika Anda ingin melanjutkan proses dengan merek ini.";
    }
    
    public static function suratKeteranganDifasilitasi() {
        return "Selamat! Merek Alternatif 1 (Utama) Anda telah disetujui untuk difasilitasi.\n\nSilakan lengkapi Surat Keterangan Difasilitasi yang tersedia di halaman Status Pendaftaran.";
    }
    
    public static function suratKeteranganTersedia() {
        return "Surat Keterangan Difasilitasi telah tersedia.\n\nSilakan download, tanda tangani di atas materai Rp 10.000, dan upload kembali surat tersebut di halaman Status Pendaftaran.";
    }
    
    public static function suratIKMTersedia() {
        return "Surat Keterangan IKM telah tersedia untuk didownload di halaman Status Pendaftaran.";
    }
    
    public static function buktiPendaftaranTersedia() {
        return "Bukti Pendaftaran merek Anda telah tersedia dan sudah diajukan ke Kementerian.\n\nSilakan download di halaman Status Pendaftaran.";
    }
    
    public static function sertifikatTerbit() {
        return "Selamat! Sertifikat merek Anda telah terbit dan DITERIMA oleh Kementerian.\n\nSilakan download di halaman Status Pendaftaran. Masa berlaku sertifikat adalah 10 tahun.";
    }
    
    public static function suratPenolakan() {
        return "Mohon maaf, permohonan merek Anda tidak dapat disetujui oleh Kementerian.\n\nSilakan download Surat Penolakan di halaman Status Pendaftaran untuk mengetahui alasan detail.";
    }
    
    // Untuk Admin
    public static function pemohonUploadSuratTTD($id_pendaftaran, $nama_pemohon) {
        return "Pemohon {$nama_pemohon} telah mengupload Surat Keterangan yang ditandatangani untuk pendaftaran #{$id_pendaftaran}.\n\nSilakan cek dan lanjutkan proses di halaman Detail Pendaftar.";
    }
    
    public static function pemohonKonfirmasiLanjut($id_pendaftaran, $nama_pemohon, $merek_no) {
        return "Pemohon {$nama_pemohon} telah mengkonfirmasi untuk melanjutkan proses dengan Merek Alternatif {$merek_no} pada pendaftaran #{$id_pendaftaran}.\n\nSilakan lanjutkan proses di halaman Detail Pendaftar.";
    }
    
    public static function pendaftaranBaru($id_pendaftaran, $nama_pemohon, $nama_usaha) {
        return "Pendaftaran merek baru #{$id_pendaftaran} dari {$nama_pemohon} ({$nama_usaha}) menunggu verifikasi.\n\nSilakan cek di halaman Daftar Pendaftaran.";
    }
}


/**
 * Kirim notifikasi saat admin upload Surat Keterangan IKM
 */
function notifSuratKeteranganIKM($id_pendaftaran) {
    return notifyPemohon($id_pendaftaran, NotificationTemplates::suratIKMTersedia());
}

/**
 * Kirim notifikasi saat admin upload Bukti Pendaftaran
 */
function notifBuktiPendaftaran($id_pendaftaran) {
    return notifyPemohon($id_pendaftaran, NotificationTemplates::buktiPendaftaranTersedia());
}

/**
 * Kirim notifikasi saat admin upload Sertifikat (DITERIMA)
 */
function notifSertifikatMerek($id_pendaftaran) {
    return notifyPemohon($id_pendaftaran, NotificationTemplates::sertifikatTerbit());
}

/**
 * Kirim notifikasi saat admin upload Surat Penolakan (DITOLAK)
 */
function notifSuratPenolakan($id_pendaftaran) {
    return notifyPemohon($id_pendaftaran, NotificationTemplates::suratPenolakan());
}
?>