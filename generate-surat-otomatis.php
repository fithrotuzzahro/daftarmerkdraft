<?php
session_start();
require_once 'process/config_db.php';

// Cek login
if (!isset($_SESSION['NIK_NIP'])) {
  header("Location: login.php");
  exit;
}

$NIK = $_SESSION['NIK_NIP'];

// Ambil id_pendaftaran dari parameter
$id_pendaftaran = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_pendaftaran) {
  die("ID Pendaftaran tidak valid");
}

try {
  // Ambil data lengkap pemohon dan pendaftaran
  $stmt = $pdo->prepare("
        SELECT 
            p.id_pendaftaran,
            p.tgl_daftar,
            u.NIK_NIP,
            u.nama_lengkap,
            u.kel_desa,
            u.kecamatan,
            u.rt_rw,
            du.nama_usaha,
            du.kel_desa as kel_desa_usaha,
            du.kecamatan as kecamatan_usaha,
            du.rt_rw as rt_rw_usaha,
            m.nama_merek1,
            m.nama_merek2,
            m.nama_merek3,
            m.kelas_merek,
            m.logo1,
            m.logo2,
            m.logo3,
            p.merek_difasilitasi
        FROM pendaftaran p
        JOIN user u ON p.NIK = u.NIK_NIP
        JOIN datausaha du ON p.id_usaha = du.id_usaha
        LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
        WHERE p.id_pendaftaran = ? AND p.NIK = ?
    ");
  $stmt->execute([$id_pendaftaran, $NIK]);
  $data = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$data) {
    die("Data tidak ditemukan atau akses ditolak");
  }

  // Tentukan merek yang difasilitasi
  $merek_terpilih = '';
  $logo_terpilih = '';

  if ($data['merek_difasilitasi'] == 1) {
    $merek_terpilih = $data['nama_merek1'];
    $logo_terpilih = $data['logo1'];
  } elseif ($data['merek_difasilitasi'] == 2) {
    $merek_terpilih = $data['nama_merek2'];
    $logo_terpilih = $data['logo2'];
  } elseif ($data['merek_difasilitasi'] == 3) {
    $merek_terpilih = $data['nama_merek3'];
    $logo_terpilih = $data['logo3'];
  }

  // Format tanggal Indonesia
  $bulan = [
    1 => 'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
  ];

  $tgl = date('d', strtotime($data['tgl_daftar']));
  $bln = $bulan[(int)date('m', strtotime($data['tgl_daftar']))];
  $thn = date('Y', strtotime($data['tgl_daftar']));
  $tanggal_surat = "$tgl $bln $thn";

  // Format alamat
  $alamat_pemohon = "Jl. " . ($data['kel_desa'] ? $data['kel_desa'] : '-') .
    " RT " . ($data['rt_rw'] ? str_replace('/', ' RW ', $data['rt_rw']) : '-') .
    " Desa/Kel " . ($data['kel_desa'] ? $data['kel_desa'] : '-') .
    "<br>Kec. " . ($data['kecamatan'] ? $data['kecamatan'] : '-') .
    ", Kab. Sidoarjo";

  $alamat_usaha = "Jl. " . ($data['kel_desa_usaha'] ? $data['kel_desa_usaha'] : '-') .
    " RT " . ($data['rt_rw_usaha'] ? str_replace('/', ' RW ', $data['rt_rw_usaha']) : '-') .
    " Desa/Kel " . ($data['kel_desa_usaha'] ? $data['kel_desa_usaha'] : '-') .
    "<br>Kec. " . ($data['kecamatan_usaha'] ? $data['kecamatan_usaha'] : '-') .
    ", Kab. Sidoarjo";

  // Konversi logo ke base64 untuk embed di HTML
  $logo_base64 = '';
  if ($logo_terpilih && file_exists($logo_terpilih)) {
    $image_data = file_get_contents($logo_terpilih);
    $image_type = pathinfo($logo_terpilih, PATHINFO_EXTENSION);
    $logo_base64 = 'data:image/' . $image_type . ';base64,' . base64_encode($image_data);
  }
} catch (PDOException $e) {
  die("Error: " . $e->getMessage());
}

// Generate filename yang aman
$safe_filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $data['nama_lengkap']);
$filename = "Surat_Keterangan_{$safe_filename}.doc";

// Set header untuk download sebagai Word document dengan force download
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: public");
header("Expires: 0");

// Output buffer untuk memastikan tidak ada output sebelumnya
ob_clean();
flush();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Surat Pendaftaran Merek</title>
  <style>
    @page {
      size: 21.59cm 33.02cm !important;
      /* F4/Folio exact size */
      margin: 2cm 2.5cm;
    }

    @media print {
      .page-break {
        page-break-before: always;
      }
    }

    body {
      font-family: Arial, sans-serif;
      max-width: 21.59cm;
      margin: 0 auto;
      padding: 20px;
      line-height: 1.5;
      font-size: 12pt;
    }

    .document {
      margin-bottom: 40px;
      page-break-after: always;
    }

    .header {
      margin-bottom: 20px;
    }

    .brand-name {
      margin-bottom: 8px;
      text-align: left;
    }

    .logo-placeholder {
      width: 80px;
      height: 80px;
      border: 1px solid #000;
      margin: 8px 0;
      display: inline-block;
      vertical-align: middle;
      text-align: center;
      line-height: 80px;
      overflow: hidden;
    }

    .logo-placeholder img {
      max-width: 75px;
      max-height: 75px !important;
      width: auto;
      height: auto;
      object-fit: contain;
      vertical-align: middle;
    }

    .intro-text {
      margin: 15px 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 15px 0;
    }

    table td {
      vertical-align: top;
      padding: 2px 0;
      line-height: 1.4;
    }

    table td:first-child {
      width: 140px;
      font-weight: normal;
    }

    table td:nth-child(2) {
      width: 15px;
      text-align: center;
    }

    table td:nth-child(3) {
      padding-left: 5px;
    }

    .statement {
      margin: 15px 0;
      text-align: justify;
    }

    .signature-section {
      margin-top: 30px;
      text-align: right;
    }

    .signature-content {
      display: inline-block;
      text-align: left;
      min-width: 200px;
    }

    .signature-placeholder {
      margin: 70px 0 5px 0;
      font-weight: bold;
    }

    .title-underline {
      text-decoration: underline;
      font-weight: bold;
      text-align: center;
      margin: 20px 0 15px 0;
    }

    .label-ttd {
      text-align: center;
      text-decoration: underline;
      margin-top: 40px;
    }
  </style>
</head>

<body>
  <!-- DOKUMEN 1: Surat Pernyataan Kepemilikan Merek -->
  <div class="document">
    <div class="header">
      <div class="title-underline" style="margin-bottom: 20px;">SURAT PERNYATAAN PERMOHONAN PENDAFTARAN MEREK</div>
      <div class="brand-name">
        Merek: <strong><?php echo strtoupper(htmlspecialchars($merek_terpilih)); ?></strong>
      </div>
      <div class="logo-placeholder">
        <?php if ($logo_base64): ?>
          <img src="<?php echo $logo_base64; ?>" alt="Logo Merek">
        <?php else: ?>
          [Logo]
        <?php endif; ?>
      </div>
    </div>

    <div class="intro-text">
      Yang diajukan untuk permohonan pendaftaran merek oleh:
    </div>

    <table>
      <tr>
        <td>Nama Pemohon</td>
        <td>:</td>
        <td><?php echo strtoupper(htmlspecialchars($data['nama_lengkap'])); ?></td>
      </tr>
      <tr>
        <td>Alamat Pemohon</td>
        <td>:</td>
        <td><?php echo $alamat_pemohon; ?></td>
      </tr>
      <tr>
        <td>Alamat Usaha</td>
        <td>:</td>
        <td><?php echo $alamat_usaha; ?></td>
      </tr>
    </table>

    <div class="statement">
      Dengan ini menyatakan bahwa merek tersebut merupakan milik pemohon dan tidak meniru merek milik pihak lain.
    </div>

    <div class="signature-section">
      <div class="signature-content">
        <div>Sidoarjo, <?php echo $tanggal_surat; ?></div>
        <div style="margin-top: 8px; font-weight: bold;">Pemohon</div>
        <div class="signature-placeholder">(<?php echo strtoupper(htmlspecialchars($data['nama_lengkap'])); ?>)</div>
      </div>
    </div>
  </div>

  <!-- DOKUMEN 2: Surat Pernyataan UKM -->
  <div class="document">
    <div class="title-underline">SURAT PERNYATAAN UKM</div>

    <div style="margin: 15px 0;">
      Yang Bertanda tangan di bawah ini :
    </div>

    <table>
      <tr>
        <td>Nama Pemohon</td>
        <td>:</td>
        <td><?php echo strtoupper(htmlspecialchars($data['nama_lengkap'])); ?></td>
      </tr>
      <tr>
        <td>Alamat Pemohon</td>
        <td>:</td>
        <td><?php echo $alamat_pemohon; ?></td>
      </tr>
      <tr>
        <td>Alamat Usaha</td>
        <td>:</td>
        <td><?php echo $alamat_usaha; ?></td>
      </tr>
      <tr>
        <td>Merek</td>
        <td>:</td>
        <td><strong><?php echo strtoupper(htmlspecialchars($merek_terpilih)); ?></strong></td>
      </tr>
      <tr>
        <td>Kelas Merek</td>
        <td>:</td>
        <td><?php echo htmlspecialchars($data['kelas_merek']); ?></td>
      </tr>
    </table>

    <div class="statement">
      Dengan ini menyatakan bahwa Surat Rekomendasi Usaha Kecil Mikro yang saya lampirkan adalah benar, Apabila dikemudian hari terbukti tidak benar / palsu, maka saya bersedia untuk dilakukan tindakan <strong>Ditarik Kembali</strong> dan <strong>Dihapus</strong> oleh Kantor Direktorat Jenderal Kekayaan Intelektual terhadap Pengajuan Permohonan Merek saya.
    </div>

    <div class="statement">
      Demikian surat pernyataan ini saya buat dengan sebenarnya dan untuk digunakan sebagai mestinya.
    </div>

    <div class="signature-section">
      <div class="signature-content">
        <div>Sidoarjo, <?php echo $tanggal_surat; ?></div>
        <div style="margin-top: 8px; font-weight: bold;">Pemohon</div>
        <div class="signature-placeholder">(<?php echo strtoupper(htmlspecialchars($data['nama_lengkap'])); ?>)</div>
      </div>
    </div>
  </div>

  <!-- DOKUMEN 3: Tanda Tangan Besar -->
  <div class="document">
    <p class="label-ttd">Tanda tangan besar</p>
    <div class="signature-placeholder" style="text-align: center; margin-top: 180px;">
      (<?php echo strtoupper(htmlspecialchars($data['nama_lengkap'])); ?>)
    </div>
  </div>
</body>

</html>