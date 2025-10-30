<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrasi - Disperindag Sidoarjo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/registrasi.css">
  <style>
    .loading-spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 3px solid #f3f3f3;
      border-top: 3px solid #007bff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-left: 10px;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .btn-disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    /* Preview KTP Styles */
    .ktp-preview-container {
      margin-top: 15px;
      display: none;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      padding: 15px;
      background: #f8f9fa;
    }

    .ktp-preview-img {
      max-width: 100%;
      max-height: 300px;
      border-radius: 6px;
      display: block;
      margin: 0 auto;
    }

    .pdf-preview-box {
      text-align: center;
      padding: 30px;
      display: none;
    }

    .pdf-icon {
      font-size: 64px;
      color: #dc3545;
      margin-bottom: 10px;
    }

    .preview-actions {
      margin-top: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }

    .file-size-info {
      color: #28a745;
      font-weight: 500;
      font-size: 14px;
    }

    .btn-remove-file {
      background: #dc3545;
      color: white;
      border: none;
      padding: 6px 16px;
      border-radius: 5px;
      font-size: 14px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .btn-remove-file:hover {
      background: #c82333;
    }
  </style>
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
            <input type="file" id="fileKTP" name="fileKTP" accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName()" required>
            <label for="fileKTP" class="file-upload-label">Pilih File</label>
            <span id="fileName" class="file-name">Tidak ada file yang dipilih.</span>
          </div>
          <div class="file-info">Upload 1 file (PDF atau gambar). Maks 1 MB</div>

          <!-- Preview Container -->
          <div id="ktpPreviewContainer" class="ktp-preview-container">
            <!-- Image Preview -->
            <img id="ktpPreviewImg" class="ktp-preview-img" alt="Preview KTP" style="display: none;">
            
            <!-- PDF Preview -->
            <div id="pdfPreviewBox" class="pdf-preview-box">
              <div class="pdf-icon">📄</div>
              <p class="mb-0"><strong id="pdfFileName"></strong></p>
              <small class="text-muted">File PDF siap diupload</small>
            </div>

            <!-- Actions -->
            <div class="preview-actions">
              <span class="file-size-info" id="fileSizeInfo"></span>
              <button type="button" class="btn-remove-file" onclick="clearFilePreview()">🗑️ Hapus File</button>
            </div>
          </div>
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
  <script>
    // Validasi input angka (NIK & Telepon)
    document.getElementById('nik').addEventListener('input', function() {
      this.value = this.value.replace(/\D/g, '');
    });

    document.getElementById('telepon').addEventListener('input', function() {
      this.value = this.value.replace(/\D/g, '');
    });

    // Format RT/RW otomatis - mendukung angka besar (1-999)
    document.getElementById('rt_rw').addEventListener('input', function(e) {
      // Simpan posisi kursor
      let cursorPos = this.selectionStart;
      let value = this.value;
      
      // Hapus semua karakter non-digit
      let numbers = value.replace(/\D/g, '');
      
      // Batasi maksimal 6 digit (999/999)
      if (numbers.length > 6) {
        numbers = numbers.substring(0, 6);
      }
      
      let formattedValue = '';
      
      if (numbers.length > 0) {
        if (numbers.length <= 3) {
          // Hanya RT yang terisi
          formattedValue = numbers;
        } else {
          // RT dan RW terisi
          const rt = numbers.substring(0, 3);
          const rw = numbers.substring(3);
          formattedValue = rt + '/' + rw;
        }
      }
      
      this.value = formattedValue;
      
      // Restore posisi kursor dengan menyesuaikan slash
      if (cursorPos > 3 && formattedValue.includes('/')) {
        this.setSelectionRange(cursorPos + 1, cursorPos + 1);
      }
    });

    // Blur event untuk memformat dengan leading zeros
    document.getElementById('rt_rw').addEventListener('blur', function() {
      let value = this.value.replace(/\D/g, '');
      
      if (value.length > 0) {
        let rt, rw;
        
        if (value.length <= 3) {
          // Hanya RT, set RW default 001
          rt = value.padStart(3, '0');
          rw = '001';
        } else {
          // RT dan RW ada
          rt = value.substring(0, 3).padStart(3, '0');
          rw = value.substring(3).padStart(3, '0');
        }
        
        this.value = rt + '/' + rw;
      }
    });

    // Update nama file dan preview
    function updateFileName() {
      const fileInput = document.getElementById('fileKTP');
      const fileName = document.getElementById('fileName');
      const previewContainer = document.getElementById('ktpPreviewContainer');
      const previewImg = document.getElementById('ktpPreviewImg');
      const pdfPreviewBox = document.getElementById('pdfPreviewBox');
      const pdfFileName = document.getElementById('pdfFileName');
      const fileSizeInfo = document.getElementById('fileSizeInfo');

      if (fileInput.files.length > 0) {
        const file = fileInput.files[0];

        // Validasi ukuran file
        if (file.size > 1024 * 1024) {
          alert(
            "File terlalu besar.\n\n" +
            "Ukuran file: " + (file.size / 1024 / 1024).toFixed(2) + " MB\n" +
            "Maksimal: 1 MB.\n\n" +
            "Silakan pilih file yang lebih kecil."
          );
          clearFilePreview();
          return;
        }

        // Validasi tipe file
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
          alert(
            "Format file tidak didukung.\n\n" +
            "File yang diizinkan: PDF, JPG, JPEG, PNG.\n\n" +
            "Silakan pilih file dengan format yang benar."
          );
          clearFilePreview();
          return;
        }

        // Update nama file dan ukuran
        fileName.textContent = file.name;
        const fileSizeKB = (file.size / 1024).toFixed(0);
        fileSizeInfo.textContent = `✓ File valid (${fileSizeKB} KB)`;

        // Tampilkan preview
        previewContainer.style.display = 'block';

        if (file.type === 'application/pdf') {
          // Preview PDF
          previewImg.style.display = 'none';
          pdfPreviewBox.style.display = 'block';
          pdfFileName.textContent = file.name;
        } else {
          // Preview gambar
          pdfPreviewBox.style.display = 'none';
          previewImg.style.display = 'block';
          
          const reader = new FileReader();
          reader.onload = function(e) {
            previewImg.src = e.target.result;
          };
          reader.readAsDataURL(file);
        }
      } else {
        clearFilePreview();
      }
    }

    // Clear file dan preview
    function clearFilePreview() {
      const fileInput = document.getElementById('fileKTP');
      const fileName = document.getElementById('fileName');
      const previewContainer = document.getElementById('ktpPreviewContainer');
      const previewImg = document.getElementById('ktpPreviewImg');
      const fileSizeInfo = document.getElementById('fileSizeInfo');

      fileInput.value = '';
      fileName.textContent = 'Tidak ada file yang dipilih.';
      previewImg.src = '';
      previewContainer.style.display = 'none';
      fileSizeInfo.textContent = '';
    }

    // Form submission dengan validasi
    document.getElementById('registrationForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const btnSubmit = document.getElementById('btnSubmit');
      const spinner = document.getElementById('loadingSpinner');

      const nik = document.getElementById('nik').value;
      const password = document.getElementById('password').value;
      const telepon = document.getElementById('telepon').value;
      const email = document.getElementById('email').value;
      const fileKTP = document.getElementById('fileKTP').files[0];
      const rtRw = document.getElementById('rt_rw').value;

      // Validasi NIK
      if (nik.length !== 16) {
        alert("NIK harus 16 digit.\n\nNIK yang Anda masukkan: " + nik.length + " digit.");
        return;
      }

      // Validasi RT/RW format
      const rtRwPattern = /^\d{3}\/\d{3}$/;
      if (!rtRwPattern.test(rtRw)) {
        alert("Format RT/RW tidak valid.\n\nContoh format yang benar: 002/006");
        document.getElementById('rt_rw').focus();
        return;
      }

      // Validasi password
      if (password.length < 6) {
        alert("Password terlalu pendek.\n\nMinimal 6 karakter.");
        return;
      }

      // Validasi nomor telepon
      if (telepon.length < 10 || telepon.length > 13) {
        alert("Nomor WhatsApp tidak valid.\n\nPastikan nomor telepon 10–13 digit.");
        return;
      }

      // Validasi file
      if (!fileKTP) {
        alert("File KTP belum dipilih.\n\nSilakan upload file KTP Anda.");
        return;
      }

      // Disable button + loading
      btnSubmit.disabled = true;
      btnSubmit.classList.add('btn-disabled');
      spinner.style.display = 'inline-block';

      const formData = new FormData(e.target);

      try {
        const response = await fetch('process/proses_registrasi.php', {
          method: 'POST',
          body: formData
        });

        if (!response.ok) {
          throw new Error('Server error: ' + response.status);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          const text = await response.text();
          console.error('Response bukan JSON:', text);
          throw new Error('Server mengembalikan response yang tidak valid.');
        }

        const result = await response.json();

        if (result.success) {
          // Set email untuk verifikasi
          document.getElementById('otpEmail').value = result.email;
          document.getElementById('emailDisplay').textContent = result.email;

          // Tampilkan modal OTP
          const modal = new bootstrap.Modal(document.getElementById('otpModal'));
          modal.show();

          // Reset form
          e.target.reset();
          clearFilePreview();
        } else {
          let errorMessage = "Registrasi gagal.\n\n";
          errorMessage += result.message || "Terjadi kesalahan yang tidak diketahui.";

          if (result.details) {
            errorMessage += "\n\nDetail: " + result.details;
          }

          alert(errorMessage);
        }
      } catch (err) {
        console.error('Error:', err);
        alert(
          "Gagal menghubungi server.\n\n" +
          "Pesan error: " + err.message + "\n\n" +
          "Silakan coba lagi atau hubungi administrator."
        );
      } finally {
        btnSubmit.disabled = false;
        btnSubmit.classList.remove('btn-disabled');
        spinner.style.display = 'none';
      }
    });

    // Verifikasi OTP
    document.getElementById('verifyOtpBtn').addEventListener('click', async () => {
      const email = document.getElementById('otpEmail').value;
      const otp = document.getElementById('otpCode').value.trim();
      const alertBox = document.getElementById('otpAlert');
      const btnVerify = document.getElementById('verifyOtpBtn');
      const spinner = document.getElementById('loadingSpinnerOtp');

      // Validasi OTP
      if (!otp) {
        showAlert('warning', "Kode OTP belum diisi.");
        return;
      }
      if (otp.length !== 6) {
        showAlert('warning', "Kode OTP harus 6 digit.");
        return;
      }
      if (!/^\d+$/.test(otp)) {
        showAlert('warning', "Kode OTP hanya boleh berisi angka.");
        return;
      }

      btnVerify.disabled = true;
      btnVerify.classList.add('btn-disabled');
      spinner.style.display = 'inline-block';
      alertBox.classList.add('d-none');

      try {
        const response = await fetch('process/verify_otp.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams({
            email: email,
            otp: otp
          })
        });

        if (!response.ok) {
          throw new Error('Server error: ' + response.status);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          const text = await response.text();
          console.error('Response bukan JSON:', text);
          throw new Error('Server mengembalikan response yang tidak valid.');
        }

        const result = await response.json();

        if (result.success) {
          showAlert('success', "Verifikasi berhasil. Mengalihkan ke halaman login...");
          setTimeout(() => {
            window.location.href = 'login.php';
          }, 2000);
        } else {
          showAlert('danger', result.message || "OTP salah atau sudah kedaluwarsa.");
          document.getElementById('otpCode').value = '';
          document.getElementById('otpCode').focus();
        }
      } catch (err) {
        console.error('Error:', err);
        showAlert('danger', "Gagal verifikasi: " + err.message + "\n\nSilakan coba lagi.");
      } finally {
        btnVerify.disabled = false;
        btnVerify.classList.remove('btn-disabled');
        spinner.style.display = 'none';
      }
    });

    // Helper alert
    function showAlert(type, message) {
      const alertBox = document.getElementById('otpAlert');
      alertBox.className = 'alert alert-' + type;
      alertBox.textContent = message;
      alertBox.classList.remove('d-none');
    }

    // Enter key untuk OTP
    document.getElementById('otpCode').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('verifyOtpBtn').click();
      }
    });

    // Autofocus OTP saat modal dibuka
    document.getElementById('otpModal').addEventListener('shown.bs.modal', function() {
      document.getElementById('otpCode').focus();
    });
  </script>

</body>

</html>