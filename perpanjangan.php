<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

include 'process/config_db.php';

// ===== CEK APAKAH USER SUDAH LOGIN =====
if (!isset($_SESSION['NIK_NIP'])) {
    header("Location: login.php");
    exit();
}

$NIK = $_SESSION['NIK_NIP'];

// ===== PROSES SUBMIT FORM =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signature_data'])) {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
        throw new Exception("PHP Error: $errstr");
    });

    try {
        error_log("=== START PERPANJANGAN PROCESS ===");
        error_log("NIK: " . $NIK);

        $signature_data = $_POST['signature_data'] ?? '';
        if (empty($signature_data)) {
            throw new Exception('Data tanda tangan kosong atau tidak valid');
        }

        if (strlen($signature_data) < 100) {
            throw new Exception('Tanda tangan tidak valid (terlalu kecil)');
        }

        if (!preg_match('/^data:image\/png;base64,/', $signature_data)) {
            throw new Exception('Format tanda tangan tidak valid (harus PNG base64)');
        }

        // Start transaction
        $pdo->beginTransaction();
        error_log("Transaction started");

        // Ambil data pendaftaran terakhir
        $stmt = $pdo->prepare("
            SELECT p.*, u.id_usaha
            FROM pendaftaran p
            LEFT JOIN datausaha u ON p.id_usaha = u.id_usaha
            WHERE p.NIK = :nik 
            ORDER BY p.tgl_daftar DESC
            LIMIT 1
        ");
        $stmt->execute(['nik' => $NIK]);
        $pendaftaran_lama = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pendaftaran_lama) {
            throw new Exception('Data pendaftaran tidak ditemukan untuk NIK: ' . $NIK);
        }
        error_log("Pendaftaran Lama ID: " . $pendaftaran_lama['id_pendaftaran']);
        error_log("ID Usaha: " . $pendaftaran_lama['id_usaha']);

        if (empty($pendaftaran_lama['id_pendaftaran']) || empty($pendaftaran_lama['id_usaha'])) {
            throw new Exception('Data pendaftaran tidak lengkap');
        }

        // Insert data perpanjangan
        $tgl_pengajuan = date('Y-m-d H:i:s');
        error_log("Tanggal Pengajuan: " . $tgl_pengajuan);

        $stmt = $pdo->prepare("
            INSERT INTO perpanjangan (id_pendaftaran_lama, NIK, id_usaha, tgl_pengajuan, status_perpanjangan)
            VALUES (?, ?, ?, ?, 'Menunggu Surat Keterangan IKM')
        ");
        
        $result = $stmt->execute([
            $pendaftaran_lama['id_pendaftaran'],
            $NIK,
            $pendaftaran_lama['id_usaha'],
            $tgl_pengajuan
        ]);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("Execute error: " . json_encode($errorInfo));
            throw new Exception('Gagal menyimpan data perpanjangan: ' . $errorInfo[2]);
        }

        $id_perpanjangan = $pdo->lastInsertId();
        error_log("ID Perpanjangan Baru: " . $id_perpanjangan);

        if (!$id_perpanjangan || $id_perpanjangan <= 0) {
            throw new Exception('Gagal mendapatkan ID perpanjangan dari database');
        }

        // Simpan tanda tangan digital
        $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
        $signature_data = str_replace(' ', '+', $signature_data);
        $signature_decoded = base64_decode($signature_data, true);

        if (!$signature_decoded) {
            throw new Exception('Gagal decode tanda tangan - format base64 tidak valid');
        }
        error_log("Signature decoded, size: " . strlen($signature_decoded) . " bytes");

        if (strlen($signature_decoded) < 100) {
            throw new Exception('Ukuran tanda tangan terlalu kecil (corrupted data)');
        }

        // Simpan file tanda tangan
        $folder = "uploads/ttd_perpanjangan/ttd_{$NIK}/";
        if (!file_exists($folder)) {
            $mkdir_result = mkdir($folder, 0777, true);
            if (!$mkdir_result) {
                throw new Exception('Gagal membuat folder: ' . $folder);
            }
            error_log("Folder created: " . $folder);
        }

        $filename = "ttd_{$NIK}_" . time() . ".png";
        $filepath = $folder . $filename;
        
        $bytes_written = file_put_contents($filepath, $signature_decoded);
        if ($bytes_written === false || $bytes_written === 0) {
            throw new Exception('Gagal menyimpan file tanda tangan di: ' . $filepath);
        }
        error_log("File tersimpan: " . $filepath . " (" . $bytes_written . " bytes)");

        // Verify file was written
        if (!file_exists($filepath)) {
            throw new Exception('File tanda tangan tidak ditemukan setelah disimpan: ' . $filepath);
        }

        // Simpan ke tabel perpanjangan
        $stmt = $pdo->prepare("
            UPDATE perpanjangan 
            SET file_ttd = ? 
            WHERE id_perpanjangan = ?
        ");
        
        $result_lampiran = $stmt->execute([
            $filepath,
            $id_perpanjangan
        ]);

        if (!$result_lampiran) {
            $errorInfo = $stmt->errorInfo();
            error_log("Update file_ttd error: " . json_encode($errorInfo));
            throw new Exception('Gagal menyimpan file tanda tangan ke database: ' . $errorInfo[2]);
        }
        error_log("File tanda tangan berhasil disimpan ke perpanjangan");

        try {
            error_log("Starting PDF generation...");
            require_once __DIR__ . '/vendor/autoload.php';
            define('GENERATE_FROM_PERPANJANGAN', true);
            require_once('generate-surat-perpanjangan.php');
            error_log("PDF generation completed successfully");

            // ✅ SETELAH PDF DIBUAT, SIMPAN KE LAMPIRAN DENGAN id_jenis_file = 17
            if (isset($filepath) && file_exists($filepath)) {
                error_log("Saving surat perpanjangan to lampiran table...");
                
                $tgl_upload = date('Y-m-d H:i:s');
                $surat_filepath = "uploads/surat_perpanjangan/surat_{$NIK}/surat_perpanjangan_{$NIK}_" . time() . ".pdf";
                
                // Insert ke lampiran dengan id_perpanjangan NEGATIF sebagai referensi
                $stmt = $pdo->prepare("
                    INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) 
                    VALUES (?, 17, ?, ?)
                ");
                
                $result = $stmt->execute([
                    -$id_perpanjangan,  // ID negatif untuk perpanjangan
                    $tgl_upload,
                    $surat_filepath
                ]);

                if ($result) {
                    error_log("Surat perpanjangan berhasil disimpan ke lampiran: " . $surat_filepath);
                } else {
                    error_log("Warning: Gagal menyimpan ke lampiran, tapi PDF sudah dibuat");
                }
            }
        } catch (Exception $e) {
            error_log("Warning: PDF generation failed: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());
        }

        // COMMIT TRANSAKSI
        $pdo->commit();
        error_log("Transaction committed successfully");

        $_SESSION['alert_message'] = 'Permohonan perpanjangan berhasil diajukan! Surat permohonan telah dibuat.';
        $_SESSION['alert_type'] = 'success';
        $_SESSION['baru_perpanjangan'] = true;
        $_SESSION['id_perpanjangan_baru'] = $id_perpanjangan;

        error_log("=== PERPANJANGAN PROCESS COMPLETED SUCCESSFULLY ===");

        session_write_close();

        // REDIRECT KE STATUS SELEKSI
        header("Location: status-seleksi-pendaftaran.php", true, 302);
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("=== PDO ERROR ===");
        error_log("Error Code: " . $e->getCode());
        error_log("Error Message: " . $e->getMessage());
        error_log("Error Trace: " . $e->getTraceAsString());
        
        $_SESSION['alert_message'] = 'Terjadi kesalahan database: ' . $e->getMessage();
        $_SESSION['alert_type'] = 'danger';
        session_write_close();
        header("Location: perpanjangan.php", true, 302);
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("=== GENERAL ERROR ===");
        error_log("Error Message: " . $e->getMessage());
        error_log("Error Trace: " . $e->getTraceAsString());
        
        $_SESSION['alert_message'] = 'Terjadi kesalahan: ' . $e->getMessage();
        $_SESSION['alert_type'] = 'danger';
        session_write_close();
        header("Location: perpanjangan.php", true, 302);
        exit();
    } finally {
        restore_error_handler();
    }
}

// ===== AMBIL DATA PENDAFTARAN TERAKHIR =====
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               u.nama_usaha, u.rt_rw AS usaha_rt_rw, u.kel_desa, u.kecamatan, u.no_telp_perusahaan,
               u.hasil_produk, u.jml_tenaga_kerja,
               m.nama_merek1, m.nama_merek2, m.nama_merek3, m.kelas_merek,
               usr.nama_lengkap, usr.kel_desa AS user_kel_desa, usr.kecamatan AS user_kecamatan, 
               usr.rt_rw AS user_rt_rw, usr.no_wa, usr.email,
               usr.nama_kabupaten, usr.nama_provinsi
        FROM pendaftaran p
        LEFT JOIN datausaha u ON p.id_usaha = u.id_usaha
        LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
        LEFT JOIN user usr ON p.NIK = usr.NIK_NIP
        WHERE p.NIK = :nik 
        ORDER BY p.tgl_daftar DESC
        LIMIT 1
    ");
    $stmt->execute(['nik' => $NIK]);
    $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pendaftaran) {
        $_SESSION['alert_message'] = 'Anda belum memiliki data pendaftaran. Silakan daftar terlebih dahulu.';
        $_SESSION['alert_type'] = 'warning';
        header("Location: form-pendaftaran.php");
        exit();
    }

    // Ambil sertifikat lama
    $stmt = $pdo->prepare("
        SELECT file_path, tgl_upload 
        FROM lampiran 
        WHERE id_pendaftaran = :id_pendaftaran 
        AND id_jenis_file = 7
        ORDER BY tgl_upload DESC 
        LIMIT 1
    ");
    $stmt->execute(['id_pendaftaran' => $pendaftaran['id_pendaftaran']]);
    $sertifikat_lama = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buat alamat lengkap
    $alamat_parts = array();
    if (!empty($pendaftaran['user_kel_desa'])) $alamat_parts[] = $pendaftaran['user_kel_desa'];
    if (!empty($pendaftaran['user_rt_rw'])) $alamat_parts[] = 'RT/RW: ' . $pendaftaran['user_rt_rw'];
    if (!empty($pendaftaran['user_kecamatan'])) $alamat_parts[] = $pendaftaran['user_kecamatan'];
    if (!empty($pendaftaran['nama_kabupaten'])) $alamat_parts[] = $pendaftaran['nama_kabupaten'];
    if (!empty($pendaftaran['nama_provinsi'])) $alamat_parts[] = $pendaftaran['nama_provinsi'];
    $alamat_lengkap = implode(', ', $alamat_parts);

    // Tentukan merek yang difasilitasi
    $merek_difasilitasi = '';
    $no_merek_difasilitasi = 1;

    if ($pendaftaran['merek_difasilitasi'] == 1) {
        $merek_difasilitasi = $pendaftaran['nama_merek1'];
        $no_merek_difasilitasi = 1;
    } elseif ($pendaftaran['merek_difasilitasi'] == 2) {
        $merek_difasilitasi = $pendaftaran['nama_merek2'];
        $no_merek_difasilitasi = 2;
    } elseif ($pendaftaran['merek_difasilitasi'] == 3) {
        $merek_difasilitasi = $pendaftaran['nama_merek3'];
        $no_merek_difasilitasi = 3;
    } else {
        $merek_difasilitasi = $pendaftaran['nama_merek1'];
        $no_merek_difasilitasi = 1;
    }
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $_SESSION['alert_message'] = 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage();
    $_SESSION['alert_type'] = 'danger';
    header("Location: status-seleksi-pendaftaran.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpanjangan Sertifikat Merek - Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/form-pendaftaran.css">
    <style>
        .data-static {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            color: #495057;
            font-weight: 500;
        }

        .section-title {
            color: #0d6efd;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0d6efd;
        }

        .signature-container {
            border: 2px solid #0d6efd;
            border-radius: 0.5rem;
            padding: 1rem;
            background-color: #fff;
        }

        #signature-pad {
            border: 2px dashed #6c757d;
            border-radius: 0.375rem;
            cursor: crosshair;
            background-color: #ffffff;
            touch-action: none;
        }

        .signature-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .signature-preview {
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background-color: #f8f9fa;
            display: none;
        }

        .signature-preview img {
            max-width: 100%;
            height: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }
    </style>
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <div class="container main-container">
        <div class="row cont">
            <!-- Sidebar -->
            <div class="col-lg-4">
                <h5 class="judul">Fasilitasi Surat Keterangan IKM untuk Perpanjangan Merek</h5>
                <p>Pemohon hanya mendapatkan Surat Keterangan IKM (Industri Kecil Menengah) untuk melakukan Perpanjangan Merek di Kemenkumham RI.</p>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-info-square pe-2"></i> Informasi</h5>
                    <ul class="list-unstyled info-list">
                        <li>Output: <br> Surat Keterangan IKM untuk Perpanjangan Sertifikat Merek</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal-check pe-2"></i>Syarat dan Ketentuan</h5>
                    <ul class="info-list">
                        <li>Memiliki sertifikat merek yang akan habis masa berlakunya (≤ 1 tahun)</li>
                        <li>Industri Kecil yang masih aktif memproduksi di Sidoarjo</li>
                        <li>Data usaha masih sama dengan pendaftaran sebelumnya</li>
                        <li>Membuat tanda tangan digital sebagai persetujuan</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal pe-2"></i>Catatan</h5>
                    <ul class="info-list">
                        <li>Perpanjangan dapat diajukan maksimal 1 tahun sebelum masa berlaku habis</li>
                        <li>Pastikan data usaha Anda masih aktif dan valid</li>
                        <li>Tanda tangan digital akan digunakan sebagai bukti persetujuan</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle bg-warning bg-opacity-10">
                    <h5><i class="bi bi-exclamation-triangle pe-2"></i>Perhatian</h5>
                    <ul class="info-list">
                        <li>Data yang ditampilkan diambil dari pendaftaran sebelumnya</li>
                        <li>Jika ada perubahan data, silakan hubungi admin</li>
                        <li>Tanda tangan harus jelas dan sesuai dengan identitas</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5>Bantuan</h5>
                    <p>Jika ada kendala dalam mengisi formulir bisa menghubungi kami dibawah ini.</p>
                    <a href="https://wa.me/6281235051286?text=Halo%2C%20saya%20ingin%20bertanya%20mengenai%20perpanjangan%20sertifikat%20merek" class="help-contact" target="_blank">
                        <i class="fab fa-whatsapp pe-2"></i> Bidang Perindustrian Disperindag Sidoarjo
                    </a>
                    <p class="text-danger mt-2">* Tidak menerima panggilan, hanya chat.</p>
                </div>
            </div>

            <!-- Form Content -->
            <div class="col-lg-8">
                <div class="form-container border border-light-subtle">
                    <h4>Perpanjangan Sertifikat Merek</h4>
                    <hr class="border-2 border-secondary w-100">

                    <form method="POST" id="formPerpanjangan">
                        <!-- Data Usaha (Statis) -->
                        <h5 class="section-title">Data Usaha</h5>

                        <div class="mb-3">
                            <label class="form-label">Nama Usaha</label>
                            <div class="data-static"><?php echo htmlspecialchars($pendaftaran['nama_usaha']); ?></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kecamatan</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['kecamatan']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelurahan/Desa</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['kel_desa']); ?></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">RT/RW</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['usaha_rt_rw'] ?: '-'); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Telepon Perusahaan</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['no_telp_perusahaan'] ?: '-'); ?></div>
                            </div>
                        </div>

                        <!-- Data Pemilik (Statis) -->
                        <h5 class="section-title mt-4">Data Pemilik</h5>

                        <div class="mb-3">
                            <label class="form-label">Nama Pemilik</label>
                            <div class="data-static"><?php echo htmlspecialchars($pendaftaran['nama_lengkap']); ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alamat Pemilik</label>
                            <div class="data-static"><?php echo htmlspecialchars($alamat_lengkap); ?></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Telepon Pemilik</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['no_wa']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['email']); ?></div>
                            </div>
                        </div>

                        <!-- Informasi Usaha -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Usaha</label>
                                <div class="data-static">Industri Kecil</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jumlah Tenaga Kerja</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['jml_tenaga_kerja']); ?> orang</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Produk</label>
                            <div class="data-static"><?php echo htmlspecialchars($pendaftaran['hasil_produk']); ?></div>
                        </div>

                        <!-- Informasi Merek (Statis) -->
                        <h5 class="section-title mt-4">Informasi Merek</h5>

                        <div class="mb-3">
                            <label class="form-label">Merek yang Difasilitasi</label>
                            <div class="data-static">
                                <strong><?php echo htmlspecialchars($merek_difasilitasi); ?></strong>
                                <br>
                                <small class="text-muted">
                                    (Merek Alternatif <?php echo $no_merek_difasilitasi; ?> dari pendaftaran sebelumnya)
                                </small>
                            </div>
                        </div>

                        <!-- Sertifikat Lama -->
                        <?php if ($sertifikat_lama): ?>
                            <h5 class="section-title mt-4">Sertifikat yang Terdaftar</h5>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">
                                                <i class="fa-solid fa-certificate me-2 text-success"></i>
                                                Sertifikat Merek Terdaftar
                                            </h6>
                                            <div class="alert alert-success mb-3">
                                                <i class="fa-solid fa-check-circle me-2"></i>
                                                <strong>File Tersedia</strong>
                                                <p class="mb-0 mt-2 small">
                                                    <i class="fa-solid fa-calendar me-1"></i>
                                                    Diupload: <?php echo date('d/m/Y H:i', strtotime($sertifikat_lama['tgl_upload'])); ?> WIB
                                                </p>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-success btn-view-sertifikat"
                                                    data-src="<?php echo htmlspecialchars($sertifikat_lama['file_path']); ?>"
                                                    data-title="Sertifikat Merek">
                                                    <i class="fas fa-eye me-1"></i> Preview
                                                </button>
                                                <a class="btn btn-success btn-sm" href="<?php echo htmlspecialchars($sertifikat_lama['file_path']); ?>" target="_blank" download>
                                                    <i class="fa-solid fa-download me-1"></i> Download Sertifikat
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Tidak ada sertifikat lama yang terdaftar. Silakan hubungi admin.
                            </div>
                        <?php endif; ?>

                        <!-- Tanda Tangan Digital -->
                        <h5 class="section-title mt-4">Tanda Tangan Digital</h5>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Petunjuk:</strong> Buat tanda tangan Anda di area di bawah ini. Tanda tangan ini akan digunakan sebagai bukti persetujuan permohonan perpanjangan.
                        </div>

                        <div class="signature-container">
                            <label class="form-label">Buat Tanda Tangan Anda <span class="text-danger">*</span></label>
                            <canvas id="signature-pad" width="600" height="200"></canvas>

                            <div class="signature-buttons">
                                <button type="button" class="btn btn-secondary btn-sm" id="clear-signature">
                                    <i class="fas fa-eraser me-1"></i> Hapus
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" id="save-signature">
                                    <i class="fas fa-check me-1"></i> Simpan Tanda Tangan
                                </button>
                            </div>

                            <div class="signature-preview" id="signature-preview">
                                <label class="form-label">Preview Tanda Tangan:</label>
                                <img id="signature-image" src="/placeholder.svg" alt="Tanda Tangan">
                            </div>

                            <input type="hidden" name="signature_data" id="signature-data" required>
                        </div>

                        <!-- Submit Button -->
                        <div class="text-center mt-4">
                            <div class="alert alert-warning d-inline-block mb-3" style="max-width: 600px;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Perhatian:</strong> Dengan menekan tombol "Kirim Permohonan Perpanjangan", Anda menyatakan bahwa data yang ditampilkan masih valid dan tanda tangan digital yang dibuat adalah asli.
                            </div>
                            <br>
                            <button type="submit" class="btn btn-submitpendaftaran" id="btnSubmit" disabled>
                                <i class="fas fa-paper-plane pe-2"></i> Kirim Permohonan Perpanjangan
                            </button>
                            <br>
                            <small class="text-muted mt-2">* Tombol akan aktif setelah tanda tangan dibuat</small>
                        </div>
                    </form>
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

    <!-- Modal View -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2 bg-light">
                    <h6 class="modal-title mb-0" id="modalTitle"></h6>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div id="imageContainer" style="display: none;">
                        <img id="modalImage" src="/placeholder.svg" alt="Preview" class="img-fluid rounded" style="max-height: 50vh; width: 100%; object-fit: contain;" />
                    </div>
                    <div id="pdfContainer" style="display: none;">
                        <iframe id="modalPdf" src="" style="width: 100%; height: 50vh; border: 1px solid #dee2e6; border-radius: 0.375rem;"></iframe>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <a id="downloadBtn" href="#" download class="btn btn-success btn-sm">
                        <i class="fas fa-download me-1"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Alert Modal
        function showAlert(message, type = 'warning') {
            const icon = type === 'danger' ? '❌' : type === 'success' ? '✅' : '⚠️';
            const alertModal = ``
                + '<div class="modal fade" id="alertModal" tabindex="-1">'
                + '    <div class="modal-dialog modal-dialog-centered modal-sm">'
                + '        <div class="modal-content">'
                + '            <div class="modal-body text-center p-4">'
                + '                <div class="fs-1 mb-3">' + icon + '</div>'
                + '                <p class="mb-0" style="white-space: pre-line;">' + message + '</p>'
                + '            </div>'
                + '            <div class="modal-footer border-0 justify-content-center">'
                + '                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>'
                + '            </div>'
                + '        </div>'
                + '    </div>'
                + '</div>';
            const existingModal = document.getElementById('alertModal');
            if (existingModal) existingModal.remove();
            document.body.insertAdjacentHTML('beforeend', alertModal);
            const modal = new bootstrap.Modal(document.getElementById('alertModal'));
            modal.show();
            modal.addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Session alert
        <?php if (isset($_SESSION['alert_message'])): ?>
            showAlert(<?php echo json_encode($_SESSION['alert_message']); ?>, '<?php echo $_SESSION['alert_type']; ?>');
            <?php
            unset($_SESSION['alert_message']);
            unset($_SESSION['alert_type']);
            ?>
        <?php endif; ?>

        // Signature Pad
        const canvas = document.getElementById('signature-pad');
        const ctx = canvas.getContext('2d');
        const clearBtn = document.getElementById('clear-signature');
        const saveBtn = document.getElementById('save-signature');
        const signatureData = document.getElementById('signature-data');
        const signaturePreview = document.getElementById('signature-preview');
        const signatureImage = document.getElementById('signature-image');
        const btnSubmit = document.getElementById('btnSubmit');

        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;

        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        function getPosition(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            if (e.touches) {
                return {
                    x: (e.touches[0].clientX - rect.left) * scaleX,
                    y: (e.touches[0].clientY - rect.top) * scaleY
                };
            }
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top) * scaleY
            };
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            startDrawing(e);
        });
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            draw(e);
        });
        canvas.addEventListener('touchend', stopDrawing);

        function startDrawing(e) {
            isDrawing = true;
            const pos = getPosition(e);
            lastX = pos.x;
            lastY = pos.y;
        }

        function draw(e) {
            if (!isDrawing) return;
            const pos = getPosition(e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
        }

        function stopDrawing() {
            isDrawing = false;
        }

        clearBtn.addEventListener('click', () => {
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            signatureData.value = '';
            signaturePreview.style.display = 'none';
            btnSubmit.disabled = true;
        });

        saveBtn.addEventListener('click', () => {
            const imageData = canvas.toDataURL('image/png');
            signatureData.value = imageData;
            signatureImage.src = imageData;
            signaturePreview.style.display = 'block';
            btnSubmit.disabled = false;
            showAlert('Tanda tangan berhasil disimpan!', 'success');
        });

        document.getElementById('formPerpanjangan').addEventListener('submit', function(e) {
            if (!signatureData.value) {
                e.preventDefault();
                showAlert('Tanda tangan digital wajib dibuat dan disimpan!', 'danger');
                return false;
            }
            if (!confirm('Apakah Anda yakin data yang ditampilkan masih valid dan ingin mengajukan perpanjangan sertifikat?')) {
                e.preventDefault();
                return false;
            }
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin pe-2"></i> Memproses...';
        });

        // Preview sertifikat
        document.querySelectorAll('.btn-view-sertifikat').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const src = this.getAttribute('data-src');
                const title = this.getAttribute('data-title');
                const modalTitle = document.getElementById('modalTitle');
                const downloadBtn = document.getElementById('downloadBtn');
                const imageContainer = document.getElementById('imageContainer');
                const pdfContainer = document.getElementById('pdfContainer');
                const modalImg = document.getElementById('modalImage');
                const modalPdf = document.getElementById('modalPdf');
                modalTitle.textContent = title;
                downloadBtn.href = src;
                const fileExtension = src.split('.').pop().toLowerCase();
                if (fileExtension === 'pdf') {
                    imageContainer.style.display = 'none';
                    pdfContainer.style.display = 'block';
                    modalPdf.src = src + '#toolbar=0';
                } else {
                    pdfContainer.style.display = 'none';
                    imageContainer.style.display = 'block';
                    modalImg.src = src;
                }
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            });
        });

        const imageModal = document.getElementById('imageModal');
        imageModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('modalPdf').src = '';
            document.getElementById('modalImage').src = '';
        });
    </script>
</body>

</html>
