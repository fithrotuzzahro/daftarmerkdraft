<?php
session_start();
include 'process/config_db.php';
date_default_timezone_set('Asia/Jakarta');


// Cek login
if (!isset($_SESSION['NIK_NIP'])) {
    header("Location: login.php");
    exit;
}

$NIK = $_SESSION['NIK_NIP'];
$nama = $_SESSION['nama_lengkap'];

// ===== HANDLER AJAX REQUEST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'];
    $id_pendaftaran = isset($_POST['id_pendaftaran']) ? intval($_POST['id_pendaftaran']) : 0;
    
    if (!$id_pendaftaran) {
        echo json_encode(['success' => false, 'message' => 'ID pendaftaran tidak valid']);
        exit;
    }
    
    try {
        // Verifikasi bahwa pendaftaran ini milik user yang login
        $stmt = $pdo->prepare("SELECT NIK FROM pendaftaran WHERE id_pendaftaran = ?");
        $stmt->execute([$id_pendaftaran]);
        $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pendaftaran || $pendaftaran['NIK'] !== $NIK) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        
        // HANDLE KONFIRMASI LANJUT (dari Merek 2 atau 3)
        if ($action === 'konfirmasi_lanjut') {
            // Update status ke Surat Keterangan Difasilitasi (bukan Melengkapi Surat)
            $stmt = $pdo->prepare("UPDATE pendaftaran SET status_validasi = 'Surat Keterangan Difasilitasi' WHERE id_pendaftaran = ?");
            $stmt->execute([$id_pendaftaran]);
            
            echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui']);
            exit;
        }
        
        // HANDLE UPLOAD SURAT TTD DARI PEMOHON
        if ($action === 'upload_surat') {
            if (!isset($_FILES['fileSurat']) || $_FILES['fileSurat']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'File tidak valid']);
                exit;
            }
            
            $file = $_FILES['fileSurat'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan']);
                exit;
            }
            
            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB']);
                exit;
            }
            
            $folder = "uploads/berkas_fasilitasi/";
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }
            
            $filename = time() . "_" . uniqid() . "." . $file_extension;
            $target = $folder . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $tgl_upload = date('Y-m-d H:i:s');
                
                $pdo->beginTransaction();
                
                try {
                    $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 4");
                    $stmt->execute([$id_pendaftaran]);
                    $old_file = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old_file && file_exists($old_file['file_path'])) {
                        unlink($old_file['file_path']);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 4");
                    $stmt->execute([$id_pendaftaran]);
                    
                    // Simpan surat baru
                    $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) VALUES (?, 4, ?, ?)");
                    $stmt->execute([$id_pendaftaran, $tgl_upload, $target]);
                    
                    // ===== PENTING: UBAH STATUS SAAT PEMOHON UPLOAD =====
                    $stmt = $pdo->prepare("UPDATE pendaftaran SET status_validasi = 'Menunggu Bukti Pendaftaran' WHERE id_pendaftaran = ?");
                    $stmt->execute([$id_pendaftaran]);
                    
                    $pdo->commit();
                    
                    echo json_encode(['success' => true, 'message' => 'Surat berhasil dikirim']);
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if (file_exists($target)) {
                        unlink($target);
                    }
                    throw $e;
                }
                
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
            }
            exit;
        }
        
    } catch (PDOException $e) {
        error_log("Error AJAX: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan database']);
        exit;
    }
}

// ===== AMBIL DATA PENDAFTARAN =====
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               p.merek_difasilitasi,
               p.alasan_tidak_difasilitasi,
               p.alasan_konfirmasi,
               u.nama_usaha, u.kel_desa, u.kecamatan, 
               m.kelas_merek, m.nama_merek1, m.nama_merek2, m.nama_merek3,
               m.logo1, m.logo2, m.logo3
        FROM pendaftaran p
        LEFT JOIN datausaha u ON p.id_usaha = u.id_usaha
        LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
        WHERE p.NIK = :nik
        ORDER BY p.tgl_daftar DESC
        LIMIT 1
    ");
    $stmt->execute(['nik' => $NIK]);
    $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pendaftaran) {
        header("Location: form-pendaftaran.php");
        exit;
    }
    
    // Ambil alasan dari database
    $alasan_notifikasi = '';
    $alasan_konfirmasi = '';
    
    if ($pendaftaran['status_validasi'] === 'Tidak Bisa Difasilitasi') {
        $alasan_notifikasi = $pendaftaran['alasan_tidak_difasilitasi'] ?: "Mohon maaf merek yang anda ajukan tidak bisa difasilitasi.";
    }

    if ($pendaftaran['status_validasi'] === 'Konfirmasi Lanjut') {
        $alasan_konfirmasi = $pendaftaran['alasan_konfirmasi'] ?: '';
    }
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    die("Terjadi kesalahan saat mengambil data");
}

// Mapping status untuk kode unik
$statusMap = [
    'Pengecekan Berkas' => 'pengecekanberkas',
    'Tidak Bisa Difasilitasi' => 'tidakbisadifasilitasi',
    'Konfirmasi Lanjut' => 'konfirmasilanjut',
    'Surat Keterangan Difasilitasi' => 'melengkapisurat',
    'Menunggu Bukti Pendaftaran' => 'menunggubukti',
    'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' => 'buktiterbit',
    'Hasil Verifikasi Kementerian' => 'sertifikatterbit'
];

$statusKey = $statusMap[$pendaftaran['status_validasi']] ?? 'pengecekanberkas';

$dataStatus = [
    'pengecekanberkas' => [
        'proses' => 'Proses Pengecekan Berkas',
        'status' => 'Merek dalam Proses Pengecekan Berkas',
        'desc'   => 'Anda baru saja mengajukan permohonan merek, sekarang merek dalam proses pengecekan berkas.',
    ],
    'tidakbisadifasilitasi' => [
        'proses' => 'Tidak Bisa Difasilitasi',
        'status' => 'Merek Tidak Bisa Difasilitasi',
        'desc'   => $alasan_notifikasi,
    ],
    'konfirmasilanjut' => [
        'proses' => 'Konfirmasi Lanjut',
        'status' => 'Konfirmasi untuk Melanjutkan dengan Merek Alternatif',
        'desc'   => 'Merek yang bisa difasilitasi adalah Merek Alternatif ' . ($pendaftaran['merek_difasilitasi'] ?? '2') . '.',
        'alasan' => $alasan_konfirmasi,
    ],
    'melengkapisurat' => [
        'proses' => 'Melengkapi Surat Keterangan Difasilitasi',
        'status' => 'Melengkapi Surat Keterangan Difasilitasi',
        'desc'   => 'Silakan download Surat Keterangan Difasilitasi di bawah ini, tandatangani, lalu upload kembali untuk melanjutkan proses.',
    ],
    'menunggubukti' => [
        'proses' => 'Menunggu Bukti Pendaftaran',
        'status' => 'Menunggu Bukti Pendaftaran dari Admin',
        'desc'   => 'Surat yang sudah ditandatangani telah berhasil dikirim. Menunggu admin mengirimkan Surat Keterangan IKM dan Bukti Pendaftaran.',
    ],
    'buktiterbit' => [
        'proses' => 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian',
        'status' => 'Bukti Pendaftaran Sudah Terbit dan Diajukan Ke Kementerian',
        'desc'   => 'Bukti Pendaftaran merek Anda telah tersedia dan sudah diajukan ke Kementerian. Silakan download dokumen di bawah ini.',
        'countdown' => 'Estimasi Proses Verifikasi Kementerian: 1 tahun 6 bulan',
    ],
    'sertifikatterbit' => [
        'proses' => 'Hasil Verifikasi Kementerian',
        'status' => 'Hasil Verifikasi Kementerian',
        'desc'   => 'Selamat, merek anda sudah terdaftar dan sudah terbit sertifikatnya.',
        'masa_berlaku' => 'Masa Berlaku Sertifikat: 10 tahun',
    ],
];

$data = $dataStatus[$statusKey];

// Ambil file lampiran untuk download
$suratKeterangan = null;
$buktiPendaftaran = null;
$sertifikatMerek = null;
$suratPenolakan = null;

try {
    // Ambil Surat Keterangan IKM
    $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5 ORDER BY tgl_upload DESC LIMIT 1");
    $stmt->execute([$pendaftaran['id_pendaftaran']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $suratKeteranganIKM = $result ? $result['file_path'] : null;
    
    // Ambil Bukti Pendaftaran
    $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6 ORDER BY tgl_upload DESC LIMIT 1");
    $stmt->execute([$pendaftaran['id_pendaftaran']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $buktiPendaftaran = $result ? $result['file_path'] : null;
    
    // Ambil Sertifikat Terbit
    $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 7 ORDER BY tgl_upload DESC LIMIT 1");
    $stmt->execute([$pendaftaran['id_pendaftaran']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sertifikatMerek = $result ? $result['file_path'] : null;
    
    // Ambil Surat Penolakan - TAMBAHKAN QUERY INI
    $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 8 ORDER BY tgl_upload DESC LIMIT 1");
    $stmt->execute([$pendaftaran['id_pendaftaran']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $suratPenolakan = $result ? $result['file_path'] : null;
    
} catch (PDOException $e) {
    error_log("Error fetching lampiran: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Status Seleksi Pendaftaran Merek</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/status-seleksi.css"/>
  <style>
    .step-card {
      background: #fff;
      border: 2px solid #0d6efd;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }
    .step-header {
      background: #0d6efd;
      color: white;
      padding: 1rem 1.5rem;
      border-radius: 6px 6px 0 0;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .step-number {
      background: white;
      color: #0d6efd;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 1.2rem;
    }
    .step-body {
      padding: 1.5rem;
    }
    .warning-box {
      background: #fff3cd;
      border: 1px solid #ffc107;
      border-radius: 6px;
      padding: 1rem;
      display: flex;
      align-items: start;
      gap: 0.75rem;
    }
    .warning-icon {
      color: #ffc107;
      font-size: 1.5rem;
      flex-shrink: 0;
    }

      .card {
    transition: all 0.3s ease;
  }
  
  
  .card.border-primary {
    border-width: 2px !important;
  }
  
  .card.border-success {
    border-width: 2px !important;
  }
  
  /* Button Download Styling */
  .btn-dark, .btn-success {
    transition: all 0.3s ease;
    font-weight: 600;
  }
  
  .btn-dark:hover {
    background-color: #000;
  }
  
  .btn-success:hover {
    background-color: #157347;
  }
  
  /* Alert Styling */
  .alert {
    border-radius: 8px;
  }
  
  .alert-success {
    background-color: #d1e7dd;
    border-color: #badbcc;
    color: #0f5132;
  }
  
  .alert-warning {
    background-color: #fff3cd;
    border-color: #ffecb5;
    color: #664d03;
  }
  
  .alert-info {
    background-color: #cff4fc;
    border-color: #b6effb;
    color: #055160;
  }
  
  /* Responsive Design */
  @media (max-width: 768px) {
    .card {
      margin-bottom: 1rem;
    }
    
    .btn-dark, .btn-success {
      font-size: 0.9rem;
      padding: 0.5rem 1rem;
    }
  }
  </style>
</head>
<body>
  <?php include 'navbar-login.php' ?>
  <main class="main-content">
    <div class="container">
      <h1 class="page-title">Status Seleksi Pendaftaran Merek</h1>
      <p class="page-description">
        Cek secara berkala untuk mengetahui perkembangan lebih<br/>
        lanjut status pendaftaran merek anda.
      </p>

      <div class="info-card">
        <div class="info-header d-flex flex-column flex-md-row justify-content-between align-items-start">
          <h2 class="info-title">Informasi Merek yang Didaftarkan</h2>
          <p class="proses proses-<?php echo htmlspecialchars($statusKey); ?> mt-2 mt-md-0">
            <?php echo htmlspecialchars($data['proses']); ?>
          </p>
        </div>

        <hr class="border-2 border-secondary w-100 line"/>

        <div class="status-box">
          <div class="d-flex align-items-start">
            <i class="fa-solid fa-bell status-icon"></i>
            <div class="flex-grow-1">
              <div class="status-text"><?php echo htmlspecialchars($data['status']); ?></div>
              <div class="status-description">
                <?php if ($statusKey === 'tidakbisadifasilitasi'): ?>
                  <div class="alert alert-danger">
                    <strong><i class="fa-solid fa-exclamation-circle me-2"></i>Alasan:</strong>
                    <p class="m-0 mt-2"><?php echo nl2br(htmlspecialchars($data['desc'])); ?></p>
                  </div>
                  
                <?php elseif ($statusKey === 'konfirmasilanjut'): ?>
                  <p class="m-0"><?php echo htmlspecialchars($data['desc']); ?></p>
                  
                  <?php if (!empty($data['alasan'])): ?>
                  <div class="alert alert-info mt-3">
                    <strong><i class="fa-solid fa-info-circle me-2"></i>Alasan Pemilihan:</strong>
                    <p class="m-0 mt-2"><?php echo nl2br(htmlspecialchars($data['alasan'])); ?></p>
                  </div>
                  <?php endif; ?>
                  
                  <div class="mt-3">
                    <p class="mb-2"><strong>Mohon konfirmasi:</strong></p>
                    <p class="mb-3">Jika berkenan untuk lanjut maka tekan <strong>Lanjut</strong>, dan jika tidak berkenan tekan <strong>Mundur</strong> untuk mengubah data pendaftaran.</p>
                    <div class="d-flex gap-2">
                      <button id="btnLanjut" class="btn btn-dark">Lanjut</button>
                      <a id="btnMundur" href="form-pendaftaran.php?edit=<?php echo $pendaftaran['id_pendaftaran']; ?>" class="btn btn-outline-dark">Mundur</a>
                    </div>
                  </div>
                  
                <?php elseif ($statusKey === 'melengkapisurat'): ?>
                  <p class="m-0 mb-4"><?php echo htmlspecialchars($data['desc']); ?></p>
                  
                  <!-- TAMPILAN BARU SEPERTI GAMBAR KEDUA -->
                  <div class="step-card">
                    <div class="step-header">
                      <i class="fa-solid fa-clipboard-list" style="font-size: 1.5rem;"></i>
                      <h5 class="mb-0">Langkah Melengkapi Surat</h5>
                    </div>
                  </div>

                  <!-- Step 1: Download Surat Keterangan -->
                  <div class="step-card">
                    <div class="step-body">
                      <div class="d-flex align-items-start gap-3">
                        <div class="step-number">1</div>
                        <div class="flex-grow-1">
                          <h6 class="fw-bold mb-3">Download Surat Kelengkapan</h6>
                          
                          <!-- Download surat otomatis dari template -->
                          <a class="btn btn-dark" href="generate-surat-otomatis.php?id=<?php echo $pendaftaran['id_pendaftaran']; ?>" target="_blank">
                            <i class="fa-solid fa-download me-2"></i> Download Surat Kelengkapan Difasilitasi (Word)
                          </a>
                          
                          <div class="alert alert-info mt-3 mb-0">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            <strong>Informasi:</strong>
                            <ul class="mb-0 mt-2" style="padding-left: 20px;">
                              <li>Surat akan diunduh dalam format Word (.doc)</li>
                              <li>Data sudah terisi otomatis sesuai dengan data pendaftaran Anda</li>
                              <li>Cetak surat, tanda tangani di atas materai Rp 10.000 x2</li>
                              <li>Scan atau foto hasil tanda tangan, lalu upload di step 3</li>
                            </ul>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Step 2: Tanda Tangan Surat -->
                  <div class="step-card">
                    <div class="step-body">
                      <div class="d-flex align-items-start gap-3">
                        <div class="step-number">2</div>
                        <div class="flex-grow-1">
                          <h6 class="fw-bold mb-2">Tanda Tangan Surat</h6>
                          <p class="text-muted mb-0">Cetak surat, tanda tangani di atas materai Rp 10.000, lalu scan atau foto</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Step 3: Upload Surat -->
                  <div class="step-card">
                    <div class="step-body">
                      <div class="d-flex align-items-start gap-3">
                        <div class="step-number">3</div>
                        <div class="flex-grow-1">
                          <h6 class="fw-bold mb-3">Upload Surat yang Sudah Ditandatangani</h6>
                          <form id="formSurat">
                            <div class="mb-3">
                              <label for="fileSurat" class="form-label">Pilih file (PDF, JPG, JPEG, PNG - Max 5MB)</label>
                              <input class="form-control" type="file" id="fileSurat" name="fileSurat" accept=".pdf,.jpg,.jpeg,.png" required/>
                            </div>
                            <button id="btnKirimSurat" type="submit" class="btn btn-success">
                              <i class="fa-solid fa-paper-plane me-2"></i> Kirim Surat
                            </button>
                          </form>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                <?php elseif ($statusKey === 'menunggubukti'): ?>
  <p class="m-0 mb-3"><?php echo htmlspecialchars($data['desc']); ?></p>
  
  <div class="row g-3">
    <!-- Card Surat Keterangan IKM -->
    <div class="col-md-6">
      <div class="card border-primary">
        <div class="card-body">
          <h6 class="fw-bold mb-3">
            <i class="fa-solid fa-file-pdf me-2 text-danger"></i>
            Surat Keterangan IKM
          </h6>
          <?php if ($suratKeteranganIKM && file_exists($suratKeteranganIKM)): ?>
            <div class="alert alert-success mb-3">
              <i class="fa-solid fa-check-circle me-2"></i>
              <strong>File Tersedia</strong>
            </div>
            <a class="btn btn-dark w-100" href="<?php echo htmlspecialchars($suratKeteranganIKM); ?>" target="_blank" download>
              <i class="fa-solid fa-download me-2"></i> Download Surat Keterangan IKM
            </a>
          <?php else: ?>
            <div class="alert alert-warning mb-0">
              <i class="fa-solid fa-clock me-2"></i>
              <strong>Belum Tersedia</strong>
              <p class="mb-0 mt-2 small">Menunggu admin mengupload Surat Keterangan IKM</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- Card Bukti Pendaftaran -->
    <div class="col-md-6">
      <div class="card border-success">
        <div class="card-body">
          <h6 class="fw-bold mb-3">
            <i class="fa-solid fa-file-pdf me-2 text-danger"></i>
            Bukti Pendaftaran
          </h6>
          <?php if ($buktiPendaftaran && file_exists($buktiPendaftaran)): ?>
            <div class="alert alert-success mb-3">
              <i class="fa-solid fa-check-circle me-2"></i>
              <strong>File Tersedia</strong>
            </div>
            <a class="btn btn-success w-100" href="<?php echo htmlspecialchars($buktiPendaftaran); ?>" target="_blank" download>
              <i class="fa-solid fa-download me-2"></i> Download Bukti Pendaftaran
            </a>
          <?php else: ?>
            <div class="alert alert-warning mb-0">
              <i class="fa-solid fa-clock me-2"></i>
              <strong>Belum Tersedia</strong>
              <p class="mb-0 mt-2 small">Menunggu admin mengupload Bukti Pendaftaran</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  
  <div class="alert alert-info mt-3" role="alert">
    <i class="fa-solid fa-info-circle me-2"></i>
    <strong>Informasi:</strong> Setelah admin mengupload kedua dokumen, status akan otomatis berubah menjadi "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian"
  </div>
                  
                <?php elseif ($statusKey === 'buktiterbit'): ?>
  <p class="m-0 mb-4"><?php echo htmlspecialchars($data['desc']); ?></p>
  
  <div class="row g-3">
    <!-- Card Surat Keterangan IKM -->
    <div class="col-md-6">
      <div class="card border-primary h-100">
        <div class="card-body d-flex flex-column">
          <h6 class="fw-bold mb-3">
            <i class="fa-solid fa-file-pdf me-2 text-danger"></i>
            Surat Keterangan IKM
          </h6>
          <?php if ($suratKeteranganIKM && file_exists($suratKeteranganIKM)): ?>
            <div class="alert alert-success flex-grow-1 mb-3">
              <i class="fa-solid fa-check-circle me-2"></i>
              <strong>File Tersedia untuk Diunduh</strong>
              <p class="mb-0 mt-2 small">Surat Keterangan IKM telah diupload oleh admin</p>
            </div>
            <a class="btn btn-dark w-100" href="<?php echo htmlspecialchars($suratKeteranganIKM); ?>" target="_blank" download>
              <i class="fa-solid fa-download me-2"></i> Download Surat Keterangan IKM
            </a>
          <?php else: ?>
            <div class="alert alert-warning flex-grow-1 mb-3">
              <i class="fa-solid fa-exclamation-triangle me-2"></i>
              <strong>Tidak Tersedia</strong>
              <p class="mb-0 mt-2 small">Silakan hubungi admin jika dokumen ini diperlukan</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- Card Bukti Pendaftaran -->
    <div class="col-md-6">
      <div class="card border-success h-100">
        <div class="card-body d-flex flex-column">
          <h6 class="fw-bold mb-3">
            <i class="fa-solid fa-file-pdf me-2 text-danger"></i>
            Bukti Pendaftaran
          </h6>
          <?php if ($buktiPendaftaran && file_exists($buktiPendaftaran)): ?>
            <div class="alert alert-success flex-grow-1 mb-3">
              <i class="fa-solid fa-check-circle me-2"></i>
              <strong>File Tersedia untuk Diunduh</strong>
              <p class="mb-0 mt-2 small">Bukti Pendaftaran telah diupload oleh admin</p>
            </div>
            <a class="btn btn-success w-100" href="<?php echo htmlspecialchars($buktiPendaftaran); ?>" target="_blank" download>
              <i class="fa-solid fa-download me-2"></i> Download Bukti Pendaftaran
            </a>
          <?php else: ?>
            <div class="alert alert-warning flex-grow-1 mb-3">
              <i class="fa-solid fa-exclamation-triangle me-2"></i>
              <strong>Tidak Tersedia</strong>
              <p class="mb-0 mt-2 small">Silakan hubungi admin untuk informasi lebih lanjut</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  
  <div class="alert alert-info mt-3" role="alert">
    <i class="fa-solid fa-clock me-2"></i>
    <strong><?php echo htmlspecialchars($data['countdown']); ?></strong>
  </div>
  
  <div class="alert alert-success mt-3" role="alert">
    <i class="fa-solid fa-check-circle me-2"></i>
    Merek Anda telah diajukan ke Kementerian. Proses verifikasi sedang berlangsung. Anda akan mendapatkan notifikasi setelah sertifikat terbit.
  </div>
                  
                <?php elseif ($statusKey === 'sertifikatterbit'): ?>
  <p class="m-0 mb-4"><?php echo htmlspecialchars($data['desc']); ?></p>
  
  <!-- Cek file mana yang tersedia -->
  <?php if ($sertifikatMerek && file_exists($sertifikatMerek)): ?>
    <!-- JIKA ADA SERTIFIKAT (DITERIMA) -->
    <div class="card border-success">
      <div class="card-body">
        <div class="text-center mb-3">
          <i class="fa-solid fa-award text-success" style="font-size: 4rem;"></i>
        </div>
        <h5 class="text-center text-success fw-bold mb-3">
          Selamat! Merek Anda Telah Terdaftar
        </h5>
        <div class="alert alert-success mb-3">
          <i class="fa-solid fa-check-circle me-2"></i>
          <strong>Sertifikat Merek Tersedia</strong>
          <p class="mb-0 mt-2">Sertifikat merek Anda telah diterbitkan oleh Kementerian. Silakan download di bawah ini.</p>
        </div>
        
        <div class="d-grid gap-2">
          <a class="btn btn-success btn-lg" href="<?php echo htmlspecialchars($sertifikatMerek); ?>" target="_blank" download>
            <i class="fa-solid fa-download me-2"></i> Download Sertifikat Merek
          </a>
          <a class="btn btn-outline-success" href="<?php echo htmlspecialchars($sertifikatMerek); ?>" target="_blank">
            <i class="fa-solid fa-eye me-2"></i> Preview Sertifikat
          </a>
        </div>
        
        <div class="alert alert-info mt-3 mb-0">
          <i class="fa-solid fa-info-circle me-2"></i>
          <strong>Informasi Penting:</strong>
          <ul class="mb-0 mt-2" style="padding-left: 20px;">
            <li><strong>Masa Berlaku:</strong> 10 tahun sejak tanggal penerbitan</li>
            <li><strong>Perlindungan:</strong> Merek Anda dilindungi secara hukum di Indonesia</li>
            <li><strong>Perpanjangan:</strong> Dapat diperpanjang sebelum masa berlaku habis</li>
          </ul>
        </div>
      </div>
    </div>
    
  <?php elseif ($suratPenolakan && file_exists($suratPenolakan)): ?>
    <!-- JIKA ADA SURAT PENOLAKAN (DITOLAK) -->
    <div class="card border-danger">
      <div class="card-body">
        <div class="text-center mb-3">
          <i class="fa-solid fa-times-circle text-danger" style="font-size: 4rem;"></i>
        </div>
        <h5 class="text-center text-danger fw-bold mb-3">
          Permohonan Merek Ditolak
        </h5>
        <div class="alert alert-danger mb-3">
          <i class="fa-solid fa-exclamation-triangle me-2"></i>
          <strong>Surat Penolakan dari Kementerian</strong>
          <p class="mb-0 mt-2">Mohon maaf, permohonan merek Anda tidak dapat disetujui oleh Kementerian. Silakan download surat penolakan untuk mengetahui alasan detail.</p>
        </div>
        
        <div class="d-grid gap-2">
          <a class="btn btn-danger btn-lg" href="<?php echo htmlspecialchars($suratPenolakan); ?>" target="_blank" download>
            <i class="fa-solid fa-download me-2"></i> Download Surat Penolakan
          </a>
          <a class="btn btn-outline-danger" href="<?php echo htmlspecialchars($suratPenolakan); ?>" target="_blank">
            <i class="fa-solid fa-eye me-2"></i> Preview Surat Penolakan
          </a>
        </div>
        
        <div class="alert alert-warning mt-3 mb-0">
          <i class="fa-solid fa-lightbulb me-2"></i>
          <strong>Informasi Penting!</strong>
          <ul class="mb-0 mt-2" style="padding-left: 20px;">
            <li>Mohon maaf untuk fasilitasi merk gratis tidak bisa dilanjutkan.</li>
            <li>Anda tidak bisa mengajukan kembali fasilitasi merk di Dinas Perindustrian dan Perdagangan Kab. Sidoarjo</li>
            <li>Silahkan mengajukan Mandiri atau hubungi Admin Dinas Perindustrian dan Perdagangan Kab. Sidoarjo untuk informasi lebih lanjut</li>
          </ul>
        </div>
      </div>
    </div>
    
  <?php else: ?>
    <!-- JIKA BELUM ADA FILE -->
    <div class="alert alert-warning">
      <i class="fa-solid fa-clock me-2"></i>
      <strong>Menunggu Hasil Verifikasi</strong>
      <p class="mb-0 mt-2">Hasil verifikasi dari Kementerian belum tersedia. Silakan hubungi admin untuk informasi lebih lanjut.</p>
    </div>
  <?php endif; ?>
  
<?php else: ?>
  <p class="m-0"><?php echo htmlspecialchars($data['desc']); ?></p>
<?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div>
          <strong>Nama Pemohon:</strong><br/>
          <p><?php echo strtoupper(htmlspecialchars($nama)); ?></p>
          <div class="mb-2 mt-2">
            <strong>Nama Usaha:</strong><br/>
            <p><?php echo htmlspecialchars($pendaftaran['nama_usaha']); ?></p>
          </div>
          <div class="mb-2">
            <strong>Tanggal Pendaftaran:</strong><br/>
            <p><?php echo date('d F Y, H:i', strtotime($pendaftaran['tgl_daftar'])); ?> WIB</p>
          </div>
          <div class="mb-4">
            <strong>Merek yang Didaftarkan:</strong><br/>
          </div>
        </div>

        <!-- Kartu alternatif merek -->
        <?php
        $merek_difasilitasi = $pendaftaran['merek_difasilitasi'];
        
        $border1 = '';
        $border2 = '';
        $border3 = '';
        $badge1 = '';
        $badge2 = '';
        $badge3 = '';
        
        if ($merek_difasilitasi) {
            if ($merek_difasilitasi == 1) {
                $border1 = 'border-success border-3';
                $badge1 = '<span class="badge bg-success ms-2 mb-3">Difasilitasi</span>';
                $border2 = 'border-danger border-2';
                $badge2 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
                $border3 = 'border-danger border-2';
                $badge3 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
            } elseif ($merek_difasilitasi == 2) {
                $border1 = 'border-danger border-2';
                $badge1 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
                $border2 = 'border-success border-3';
                $badge2 = '<span class="badge bg-success ms-2 mb-3">Difasilitasi</span>';
                $border3 = 'border-danger border-2';
                $badge3 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
            } elseif ($merek_difasilitasi == 3) {
                $border1 = 'border-danger border-2';
                $badge1 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
                $border2 = 'border-danger border-2';
                $badge2 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
                $border3 = 'border-success border-3';
                $badge3 = '<span class="badge bg-success ms-2 mb-3">Difasilitasi</span>';
            }
        }
        ?>
        
        <div class="row">
          <div class="col-md-4 mb-4">
            <div class="brand-card <?php echo $border1; ?>">
              <h3 class="brand-title">Merek Alternatif 1 (diutamakan)</h3>
              <?php echo $badge1; ?>
              <div class="brand-name-label">Nama Merek Alternatif 1</div>
               <div class="brand-name-display"><?php echo htmlspecialchars($pendaftaran['nama_merek1']); ?></div>
                <div class="logo-label">Logo Merek Alternatif 1</div>
              <div class="logo-container">
                <?php if ($pendaftaran['logo1'] && file_exists($pendaftaran['logo1'])): ?>
                  <img src="<?php echo htmlspecialchars($pendaftaran['logo1']); ?>" alt="Logo 1" style="max-width: 200px; max-height: 200px;">
                <?php else: ?>
                  <i class="fas fa-image brand-logo"></i>
                  <div class="brand-logo-text"><?php echo htmlspecialchars($pendaftaran['nama_merek1']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-md-4 mb-4">
            <div class="brand-card <?php echo $border2; ?>">
              <h3 class="brand-title">Merek Alternatif 2</h3>
              <?php echo $badge2; ?>
              <div class="brand-name-label">Nama Merek Alternatif 2</div>
<div class="brand-name-display"><?php echo htmlspecialchars($pendaftaran['nama_merek2']); ?></div>
              <div class="logo-label">Logo Merek Alternatif 2</div>
              <div class="logo-container">
                <?php if ($pendaftaran['logo2'] && file_exists($pendaftaran['logo2'])): ?>
                  <img src="<?php echo htmlspecialchars($pendaftaran['logo2']); ?>" alt="Logo 2" style="max-width: 200px; max-height: 200px;">
                <?php else: ?>
                  <i class="fas fa-image brand-logo"></i>
                  <div class="brand-logo-text"><?php echo htmlspecialchars($pendaftaran['nama_merek2']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-md-4 mb-4">
            <div class="brand-card <?php echo $border3; ?>">
              <h3 class="brand-title">Merek Alternatif 3</h3>
              <?php echo $badge3; ?>
              <div class="brand-name-label">Nama Merek Alternatif 3</div>
              <div class="brand-name-display"><?php echo htmlspecialchars($pendaftaran['nama_merek3']); ?></div>
              <div class="logo-label">Logo Merek Alternatif 3</div>
              <div class="logo-container">
                <?php if ($pendaftaran['logo3'] && file_exists($pendaftaran['logo3'])): ?>
                  <img src="<?php echo htmlspecialchars($pendaftaran['logo3']); ?>" alt="Logo 3" style="max-width: 200px; max-height: 200px;">
                <?php else: ?>
                  <i class="fas fa-image brand-logo"></i>
                  <div class="brand-logo-text"><?php echo htmlspecialchars($pendaftaran['nama_merek3']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <div class="container text-center">
      <p class="mb-1">Copyright © 2025. All Rights Reserved.</p>
      <p class="mb-0">Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Handler untuk tombol Lanjut (Konfirmasi Lanjut)
    const btnLanjut = document.getElementById('btnLanjut');
    if (btnLanjut) {
      btnLanjut.addEventListener('click', function() {
        if (confirm('Apakah Anda yakin ingin melanjutkan dengan Merek Alternatif <?php echo $pendaftaran['merek_difasilitasi'] ?? '2'; ?>?')) {
          btnLanjut.disabled = true;
          btnLanjut.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Memproses...';
          
          const formData = new FormData();
          formData.append('ajax_action', 'konfirmasi_lanjut');
          formData.append('id_pendaftaran', <?php echo $pendaftaran['id_pendaftaran']; ?>);
          
          fetch(window.location.href, {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Konfirmasi berhasil! Status diperbarui ke Surat Keterangan Difasilitasi. Silakan download dan upload surat yang sudah ditandatangani.');
              location.reload();
            } else {
              alert('Terjadi kesalahan: ' + data.message);
              btnLanjut.disabled = false;
              btnLanjut.innerHTML = 'Lanjut';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengirim konfirmasi.');
            btnLanjut.disabled = false;
            btnLanjut.innerHTML = 'Lanjut';
          });
        }
      });
    }

    // Handler untuk Upload Surat
    const formSurat = document.getElementById('formSurat');
    if (formSurat) {
      formSurat.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('fileSurat');
        const btnKirim = document.getElementById('btnKirimSurat');
        
        if (!fileInput.files[0]) {
          alert('Silakan pilih file terlebih dahulu!');
          return;
        }

        // Validasi ukuran file (max 5MB)
        if (fileInput.files[0].size > 5 * 1024 * 1024) {
          alert('Ukuran file maksimal 5MB!');
          return;
        }

        // Validasi format file
        const allowedExtensions = /(\.pdf|\.jpg|\.jpeg|\.png)$/i;
        if (!allowedExtensions.exec(fileInput.files[0].name)) {
          alert('Format file harus PDF, JPG, JPEG, atau PNG!');
          return;
        }

        if (!confirm('Apakah Anda yakin ingin mengirim surat ini? Status akan berubah menjadi "Menunggu Bukti Pendaftaran".')) {
          return;
        }

        const formData = new FormData();
        formData.append('ajax_action', 'upload_surat');
        formData.append('fileSurat', fileInput.files[0]);
        formData.append('id_pendaftaran', <?php echo $pendaftaran['id_pendaftaran']; ?>);

        // Disable button dan tampilkan loading
        btnKirim.disabled = true;
        btnKirim.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Mengirim...';

        fetch(window.location.href, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Surat berhasil dikirim! Status diperbarui ke Menunggu Bukti Pendaftaran. Admin sekarang dapat melihat surat Anda.');
            location.reload();
          } else {
            alert('Gagal mengirim surat: ' + data.message);
            btnKirim.disabled = false;
            btnKirim.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Kirim Surat';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Terjadi kesalahan saat mengirim file.');
          btnKirim.disabled = false;
          btnKirim.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Kirim Surat';
        });
      });
    }

    // Auto-refresh setiap 5 menit untuk cek update status
    setInterval(function() {
      location.reload();
    }, 5 * 60 * 1000); // 5 menit
  </script>
</body>
</html>