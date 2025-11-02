<?php
/**
 * NOTIFICATION HELPER - Simplified Version
 * File ini berisi fungsi helper untuk mengirim notifikasi
 * Disesuaikan dengan struktur update_status.php dan upload_lampiran.php yang sudah ada
 */

require_once 'config_db.php';

/**
 * Fungsi untuk menambahkan notifikasi ke database
 * 
 * @param string $nik_nip - NIK/NIP user yang akan menerima notifikasi
 * @param int $id_pendaftaran - ID pendaftaran terkait
 * @param string $email - Email user
 * @param string $deskripsi - Deskripsi notifikasi
 * @return bool - True jika berhasil, False jika gagal
 */
function sendNotification($nik_nip, $id_pendaftaran, $email, $deskripsi) {
    global $pdo;
    
    try {
        $query = "INSERT INTO notifikasi (NIK_NIP, id_pendaftaran, email, deskripsi, tgl_notif, is_read) 
                  VALUES (:nik_nip, :id_pendaftaran, :email, :deskripsi, NOW(), 0)";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            'nik_nip' => $nik_nip,
            'id_pendaftaran' => $id_pendaftaran,
            'email' => $email,
            'deskripsi' => $deskripsi
        ]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error adding notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Fungsi untuk mendapatkan data user (NIK dan Email) dari id_pendaftaran
 * 
 * @param int $id_pendaftaran - ID pendaftaran
 * @return array|null - Array berisi NIK_NIP dan email, atau null jika tidak ditemukan
 */
function getUserByPendaftaran($id_pendaftaran) {
    global $pdo;
    
    try {
        $query = "SELECT p.NIK as NIK_NIP, u.email, u.nama_lengkap 
                  FROM pendaftaran p 
                  INNER JOIN user u ON p.NIK = u.NIK_NIP 
                  WHERE p.id_pendaftaran = :id_pendaftaran";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute(['id_pendaftaran' => $id_pendaftaran]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user info: " . $e->getMessage());
        return null;
    }
}

/**
 * Fungsi untuk mendapatkan semua admin
 * Berguna untuk mengirim notifikasi ke admin
 * 
 * @return array - Array berisi data admin
 */
function getAllAdmins() {
    global $pdo;
    
    try {
        $query = "SELECT NIK_NIP, email, nama_lengkap FROM user WHERE role = 'Admin'";
        $stmt = $pdo->query($query);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting admins: " . $e->getMessage());
        return [];
    }
}

/**
 * Kirim notifikasi ke pemohon berdasarkan id_pendaftaran
 * 
 * @param int $id_pendaftaran - ID pendaftaran
 * @param string $deskripsi - Deskripsi notifikasi
 * @return bool - True jika berhasil
 */
function notifyPemohon($id_pendaftaran, $deskripsi) {
    $user = getUserByPendaftaran($id_pendaftaran);
    if (!$user) return false;
    
    return sendNotification(
        $user['NIK_NIP'],
        $id_pendaftaran,
        $user['email'],
        $deskripsi
    );
}

/**
 * Kirim notifikasi ke semua admin
 * 
 * @param int $id_pendaftaran - ID pendaftaran terkait
 * @param string $deskripsi - Deskripsi notifikasi
 * @return bool - True jika minimal 1 notifikasi terkirim
 */
function notifyAllAdmins($id_pendaftaran, $deskripsi) {
    $admins = getAllAdmins();
    $success_count = 0;
    
    foreach ($admins as $admin) {
        if (sendNotification($admin['NIK_NIP'], $id_pendaftaran, $admin['email'], $deskripsi)) {
            $success_count++;
        }
    }
    
    return $success_count > 0;
}

// ===== TEMPLATE NOTIFIKASI =====

/**
 * Template notifikasi untuk berbagai aktivitas
 */
class NotificationTemplates {
    
    // Untuk Pemohon
    public static function tidakBisaDifasilitasi($alasan) {
        return "Maaf, pendaftaran merek Anda tidak bisa difasilitasi. Alasan: {$alasan}";
    }
    
    public static function konfirmasiMerekAlternatif($merek_no, $alasan) {
        return "Merek Alternatif {$merek_no} Anda telah dipilih untuk difasilitasi. Alasan: {$alasan}. Mohon konfirmasi jika Anda ingin melanjutkan proses dengan merek ini.";
    }
    
    public static function suratKeteranganDifasilitasi() {
        return "Selamat! Merek Alternatif 1 (Utama) Anda telah disetujui untuk difasilitasi. Silakan lengkapi Surat Keterangan Difasilitasi yang tersedia di halaman Status Pendaftaran.";
    }
    
    public static function suratKeteranganTersedia() {
        return "Surat Keterangan Difasilitasi telah tersedia. Silakan download, tanda tangani di atas materai Rp 10.000, dan upload kembali surat tersebut di halaman Status Pendaftaran.";
    }
    
    public static function suratIKMTersedia() {
        return "Surat Keterangan IKM telah tersedia untuk didownload di halaman Status Pendaftaran.";
    }
    
    public static function buktiPendaftaranTersedia() {
        return "Bukti Pendaftaran merek Anda telah tersedia dan sudah diajukan ke Kementerian. Silakan download di halaman Status Pendaftaran.";
    }
    
    public static function sertifikatTerbit() {
        return "Selamat! Sertifikat merek Anda telah terbit dan DITERIMA oleh Kementerian. Silakan download di halaman Status Pendaftaran. Masa berlaku sertifikat adalah 10 tahun.";
    }
    
    public static function suratPenolakan() {
        return "Mohon maaf, permohonan merek Anda tidak dapat disetujui oleh Kementerian. Silakan download Surat Penolakan di halaman Status Pendaftaran untuk mengetahui alasan detail.";
    }
    
    // Untuk Admin
    public static function pemohonUploadSuratTTD($id_pendaftaran, $nama_pemohon) {
        return "Pemohon {$nama_pemohon} telah mengupload Surat Keterangan yang ditandatangani untuk pendaftaran #{$id_pendaftaran}. Silakan cek dan lanjutkan proses di halaman Detail Pendaftar.";
    }
    
    public static function pemohonKonfirmasiLanjut($id_pendaftaran, $nama_pemohon, $merek_no) {
        return "Pemohon {$nama_pemohon} telah mengkonfirmasi untuk melanjutkan proses dengan Merek Alternatif {$merek_no} pada pendaftaran #{$id_pendaftaran}. Silakan lanjutkan proses di halaman Detail Pendaftar.";
    }
    
    public static function pendaftaranBaru($id_pendaftaran, $nama_pemohon, $nama_usaha) {
        return "Pendaftaran merek baru #{$id_pendaftaran} dari {$nama_pemohon} ({$nama_usaha}) menunggu verifikasi. Silakan cek di halaman Daftar Pendaftaran.";
    }
}

// ===== FUNGSI SHORTCUT UNTUK KOMPATIBILITAS =====

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