<?php
session_start();
if (!isset($_SESSION['NIK_NIP']) || !isset($_SESSION['nama_lengkap'])) {
    header("Location: login.php");
    exit();
}

$nama = $_SESSION['nama_lengkap'];
$nik = $_SESSION['NIK_NIP'];

// ===== CEK APAKAH SUDAH PERNAH MENDAFTAR =====
require_once 'process/config_db.php';

$id_pendaftaran_aktif = null;

$sudahDaftar = false;

try {
    $stmt = $pdo->prepare("SELECT id_pendaftaran, status_validasi FROM pendaftaran WHERE NIK = ? ORDER BY tgl_daftar DESC LIMIT 1");
    $stmt->execute([$nik]);
    $pendaftaran_aktif = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pendaftaran_aktif) {
        $sudahDaftar = true;
        $id_pendaftaran_aktif = $pendaftaran_aktif['id_pendaftaran'];
    }
} catch (PDOException $e) {
    error_log("Error checking pendaftaran: " . $e->getMessage());
}

$total_kuota = 100;

$tahun_sekarang = date('Y');

try {
    $query_difasilitasi = "SELECT COUNT(*) as jumlah_difasilitasi 
                           FROM pendaftaran 
                           WHERE merek_difasilitasi IS NOT NULL 
                           AND YEAR(tgl_daftar) = :tahun";

    $stmt = $pdo->prepare($query_difasilitasi);
    $stmt->execute(['tahun' => $tahun_sekarang]);
    $data = $stmt->fetch();
    $jumlah_difasilitasi = $data['jumlah_difasilitasi'];

    $kuota_tersedia = $total_kuota - $jumlah_difasilitasi;

    // Pastikan kuota tersedia tidak negatif
    if ($kuota_tersedia < 0) {
        $kuota_tersedia = 0;
    }
} catch (PDOException $e) {
    // Jika terjadi error, set nilai default
    $jumlah_difasilitasi = 0;
    $kuota_tersedia = $total_kuota;
    error_log("Error mengambil data kuota: " . $e->getMessage());
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Merek - Disperindag</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/home.css">
</head>

<body>
    <?php include 'navbar-login.php' ?>


    <!-- Hero -->
    <section class="hero-section" id="hero">
        <div class="container text-center py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-md-10">
                    <h1 class="hero-title mb-3">
                        HAI, <?php echo strtoupper(htmlspecialchars($nama)); ?><br>
                        SELAMAT DATANG DI LAYANAN PENDAFTARAN MEREK
                    </h1>
                    <p class="hero-subtitle mb-4">
                        SILAHKAN MELAKUKAN PENDAFTARAN MEREK DI FORM PENDAFTARAN MEREK,
                        JIKA SUDAH MELAKUKAN PENDAFTARAN ANDA BISA MELIHAT STATUS PENGAJUAN
                        DI LIHAT PENGAJUAN ANDA
                    </p>
                    <?php if ($sudahDaftar): ?>

                        <!-- Notifikasi jika sudah pernah daftar -->
                        <div class="alert-info-custom mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Informasi:</strong> Anda sudah memiliki pendaftaran merek yang aktif.
                            Silakan cek status pengajuan Anda dengan klik tombol <strong>"LIHAT PENGAJUAN ANDA"</strong> di bawah ini.
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-column flex-sm-row justify-content-center align-items-center gap-2">
                        <?php if ($sudahDaftar): ?>
                            <!-- Button untuk pendaftaran kedua/ketiga jika sudah pernah daftar -->
                            <a class="btn-form px-4 py-2" href="pendaftaran2.php">
                                <strong>FORM PENDAFTARAN MEREK</strong>
                            </a>
                        <?php else: ?>
                            <!-- Button untuk pendaftaran pertama -->
                            <a class="btn-form px-4 py-2" href="form-pendaftaran.php">
                                <strong>FORM PENDAFTARAN MEREK</strong>
                            </a>
                        <?php endif; ?>

                        <a class="btn-register px-4 py-2" href="status-seleksi-pendaftaran.php">
                            <strong>LIHAT PENGAJUAN ANDA</strong>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Visi Misi -->
    <section class="vision-mission" id="visimisi">
        <div class="container">
            <h2 class="section-title">DINAS PERINDUSTRIAN DAN PERDAGANGAN KABUPATEN SIDOARJO</h2>
            <div class="row">
                <div class="col-md-6">
                    <h5 style="font-weight: 600; margin-bottom: 1rem;">Syarat dan Ketentuan</h5>
                    <ul class="ps-3">
                        <li class="mb-2">Industri Kecil yang memproduksi produk di Sidoarjo (tidak untuk jasa, catering, rumah makan, repacking, dst).</li>
                        <li class="mb-2">Aktif memproduksi dan memasarkan produknya secara kontinyu
                        <li class="mb-2">Produk kemasan dengan masa simpan lebih dari 7 hari</li>
                        <li class="mb-2">Nomor Induk Berusaha (NIB) berbasis risiko dengan KBLI industri sesuai jenis produk</li>
                        <li class="mb-2">Logo Merek (3 Alternatif - beda gambar maupun tulisannya)</li>
                        <li class="mb-2">Foto produk jadi</li>
                        <li class="mb-2">Foto proses produksi yang membuktikan memang memproduksi sendiri</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5 style="font-weight: 600; margin-bottom: 1rem;">Catatan</h5>
                    <ul class="ps-3">
                        <li class="mb-2"> <span class="fw-medium">Cek Ketersediaan Merek: </span><br>Pastikan merek tersebut belum didaftarkan oleh orang lain <br> <a href="https://pdki-indonesia.dgip.go.id/" target="_blank">Cek di PDKI Indonesia</a>
                        <li class="mb-2"><span class="fw-medium">Kelas Merek: </span><br>Tentukan Kelas Merek <br>
                            <a href="https://skm.dgip.go.id/" target="_blank">Sistem Klasifikasi Merek</a>
                        </li>
                        <li class="mb2"><span class="fw-medium">Pengumuman:</span> <br>Peserta terpilih akan diumumkan setiap 3 bulan melalui:
                            <br><i class="bi bi-instagram"></i> disperindagsidoarjo
                            <br class="mb-2"><i class="bi bi-whatsapp"></i> 081235051286
                        </li>
                        <li class="mb-2"><span class="fw-medium">Kelengkapan Berkas: </span> <br>Peserta terpilih wajib datang langsung ke kantor Disperindag Sidoarjo (tidak boleh diwakilkan)
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Info -->
    <section class="info-section" id="info">
        <div class="container">
            <h2 class="section-title">APA SIH MEREK ITU?</h2>
            <div class="row g-4 d-flex align-items-stretch">
                <div class="col-12 col-md-6">
                    <div class="info-card h-100">
                        <h5>Informasi Merek</h5>
                        <p>Merek adalah tanda berupa nama, simbol, logo, huruf, angka, susunan warna, atau kombinasi dari semuanya yang digunakan untuk membedakan barang dan/atau jasa yang diproduksi oleh seseorang atau beberapa orang secara bersama-sama atau badan hukum lainnya. Pendaftaran merek di Indonesia diatur oleh <strong>Undang-Undang Nomor 20 Tahun 2016 tentang Merek dan Indikasi Geografis.</strong></p>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="info-card h-100">
                        <h5>Manfaat Pendaftaran Merek</h5>
                        <ul class="ps-3">
                            <li class="mb-2">Memberikan perlindungan hukum terhadap merek usaha.</li>
                            <li class="mb-2">Menjadi identitas dan pembeda produk/jasa dengan menambah nilai komersial dan daya saing usaha.</li>
                            <li class="mb-2">Menjadi aset berharga yang bisa dilisensikan atau dialihkan.</li>
                            <li class="mb-2">Memperkuat strategi promosi dan pemasaran.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Kuota -->
    <section class="kuota-section" id="kuota">
        <div class="container">
            <h2 class="section-title">KUOTA PENDAFTARAN MEREK</h2>
            <div class="row justify-content-center">
                <div class="col-md-3">
                    <div class="kuota-card blue">
                        <div class="kuota-nomor"><?php echo $total_kuota; ?></div>
                        <div class="kuota-text">Jumlah kuota<br>per tahun</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kuota-card green">
                        <div class="kuota-nomor"><?php echo $kuota_tersedia; ?></div>
                        <div class="kuota-text">Jumlah kuota<br>tersedia</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="kuota-card red">
                        <div class="kuota-nomor"><?php echo $jumlah_difasilitasi; ?></div>
                        <div class="kuota-text">Merek sudah<br>difasilitasi</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <p style="font-size: 1.1rem; margin-bottom: 2rem; color: #161616;">AMANKAN IDENTITAS BISNIS ANDA, DAFTARKAN MEREK SEKARANG!</p>
            <a class="btn-register" data-bs-toggle="modal" data-bs-target="#daftarModal" style="background-color: #161616; color: white;">DAFTAR MEREK SEKARANG!</a>
        </div>
    </section>

    <!-- Modal -->
    <div class="modal fade" id="daftarModal" tabindex="-1" aria-labelledby="daftarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4" style="border-radius: 12px;">
                <div class="modal-body">
                    <h4 class="mb-3" style="font-weight: 700;">Apakah Sudah Memiliki Akun?</h4>
                    <p>Jika sudah memiliki akun, tekan <strong>Sudah</strong>,
                        dan jika belum tekan <strong>Registrasi</strong> untuk mengisi data diri.</p>
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <a href="registrasi.php" class="btn px-4 py-2" style="border: 1px solid #161616; color: #161616;">
                            Registrasi
                        </a>
                        <a href="login.php" class="btn px-4 py-2" style="background-color: #161616; color: white;">
                            Sudah
                        </a>

                    </div>
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


</body>

</html>