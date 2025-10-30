<?php
session_start();
require_once 'process/config_db.php';
date_default_timezone_set('Asia/Jakarta');

// Ambil ID pendaftaran dari URL
$id_pendaftaran = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pendaftaran == 0) {
  header("Location: daftar-pendaftaran.php");
  exit();
}

// ===== HANDLER AJAX REQUEST ===== (Ganti bagian ini di detail-pendaftar.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
  header('Content-Type: application/json');

  $action = $_POST['ajax_action'];

  try {
    // HANDLER UPLOAD SURAT KETERANGAN IKM
    if ($action === 'upload_surat_keterangan') {
      if (!isset($_FILES['fileSuratKeterangan']) || $_FILES['fileSuratKeterangan']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File tidak valid']);
        exit;
      }

      $file = $_FILES['fileSuratKeterangan'];
      $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
      $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];

      if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan']);
        exit;
      }

      if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 10MB']);
        exit;
      }

      // Ambil NIK dari pendaftaran
      $stmt = $pdo->prepare("SELECT NIK FROM pendaftaran WHERE id_pendaftaran = ?");
      $stmt->execute([$id_pendaftaran]);
      $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$pendaftaran) {
        echo json_encode(['success' => false, 'message' => 'Data pendaftaran tidak ditemukan']);
        exit;
      }

      $nik = $pendaftaran['NIK'];

      $folder = "uploads/suratikm/";
      if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
      }

      // Nama file: suratikm_NIK.extension
      $filename = "suratikm_" . $nik . "." . $file_extension;
      $target = $folder . $filename;

      if (move_uploaded_file($file['tmp_name'], $target)) {
        $tgl_upload = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
          // Hapus surat keterangan lama jika ada
          $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5");
          $stmt->execute([$id_pendaftaran]);
          $old_file = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($old_file && file_exists($old_file['file_path'])) {
            unlink($old_file['file_path']);
          }

          $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5");
          $stmt->execute([$id_pendaftaran]);

          // Simpan surat keterangan baru
          $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) VALUES (?, 5, ?, ?)");
          $stmt->execute([$id_pendaftaran, $tgl_upload, $target]);

          $pdo->commit();
          echo json_encode(['success' => true, 'message' => 'Surat Keterangan IKM berhasil diupload']);
        } catch (PDOException $e) {
          $pdo->rollBack();
          if (file_exists($target)) unlink($target);
          throw $e;
        }
      } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
      }
      exit;
    }

    // HANDLER UPLOAD BUKTI PENDAFTARAN
    if ($action === 'upload_bukti_pendaftaran') {
      if (!isset($_FILES['fileBuktiPendaftaran']) || $_FILES['fileBuktiPendaftaran']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File tidak valid']);
        exit;
      }

      $file = $_FILES['fileBuktiPendaftaran'];
      $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
      $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];

      if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan']);
        exit;
      }

      if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 10MB']);
        exit;
      }

      // Ambil NIK dari pendaftaran
      $stmt = $pdo->prepare("SELECT NIK FROM pendaftaran WHERE id_pendaftaran = ?");
      $stmt->execute([$id_pendaftaran]);
      $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$pendaftaran) {
        echo json_encode(['success' => false, 'message' => 'Data pendaftaran tidak ditemukan']);
        exit;
      }

      $nik = $pendaftaran['NIK'];

      $folder = "uploads/buktipendaftaran/";
      if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
      }

      // Nama file: buktipendaftaran_NIK.extension
      $filename = "buktipendaftaran_" . $nik . "." . $file_extension;
      $target = $folder . $filename;

      if (move_uploaded_file($file['tmp_name'], $target)) {
        $tgl_upload = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
          // Hapus bukti pendaftaran lama jika ada
          $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6");
          $stmt->execute([$id_pendaftaran]);
          $old_file = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($old_file && file_exists($old_file['file_path'])) {
            unlink($old_file['file_path']);
          }

          $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6");
          $stmt->execute([$id_pendaftaran]);

          // Simpan bukti pendaftaran baru
          $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) VALUES (?, 6, ?, ?)");
          $stmt->execute([$id_pendaftaran, $tgl_upload, $target]);

          // Update status ke "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian"
          $stmt = $pdo->prepare("UPDATE pendaftaran SET status_validasi = 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' WHERE id_pendaftaran = ?");
          $stmt->execute([$id_pendaftaran]);

          $pdo->commit();
          echo json_encode([
            'success' => true,
            'message' => 'Bukti Pendaftaran berhasil diupload',
            'new_status' => 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian'
          ]);
        } catch (PDOException $e) {
          $pdo->rollBack();
          if (file_exists($target)) unlink($target);
          throw $e;
        }
      } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
      }
      exit;
    }
  } catch (PDOException $e) {
    error_log("Error AJAX: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan database: ' . $e->getMessage()]);
    exit;
  }
}

try {
  // Query untuk mengambil data lengkap
  $query = "SELECT 
        p.id_pendaftaran,
        p.tgl_daftar,
        p.status_validasi,
        p.merek_difasilitasi,
        p.alasan_tidak_difasilitasi,
        p.alasan_konfirmasi,
        u.NIK_NIP,
        u.nama_lengkap,
        u.no_wa,
        u.rt_rw AS rt_rw_pemilik,
        u.kel_desa AS kel_desa_pemilik,
        u.kecamatan AS kecamatan_pemilik,
        u.foto_ktp,
        du.id_usaha,
        du.nama_usaha,
        du.rt_rw AS rt_rw_usaha,
        du.kel_desa AS kel_desa_usaha,
        du.kecamatan AS kecamatan_usaha,
        du.no_telp_perusahaan,
        du.hasil_produk,
        du.jml_tenaga_kerja,
        du.kapasitas_produk,
        du.omset_perbulan,
        du.wilayah_pemasaran,
        du.legalitas,
        m.kelas_merek,
        m.nama_merek1,
        m.nama_merek2,
        m.nama_merek3,
        m.logo1,
        m.logo2,
        m.logo3
    FROM pendaftaran p
    INNER JOIN user u ON p.NIK = u.NIK_NIP
    INNER JOIN datausaha du ON p.id_usaha = du.id_usaha
    LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
    WHERE p.id_pendaftaran = :id_pendaftaran";

  $stmt = $pdo->prepare($query);
  $stmt->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
  $stmt->execute();

  $data = $stmt->fetch();

  if (!$data) {
    echo "Data tidak ditemukan";
    exit();
  }

  // Query untuk lampiran
  $query_lampiran = "SELECT l.*, mf.nama_jenis_file 
    FROM lampiran l
    INNER JOIN masterfilelampiran mf ON l.id_jenis_file = mf.id_jenis_file
    WHERE l.id_pendaftaran = :id_pendaftaran
    ORDER BY l.id_jenis_file";

  $stmt_lampiran = $pdo->prepare($query_lampiran);
  $stmt_lampiran->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
  $stmt_lampiran->execute();

  $lampiran = [];
  while ($row = $stmt_lampiran->fetch()) {
    $lampiran[$row['nama_jenis_file']][] = $row;
  }

  // ===== AMBIL FILE-FILE PENTING =====
  $suratKeterangan = null;
  $suratTTD = null;
  $buktiPendaftaran = null;
  $sertifikatMerek = null;  // TAMBAHKAN INI
  $suratPenolakan = null;

  // Ambil Surat Keterangan IKM
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $suratKeterangan = $stmt->fetch(PDO::FETCH_ASSOC);

  // Ambil Surat TTD dari Pemohon
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 4 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $suratTTD = $stmt->fetch(PDO::FETCH_ASSOC);

  // Ambil Bukti Pendaftaran (id_jenis_file = 5)
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $buktiPendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

  //  SERTIFIKAT
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 7 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $sertifikatMerek = $stmt->fetch(PDO::FETCH_ASSOC);

  //  SURAT PENOLAKAN
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 8 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $suratPenolakan = $stmt->fetch(PDO::FETCH_ASSOC);

  // Format tanggal
  $tgl_daftar = date('d/m/Y H:i:s', strtotime($data['tgl_daftar']));

  // Pisahkan legalitas
  $legalitas_array = explode(',', $data['legalitas']);
} catch (PDOException $e) {
  die("Error: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Detail Data Pendaftaran</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/detail-pendaftar.css">
  <style>
    .document-section {
      background: #f8f9fa;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .document-title {
      font-weight: 600;
      font-size: 1.1rem;
      margin-bottom: 1rem;
      color: #495057;
    }

    .upload-box {
      border: 2px dashed #6c757d;
      border-radius: 8px;
      padding: 2rem;
      text-align: center;
      background: white;
      transition: all 0.3s;
    }

    .upload-box:hover {
      border-color: #495057;
      background: #f8f9fa;
    }

    .file-info {
      background: #e7f3ff;
      border: 1px solid #b3d9ff;
      border-radius: 6px;
      padding: 1rem;
      margin-top: 1rem;
    }

    .status-badge {
      display: inline-block;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 500;
      font-size: 0.9rem;
    }

    .status-menunggu {
      background: #fff3cd;
      color: #856404;
    }

    .status-tersedia {
      background: #d1e7dd;
      color: #0f5132;
    }

    .download-section {
      background: #fff3e0;
      border: 2px solid #ff9800;
      border-radius: 8px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .download-section h5 {
      color: #e65100;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    /* Style untuk section surat TTD pemohon */
    .surat-ttd-section {
      background: #e8f5e9;
      border: 2px solid #4caf50;
      border-radius: 8px;
      padding: 1.5rem;
      margin-top: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .surat-ttd-section h5 {
      color: #2e7d32;
      font-weight: 600;
      margin-bottom: 1rem;
    }
  </style>
</head>

<body>
  <?php include 'navbar-admin.php' ?>

  <main class="container-xxl main-container">
    <div class="mb-3">
      <h1 class="section-heading mb-1">Detail Data Pendaftaran</h1>
      <p class="lead-note mb-0">Gunakan halaman ini untuk memastikan kelengkapan dan kebenaran data pendaftaran.</p>
    </div>

    <div class="card mb-4">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
          <div>
            <div class="small text-muted-600"><?php echo $tgl_daftar; ?></div>
            <h2 class="h5 fw-bold mb-0"><?php echo strtoupper($data['nama_lengkap']); ?></h2>
          </div>
          <button class="btn btn-secondary btn-pill fw-semibold" id="statusButton">
            <?php echo $data['status_validasi'] == 'Menunggu' ? 'Pengecekan Berkas' : $data['status_validasi']; ?>
          </button>
        </div>

        <!-- ===== SECTION DOWNLOAD SURAT TTD DARI PEMOHON (DI BAWAH NAMA PEMOHON) ===== -->
        <?php if ($suratTTD && file_exists($suratTTD['file_path'])): ?>
          <div class="surat-ttd-section">
            <h5>
              <i class="bi bi-file-earmark-check me-2"></i>
              Surat yang Sudah Ditandatangani Pemohon
            </h5>
            <p class="text-muted mb-3">
              Pemohon telah mengupload surat kelengkapan yang sudah ditandatangani. Silakan download untuk diproses lebih lanjut.
            </p>
            <div class="file-info bg-white border-success">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                  <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2.5rem;"></i>
                  <div>
                    <span class="fw-bold d-block">Surat Kelengkapan Ditandatangani</span>
                    <small class="text-muted">
                      <i class="bi bi-clock me-1"></i>
                      Diupload: <?php echo date('d/m/Y H:i', strtotime($suratTTD['tgl_upload'])); ?> WIB
                    </small>
                    <br>
                    <small class="text-muted">
                      <i class="bi bi-file-earmark me-1"></i>
                      <?php echo basename($suratTTD['file_path']); ?>
                    </small>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <a href="<?php echo htmlspecialchars($suratTTD['file_path']); ?>"
                    class="btn btn-outline-success"
                    target="_blank">
                    <i class="bi bi-eye me-2"></i>Preview
                  </a>
                  <a href="<?php echo htmlspecialchars($suratTTD['file_path']); ?>"
                    class="btn btn-success"
                    download>
                    <i class="bi bi-download me-2"></i>Download Surat
                  </a>
                </div>
              </div>
            </div>
            <div class="mt-3">
              <span class="status-badge status-tersedia">
                <i class="bi bi-check-circle-fill me-1"></i>File Tersedia untuk Diproses
              </span>
            </div>
          </div>
        <?php endif; ?>

        <fieldset class="review-box mt-3" id="reviewFieldset">
          <legend>Cek Berkas</legend>
          <div class="row g-2 align-items-center">
            <div class="col-12 col-lg-8">
              <div class="text-muted-600 small mb-2">
                Merek baru telah didaftarkan oleh pemohon, silakan cek berkasnya terlebih dahulu.
                Jika merek bisa difasilitasi, tekan <strong>Bisa Difasilitasi</strong>.
                Jika tidak bisa difasilitasi, tekan <strong>Tidak Bisa Difasilitasi</strong> dan berikan alasannya.
              </div>
              <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" id="btnBisa" class="btn btn-dark btn-pill">
                  Bisa Difasilitasi
                </button>
                <button type="button" id="btnTidakBisa" class="btn btn-outline-danger btn-pill">
                  Tidak Bisa Difasilitasi
                </button>
              </div>
            </div>

            <div class="col-12" id="alasanBox" style="display: none;">
              <div class="input-group mt-2 mt-lg-0">
                <input class="form-control" id="inputAlasan" placeholder="Berikan alasan tidak bisa difasilitasi" />
                <button class="btn btn-dark fw-semibold" id="btnKonfirmasiTidakBisa">Konfirmasi</button>
              </div>
            </div>
          </div>
        </fieldset>

        <!-- ===== SECTION DOWNLOAD SURAT TTD & UPLOAD FILES ===== -->
        <?php if ($data['status_validasi'] === 'Menunggu Bukti Pendaftaran'): ?>
          <div class="mt-4">
            <!-- SECTION 1: DOWNLOAD SURAT KELENGKAPAN DARI PEMOHON -->
            <!-- <div class="document-section">
            <div class="document-title">
              <i class="bi bi-file-earmark-arrow-down me-2"></i>
              Download Surat Kelengkapan Difasilitasi
            </div>
            <p class="text-muted mb-3">
              Download surat kelengkapan yang telah ditandatangani oleh pemohon untuk diproses lebih lanjut.
            </p> -->

            <!-- <?php if ($suratTTD): ?>
              <div class="file-info">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <i class="bi bi-file-pdf text-danger me-2" style="font-size: 2rem;"></i>
                    <span class="fw-bold">Surat Kelengkapan Ditandatangani</span>
                    <br>
                    <small class="text-muted">
                      Diupload: <?php echo date('d/m/Y H:i', strtotime($suratTTD['tgl_upload'])); ?>
                    </small>
                  </div>
                  <a href="<?php echo htmlspecialchars($suratTTD['file_path']); ?>" 
                     class="btn btn-primary" 
                     target="_blank" 
                     download>
                    <i class="bi bi-download me-2"></i>Download
                  </a>
                </div>
              </div>
              <span class="status-badge status-tersedia mt-2">
                <i class="bi bi-check-circle-fill me-1"></i>File Tersedia
              </span> -->
          <?php else: ?>
            <div class="alert alert-warning mb-0">
              <i class="bi bi-clock me-2"></i>
              <strong>Menunggu Upload dari Pemohon</strong>
              <p class="mb-0 mt-2">Pemohon belum mengupload surat kelengkapan yang ditandatangani. File akan muncul di sini setelah pemohon mengirimkan.</p>
            </div>
            <span class="status-badge status-menunggu mt-2">
              <i class="bi bi-hourglass-split me-1"></i>Menunggu dari Pemohon
            </span>
          <?php endif; ?>
          </div>

          <!-- SECTION 2: UPLOAD SURAT KETERANGAN IKM -->
          <div class="document-section">
            <div class="document-title">
              <i class="bi bi-file-earmark-arrow-up me-2"></i>
              Upload Surat Keterangan IKM
            </div>
            <p class="text-muted mb-3">
              Upload Surat Keterangan IKM yang telah diproses untuk diberikan kepada pemohon.
            </p>

            <?php if ($suratKeterangan && file_exists($suratKeterangan['file_path'])): ?>
              <div class="file-info mb-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <i class="bi bi-file-pdf text-danger me-2" style="font-size: 2rem;"></i>
                    <span class="fw-bold">Surat Keterangan IKM</span>
                    <br>
                    <small class="text-muted">File sudah diupload</small>
                  </div>
                  <div class="d-flex gap-2">
                    <a href="<?php echo htmlspecialchars($suratKeterangan['file_path']); ?>"
                      class="btn btn-sm btn-outline-primary"
                      target="_blank">
                      <i class="bi bi-eye me-1"></i>Lihat
                    </a>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <form id="formSuratKeterangan" enctype="multipart/form-data">
              <div class="upload-box">
                <i class="bi bi-cloud-arrow-up text-primary mb-3" style="font-size: 3rem;"></i>
                <h5>Pilih File Surat Keterangan IKM</h5>
                <p class="text-muted">Format: PDF, JPG, JPEG, PNG (Max 10MB)</p>
                <input type="file"
                  class="form-control mt-3"
                  id="fileSuratKeterangan"
                  name="fileSuratKeterangan"
                  accept=".pdf,.jpg,.jpeg,.png"
                  required>
                <button type="submit" class="btn btn-dark mt-3" id="btnUploadSuratKeterangan">
                  <i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM
                </button>
              </div>
            </form>
          </div>

          <!-- SECTION 3: UPLOAD BUKTI PENDAFTARAN -->
          <div class="document-section">
            <div class="document-title">
              <i class="bi bi-file-earmark-check me-2"></i>
              Upload Bukti Pendaftaran
            </div>
            <p class="text-muted mb-3">
              Upload Bukti Pendaftaran yang telah diproses. Status akan otomatis berubah menjadi "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian".
            </p>

            <?php if ($buktiPendaftaran && file_exists($buktiPendaftaran['file_path'])): ?>
              <div class="file-info mb-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <i class="bi bi-file-pdf text-danger me-2" style="font-size: 2rem;"></i>
                    <span class="fw-bold">Bukti Pendaftaran</span>
                    <br>
                    <small class="text-muted">File sudah diupload</small>
                  </div>
                  <div class="d-flex gap-2">
                    <a href="<?php echo htmlspecialchars($buktiPendaftaran['file_path']); ?>"
                      class="btn btn-sm btn-outline-primary"
                      target="_blank">
                      <i class="bi bi-eye me-1"></i>Lihat
                    </a>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <form id="formBuktiPendaftaran" enctype="multipart/form-data">
              <div class="upload-box">
                <i class="bi bi-cloud-arrow-up text-success mb-3" style="font-size: 3rem;"></i>
                <h5>Pilih File Bukti Pendaftaran</h5>
                <p class="text-muted">Format: PDF, JPG, JPEG, PNG (Max 10MB)</p>
                <input type="file"
                  class="form-control mt-3"
                  id="fileBuktiPendaftaran"
                  name="fileBuktiPendaftaran"
                  accept=".pdf,.jpg,.jpeg,.png"
                  required>
                <button type="submit" class="btn btn-success mt-3" id="btnUploadBuktiPendaftaran">
                  <i class="bi bi-upload me-2"></i>Upload Bukti Pendaftaran
                </button>
              </div>
            </form>
          </div>
      </div>
    <?php endif; ?>
    </div>
    </div>

    <!-- Kolom Data Pemilik & Usaha -->
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card mb-4">
          <div class="card-body p-3 p-md-4">
            <h3 class="subsection-title mb-1">Data Pemilik</h3>
            <div class="divider"></div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small">Nama Pemilik</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['nama_lengkap']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">NIK</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['NIK_NIP']); ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label small">RT/RW</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['rt_rw_pemilik']); ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label small">Kelurahan/Desa</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['kel_desa_pemilik']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Kecamatan</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['kecamatan_pemilik']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Nomor Telepon/HP Pemilik</label>
                <div class="input-group">
                  <input class="form-control" value="<?php echo htmlspecialchars($data['no_wa']); ?>" readonly style="max-width: 200px;" />
                  <?php
                  $no_telp = preg_replace('/[^0-9]/', '', $data['no_wa']);
                  if (substr($no_telp, 0, 1) === '0') {
                    $no_wauser = '62' . substr($no_telp, 1);
                  } elseif (substr($no_telp, 0, 2) === '62') {
                    $no_wauser = $no_telp;
                  } else {
                    $no_wauser = '62' . $no_telp;
                  }
                  ?>
                  <a href="https://wa.me/<?php echo $no_wauser; ?>" target="_blank" class="btn btn-success" title="Chat via WhatsApp">
                    <i class="bi bi-whatsapp me-1"></i>WhatsApp
                  </a>
                </div>
              </div>
            </div>

            <hr class="my-4" />

            <h3 class="subsection-title mb-1">Data Usaha</h3>
            <div class="divider"></div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small">Nama Usaha</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['nama_usaha']); ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label small">RT/RW Usaha</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['rt_rw_usaha']); ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label small">Kelurahan/Desa Usaha</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['kel_desa_usaha']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Kecamatan Usaha</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['kecamatan_usaha']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Nomor Telepon Perusahaan</label>
                <div class="input-group">
                  <?php
                  $no_telp = preg_replace('/[^0-9]/', '', $data['no_telp_perusahaan']);
                  if (substr($no_telp, 0, 1) === '0') {
                    $no_wa = '62' . substr($no_telp, 1);
                  } elseif (substr($no_telp, 0, 2) === '62') {
                    $no_wa = $no_telp;
                  } else {
                    $no_wa = '62' . $no_telp;
                  }
                  ?>
                  <input class="form-control" value="<?php echo htmlspecialchars($data['no_telp_perusahaan']); ?>" readonly style="max-width: 200px;" />
                  <a href="https://wa.me/<?php echo $no_wa; ?>" target="_blank" class="btn btn-success" title="Chat via WhatsApp">
                    <i class="bi bi-whatsapp me-1"></i>WhatsApp
                  </a>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small">Produk-produk yang Dihasilkan</label>
                <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($data['hasil_produk']); ?></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label small">Jumlah Tenaga Kerja</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['jml_tenaga_kerja']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Kapasitas produksi per Bulan, per produk</label>
                <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($data['kapasitas_produk']); ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label small">Omset per Bulan</label>
                <input class="form-control" value="Rp <?php echo htmlspecialchars($data['omset_perbulan']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Wilayah pemasaran</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['wilayah_pemasaran']); ?>" readonly />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Kolom kanan - Lampiran -->
      <div class="col-lg-5">
        <div class="card">
          <div class="card-body p-3 p-md-4">
            <h3 class="subsection-title mb-1">Lampiran Dokumen</h3>
            <div class="divider"></div>

            <?php if (!empty($data['foto_ktp'])): ?>
              <div class="mb-3">
                <div class="small fw-semibold mb-2">Foto KTP</div>
                <img class="attach-img" alt="Foto KTP" src="<?php echo htmlspecialchars($data['foto_ktp']); ?>" />
                <div class="text-end mt-2 mb-3">
                  <button class="btn btn-dark btn-sm btn-view"
                    data-src="<?php echo htmlspecialchars($data['foto_ktp']); ?>"
                    data-title="Foto KTP">
                    <i class="bi bi-eye me-1"></i>View
                  </button>
                </div>
              </div>
            <?php endif; ?>

            <?php if (isset($lampiran['Nomor Induk Berusaha (NIB)'])): ?>
              <?php foreach ($lampiran['Nomor Induk Berusaha (NIB)'] as $item): ?>
                <div class="mb-3">
                  <div class="small fw-semibold mb-2">Nomor Induk Berusaha (NIB)</div>
                  <img class="attach-img" alt="Lampiran NIB" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                  <div class="text-end mt-2 mb-3">
                    <button class="btn btn-dark btn-sm btn-view"
                      data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                      data-title="Nomor Induk Berusaha (NIB)">
                      <i class="bi bi-eye me-1"></i>View
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <div class="mb-3">
              <div class="small fw-semibold mb-2">Legalitas/Standardisasi yang telah dimiliki</div>
              <div class="legalitas d-flex flex-wrap gap-2">
                <?php foreach ($legalitas_array as $legal): ?>
                  <button type="button" class="btn btn-sm muted-pill"><?php echo trim($legal); ?></button>
                <?php endforeach; ?>
                </div>
                <?php if (isset($lampiran['P-IRT'])): ?>
                  <div class="mb-3">
                    <div class="small fw-semibold mb-2">Lampiran: P-IRT</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['P-IRT'] as $item): ?>
                        <div class="col-4">
                          <?php if (strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                            <div class="attach-img d-flex align-items-center justify-content-center bg-light border" style="height: 150px;">
                              <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                            </div>
                          <?php else: ?>
                            <img class="attach-img" alt="P-IRT" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                          <?php endif; ?>
                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="P-IRT">
                              <i class="bi bi-eye me-1"></i>View
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['BPOM-MD'])): ?>
                  <div class="mb-3">
                    <div class="small fw-semibold mb-2">Lampiran: BPOM-MD</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['BPOM-MD'] as $item): ?>
                        <div class="col-4">
                          <?php if (strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                            <div class="attach-img d-flex align-items-center justify-content-center bg-light border" style="height: 150px;">
                              <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                            </div>
                          <?php else: ?>
                            <img class="attach-img" alt="BPOM-MD" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                          <?php endif; ?>
                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="BPOM-MD">
                              <i class="bi bi-eye me-1"></i>View
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['HALAL'])): ?>
                  <div class="mb-3">
                    <div class="small fw-semibold mb-2">Lampiran: HALAL</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['HALAL'] as $item): ?>
                        <div class="col-4">
                          <?php if (strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                            <div class="attach-img d-flex align-items-center justify-content-center bg-light border" style="height: 150px;">
                              <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                            </div>
                          <?php else: ?>
                            <img class="attach-img" alt="HALAL" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                          <?php endif; ?>
                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="HALAL">
                              <i class="bi bi-eye me-1"></i>View
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['NUTRITION FACTS'])): ?>
                  <div class="mb-3">
                    <div class="small fw-semibold mb-2">Lampiran: NUTRITION FACTS</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['NUTRITION FACTS'] as $item): ?>
                        <div class="col-4">
                          <?php if (strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                            <div class="attach-img d-flex align-items-center justify-content-center bg-light border" style="height: 150px;">
                              <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                            </div>
                          <?php else: ?>
                            <img class="attach-img" alt="NUTRITION FACTS" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                          <?php endif; ?>
                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="NUTRITION FACTS">
                              <i class="bi bi-eye me-1"></i>View
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['SNI'])): ?>
                  <div class="mb-3">
                    <div class="small fw-semibold mb-2">Lampiran: SNI</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['SNI'] as $item): ?>
                        <div class="col-4">
                          <?php if (strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                            <div class="attach-img d-flex align-items-center justify-content-center bg-light border" style="height: 150px;">
                              <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                            </div>
                          <?php else: ?>
                            <img class="attach-img" alt="SNI" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                          <?php endif; ?>
                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="SNI">
                              <i class="bi bi-eye me-1"></i>View
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['Legalitas Lainnya'])): ?>
                  <div class="mb-3">
                    <div class="small fw-semibold mb-2">Lampiran: Legalitas Lainnya</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['Legalitas Lainnya'] as $item): ?>
                        <div class="col-4">
                          <?php if (strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                            <div class="attach-img d-flex align-items-center justify-content-center bg-light border" style="height: 150px;">
                              <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                            </div>
                          <?php else: ?>
                            <img class="attach-img" alt="Legalitas Lainnya" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                          <?php endif; ?>
                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="Legalitas Lainnya">
                              <i class="bi bi-eye me-1"></i>View
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
            </div>

            <?php if (isset($lampiran['Foto Produk'])): ?>
              <div class="mb-3">
                <div class="small fw-semibold mb-2">Lampiran: Foto Produk</div>
                <div class="row g-3">
                  <?php foreach ($lampiran['Foto Produk'] as $item): ?>
                    <div class="col-4">
                      <img class="attach-img" alt="Foto Produk" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                      <div class="text-end mt-2 mb-3">
                        <button class="btn btn-dark btn-sm btn-view"
                          data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                          data-title="Foto Produk">
                          <i class="bi bi-eye me-1"></i>View
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if (isset($lampiran['Foto Proses Produksi'])): ?>
              <div class="mb-2">
                <div class="small fw-semibold mb-2">Lampiran: Foto Proses Produksi</div>
                <div class="row g-3">
                  <?php foreach ($lampiran['Foto Proses Produksi'] as $item): ?>
                    <div class="col-6">
                      <img class="attach-img" alt="Proses Produksi" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                      <div class="text-end mt-2 mb-3">
                        <button class="btn btn-dark btn-sm btn-view"
                          data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                          data-title="Foto Proses Produksi">
                          <i class="bi bi-eye me-1"></i>View
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- INFORMASI MEREK -->
      <div class="card mt-4">
        <div class="card-body p-3 p-md-4">
          <h3 class="subsection-title mb-1">Informasi Merek</h3>
          <div class="divider"></div>

          <div class="mb-3">
            <label class="form-label small">Kelas Merek</label>
            <input class="form-control" value="Kelas <?php echo htmlspecialchars($data['kelas_merek']); ?>" readonly />
          </div>

          <div class="row g-4">
            <?php if (!empty($data['nama_merek1'])): ?>
              <div class="col-md-4">
                <div class="mb-2 fw-semibold">Merek Alternatif 1 (diutamakan)</div>
                <label class="form-label small">Nama Merek Alternatif 1</label>
                <input class="form-control mb-2" value="<?php echo htmlspecialchars($data['nama_merek1']); ?>" readonly />
                <?php if (!empty($data['logo1'])): ?>
                  <div class="border rounded-3 p-3 text-center">
                    <img alt="Logo Merek 1" class="img-fluid mb-2" style="max-height:130px" src="<?php echo htmlspecialchars($data['logo1']); ?>" />
                    <div class="text-center mt-2 mb-3">
                      <button class="btn btn-dark btn-sm btn-view"
                        data-src="<?php echo htmlspecialchars($data['logo1']); ?>"
                        data-title="Logo Merek 1">
                        <i class="bi bi-eye me-1"></i>View
                      </button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($data['nama_merek2'])): ?>
              <div class="col-md-4">
                <div class="mb-2 fw-semibold">Merek Alternatif 2</div>
                <label class="form-label small">Nama Merek Alternatif 2</label>
                <input class="form-control mb-2" value="<?php echo htmlspecialchars($data['nama_merek2']); ?>" readonly />
                <?php if (!empty($data['logo2'])): ?>
                  <div class="border rounded-3 p-3 text-center">
                    <img alt="Logo Merek 2" class="img-fluid mb-2" style="max-height:130px" src="<?php echo htmlspecialchars($data['logo2']); ?>" />
                    <div class="text-center mt-2 mb-3">
                      <button class="btn btn-dark btn-sm btn-view"
                        data-src="<?php echo htmlspecialchars($data['logo2']); ?>"
                        data-title="Logo Merek 2">
                        <i class="bi bi-eye me-1"></i>View
                      </button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($data['nama_merek3'])): ?>
              <div class="col-md-4">
                <div class="mb-2 fw-semibold">Merek Alternatif 3</div>
                <label class="form-label small">Nama Merek Alternatif 3</label>
                <input class="form-control mb-2" value="<?php echo htmlspecialchars($data['nama_merek3']); ?>" readonly />
                <?php if (!empty($data['logo3'])): ?>
                  <div class="border rounded-3 p-3 text-center">
                    <img alt="Logo Merek 3" class="img-fluid mb-2" style="max-height:130px" src="<?php echo htmlspecialchars($data['logo3']); ?>" />
                    <div class="text-center mt-2 mb-3">
                      <button class="btn btn-dark btn-sm btn-view"
                        data-src="<?php echo htmlspecialchars($data['logo3']); ?>"
                        data-title="Logo Merek 3">
                        <i class="bi bi-eye me-1"></i>View
                      </button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Modal View Foto -->
  <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-white border-0">
        <div class="modal-body text-center position-relative">
          <button type="button" class="btn-close btn-close-dark position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
          <h6 class="text-dark mb-3" id="modalTitle"></h6>
          <img id="modalImage" src="" alt="Preview" class="img-fluid rounded mb-3" />
          <div>
            <a id="downloadBtn" href="#" download class="btn btn-success">
              <i class="bi bi-download me-1"></i>Download
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <p>Copyright © 2025. All Rights Reserved.</p>
      <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const ID_PENDAFTARAN = <?php echo $id_pendaftaran; ?>;
    const btnBisa = document.getElementById('btnBisa');
    const btnTidakBisa = document.getElementById('btnTidakBisa');
    const alasanBox = document.getElementById('alasanBox');
    const inputAlasan = document.getElementById('inputAlasan');
    const statusButton = document.getElementById('statusButton');
    const reviewFieldset = document.getElementById('reviewFieldset');
    const btnKonfirmasiTidakBisa = document.getElementById('btnKonfirmasiTidakBisa');

    // ===== HANDLER UPLOAD SURAT KETERANGAN IKM =====
    const formSuratKeterangan = document.getElementById('formSuratKeterangan');
    if (formSuratKeterangan) {
      formSuratKeterangan.addEventListener('submit', function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('fileSuratKeterangan');
        const btnUpload = document.getElementById('btnUploadSuratKeterangan');

        if (!fileInput.files[0]) {
          alert('Silakan pilih file terlebih dahulu!');
          return;
        }

        if (fileInput.files[0].size > 10 * 1024 * 1024) {
          alert('Ukuran file maksimal 10MB!');
          return;
        }

        if (!confirm('Apakah Anda yakin ingin mengupload Surat Keterangan IKM ini?')) {
          return;
        }

        const formData = new FormData();
        formData.append('ajax_action', 'upload_surat_keterangan');
        formData.append('fileSuratKeterangan', fileInput.files[0]);

        btnUpload.disabled = true;
        btnUpload.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengupload...';

        fetch(window.location.href, {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Surat Keterangan IKM berhasil diupload!');
              location.reload();
            } else {
              alert('Gagal: ' + data.message);
              btnUpload.disabled = false;
              btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengupload file.');
            btnUpload.disabled = false;
            btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM';
          });
      });
    }

    // ===== HANDLER UPLOAD BUKTI PENDAFTARAN =====
    const formBuktiPendaftaran = document.getElementById('formBuktiPendaftaran');
    if (formBuktiPendaftaran) {
      formBuktiPendaftaran.addEventListener('submit', function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('fileBuktiPendaftaran');
        const btnUpload = document.getElementById('btnUploadBuktiPendaftaran');

        if (!fileInput.files[0]) {
          alert('Silakan pilih file terlebih dahulu!');
          return;
        }

        if (fileInput.files[0].size > 10 * 1024 * 1024) {
          alert('Ukuran file maksimal 10MB!');
          return;
        }

        if (!confirm('Apakah Anda yakin ingin mengupload Bukti Pendaftaran ini? Status akan otomatis berubah menjadi "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian".')) {
          return;
        }

        const formData = new FormData();
        formData.append('ajax_action', 'upload_bukti_pendaftaran');
        formData.append('fileBuktiPendaftaran', fileInput.files[0]);

        btnUpload.disabled = true;
        btnUpload.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengupload...';

        fetch(window.location.href, {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Bukti Pendaftaran berhasil diupload dan status diperbarui ke "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian"!');
              location.reload();
            } else {
              alert('Gagal: ' + data.message);
              btnUpload.disabled = false;
              btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Bukti Pendaftaran';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengupload file.');
            btnUpload.disabled = false;
            btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Bukti Pendaftaran';
          });
      });
    }

    // Fungsi untuk update status ke server
    function updateStatus(status, alasan = '', merekDipilih = 0) {
      const formData = new FormData();
      formData.append('id_pendaftaran', ID_PENDAFTARAN);
      formData.append('status', status);
      formData.append('alasan', alasan);
      formData.append('merek_dipilih', merekDipilih);

      return fetch('process/update_status.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            console.log('Status berhasil diupdate:', data.new_status);
            setStatus(data.new_status);
            return data;
          } else {
            alert('Gagal mengupdate status: ' + data.message);
            throw new Error(data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Terjadi kesalahan saat mengupdate status');
          throw error;
        });
    }

    // Handler tombol "Tidak Bisa Difasilitasi"
    if (btnTidakBisa) {
      btnTidakBisa.addEventListener('click', () => {
        alasanBox.style.display = 'block';
        btnTidakBisa.classList.add('active');
        btnBisa.classList.remove('active');
      });
    }

    // Handler konfirmasi tidak bisa difasilitasi
    if (btnKonfirmasiTidakBisa) {
      btnKonfirmasiTidakBisa.addEventListener('click', () => {
        const alasan = inputAlasan.value.trim();

        if (alasan === '') {
          alert('Mohon berikan alasan mengapa tidak bisa difasilitasi');
          return;
        }

        if (confirm('Apakah Anda yakin merek ini tidak bisa difasilitasi?')) {
          updateStatus('Tidak Bisa Difasilitasi', alasan)
            .then(() => {
              renderTidakBisaDifasilitasi(alasan);
            });
        }
      });
    }

    // Handler tombol "Bisa Difasilitasi"
    if (btnBisa) {
      btnBisa.addEventListener('click', () => {
        alasanBox.style.display = 'none';
        inputAlasan.value = '';
        btnBisa.classList.add('active');
        btnTidakBisa.classList.remove('active');

        renderKonfirmasiMerek();
      });
    }

    // View image modal
    document.querySelectorAll('.btn-view').forEach(btn => {
      btn.addEventListener('click', () => {
        const src = btn.getAttribute('data-src');
        const title = btn.getAttribute('data-title');
        const modalImg = document.getElementById('modalImage');
        const modalTitle = document.getElementById('modalTitle');
        const downloadBtn = document.getElementById('downloadBtn');

        modalImg.src = src;
        modalTitle.textContent = title;
        downloadBtn.href = src;

        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        modal.show();
      });
    });

    function setStatus(text) {
      const btn = statusButton;
      if (btn) {
        btn.textContent = text;
        btn.className = 'btn btn-pill fw-semibold';

        if (text === 'Pengecekan Berkas') {
          btn.classList.add('btn-secondary');
        } else if (text === 'Konfirmasi Lanjut') {
          btn.classList.add('btn-primary');
        } else if (text === 'Surat Keterangan Difasilitasi') {
          btn.classList.add('btn-info', 'text-dark');
        } else if (text === 'Menunggu Bukti Pendaftaran') {
          btn.classList.add('btn-warning', 'text-dark');
        } else if (text === 'Diajukan ke Kementerian') {
          btn.classList.add('btn-warning', 'text-dark');
        } else if (text === 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian') {
          btn.classList.add('btn-info', 'text-dark');
        } else if (text === 'Hasil Verifikasi Kementerian') {
          btn.classList.add('btn-success');
        } else if (text === 'Tidak Bisa Difasilitasi') {
          btn.classList.add('btn-danger');
        }
      }
    }

    function renderTidakBisaDifasilitasi(alasan) {
      reviewFieldset.innerHTML = `
        <legend>Tidak Bisa Difasilitasi</legend>
        <div class="alert alert-danger mb-0">
          <strong><i class="bi bi-x-circle me-2"></i>Merek tidak bisa difasilitasi</strong>
          <p class="mb-0 mt-2">Alasan: ${alasan}</p>
        </div>
      `;
    }

    function renderKonfirmasiMerek() {
      reviewFieldset.innerHTML = `
        <legend>Konfirmasi Merek</legend>
        <div class="text-muted-600 small mb-2">
          Silahkan pilih merek mana yang bisa difasilitasi dengan cara menekan tombol dibawah.
          <ul class="mt-2 mb-0">
            <li><strong>Merek 1 (Utama):</strong> Akan langsung lanjut ke tahap upload Surat Keterangan Difasilitasi</li>
            <li><strong>Merek 2 atau 3:</strong> Akan dikirimkan notifikasi ke pemohon untuk konfirmasi lanjut dengan alasan</li>
          </ul>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button id="btnMerek1" type="button" class="btn btn-dark btn-pill">Merek 1 (Utama)</button>
          <button id="btnMerek2" type="button" class="btn btn-outline-dark btn-pill">Merek 2</button>
          <button id="btnMerek3" type="button" class="btn btn-outline-dark btn-pill">Merek 3</button>
        </div>
        <div id="alasanMerekBox" style="display: none;" class="mt-3">
          <label class="form-label small">Berikan alasan mengapa memilih merek alternatif ini:</label>
          <textarea id="inputAlasanMerek" class="form-control mb-2" rows="3" placeholder="Contoh: Merek 1 sudah terdaftar oleh pihak lain, sehingga kami memilih Merek 2 sebagai alternatif..."></textarea>
          <button id="btnKonfirmasiMerek" type="button" class="btn btn-dark">Konfirmasi Pilihan</button>
        </div>
      `;

      let selectedMerek = 0;

      // Handler Merek 1 - Langsung ke Surat Keterangan Difasilitasi
      document.getElementById('btnMerek1').addEventListener('click', function() {
        if (confirm('Apakah Anda yakin memilih Merek 1 (Utama) untuk difasilitasi?')) {
          updateStatus('Surat Keterangan Difasilitasi', '', 1)
            .then(() => {
              alert('Merek 1 (Utama) telah dipilih. Silakan upload Surat Keterangan Difasilitasi.');
              renderSuratKeterangan();
            });
        }
      });

      // Handler Merek 2 - Perlu alasan
      document.getElementById('btnMerek2').addEventListener('click', function() {
        selectedMerek = 2;
        document.getElementById('alasanMerekBox').style.display = 'block';
        document.getElementById('btnMerek2').classList.add('active');
        document.getElementById('btnMerek3').classList.remove('active');
      });

      // Handler Merek 3 - Perlu alasan
      document.getElementById('btnMerek3').addEventListener('click', function() {
        selectedMerek = 3;
        document.getElementById('alasanMerekBox').style.display = 'block';
        document.getElementById('btnMerek3').classList.add('active');
        document.getElementById('btnMerek2').classList.remove('active');
      });

      // Handler konfirmasi pilihan merek dengan alasan
      document.getElementById('btnKonfirmasiMerek').addEventListener('click', function() {
        const alasanMerek = document.getElementById('inputAlasanMerek').value.trim();

        if (!selectedMerek) {
          alert('Silakan pilih Merek 2 atau Merek 3 terlebih dahulu');
          return;
        }

        if (alasanMerek === '') {
          alert('Mohon berikan alasan mengapa memilih merek alternatif ini');
          return;
        }

        if (confirm('Apakah Anda yakin memilih Merek Alternatif ' + selectedMerek + '?\n\nNotifikasi akan dikirim ke pemohon untuk konfirmasi.')) {
          updateStatus('Konfirmasi Lanjut', alasanMerek, selectedMerek)
            .then(() => {
              alert('Notifikasi berhasil dikirim ke Pemohon. Menunggu konfirmasi dari pemohon untuk melanjutkan proses.');
              renderMenungguKonfirmasi(selectedMerek);
            });
        }
      });
    }

    function renderMenungguKonfirmasi(merek) {
      reviewFieldset.innerHTML = `
        <legend>Menunggu Konfirmasi Pemohon</legend>
        <div class="alert alert-info mb-0">
          <strong><i class="bi bi-clock me-2"></i>Menunggu Konfirmasi Pemohon</strong>
          <p class="mb-0 mt-2">
            Notifikasi telah dikirim ke pemohon untuk konfirmasi melanjutkan proses dengan Merek Alternatif ${merek}.
            Sistem akan otomatis melanjutkan ke tahap berikutnya setelah pemohon mengkonfirmasi.
          </p>
        </div>
      `;
    }

    function renderSuratKeterangan() {
      reviewFieldset.innerHTML = `
    <legend>Menunggu Pemohon Melengkapi Surat</legend>
    <div class="alert alert-info mb-3">
      <strong><i class="bi bi-info-circle me-2"></i>Informasi Proses</strong>
      <p class="mb-0 mt-2">
        Notifikasi telah dikirim ke pemohon untuk melengkapi Surat Keterangan Difasilitasi. 
        Sistem akan otomatis melanjutkan ke tahap berikutnya setelah pemohon mengupload surat yang sudah ditandatangani.
      </p>
    </div>
    
    <div class="alert alert-warning mb-3">
      <strong><i class="bi bi-hourglass-split me-2"></i>Status Saat Ini</strong>
      <ul class="mb-0 mt-2 ps-3">
        <li>Pemohon sedang dalam proses download surat keterangan</li>
        <li>Pemohon akan menandatangani surat di atas materai Rp 10.000</li>
        <li>Pemohon akan mengupload surat yang sudah ditandatangani</li>
        <li>Setelah pemohon upload, Anda akan dapat melanjutkan ke tahap berikutnya</li>
      </ul>
    </div>
  `;
    }

    function renderMenungguTandaTangan() {
      reviewFieldset.innerHTML = `
        <legend>Menunggu Surat Bertanda Tangan dari Pemohon</legend>
        <div class="alert alert-warning mb-0">
          <strong><i class="bi bi-clock me-2"></i>Menunggu Tanda Tangan</strong>
          <p class="mb-0 mt-2">
            Surat Keterangan telah dikirim ke pemohon. Menunggu pemohon menandatangani dan mengirim kembali surat tersebut.
          </p>
        </div>
      `;
    }

    function renderBuktiPendaftaran() {
      reviewFieldset.innerHTML = `
        <legend>Bukti Pendaftaran</legend>
        <div class="text-muted-600 small mb-2">
          Jika Bukti Pendaftaran sudah di terbitkan, kirim file-nya untuk diberikan ke pemohon.
          Tekan pilih file, lalu kirim.
        </div>
        <div class="d-flex flex-column flex-sm-row align-items-start gap-2">
          <input id="fileBukti" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
          <button id="btnKirimBukti" type="button" class="btn btn-dark btn-pill">Kirim</button>
        </div>
      `;

      document.getElementById('btnKirimBukti').addEventListener('click', function() {
        const file = document.getElementById('fileBukti').files[0];
        if (!file) {
          alert('Mohon pilih file terlebih dahulu');
          return;
        }

        // Validasi ukuran file (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
          alert('Ukuran file maksimal 5MB!');
          return;
        }

        const formData = new FormData();
        formData.append('id_pendaftaran', ID_PENDAFTARAN);
        formData.append('id_jenis_file', 5); // 5 = Bukti Pendaftaran
        formData.append('file', file);

        const btnKirim = document.getElementById('btnKirimBukti');
        btnKirim.disabled = true;
        btnKirim.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Uploading...';

        fetch('process/upload_lampiran.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Bukti Pendaftaran berhasil dikirim ke pemohon!');
              updateStatus('Diajukan ke Kementerian');
              renderDiajukanKementerian();
            } else {
              alert('Gagal upload: ' + data.message);
              btnKirim.disabled = false;
              btnKirim.innerHTML = 'Kirim';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat upload file');
            btnKirim.disabled = false;
            btnKirim.innerHTML = 'Kirim';
          });
      });
    }

    function renderDiajukanKementerian() {
      reviewFieldset.innerHTML = `
        <legend>Merek Diajukan ke Kementerian</legend>
        <div class="text-muted-600 small mb-2">
          Jika sertifikat/surat penolakan sudah terbit, kirimkan file-nya untuk dikirim ke pemohon.
          Silahkan pilih file, dan tekan kirim.
        </div>
        <div class="d-flex flex-column flex-sm-row align-items-start gap-2">
          <input id="fileHasil" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" />
          <button id="btnKirimHasil" type="button" class="btn btn-dark btn-pill">Kirim</button>
        </div>
      `;

      document.getElementById('btnKirimHasil').addEventListener('click', function() {
        const file = document.getElementById('fileHasil').files[0];
        if (!file) {
          alert('Mohon pilih file terlebih dahulu');
          return;
        }

        // Validasi ukuran file (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
          alert('Ukuran file maksimal 5MB!');
          return;
        }

        const formData = new FormData();
        formData.append('id_pendaftaran', ID_PENDAFTARAN);
        formData.append('id_jenis_file', 6); // 6 = Sertifikat Merek
        formData.append('file', file);

        const btnKirim = document.getElementById('btnKirimHasil');
        btnKirim.disabled = true;
        btnKirim.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Uploading...';

        fetch('process/upload_lampiran.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Sertifikat berhasil dikirim ke pemohon!');
              updateStatus('Hasil Verifikasi Kementerian');
              renderHasilVerifikasi();
            } else {
              alert('Gagal upload: ' + data.message);
              btnKirim.disabled = false;
              btnKirim.innerHTML = 'Kirim';
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat upload file');
            btnKirim.disabled = false;
            btnKirim.innerHTML = 'Kirim';
          });
      });
    }

    function renderHasilVerifikasi() {
      // Data dari PHP
      const sertifikatData = <?php echo json_encode($sertifikatMerek); ?>;
      const penolakanData = <?php echo json_encode($suratPenolakan); ?>;

      let sertifikatHTML = '';
      let penolakanHTML = '';

      // Generate HTML untuk Sertifikat
      if (sertifikatData && sertifikatData.file_path) {
        const tglSertifikat = sertifikatData.tgl_upload ? new Date(sertifikatData.tgl_upload).toLocaleString('id-ID', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        }) : '';

        sertifikatHTML = `
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle me-2"></i>
        <strong>File Tersedia</strong>
        <p class="mb-0 mt-2 small">
          <i class="bi bi-calendar3 me-1"></i>
          Diupload: ${tglSertifikat} WIB
        </p>
      </div>
      <div class="d-grid gap-2">
        <a href="${sertifikatData.file_path}" 
           class="btn btn-outline-success" 
           target="_blank">
          <i class="bi bi-eye me-2"></i>Preview Sertifikat
        </a>
        <a href="${sertifikatData.file_path}" 
           class="btn btn-success" 
           download>
          <i class="bi bi-download me-2"></i>Download Sertifikat
        </a>
      </div>
    `;
      } else {
        sertifikatHTML = `
      <div class="alert alert-warning mb-0">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Tidak Tersedia</strong>
        <p class="mb-0 mt-2 small">File sertifikat tidak ditemukan</p>
      </div>
    `;
      }

      // Generate HTML untuk Surat Penolakan
      if (penolakanData && penolakanData.file_path) {
        const tglPenolakan = penolakanData.tgl_upload ? new Date(penolakanData.tgl_upload).toLocaleString('id-ID', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        }) : '';

        penolakanHTML = `
      <div class="alert alert-danger mb-3">
        <i class="bi bi-x-circle me-2"></i>
        <strong>File Tersedia</strong>
        <p class="mb-0 mt-2 small">
          <i class="bi bi-calendar3 me-1"></i>
          Diupload: ${tglPenolakan} WIB
        </p>
      </div>
      <div class="d-grid gap-2">
        <a href="${penolakanData.file_path}" 
           class="btn btn-outline-danger" 
           target="_blank">
          <i class="bi bi-eye me-2"></i>Preview Surat Penolakan
        </a>
        <a href="${penolakanData.file_path}" 
           class="btn btn-danger" 
           download>
          <i class="bi bi-download me-2"></i>Download Surat Penolakan
        </a>
      </div>
    `;
      } else {
        penolakanHTML = `
      <div class="alert alert-warning mb-0">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Tidak Tersedia</strong>
        <p class="mb-0 mt-2 small">File surat penolakan tidak ditemukan</p>
      </div>
    `;
      }

      reviewFieldset.innerHTML = `
    <legend>Hasil Verifikasi Kementerian</legend>
    <div class="alert alert-info mb-3">
      <strong><i class="bi bi-info-circle me-2"></i>Proses Selesai</strong>
      <p class="mb-0 mt-2">
        Hasil verifikasi dari kementerian telah diupload. Lihat file yang tersedia di bawah ini.
      </p>
    </div>
    
    <div class="row g-3">
      <!-- Card Sertifikat Merek -->
      <div class="col-md-6">
        <div class="card border-success h-100">
          <div class="card-body">
            <h6 class="fw-bold text-success mb-3">
              <i class="bi bi-award me-2"></i>Sertifikat Merek
            </h6>
            <p class="text-muted small mb-3">Dokumen sertifikat merek yang <strong>DITERIMA</strong></p>
            ${sertifikatHTML}
          </div>
        </div>
      </div>
      
      <!-- Card Surat Penolakan -->
      <div class="col-md-6">
        <div class="card border-danger h-100">
          <div class="card-body">
            <h6 class="fw-bold text-danger mb-3">
              <i class="bi bi-x-circle me-2"></i>Surat Penolakan
            </h6>
            <p class="text-muted small mb-3">Dokumen surat merek yang <strong>DITOLAK</strong></p>
            ${penolakanHTML}
          </div>
        </div>
      </div>
    </div>
    
    <div class="alert alert-success mt-3 mb-0">
      <i class="bi bi-check-circle me-2"></i>
      <strong>Informasi:</strong> Masa berlaku sertifikat merek adalah <strong>10 tahun</strong> sejak tanggal penerbitan.
    </div>
  `;
    }

    // Cek status awal dari PHP dan render UI yang sesuai
    const statusAwal = '<?php echo $data['status_validasi']; ?>';
    const merekDifasilitasi = <?php echo $data['merek_difasilitasi'] ?? 'null'; ?>;
    const alasanTidakDifasilitasi = <?php echo json_encode($data['alasan_tidak_difasilitasi'] ?? ''); ?>;
    const alasanKonfirmasi = <?php echo json_encode($data['alasan_konfirmasi'] ?? ''); ?>;

    if (statusAwal === 'Tidak Bisa Difasilitasi') {
      const alasan = alasanTidakDifasilitasi || 'Lihat detail notifikasi';
      renderTidakBisaDifasilitasi(alasan);
    } else if (statusAwal === 'Konfirmasi Lanjut') {
      renderMenungguKonfirmasi(merekDifasilitasi || '2');
    } else if (statusAwal === 'Surat Keterangan Difasilitasi') {
      renderSuratKeterangan();
    } else if (statusAwal === 'Menunggu Bukti Pendaftaran') {
      // Render UI khusus dengan section download & upload (sudah ada di HTML)
      reviewFieldset.innerHTML = `
        <legend>Menunggu Bukti Pendaftaran</legend>
        <div class="alert alert-info mb-0">
          <strong><i class="bi bi-clock me-2"></i>Proses Upload Dokumen</strong>
          <p class="mb-0 mt-2">
            Pemohon telah mengirim surat bertanda tangan. Silakan download surat tersebut di atas, kemudian upload Surat Keterangan IKM dan Bukti Pendaftaran di bawah.
          </p>
        </div>
      `;
    } // ===== GANTI BAGIAN INI DI JAVASCRIPT detail-pendaftar.php =====

    // Bagian render untuk status "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian"
    else if (statusAwal === 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian') {
      reviewFieldset.innerHTML = `
    <legend>Upload Hasil Verifikasi Kementerian</legend>
    <div class="alert alert-info mb-3">
      <strong><i class="bi bi-info-circle me-2"></i>Informasi</strong>
      <p class="mb-0 mt-2">
        Upload hasil verifikasi dari kementerian. Pilih salah satu: Sertifikat (jika diterima) atau Surat Penolakan (jika ditolak).
      </p>
    </div>
    
    <div class="row g-3">
      <!-- Upload Sertifikat Merek -->
      <div class="col-md-6">
        <div class="card border-success h-100">
          <div class="card-body">
            <h6 class="fw-bold text-success mb-3">
              <i class="bi bi-award me-2"></i>Upload Sertifikat Merek
            </h6>
            <p class="text-muted small mb-3">Upload jika merek <strong>DITERIMA</strong> oleh kementerian</p>
            <form id="formSertifikat" enctype="multipart/form-data">
              <div class="mb-3">
                <input type="file" 
                       class="form-control" 
                       id="fileSertifikat" 
                       accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">Format: PDF, JPG, JPEG, PNG (Max 10MB)</div>
              </div>
              <button type="submit" class="btn btn-success w-100" id="btnUploadSertifikat">
                <i class="bi bi-upload me-2"></i>Upload Sertifikat
              </button>
            </form>
          </div>
        </div>
      </div>
      
      <!-- Upload Surat Penolakan -->
      <div class="col-md-6">
        <div class="card border-danger h-100">
          <div class="card-body">
            <h6 class="fw-bold text-danger mb-3">
              <i class="bi bi-x-circle me-2"></i>Upload Surat Penolakan
            </h6>
            <p class="text-muted small mb-3">Upload jika merek <strong>DITOLAK</strong> oleh kementerian</p>
            <form id="formPenolakan" enctype="multipart/form-data">
              <div class="mb-3">
                <input type="file" 
                       class="form-control" 
                       id="filePenolakan" 
                       accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">Format: PDF, JPG, JPEG, PNG (Max 10MB)</div>
              </div>
              <button type="submit" class="btn btn-danger w-100" id="btnUploadPenolakan">
                <i class="bi bi-upload me-2"></i>Upload Surat Penolakan
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  `;

      // Handler Upload Sertifikat
      const formSertifikat = document.getElementById('formSertifikat');
      if (formSertifikat) {
        formSertifikat.addEventListener('submit', function(e) {
          e.preventDefault();

          const fileInput = document.getElementById('fileSertifikat');
          const btnUpload = document.getElementById('btnUploadSertifikat');

          if (!fileInput.files[0]) {
            alert('Silakan pilih file terlebih dahulu!');
            return;
          }

          if (fileInput.files[0].size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10MB!');
            return;
          }

          if (!confirm('Apakah Anda yakin ingin mengupload Sertifikat Merek ini?')) {
            return;
          }

          const formData = new FormData();
          formData.append('id_pendaftaran', ID_PENDAFTARAN);
          formData.append('id_jenis_file', 7); // 7 = Sertifikat Terbit
          formData.append('file', fileInput.files[0]);

          btnUpload.disabled = true;
          btnUpload.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Mengupload...';

          fetch('process/upload_lampiran.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                updateStatus('Hasil Verifikasi Kementerian')
                  .then(() => {
                    alert('Sertifikat berhasil diupload! Status diperbarui ke "Hasil Verifikasi Kementerian".');
                    location.reload();
                  });
              } else {
                alert('Gagal upload: ' + data.message);
                btnUpload.disabled = false;
                btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Sertifikat';
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Terjadi kesalahan saat upload file');
              btnUpload.disabled = false;
              btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Sertifikat';
            });
        });
      }

      // Handler Upload Surat Penolakan
      const formPenolakan = document.getElementById('formPenolakan');
      if (formPenolakan) {
        formPenolakan.addEventListener('submit', function(e) {
          e.preventDefault();

          const fileInput = document.getElementById('filePenolakan');
          const btnUpload = document.getElementById('btnUploadPenolakan');

          if (!fileInput.files[0]) {
            alert('Silakan pilih file terlebih dahulu!');
            return;
          }

          if (fileInput.files[0].size > 10 * 1024 * 1024) {
            alert('Ukuran file maksimal 10MB!');
            return;
          }

          if (!confirm('Apakah Anda yakin ingin mengupload Surat Penolakan ini?')) {
            return;
          }

          const formData = new FormData();
          formData.append('id_pendaftaran', ID_PENDAFTARAN);
          formData.append('id_jenis_file', 8); // 8 = Surat Penolakan
          formData.append('file', fileInput.files[0]);

          btnUpload.disabled = true;
          btnUpload.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Mengupload...';

          fetch('process/upload_lampiran.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                updateStatus('Hasil Verifikasi Kementerian')
                  .then(() => {
                    alert('Surat Penolakan berhasil diupload! Status diperbarui ke "Hasil Verifikasi Kementerian".');
                    location.reload();
                  });
              } else {
                alert('Gagal upload: ' + data.message);
                btnUpload.disabled = false;
                btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Penolakan';
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Terjadi kesalahan saat upload file');
              btnUpload.disabled = false;
              btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Penolakan';
            });
        });
      }
    } else if (statusAwal === 'Diajukan ke Kementerian') {
      renderDiajukanKementerian();
    } else if (statusAwal === 'Hasil Verifikasi Kementerian') {
      renderHasilVerifikasi();
    }
  </script>
</body>

</html>