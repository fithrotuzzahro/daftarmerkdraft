<?php
session_start();
require_once('vendor/autoload.php'); // Pastikan TCPDF sudah terinstall via composer
date_default_timezone_set('Asia/Jakarta');

include 'process/config_db.php';

// Cek login
if (!isset($_SESSION['NIK_NIP'])) {
    header("Location: login.php");
    exit();
}

$NIK = $_SESSION['NIK_NIP'];

// Ambil id_pendaftaran dari parameter
$id_pendaftaran = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_pendaftaran) {
    die("ID Pendaftaran tidak valid");
}

try {
    // Ambil data pendaftaran perpanjangan
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
        WHERE p.id_pendaftaran = :id_pendaftaran AND p.NIK = :nik
    ");
    $stmt->execute(['id_pendaftaran' => $id_pendaftaran, 'nik' => $NIK]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Data tidak ditemukan atau Anda tidak memiliki akses");
    }

    // Ambil tanda tangan digital
    $stmt = $pdo->prepare("
        SELECT file_path 
        FROM lampiran 
        WHERE id_pendaftaran = :id_pendaftaran 
        AND id_jenis_file = 16
        ORDER BY tgl_upload DESC 
        LIMIT 1
    ");
    $stmt->execute(['id_pendaftaran' => $id_pendaftaran]);
    $ttd = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buat alamat lengkap usaha
    $alamat_usaha = $data['kel_desa'] . ', RT/RW ' . $data['usaha_rt_rw'] . ', ' . 
                    $data['kecamatan'] . ', Kabupaten Sidoarjo';

    // Buat alamat lengkap pemilik
    $alamat_pemilik = $data['user_kel_desa'] . ', RT/RW ' . $data['user_rt_rw'] . ', ' . 
                      $data['user_kecamatan'] . ', ' . $data['nama_kabupaten'] . ', ' . $data['nama_provinsi'];

    // Tentukan merek yang difasilitasi
    $merek_difasilitasi = '';
    if ($data['merek_difasilitasi'] == 1) {
        $merek_difasilitasi = $data['nama_merek1'];
    } elseif ($data['merek_difasilitasi'] == 2) {
        $merek_difasilitasi = $data['nama_merek2'];
    } elseif ($data['merek_difasilitasi'] == 3) {
        $merek_difasilitasi = $data['nama_merek3'];
    } else {
        $merek_difasilitasi = $data['nama_merek1'];
    }

    // Format tanggal
    $bulan_indonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $tanggal = date('d');
    $bulan = $bulan_indonesia[(int)date('m')];
    $tahun = date('Y');
    $tanggal_surat = "Sidoarjo, $tanggal $bulan $tahun";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo');
$pdf->SetAuthor($data['nama_lengkap']);
$pdf->SetTitle('Surat Permohonan Perpanjangan Merek');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(TRUE, 20);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('times', '', 12);

// KOP Surat (Nama Usaha sebagai header)
$pdf->SetFont('times', 'B', 14);
$pdf->Cell(0, 7, strtoupper($data['nama_usaha']), 0, 1, 'C');
$pdf->SetFont('times', '', 10);
$pdf->Cell(0, 5, $alamat_usaha, 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: ' . $data['no_telp_perusahaan'], 0, 1, 'C');

// Garis pembatas
$pdf->Ln(2);
$pdf->SetLineWidth(0.5);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->SetLineWidth(0.2);
$pdf->Ln(1);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(8);

// Tanggal dan Tujuan
$pdf->SetFont('times', '', 12);
$pdf->Cell(0, 6, $tanggal_surat, 0, 1, 'R');
$pdf->Ln(4);

$pdf->Cell(40, 6, 'Kepada', 0, 0);
$pdf->Cell(5, 6, ':', 0, 1);
$pdf->Cell(40, 6, 'Yth. Kepala Dinas Perindustrian dan Perdagangan', 0, 1);
$pdf->Cell(40, 6, 'Kabupaten Sidoarjo', 0, 1);
$pdf->Cell(40, 6, 'Di', 0, 1);
$pdf->Cell(40, 6, 'Tempat', 0, 1);
$pdf->Ln(4);

// Salam Pembuka
$pdf->SetFont('times', 'B', 12);
$pdf->Cell(0, 6, 'Dengan hormat,', 0, 1);
$pdf->Ln(2);

// Isi Surat
$pdf->SetFont('times', '', 12);
$pdf->MultiCell(0, 6, 'Yang bertanda tangan di bawah ini:', 0, 'L');
$pdf->Ln(2);

// Data dalam tabel
$col1_width = 50;
$col2_width = 5;
$col3_width = 115;

$pdf->SetFont('times', '', 11);

// 1. Nama Usaha
$pdf->Cell($col1_width, 6, '1. Nama Usaha', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, $data['nama_usaha'], 0, 1);

// 2. Alamat Usaha
$pdf->Cell($col1_width, 6, '2. Alamat Usaha', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->MultiCell($col3_width, 6, $alamat_usaha, 0, 'L');

// 3. No. Telp Perusahaan
$pdf->Cell($col1_width, 6, '3. No. Telp Perusahaan', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, $data['no_telp_perusahaan'], 0, 1);

// 4. Nama Pemilik
$pdf->Cell($col1_width, 6, '4. Nama Pemilik', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, $data['nama_lengkap'], 0, 1);

// 5. Alamat Pemilik
$pdf->Cell($col1_width, 6, '5. Alamat Pemilik', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->MultiCell($col3_width, 6, $alamat_pemilik, 0, 'L');

// 6. No. Telp Pemilik
$pdf->Cell($col1_width, 6, '6. No. Telp Pemilik', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, $data['no_wa'], 0, 1);

// 7. E-mail
$pdf->Cell($col1_width, 6, '7. E-mail', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, $data['email'], 0, 1);

// 8. Jenis Usaha
$pdf->Cell($col1_width, 6, '8. Jenis Usaha', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, 'Industri Kecil', 0, 1);

// 9. Produk
$pdf->Cell($col1_width, 6, '9. Produk', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, $data['hasil_produk'], 0, 1);

// 10. Jumlah tenaga kerja
$pdf->Cell($col1_width, 6, '10. Jumlah tenaga kerja', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, $data['jml_tenaga_kerja'] . ' orang', 0, 1);

// 11. Merek
$pdf->Cell($col1_width, 6, '11. Merek', 0, 0);
$pdf->Cell($col2_width, 6, ':', 0, 0);
$pdf->Cell($col3_width, 6, $merek_difasilitasi, 0, 1);

$pdf->Ln(4);

// Permohonan
$pdf->SetFont('times', '', 12);
$permohonan_text = 'Mengajukan permohonan Surat Keterangan Industri Kecil dan Menengah (IKM) untuk keperluan PERPANJANGAN pengurusan merek "' . $merek_difasilitasi . '" di Kementerian Hukum dan HAM.';
$pdf->MultiCell(0, 6, $permohonan_text, 0, 'J');
$pdf->Ln(2);

$pdf->MultiCell(0, 6, 'Demikian untuk menjadikan maklum. Atas perhatian dan bantuannya disampaikan terima kasih.', 0, 'J');
$pdf->Ln(6);

// Lampiran
$pdf->SetFont('times', 'B', 12);
$pdf->Cell(0, 6, 'Lampiran:', 0, 1);
$pdf->SetFont('times', '', 11);
$pdf->Cell(10, 6, '1.', 0, 0);
$pdf->Cell(0, 6, 'Sertifikat Merek yang akan diperpanjang', 0, 1);
$pdf->Cell(10, 6, '2.', 0, 0);
$pdf->Cell(0, 6, 'Izin usaha (NIB/Surat Izin Usaha)', 0, 1);
$pdf->Cell(10, 6, '3.', 0, 0);
$pdf->Cell(0, 6, 'Foto lokasi dan tempat produksi', 0, 1);
$pdf->Ln(6);

// Penutup dan Tanda Tangan
$pdf->SetFont('times', '', 12);
$pdf->Cell(100, 6, '', 0, 0);
$pdf->Cell(0, 6, 'Hormat kami,', 0, 1, 'L');
$pdf->Cell(100, 6, '', 0, 0);
$pdf->Cell(0, 6, 'Pemilik', 0, 1, 'L');
$pdf->Ln(2);

// Tanda Tangan Digital
if ($ttd && file_exists($ttd['file_path'])) {
    $pdf->Cell(100, 6, '', 0, 0);
    $pdf->Image($ttd['file_path'], $pdf->GetX() + 105, $pdf->GetY(), 40, 20);
    $pdf->Ln(22);
} else {
    $pdf->Ln(20);
}

$pdf->Cell(100, 6, '', 0, 0);
$pdf->SetFont('times', 'BU', 12);
$pdf->Cell(0, 6, $data['nama_lengkap'], 0, 1, 'L');

// Simpan PDF ke folder
$folder = "uploads/surat_perpanjangan/surat_{$NIK}/";
if (!file_exists($folder)) {
    mkdir($folder, 0777, true);
}

$filename = "surat_perpanjangan_{$NIK}_" . time() . ".pdf";
$filepath = $folder . $filename;

// Simpan file
$pdf->Output($filepath, 'F');

// Simpan ke database
try {
    $tgl_upload = date('Y-m-d H:i:s');
    
    // id_jenis_file = 17 untuk Surat Perpanjangan
    $stmt = $pdo->prepare("
        INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path)
        VALUES (?, 17, ?, ?)
    ");
    $stmt->execute([$id_pendaftaran, $tgl_upload, $filepath]);
    
    // Output PDF ke browser
    $pdf->Output($filename, 'I');
    
} catch (PDOException $e) {
    die("Error menyimpan ke database: " . $e->getMessage());
}
?>