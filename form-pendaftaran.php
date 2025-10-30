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

// ===== CEK APAKAH USER SUDAH PERNAH MENDAFTAR =====
try {
    $stmt = $pdo->prepare("SELECT id_pendaftaran, status_validasi, tgl_daftar FROM pendaftaran WHERE NIK = ? ORDER BY tgl_daftar DESC LIMIT 1");
    $stmt->execute([$NIK]);
    $pendaftaran_aktif = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika sudah pernah mendaftar, redirect ke halaman status
    if ($pendaftaran_aktif) {
        echo "<script>
                alert('Anda sudah memiliki pendaftaran merek yang aktif.\\n\\nSetiap akun hanya dapat melakukan 1 kali pendaftaran merek.\\n\\nSilakan cek status pengajuan Anda.');
                window.location.href = 'status-seleksi-pendaftaran.php';
              </script>";
        exit();
    }
} catch (PDOException $e) {
    error_log("Error checking pendaftaran: " . $e->getMessage());
    echo "<script>
            alert('Terjadi kesalahan sistem. Silakan coba lagi.');
            window.location.href = 'home.php';
          </script>";
    exit();
}

// ===== PROSES FORM SUBMISSION =====
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Double check apakah sudah pernah mendaftar (proteksi tambahan)
        $stmt = $pdo->prepare("SELECT id_pendaftaran FROM pendaftaran WHERE NIK = ? LIMIT 1");
        $stmt->execute([$NIK]);
        if ($stmt->fetch()) {
            throw new Exception("Anda sudah memiliki pendaftaran aktif. Setiap akun hanya dapat mendaftar 1 kali.");
        }

        // Mulai transaction
        $pdo->beginTransaction();

        // ===== 1. Simpan ke tabel datausaha =====
        $nama_usaha = trim($_POST['nama_usaha']);
        $rt_rw = trim($_POST['rt_rw']);
        $kel_desa = trim($_POST['kel_desa']);
        $kecamatan = trim($_POST['kecamatan']);
        $no_telp_perusahaan = trim($_POST['no_telp_perusahaan']);

        // Parse data produk dari JSON
        $produk_data = json_decode($_POST['produk_data'], true);
        $hasil_produk = [];
        $kapasitas_produk = [];
        $omset_perbulan = [];

        foreach ($produk_data as $produk) {
            $hasil_produk[] = $produk['nama'];
            $kapasitas_produk[] = $produk['nama'] . ": " . $produk['jumlah'] . " unit/bulan";
            $omset_perbulan[] = $produk['nama'] . ": Rp " . number_format($produk['omset'], 0, ',', '.');
        }

        // Hitung total omset
        $total_omset = array_sum(array_column($produk_data, 'omset'));
        $omset_perbulan[] = "TOTAL OMSET: Rp " . number_format($total_omset, 0, ',', '.');

        $hasil_produk_str = implode(', ', $hasil_produk);
        $kapasitas_produk_str = implode('; ', $kapasitas_produk);
        $omset_perbulan_str = implode('; ', $omset_perbulan);

        $jml_tenaga_kerja = intval($_POST['jml_tenaga_kerja']);
        $wilayah_pemasaran = trim($_POST['wilayah_pemasaran']);

        // Gabungkan legalitas yang dipilih
        $legalitas = [];
        if (isset($_POST['legalitas']) && is_array($_POST['legalitas'])) {
            $legalitas = $_POST['legalitas'];
        }
        if (isset($_POST['legalitas_lain']) && !empty(trim($_POST['legalitas_lain']))) {
            $legalitas[] = trim($_POST['legalitas_lain']);
        }
        $legalitas_string = implode(', ', $legalitas);

        $stmt = $pdo->prepare("INSERT INTO datausaha (nama_usaha, rt_rw, kel_desa, kecamatan, no_telp_perusahaan, hasil_produk, jml_tenaga_kerja, kapasitas_produk, omset_perbulan, wilayah_pemasaran, legalitas)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nama_usaha, $rt_rw, $kel_desa, $kecamatan, $no_telp_perusahaan, $hasil_produk_str, $jml_tenaga_kerja, $kapasitas_produk_str, $omset_perbulan_str, $wilayah_pemasaran, $legalitas_string]);
        $id_usaha = $pdo->lastInsertId();

        // ===== 2. Simpan ke tabel pendaftaran =====
        $tgl_daftar = date('Y-m-d H:i:s');
        $status_validasi = 'Pengecekan Berkas';

        $stmt2 = $pdo->prepare("INSERT INTO pendaftaran (NIK, id_usaha, tgl_daftar, status_validasi)
                                VALUES (?, ?, ?, ?)");
        $stmt2->execute([$NIK, $id_usaha, $tgl_daftar, $status_validasi]);
        $id_pendaftaran = $pdo->lastInsertId();

        // ===== 3. Upload file helper function =====
        function uploadFile($fileInputName, $folder = 'uploads/')
        {
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            if (isset($_FILES[$fileInputName]) && !empty($_FILES[$fileInputName]['name'])) {
                $file_extension = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Format file tidak diizinkan untuk {$fileInputName}");
                }

                $filename = time() . "_" . uniqid() . "." . $file_extension;
                $target = $folder . $filename;

                if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $target)) {
                    return $target;
                } else {
                    throw new Exception("Gagal mengupload file {$fileInputName}");
                }
            }
            return null;
        }

        // ===== 4. Simpan ke tabel merek =====
        $kelas_merek = trim($_POST['kelas_merek']);
        $nama_merek1 = trim($_POST['nama_merek1']);
        $nama_merek2 = trim($_POST['nama_merek2']);
        $nama_merek3 = trim($_POST['nama_merek3']);

        $logo1 = uploadFile('logo1', 'uploads/logo/');
        $logo2 = uploadFile('logo2', 'uploads/logo/');
        $logo3 = uploadFile('logo3', 'uploads/logo/');

        $stmt3 = $pdo->prepare("INSERT INTO merek (id_pendaftaran, kelas_merek, nama_merek1, nama_merek2, nama_merek3, logo1, logo2, logo3)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt3->execute([$id_pendaftaran, $kelas_merek, $nama_merek1, $nama_merek2, $nama_merek3, $logo1, $logo2, $logo3]);

        // ===== 5. Simpan ke tabel lampiran =====
        function uploadMultipleFiles($inputName, $id_jenis_file, $id_pendaftaran, $pdo)
        {
            if (isset($_FILES[$inputName]) && !empty($_FILES[$inputName]['name'][0])) {
                $folder = "uploads/lampiran/";
                if (!file_exists($folder)) {
                    mkdir($folder, 0777, true);
                }

                $total_files = count($_FILES[$inputName]['name']);

                for ($i = 0; $i < $total_files; $i++) {
                    if (!empty($_FILES[$inputName]['name'][$i])) {
                        $file_extension = strtolower(pathinfo($_FILES[$inputName]['name'][$i], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

                        if (!in_array($file_extension, $allowed_extensions)) {
                            throw new Exception("Format file tidak diizinkan pada {$inputName}");
                        }

                        // Cek ukuran file
                        $max_size = ($id_jenis_file == 1) ? 10 * 1024 * 1024 : 1 * 1024 * 1024;
                        if ($_FILES[$inputName]['size'][$i] > $max_size) {
                            throw new Exception("Ukuran file {$_FILES[$inputName]['name'][$i]} melebihi batas maksimal");
                        }

                        $filename = time() . "_" . uniqid() . "_" . $i . "." . $file_extension;
                        $target = $folder . $filename;

                        if (move_uploaded_file($_FILES[$inputName]['tmp_name'][$i], $target)) {
                            $tgl_upload = date('Y-m-d H:i:s');
                            $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path)
                                                   VALUES (?, ?, ?, ?)");
                            $stmt->execute([$id_pendaftaran, $id_jenis_file, $tgl_upload, $target]);
                        } else {
                            throw new Exception("Gagal mengupload file {$_FILES[$inputName]['name'][$i]}");
                        }
                    }
                }
            }
        }

        // Upload lampiran NIB
        uploadMultipleFiles('nib_files', 1, $id_pendaftaran, $pdo);

        // Upload lampiran legalitas lainnya
        if (isset($_POST['legalitas']) && is_array($_POST['legalitas'])) {
            $legalitas_map = [
                'P-IRT' => 9,
                'BPOM-MD' => 10,
                'HALAL' => 11,
                'NUTRITION FACTS' => 12,
                'SNI' => 13
            ];

            foreach ($_POST['legalitas'] as $index => $legal) {
                $inputName = 'legalitas_files_' . $index;

                // Tentukan id_jenis_file berdasarkan nama legalitas
                $id_jenis_file = 14; // Default untuk legalitas lainnya

                if (isset($legalitas_map[$legal])) {
                    $id_jenis_file = $legalitas_map[$legal];
                }

                uploadMultipleFiles($inputName, $id_jenis_file, $id_pendaftaran, $pdo);
            }
        }

        // Upload legalitas lainnya (yang diinput manual)
        if (isset($_POST['legalitas_lain']) && !empty(trim($_POST['legalitas_lain']))) {
            // Cek apakah ada file untuk legalitas lain
            $legalitas_lain_index = count($_POST['legalitas'] ?? []);
            $inputName = 'legalitas_files_lain';

            // Buat input khusus untuk legalitas lainnya jika ada file
            if (isset($_FILES[$inputName]) && !empty($_FILES[$inputName]['name'][0])) {
                uploadMultipleFiles($inputName, 14, $id_pendaftaran, $pdo); // 14 = Legalitas Lainnya
            }
        }

        // Upload foto produk dan proses
        uploadMultipleFiles('foto_produk', 2, $id_pendaftaran, $pdo);
        uploadMultipleFiles('foto_proses', 3, $id_pendaftaran, $pdo);

        // Commit transaction
        $pdo->commit();

        // Redirect ke halaman status
        echo "<script>
                alert('Data pendaftaran merek berhasil dikirim!\\n\\nSilakan cek status pengajuan Anda secara berkala.\\n\\nTerima kasih.');
                window.location.href = 'status-seleksi-pendaftaran.php';
              </script>";
        exit();
    } catch (Exception $e) {
        // Rollback jika ada error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log error
        error_log("Error form pendaftaran: " . $e->getMessage());

        echo "<script>
                alert('Terjadi kesalahan: " . addslashes($e->getMessage()) . "\\n\\nSilakan coba lagi.');
                window.history.back();
              </script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pendaftaran Merek - Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/form-pendaftaran.css">

    <style>
        /* Alert info tambahan */
        .alert-info-pendaftaran {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .alert-info-pendaftaran strong {
            font-size: 1.1rem;
        }

        .alert-info-pendaftaran p {
            margin-bottom: 0;
            margin-top: 0.5rem;
            font-size: 0.95rem;
            opacity: 0.95;
        }

        /* Tabel Produk */
        .produk-table {
            width: 100%;
            margin-bottom: 1rem;
        }

        .produk-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
        }

        .produk-table td {
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .produk-table input {
            width: 100%;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
        }

        .produk-table .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        /* Preview Image Styles */
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background-color: #f8f9fa;
            min-height: 60px;
        }

        .preview-container:empty {
            display: none;
        }

        .preview-item {
            position: relative;
            width: 120px;
        }

        .preview-item img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }

        .preview-item .pdf-preview {
            width: 120px;
            height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
        }

        .preview-item .pdf-preview i {
            font-size: 3rem;
            color: #dc3545;
            margin-bottom: 8px;
        }

        .preview-item .remove-preview {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }

        .preview-item .remove-preview:hover {
            background: #c82333;
        }

        /* Drag & Drop Styles */
        .file-drop-zone {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-drop-zone:hover,
        .file-drop-zone.drag-over {
            border-color: #667eea;
            background-color: #e7f1ff;
        }

        .file-drop-zone i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .legalitas-upload-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px solid #dee2e6;
        }

        .legalitas-upload-section h6 {
            color: #495057;
            margin-bottom: 0.75rem;
        }
    </style>
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <div class="container main-container">
        <div class="row cont">
            <div class="col-lg-4">
                <h5 class="judul">Seleksi Peserta Fasilitasi Pendaftaran Merek Diperindang Sidoarjo</h5>
                <p>Fasilitasi gratis dari Disperindang Sidoarjo untuk membantu proses Pendaftaran Hak Merek Produk di Kemenkumham RI.</p>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-info-square pe-2"></i> Informasi Pendaftaran</h5>
                    <ul class="list-unstyled info-list">
                        <li>Tanggal Pendaftaran: <br> Sepanjang Tahun hingga kuota Habis</li>
                        <li>Output: <br> Sertifikat Merek</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal-check pe-2"></i>Syarat dan Ketentuan</h5>
                    <ul class="info-list">
                        <li>Industri Kecil yang memproduksi produk di Sidoarjo (tidak untuk jasa, catering, rumah makan, repacking, dst)</li>
                        <li>Aktif memproduksi dan memasarkan produknya secara kontinyu</li>
                        <li>Produk kemasan dengan masa simpan lebih dari 7 hari</li>
                        <li>Nomor Induk Berusaha (NIB) berbasis risiko dengan KBLI industri sesuai jenis produk</li>
                        <li>Logo Merek (3 Alternatif - beda gambar maupun tulisannya)</li>
                        <li>Foto produk jadi</li>
                        <li>Foto proses produksi yang membuktikan memang memproduksi sendiri</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal pe-2"></i>Catatan</h5>
                    <ul class="info-list">
                        <li>Cek Ketersediaan Merek: <br> Pastikan merek tersebut belum didaftarkan oleh orang lain <br> <a href="https://pdki-indonesia.dgip.go.id/" target="_blank">Cek di PDKI Indonesia</a></li>
                        <li>Kelas Merek: <br>Tentukan Kelas Merek dengan mencari "Sistem Klasifikasi Merek" di Google</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle bg-warning bg-opacity-10">
                    <h5><i class="bi bi-exclamation-triangle pe-2"></i>Perhatian</h5>
                    <ul class="info-list">
                        <li><strong>Setiap akun hanya dapat melakukan 1 kali pendaftaran merek</strong></li>
                        <li>Pastikan semua data yang diisi sudah benar dan lengkap</li>
                        <li>Data yang sudah dikirim tidak dapat diubah</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5>Bantuan</h5>
                    <p>Jika ada kendala dalam mengisi formulir bisa menghubungi kami dibawah ini.</p>
                    <a href="https://wa.me/6285233499260" class="help-contact" target="_blank">
                        <i class="fab fa-whatsapp pe-2"></i> Bidang Perindustrian Disperindag Sidoarjo
                    </a>
                    <p class="text-danger mt-2">* Tidak menerima panggilan, hanya chat.</p>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Alert Info Penting -->
                <div class="alert-info-pendaftaran">
                    <i class="bi bi-info-circle me-2" style="font-size: 1.5rem; vertical-align: middle;"></i>
                    <strong>Informasi Penting:</strong>
                    <p>Setiap akun hanya dapat melakukan <strong>1 kali pendaftaran merek</strong>. Pastikan semua data yang Anda isi sudah benar dan lengkap sebelum mengirim formulir ini.</p>
                </div>

                <div class="form-container border border-light-subtle">
                    <h4>Data Usaha</h4>
                    <hr class="border-2 border-secondary w-100">
                    <form method="POST" enctype="multipart/form-data" id="formPendaftaran">
                        <div class="row">
                            <div class="mb-3">
                                <label class="form-label">Nama Usaha <span class="text-danger">*</span></label>
                                <input type="text" name="nama_usaha" class="form-control" placeholder="Masukkan nama usaha sesuai ijin yang dimiliki" required>
                            </div>
                        </div>

                        <div class="row">
                            <label class="form-label">Alamat Perusahaan</label>
                            <div class="mb-3">
                                <label class="form-label-alamat">Kecamatan <span class="text-danger">*</span></label>
                                <select name="kecamatan" id="kecamatan" class="form-control" required>
                                    <option value="">-- Pilih Kecamatan --</option>
                                    <option value="Sidoarjo">Sidoarjo</option>
                                    <option value="Buduran">Buduran</option>
                                    <option value="Candi">Candi</option>
                                    <option value="Porong">Porong</option>
                                    <option value="Krembung">Krembung</option>
                                    <option value="Tulangan">Tulangan</option>
                                    <option value="Tanggulangin">Tanggulangin</option>
                                    <option value="Jabon">Jabon</option>
                                    <option value="Krian">Krian</option>
                                    <option value="Balongbendo">Balongbendo</option>
                                    <option value="Wonoayu">Wonoayu</option>
                                    <option value="Tarik">Tarik</option>
                                    <option value="Prambon">Prambon</option>
                                    <option value="Taman">Taman</option>
                                    <option value="Waru">Waru</option>
                                    <option value="Gedangan">Gedangan</option>
                                    <option value="Sedati">Sedati</option>
                                    <option value="Sukodono">Sukodono</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-alamat">RT/RW</label>
                                <input type="text" name="rt_rw" class="form-control" placeholder="Contoh: 003/005" pattern="\d{3}/\d{3}" title="Format: xxx/xxx (contoh: 003/005)">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-alamat">Kelurahan/Desa <span class="text-danger">*</span></label>
                                <select name="kel_desa" id="kel_desa" class="form-control" required>
                                    <option value="">-- Pilih Kecamatan Terlebih Dahulu --</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nomor Telepon Perusahaan</label>
                                <input type="tel" name="no_telp_perusahaan" class="form-control" placeholder="Kosongi jika tidak ada atau sama dengan nomor telepon pemilik">
                            </div>
                        </div>

                        <script>
                            // Data kelurahan/desa per kecamatan di Sidoarjo
                            const desaKelurahan = {
                                "Sidoarjo": ["Sidoarjo", "Lemahputro", "Magersari", "Gebang", "Celep", "Bulusidokare", "Urangagung", "Banjarbendo", "Blurukidul", "Cemengbakalan", "Jati", "Kemiri", "Lebo", "Rangkalkidul", "Sarirogo", "Suko", "Sumput", "Cemengkalan", "Pekawuman", "Pucang", "Pucanganom", "Sekardangan", "Sidoklumpuk", "Sidokumpul"],
                                "Buduran": ["Buduran", "Sawohan", "Siwalanpanji", "Prasung", "Banjarkemantren", "Banjarsari", "Damarsi", "Dukuhtengah", "Entalsewu", "Pagerwojo", "Sidokerto", "Sidomulyo", "Sidokepung", "Sukorejo", "Wadungasin"],
                                "Candi": ["Candi", "Durungbanjar", "Larangan", "Sumokali", "Sepande", "Kebonsari", "Kedensari", "Bligo", "Balongdowo", "Balonggabus", "Durungbanjar", "Durungbedug", "Gelam", "Jambangan", "Kalipecabean", "Karangtanjung", "Kebonsari", "Kedungkendo", "Kedungpeluk", "Kendalpencabean", "Klurak", "Ngampelsari", "Sidodadi", "Sugihwaras", "Sumorame", "Tenggulunan", "Wedoroklurak"],
                                "Porong": ["Porong", "Kebonagung", "Kesambi", "Plumbon", "Pesawahan", "Gedang", "Juwetkenongo", "Kedungboto", "Wunut", "Pamotan", "Kebakalan", "Gempol Pasargi", "Glagaharum", "Lajuk", "Candipari"],
                                "Krembung": ["Krembung", "Balanggarut", "Cangkring", "Gading", "Jenggot", "Kandangan", "Kedungrawan", "Kedungsumur", "Keperkeret", "Lemujut", "Ploso", "Rejeni", "Tambakrejo", "Tanjekwagir", "Wangkal", "Wonomlati", "Waung", "Mojoruntut"],
                                "Tulangan": ["Tulangan", "Jiken", "Kajeksan", "Kebaran", "Kedondong", "Kepatihan", "Kepunten", "Medalem", "Pangkemiri", "Sudimoro", "Tlasih", "Gelang", "Kepadangan", "Grabagan", "Singopadu", "Kemantren", "Janti", "Modong", "Grogol", "Kenongo", "Grinting"],
                                "Tanggulangin": ["kalisampurno", "kedensari", "Ganggang Pnjang", "Randegan", "Kalitengah", "Kedung Banteng", "Putat", "Ketapang", "Kalidawir", "Ketegan", "Banjar Panji", "Gempolsari", "Sentul", "Penatarsewu", "Banjarsari", "Ngaban", "Boro", "Kludan"],
                                "Jabon": ["Trompoasri", "Kedung Pandan", "Permisan", "Semambung", "Pangrih", "Kupang", "Tambak Kalisogo", "Kedungrejo", "Kedungcangkring", "Keboguyang", "Jemirahan", "Balongtani", "dukuhsari"],
                                "Krian": ["Sidomojo", "Sidomulyo", "Sidorejo", "Tempel", "Terik", "Terungkulon", "Terungwetan", "Tropodo", "Watugolong", "Krian", "Kemasan", "Tambakkemeraan", "Sedenganmijen", "Bareng Krajan", "Keraton", "Keboharan", "Katerungan", "Jeruk Gamping", "Junwangi", "Jatikalang", "Gamping", "Ponokawan"],
                                "Balongbendo": ["Balongbendo", "", "WonoKupang", "Kedungsukodani", "Kemangsen", "Penambangan", "Seduri", "Seketi", "Singkalan", "SumoKembangsri", "Waruberon", "Watesari", "Wonokarang", "Jeruklegi", "Jabaran", "Suwaluh", "Gadungkepuhsari", "Bogempinggir", "Bakungtemenggungan", "Bakungpringgodani", "Wringinpitu", "Bakalan"],
                                "Wonoayu": ["Becirongengor", "Candinegoro", "Jimbaran Kulon", "Jimbaran wetan", "Pilang", "Karangturi", "Ketimang", "Lambangan", "Mohorangagung", "Mulyodadi", "Pagerngumbuk", "Plaosan", "Ploso", "Popoh", "Sawocangkring", "semambung", "Simoangin-angin", "Simoketawang", "Sumberejo", "Tanggul", "Wonoayu", "Wonokalang", "Wonokasian"],
                                "Tarik": ["Tarik", "Klantingsari", "GedangKlutuk", "Mergosari", "Kedinding", "Kemuning", "Janti", "Mergobener", "Mliriprowo", "Singogalih", "Kramat Temenggung", "Kedungbocok", "Segodobancang", "Gampingrowo", "Mindugading", "Kalimati", "Banjarwungu", "Balongmacekan", "Kendalsewu", "Sebani"],
                                "Prambon": ["Prambon", "Bendotretek", "Bulang", "Cangkringturi", "Gampang", "Gedangrowo", "Jati alun-alun", "Watutulis", "jatikalang", "jedongcangkring", "Kajartengguli", "Kedungkembanr", "Kedung Sugo", "Kedungwonokerto", "Penjangkkungan", "Simogirang", "Simpang", "Temu", "Wirobiting", "Wonoplintahan"],
                                "Taman": ["Taman", "Trosobo", "Sepanjang", "Ngelom", "Ketegan", "Jemundo", "Geluran", "Wage", "Bebekan", "Kalijaten", "Tawangsari", "Sidodadi", "Sambibulu", "Sadang", "Maduretno", "Krembangan", "Pertapan", "Kramatjegu", "Kletek", "Tanjungsari", "Kedungturi", "Gilang", "Bringinbendo", "Bohar", "Wonocolo"],
                                "Waru": ["Waru", "Tropodo", "Kureksari", "Jambangan", "Medaeng", "Berbek", "Bungurasih", "Janti", "Kedungrejo", "Kepuhkiriman", "Ngingas", "Pepelegi", "Tambakoso", "Tambakrejo", "Tambahsawah", "Tambaksumur", "Wadungasri", "Wedoro"],
                                "Gedangan": ["Gedangan", "Ketajen", "Wedi", "Bangah", "Sawotratap", "Semambung", "Ganting", "Tebel", "Kebonanom", "Gemurung", "Karangbong", "Kebiansikep", "Kragan", "Punggul", "Seruni"],
                                "Sedati": ["Sedati", "Pabean", "Semampir", "Banjarkemuningtambak", "Pulungan", "Betro", "Segoro Tambak", "Gisik Cemandi", "Cemandi", "Kalanganyar", "Buncitan", "Wangsan", "Pranti", "Pepe", "Sedatiagung", "Sedatigede", "Tambakcemandi"],
                                "Sukodono": ["Sukodono", "Jumputrejo", "Kebonagung", "Keloposepuluh", "Jogosatru", "Suruh", "Ngaresrejo", "Cangkringsari", "Masangan Wetan", "Masangan Kulon", "Bangsri", "Anggaswangi", "Pandemonegoro", "Panjunan", "Pekarungan", "Plumbungan", "Sambungrejo", "Suko", "Wilayut"]
                            };

                            // Event listener untuk perubahan kecamatan
                            document.getElementById('kecamatan').addEventListener('change', function() {
                                const kecamatan = this.value;
                                const kelDesaSelect = document.getElementById('kel_desa');

                                // Reset dropdown kelurahan/desa
                                kelDesaSelect.innerHTML = '<option value="">-- Pilih Kelurahan/Desa --</option>';

                                if (kecamatan && desaKelurahan[kecamatan]) {
                                    desaKelurahan[kecamatan].forEach(function(desa) {
                                        const option = document.createElement('option');
                                        option.value = desa;
                                        option.textContent = desa;
                                        kelDesaSelect.appendChild(option);
                                    });
                                    kelDesaSelect.disabled = false;
                                } else {
                                    kelDesaSelect.disabled = true;
                                }
                            });
                        </script>
                        <div class="mb-3">
                            <label class="form-label">Produk, Kapasitas Produksi, dan Omset <span class="text-danger">*</span></label>
                            <p class="text-muted small">Isi data produk yang dihasilkan beserta kapasitas produksi per bulan, harga satuan, dan omset akan dihitung otomatis</p>

                            <div class="table-responsive">
                                <table class="produk-table table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%">Nama Produk</th>
                                            <th style="width: 15%">Jumlah Produk/Bulan</th>
                                            <th style="width: 18%">Harga Satuan (Rp)</th>
                                            <th style="width: 20%">Omset/Bulan (Rp)</th>
                                            <th style="width: 7%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="produkTableBody">
                                        <tr>
                                            <td><input type="text" class="form-control form-control-sm produk-nama" placeholder="Contoh: Minuman Sinom Botol" required></td>
                                            <td><input type="number" class="form-control form-control-sm produk-jumlah" placeholder="50" min="1" required></td>
                                            <td><input type="number" class="form-control form-control-sm produk-harga" placeholder="5000" min="0" required></td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm produk-omset bg-light" readonly value="Rp 0">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-sm remove-produk" disabled>
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-secondary">
                                            <td colspan="3" class="text-end"><strong>Total Omset per Bulan:</strong></td>
                                            <td><strong id="totalOmset">Rp 0</strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <button type="button" class="btn btn-primary btn-sm" id="addProdukRow">
                                <i class="fas fa-plus me-1"></i> Tambah Produk
                            </button>

                            <input type="hidden" name="produk_data" id="produkData">
                        </div>

                        <div class="row">
                            <div class="mb-3">
                                <label class="form-label">Jumlah Tenaga Kerja <span class="text-danger">*</span></label>
                                <input type="number" name="jml_tenaga_kerja" class="form-control" placeholder="Apabila dilakukan sendiri maka tenaga kerja = 1" required min="1">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Wilayah pemasaran <span class="text-danger">*</span></label>
                            <textarea name="wilayah_pemasaran" class="form-control" rows="3" placeholder="Sebutkan kota tujuan pemasaran. Misal: Sidoarjo, Gresik, Surabaya, Malang, dst." required></textarea>
                        </div>

                        <h5>Lampiran Dokumen</h5>
                        <hr class="border-2 border-secondary w-100">

                        <div class="mb-3">
                            <label class="form-label">Lampiran 1: Nomor Induk Berusaha (NIB) <span class="text-danger">*</span></label>
                            <label class="form-label-alamat">Beserta lampiran tabel KBLI halaman 2, dari website <a href="https://oss.go.id/" target="_blank"> https://oss.go.id/. </a></label>
                            <div class="file-drop-zone" id="nibDropZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                <small>Upload maksimal 5 file (PDF atau image). Maks 10 MB per file</small>
                                <input type="file" name="nib_files[]" id="nib-file" accept=".pdf,.jpg,.jpeg,.png" multiple hidden>
                            </div>
                            <div class="preview-container" id="nibPreview"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Legalitas/Standardisasi yang telah dimiliki</label>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input legalitas-checkbox" type="checkbox" name="legalitas[]" value="NIB" id="nib">
                                        <label class="form-check-label" for="nib">NIB</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input legalitas-checkbox" type="checkbox" name="legalitas[]" value="P-IRT" id="pirt">
                                        <label class="form-check-label" for="pirt">P-IRT</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input legalitas-checkbox" type="checkbox" name="legalitas[]" value="BPOM-MD" id="bpommd">
                                        <label class="form-check-label" for="bpommd">BPOM-MD</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input legalitas-checkbox" type="checkbox" name="legalitas[]" value="HALAL" id="halal">
                                        <label class="form-check-label" for="halal">HALAL</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input legalitas-checkbox" type="checkbox" name="legalitas[]" value="NUTRITION FACTS" id="nutrition">
                                        <label class="form-check-label" for="nutrition">NUTRITION FACTS</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input legalitas-checkbox" type="checkbox" name="legalitas[]" value="SNI" id="sni">
                                        <label class="form-check-label" for="sni">SNI</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="legalitas_lain" placeholder="Yang lain" class="form-control">
                                </div>
                            </div>

                            <!-- Upload Section untuk Legalitas yang dipilih -->
                            <div id="legalitasUploadContainer"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lampiran 2: Foto Produk <span class="text-danger">*</span></label>
                            <div class="file-drop-zone" id="produkDropZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                <small>Upload maksimal 5 file (JPG/PNG). Maks 1 MB per file</small>
                                <input type="file" name="foto_produk[]" id="product-file" accept=".jpg,.jpeg,.png" multiple hidden>
                            </div>
                            <div class="preview-container" id="produkPreview"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lampiran 3: Foto Proses Produksi <span class="text-danger">*</span></label>
                            <div class="file-drop-zone" id="prosesDropZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                <small>Upload maksimal 5 file (JPG/PNG). Maks 1 MB per file</small>
                                <input type="file" name="foto_proses[]" id="prosesproduksi-file" accept=".jpg,.jpeg,.png" multiple hidden>
                            </div>
                            <div class="preview-container" id="prosesPreview"></div>
                        </div>

                        <h5>Informasi Merek</h5>
                        <hr class="border-2 border-secondary w-100">

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <label class="form-label mb-0">
                                    Kelas Merek sesuai produk <span class="text-danger">*</span>
                                </label>
                                <a href="#"
                                    class="text-primary text-decoration-none"
                                    style="font-size: 0.9rem;"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalKlasifikasiMerek">
                                    Lihat Sistem Klasifikasi Merek
                                </a>
                            </div>

                            <textarea name="kelas_merek" class="form-control mt-2" rows="3"
                                placeholder="Tentukan Kelas Merek (cek 'Sistem Klasifikasi Merek' di Google)" required></textarea>
                        </div>

                        <!-- Modal Sistem Klasifikasi Merek -->
                        <div class="modal fade" id="modalKlasifikasiMerek" tabindex="-1" aria-labelledby="modalKlasifikasiMerekLabel" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalKlasifikasiMerekLabel">
                                            <i class="fas fa-book me-2"></i>Sistem Klasifikasi Merek
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0" style="height: 65vh;">
                                        <iframe src="https://skm.dgip.go.id/"
                                            class="w-100 h-100"
                                            frameborder="0"
                                            title="Sistem Klasifikasi Merek">
                                        </iframe>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-2"></i>Tutup
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="w-100 trademark-alternative">
                            <div>
                                <label class="form-label">Contoh Alternatif Merek yang Sesuai</label>
                                <div class="trademark-examples">
                                    <div class="trademark-example">
                                        <img src="assets/img/aqua.png" alt="AQUA">
                                        <h6 class="fw-normal">Nama Merek Alternatif 1<br><strong>AQUA</strong></h6>
                                    </div>
                                    <div class="trademark-example">
                                        <img src="assets/img/leminerale.png" alt="LE MINERALE">
                                        <h6 class="fw-normal">Nama Merek Alternatif 2<br><strong>LE MINERALE</strong></h6>
                                    </div>
                                    <div class="trademark-example">
                                        <img src="assets/img/cleo.png" alt="CLEO">
                                        <h6 class="fw-normal">Nama Merek Alternatif 3<br><strong>CLEO</strong></h6>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <p style="font-size: 0.9rem; color: #666;">
                                    <strong>Catatan:</strong>
                                <ul class="info-list">
                                    <li>Harus berbeda baik logo dan namanya</li>
                                    <li>Tanpa tulisan lain seperti nomor whatsapp, komposisi, dll. Cukup nama merek saja.</li>
                                </ul>
                                </p>
                            </div>
                        </div>

                        <div class="trademark-alternative ms-4">
                            <h6>Merek Alternatif 1 (diutamakan) <span class="text-danger">*</span></h6>
                            <div class="mb-2">
                                <label class="form-label">Nama Merek Alternatif 1</label>
                                <input type="text" name="nama_merek1" class="form-control" placeholder="Masukkan nama merek alternatif 1" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Logo Merek Alternatif 1</label>
                                <div class="file-drop-zone" id="logo1DropZone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                    <small>Upload 1 file (JPG/PNG). Maks 1 MB</small>
                                    <input type="file" name="logo1" id="logo1-file" accept=".jpg,.jpeg,.png" hidden>
                                </div>
                                <div class="preview-container" id="logo1Preview"></div>
                            </div>
                        </div>

                        <div class="trademark-alternative ms-4">
                            <h6>Merek Alternatif 2 <span class="text-danger">*</span></h6>
                            <div class="mb-2">
                                <label class="form-label">Nama Merek Alternatif 2</label>
                                <input type="text" name="nama_merek2" class="form-control" placeholder="Masukkan nama merek alternatif 2" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Logo Merek Alternatif 2</label>
                                <div class="file-drop-zone" id="logo2DropZone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                    <small>Upload 1 file (JPG/PNG). Maks 1 MB</small>
                                    <input type="file" name="logo2" id="logo2-file" accept=".jpg,.jpeg,.png" hidden>
                                </div>
                                <div class="preview-container" id="logo2Preview"></div>
                            </div>
                        </div>

                        <div class="trademark-alternative ms-4">
                            <h6>Merek Alternatif 3 <span class="text-danger">*</span></h6>
                            <div class="mb-2">
                                <label class="form-label">Nama Merek Alternatif 3</label>
                                <input type="text" name="nama_merek3" class="form-control" placeholder="Masukkan nama merek alternatif 3" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Logo Merek Alternatif 3</label>
                                <div class="file-drop-zone" id="logo3DropZone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                    <small>Upload 1 file (JPG/PNG). Maks 1 MB</small>
                                    <input type="file" name="logo3" id="logo3-file" accept=".jpg,.jpeg,.png" hidden>
                                </div>
                                <div class="preview-container" id="logo3Preview"></div>
                            </div>
                        </div>

                        <div class="text-center">
                            <div class="alert alert-warning d-inline-block mb-3" style="max-width: 600px;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Perhatian:</strong> Dengan menekan tombol "Kirim Data Pendaftaran", Anda menyatakan bahwa semua data yang diisi sudah benar dan lengkap. Data yang sudah dikirim tidak dapat diubah.
                            </div>
                            <br>
                            <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                                <strong>AMANKAN IDENTITAS BISNIS ANDA, DAFTARKAN MEREK SEKARANG!</strong>
                            </p>
                            <button type="submit" class="btn btn-submitpendaftaran" id="btnSubmit">
                                <i class="fas fa-paper-plane pe-2"></i> Kirim Data Pendaftaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer  -->
    <footer class="footer">
        <div class="container">
            <p>Copyright © 2025. All Rights Reserved.</p>
            <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('addProdukRow').addEventListener('click', function() {
            const tbody = document.getElementById('produkTableBody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" class="form-control form-control-sm produk-nama" placeholder="Contoh: Minuman Sinom Botol" required></td>
                <td><input type="number" class="form-control form-control-sm produk-jumlah" placeholder="50" min="1" required></td>
                <td><input type="number" class="form-control form-control-sm produk-harga" placeholder="5000" min="0" required></td>
                <td>
                    <input type="text" class="form-control form-control-sm produk-omset bg-light" readonly value="Rp 0">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-produk">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(newRow);
            updateRemoveButtons();
            attachCalculationListeners();
        });

        document.getElementById('produkTableBody').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-produk') || e.target.parentElement.classList.contains('remove-produk')) {
                const button = e.target.classList.contains('remove-produk') ? e.target : e.target.parentElement;
                button.closest('tr').remove();
                updateRemoveButtons();
                calculateTotalOmset();
            }
        });

        function updateRemoveButtons() {
            const rows = document.querySelectorAll('#produkTableBody tr');
            rows.forEach((row, index) => {
                const removeBtn = row.querySelector('.remove-produk');
                if (rows.length === 1) {
                    removeBtn.disabled = true;
                } else {
                    removeBtn.disabled = false;
                }
            });
        }

        // ===== PERHITUNGAN OMSET =====
        function calculateRowOmset(row) {
            const jumlah = parseFloat(row.querySelector('.produk-jumlah').value) || 0;
            const harga = parseFloat(row.querySelector('.produk-harga').value) || 0;
            const omset = jumlah * harga;

            row.querySelector('.produk-omset').value = 'Rp ' + omset.toLocaleString('id-ID');

            calculateTotalOmset();
        }

        function calculateTotalOmset() {
            const rows = document.querySelectorAll('#produkTableBody tr');
            let total = 0;

            rows.forEach(row => {
                const jumlah = parseFloat(row.querySelector('.produk-jumlah').value) || 0;
                const harga = parseFloat(row.querySelector('.produk-harga').value) || 0;
                total += (jumlah * harga);
            });

            document.getElementById('totalOmset').textContent = 'Rp ' + total.toLocaleString('id-ID');
        }

        function attachCalculationListeners() {
            const rows = document.querySelectorAll('#produkTableBody tr');
            rows.forEach(row => {
                const jumlahInput = row.querySelector('.produk-jumlah');
                const hargaInput = row.querySelector('.produk-harga');

                jumlahInput.removeEventListener('input', () => calculateRowOmset(row));
                hargaInput.removeEventListener('input', () => calculateRowOmset(row));

                jumlahInput.addEventListener('input', () => calculateRowOmset(row));
                hargaInput.addEventListener('input', () => calculateRowOmset(row));
            });
        }

        // Attach listeners untuk baris pertama
        attachCalculationListeners();

        // ===== RT/RW FORMAT AUTO =====
        document.querySelector('input[name="rt_rw"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');

            if (value.length >= 3) {
                value = value.substring(0, 3) + '/' + value.substring(3, 6);
            }

            e.target.value = value;
        });
    </script>

    <!-- ===== NEW FILE UPLOAD HANDLER WITH STORAGE ===== -->
    <script>
        // ===== FILE STORAGE SYSTEM =====
        const fileStorage = {
            'nib_files': [],
            'foto_produk': [],
            'foto_proses': [],
            'logo1': [],
            'logo2': [],
            'logo3': []
        };

        // Fungsi untuk format ukuran file
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Fungsi untuk handle file input dengan akumulasi file
        function handleFileInput(inputElement, storageKey, previewContainerId, maxFiles = 5) {
            const files = Array.from(inputElement.files);

            // Cek jumlah total file
            if (fileStorage[storageKey].length + files.length > maxFiles) {
                alert(`Maksimal ${maxFiles} file untuk ${storageKey.replace(/_/g, ' ')}`);
                inputElement.value = '';
                return;
            }

            // Tambahkan file baru ke storage
            files.forEach(file => {
                const exists = fileStorage[storageKey].some(f =>
                    f.name === file.name && f.size === file.size
                );
                if (!exists) {
                    fileStorage[storageKey].push(file);
                }
            });

            // Update preview
            updateFilePreview(storageKey, previewContainerId);
            inputElement.value = '';
        }

        // Fungsi untuk update preview file
        function updateFilePreview(storageKey, containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;

            container.innerHTML = '';

            if (fileStorage[storageKey].length === 0) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'flex';

            fileStorage[storageKey].forEach((file, index) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.style.cssText = 'position: relative; width: 120px; margin: 5px;';

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        previewItem.innerHTML = `
                    <img src="${e.target.result}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #dee2e6;">
                    <button type="button" onclick="removeFile('${storageKey}', ${index}, '${containerId}')" 
                            class="remove-preview">×</button>
                    <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">
                        ${file.name}
                    </div>
                    <div style="font-size: 10px; text-align: center; color: #6c757d;">
                        ${formatFileSize(file.size)}
                    </div>
                `;
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    previewItem.innerHTML = `
                <div class="pdf-preview">
                    <i class="fas fa-file-pdf" style="font-size: 3rem; color: #dc3545; margin-bottom: 8px;"></i>
                    <div style="font-size: 10px; text-align: center; padding: 0 5px; color: #495057;">PDF</div>
                </div>
                <button type="button" onclick="removeFile('${storageKey}', ${index}, '${containerId}')" 
                        class="remove-preview">×</button>
                <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">
                    ${file.name}
                </div>
                <div style="font-size: 10px; text-align: center; color: #6c757d;">
                    ${formatFileSize(file.size)}
                </div>
            `;
                }

                container.appendChild(previewItem);
            });
        }

        // Fungsi untuk hapus file
        function removeFile(storageKey, index, containerId) {
            fileStorage[storageKey].splice(index, 1);
            updateFilePreview(storageKey, containerId);
        }

        // Setup Drag & Drop
        function setupDragDropWithStorage(dropZone, fileInput, previewContainer, storageKey, maxFiles = 5, maxSizeMB = 1) {
            if (!dropZone || !fileInput) return;

            dropZone.addEventListener('click', () => fileInput.click());

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('drag-over');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('drag-over');

                const dt = new DataTransfer();
                const droppedFiles = Array.from(e.dataTransfer.files);

                if (fileStorage[storageKey].length + droppedFiles.length > maxFiles) {
                    alert(`Maksimal ${maxFiles} file. Anda sudah memiliki ${fileStorage[storageKey].length} file.`);
                    return;
                }

                for (let file of droppedFiles) {
                    if (file.size > maxSizeMB * 1024 * 1024) {
                        alert(`File ${file.name} melebihi ${maxSizeMB} MB.`);
                        return;
                    }
                    dt.items.add(file);
                }

                fileInput.files = dt.files;
                handleFileInput(fileInput, storageKey, previewContainer.id, maxFiles);
            });

            fileInput.addEventListener('change', () => {
                const files = Array.from(fileInput.files);

                if (fileStorage[storageKey].length + files.length > maxFiles) {
                    alert(`Maksimal ${maxFiles} file. Anda sudah memiliki ${fileStorage[storageKey].length} file.`);
                    fileInput.value = '';
                    return;
                }

                for (let file of files) {
                    if (file.size > maxSizeMB * 1024 * 1024) {
                        alert(`File ${file.name} melebihi ${maxSizeMB} MB.`);
                        fileInput.value = '';
                        return;
                    }
                }

                handleFileInput(fileInput, storageKey, previewContainer.id, maxFiles);
            });
        }

        // ===== LEGALITAS UPLOAD SECTIONS =====
        function updateLegalitasUploads() {
            const legalitasCheckboxes = document.querySelectorAll('.legalitas-checkbox');
            const legalitasContainer = document.getElementById('legalitasUploadContainer');
            const legalitasLainInput = document.querySelector('input[name="legalitas_lain"]');

            legalitasContainer.innerHTML = '';

            // Upload untuk checkbox yang dipilih
            legalitasCheckboxes.forEach((checkbox, index) => {
                if (checkbox.checked) {
                    const legalitasName = checkbox.value;
                    const storageKey = `legalitas_files_${index}`;

                    if (!fileStorage[storageKey]) {
                        fileStorage[storageKey] = [];
                    }

                    const uploadSection = document.createElement('div');
                    uploadSection.className = 'legalitas-upload-section';
                    uploadSection.innerHTML = `
                <h6><i class="fas fa-file-upload me-2"></i>Upload File ${legalitasName}</h6>
                <div class="file-drop-zone legalitas-drop-zone" id="legalitas-drop-${index}">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                    <small>Upload maksimal 3 file (PDF/JPG/PNG). Maks 1 MB per file</small>
                    <input type="file" name="legalitas_files_${index}[]" id="legalitas-input-${index}" class="legalitas-file-input" accept=".pdf,.jpg,.jpeg,.png" multiple hidden>
                </div>
                <div class="preview-container" id="legalitas-preview-${index}"></div>
            `;
                    legalitasContainer.appendChild(uploadSection);

                    setTimeout(() => {
                        setupDragDropWithStorage(
                            document.getElementById(`legalitas-drop-${index}`),
                            document.getElementById(`legalitas-input-${index}`),
                            document.getElementById(`legalitas-preview-${index}`),
                            storageKey, 3, 1
                        );
                    }, 100);
                }
            });

            // Upload untuk "Legalitas Lainnya" jika ada input
            if (legalitasLainInput && legalitasLainInput.value.trim() !== '') {
                const storageKey = 'legalitas_files_lain';

                if (!fileStorage[storageKey]) {
                    fileStorage[storageKey] = [];
                }

                const uploadSection = document.createElement('div');
                uploadSection.className = 'legalitas-upload-section';
                uploadSection.innerHTML = `
            <h6><i class="fas fa-file-upload me-2"></i>Upload File ${legalitasLainInput.value}</h6>
            <div class="file-drop-zone legalitas-drop-zone" id="legalitas-drop-lain">
                <i class="fas fa-cloud-upload-alt"></i>
                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                <small>Upload maksimal 3 file (PDF/JPG/PNG). Maks 1 MB per file</small>
                <input type="file" name="legalitas_files_lain[]" id="legalitas-input-lain" class="legalitas-file-input" accept=".pdf,.jpg,.jpeg,.png" multiple hidden>
            </div>
            <div class="preview-container" id="legalitas-preview-lain"></div>
        `;
                legalitasContainer.appendChild(uploadSection);

                setTimeout(() => {
                    setupDragDropWithStorage(
                        document.getElementById('legalitas-drop-lain'),
                        document.getElementById('legalitas-input-lain'),
                        document.getElementById('legalitas-preview-lain'),
                        storageKey, 3, 1
                    );
                }, 100);
            }
        }

        // Event listener untuk input "Legalitas Lainnya"
        const legalitasLainInput = document.querySelector('input[name="legalitas_lain"]');
        if (legalitasLainInput) {
            legalitasLainInput.addEventListener('input', function() {
                // Delay untuk memberikan waktu user mengetik
                clearTimeout(window.legalitasLainTimeout);
                window.legalitasLainTimeout = setTimeout(() => {
                    updateLegalitasUploads();
                }, 500);
            });
        }

        // ===== FORM SUBMISSION HANDLER - FIXED VERSION =====
        function handleFormSubmit(e) {
            e.preventDefault();

            console.log('Form submission started');

            // 1. Validasi dan collect produk data
            const rows = document.querySelectorAll('#produkTableBody tr');
            const produkData = [];

            rows.forEach(row => {
                const nama = row.querySelector('.produk-nama').value.trim();
                const jumlah = parseInt(row.querySelector('.produk-jumlah').value) || 0;
                const harga = parseInt(row.querySelector('.produk-harga').value) || 0;

                if (nama && jumlah > 0 && harga > 0) {
                    produkData.push({
                        nama,
                        jumlah,
                        harga,
                        omset: jumlah * harga,
                        kapasitas: jumlah + ' unit'
                    });
                }
            });

            if (produkData.length === 0) {
                alert('Mohon isi minimal 1 data produk dengan lengkap!');
                return false;
            }

            document.getElementById('produkData').value = JSON.stringify(produkData);

            // 2. Validasi file wajib
            const requiredFiles = {
                'nib_files': 'File NIB',
                'foto_produk': 'Foto Produk',
                'foto_proses': 'Foto Proses Produksi',
                'logo1': 'Logo Merek Alternatif 1',
                'logo2': 'Logo Merek Alternatif 2',
                'logo3': 'Logo Merek Alternatif 3'
            };

            for (let [key, label] of Object.entries(requiredFiles)) {
                if (!fileStorage[key] || fileStorage[key].length === 0) {
                    alert(`${label} wajib diupload!`);
                    return false;
                }
            }

            console.log('All validations passed');

            // 3. Transfer files dari storage ke form inputs
            Object.keys(fileStorage).forEach(key => {
                if (fileStorage[key] && fileStorage[key].length > 0) {
                    let inputSelector;

                    // Untuk logo (single file)
                    if (key.startsWith('logo')) {
                        inputSelector = `input[name="${key}"]`;
                    }
                    // Untuk files array (multiple files)
                    else if (key.startsWith('legalitas_files_')) {
                        inputSelector = `input[name="${key}[]"]`;
                    }
                    // Untuk file arrays biasa
                    else {
                        inputSelector = `input[name="${key}[]"]`;
                    }

                    const input = document.querySelector(inputSelector);

                    if (input) {
                        const dataTransfer = new DataTransfer();
                        fileStorage[key].forEach(file => {
                            dataTransfer.items.add(file);
                        });
                        input.files = dataTransfer.files;
                        console.log(`Transferred ${fileStorage[key].length} files to ${key}`);
                    }
                }
            });

            // 4. Konfirmasi submit
            const confirmed = confirm(
                'Apakah Anda yakin semua data yang diisi sudah benar dan lengkap?\n\n' +
                'Setiap akun hanya dapat melakukan 1 kali pendaftaran merek.\n\n' +
                'Data yang sudah dikirim tidak dapat diubah.'
            );

            if (!confirmed) {
                console.log('User cancelled submission');
                return false;
            }

            // 5. Disable button dan submit
            const btnSubmit = document.getElementById('btnSubmit');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin pe-2"></i> Mengirim Data...';

            // Set flag untuk prevent warning
            window.formChanged = false;

            console.log('Submitting form...');

            // Submit form secara native
            e.target.submit();
        }

        // ===== INITIALIZE ON PAGE LOAD =====
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, initializing...');

            // Setup drag & drop untuk semua file inputs
            setupDragDropWithStorage(
                document.getElementById('nibDropZone'),
                document.getElementById('nib-file'),
                document.getElementById('nibPreview'),
                'nib_files', 5, 10
            );

            setupDragDropWithStorage(
                document.getElementById('produkDropZone'),
                document.getElementById('product-file'),
                document.getElementById('produkPreview'),
                'foto_produk', 5, 1
            );

            setupDragDropWithStorage(
                document.getElementById('prosesDropZone'),
                document.getElementById('prosesproduksi-file'),
                document.getElementById('prosesPreview'),
                'foto_proses', 5, 1
            );

            setupDragDropWithStorage(
                document.getElementById('logo1DropZone'),
                document.getElementById('logo1-file'),
                document.getElementById('logo1Preview'),
                'logo1', 1, 1
            );

            setupDragDropWithStorage(
                document.getElementById('logo2DropZone'),
                document.getElementById('logo2-file'),
                document.getElementById('logo2Preview'),
                'logo2', 1, 1
            );

            setupDragDropWithStorage(
                document.getElementById('logo3DropZone'),
                document.getElementById('logo3-file'),
                document.getElementById('logo3Preview'),
                'logo3', 1, 1
            );

            // Setup legalitas checkboxes
            const legalitasCheckboxes = document.querySelectorAll('.legalitas-checkbox');
            legalitasCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateLegalitasUploads);
            });

            // Setup form submit handler
            const form = document.getElementById('formPendaftaran');
            if (form) {
                form.addEventListener('submit', handleFormSubmit);
                console.log('Form submit handler attached');
            }

            // Warning when leaving page
            window.formChanged = false;

            document.querySelectorAll('#formPendaftaran input, #formPendaftaran textarea, #formPendaftaran select').forEach(element => {
                element.addEventListener('change', function() {
                    window.formChanged = true;
                });
            });

            window.addEventListener('beforeunload', function(e) {
                if (window.formChanged) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });

            console.log('Initialization complete');
        });
    </script>
</body>

</html>