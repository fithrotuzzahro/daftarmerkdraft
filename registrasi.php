<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrasi - Disperindag Sidoarjo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/registrasi.css">
</head>

<body>
  <!-- Navbar -->
  <?php include 'navbar.php' ?>

  <!-- Registrasi Section -->
  <section class="hero-section">
    <div class="registration-card">
      <h2>Registrasi - Pemohon</h2>
      <form method="post" enctype="multipart/form-data" id="registrationForm">
        <div class="mb-3">
          <label for="namaPemilik" class="form-label">Nama Pemilik</label>
          <input type="text" class="form-control" id="namaPemilik" name="namaPemilik" placeholder="Nama sesuai KTP" required>
        </div>

        <div class="mb-3">
          <label for="nik" class="form-label">NIK</label>
          <input type="text" class="form-control" id="nik" name="nik" maxlength="16" placeholder="16 digit NIK" required>
          <small class="text-muted">Masukkan 16 digit NIK sesuai KTP</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Alamat Pemilik</label>
          <div class="row">
            <div class="col-md-6">
              <small class="text-muted">RT/RW</small>
              <input type="text" class="form-control mb-3" id="rt_rw" name="rt_rw" placeholder="Contoh: 002/006" maxlength="7" required>
              <small class="text-muted d-block mt-1">Format otomatis: 002/006</small>
            </div>
            <div class="col-md-6">
              <small class="text-muted">Kelurahan/Desa</small>
              <input type="text" class="form-control" placeholder="" id="kel_desa" name="kel_desa" required>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="kecamatan" class="form-label">Kecamatan</label>
          <input type="text" class="form-control" id="kecamatan" name="kecamatan" required>
        </div>

        <div class="mb-3">
          <label for="telepon" class="form-label">Nomor WhatsApp</label>
          <input type="tel" class="form-control" id="telepon" name="telepon" placeholder="08xxxxxxxxxx" required>
          <small class="text-muted">Contoh: 081234567890</small>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email" name="email" placeholder="email@example.com" required>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
          <small class="text-muted">Minimal 6 karakter</small>
        </div>

        <div class="mb-3">
          <label class="form-label">File KTP</label>
          <div class="file-upload-container">
            <div class="file-input-wrapper">
              <input type="file" id="fileKTP" name="fileKTP" accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName()" required>
              <label for="fileKTP" class="file-upload-label">Pilih File</label>
              <span id="fileName" class="file-name">Tidak ada file yang dipilih.</span>
            </div>
            
            <!-- Preview Container di dalam file-upload-container -->
            <div id="ktpPreviewContainer" class="ktp-preview-wrapper">
              <!-- Image Preview -->
              <img id="ktpPreviewImg" class="ktp-preview-img" alt="Preview KTP">
              
              <!-- PDF Preview -->
              <div id="pdfPreviewBox" class="pdf-preview-box">
                <div class="pdf-icon">📄</div>
                <p class="mb-0"><strong id="pdfFileName"></strong></p>
                <small class="text-muted">File PDF siap diupload</small>
              </div>

              <!-- Actions -->
              <div class="preview-actions">
                <span class="file-size-info" id="fileSizeInfo"></span>
                <button type="button" class="btn-remove-file" onclick="clearFilePreview()">Hapus File</button>
              </div>
            </div>
          </div>
          <div class="file-info">Upload 1 file (PDF atau gambar). Maks 1 MB</div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-kembali" onclick="window.history.back()">Kembali</button>
          <button type="submit" class="btn btn-registrasi" id="btnSubmit">
            Registrasi
            <span class="loading-spinner" id="loadingSpinner"></span>
          </button>
        </div>
      </form>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <p>Copyright © 2025. All Rights Reserved.</p>
      <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
    </div>
  </footer>

  <!-- Modal Verifikasi OTP -->
  <div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header">
          <h5 class="modal-title" id="otpModalLabel">Verifikasi OTP</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Kami telah mengirim kode OTP ke email <strong id="emailDisplay"></strong></p>
          <p class="text-muted small">Silakan cek inbox atau folder spam Anda.</p>
          <input type="hidden" id="otpEmail">
          <input type="text" id="otpCode" class="form-control text-center mb-3" maxlength="6" placeholder="Masukkan kode 6 digit OTP" required>
          <div id="otpAlert" class="alert d-none mb-2"></div>
          <button class="btn btn-primary w-100" id="verifyOtpBtn">
            Verifikasi
            <span class="loading-spinner" id="loadingSpinnerOtp"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/registrasi.js"></script>

</body>

</html>