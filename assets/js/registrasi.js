// Validasi input angka (NIK & Telepon)
document.getElementById('nik').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
});

document.getElementById('telepon').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
});

// Format RT/RW - hanya validasi angka dan slash saat input
document.getElementById('rt_rw').addEventListener('input', function(e) {
  let value = this.value;
  
  // Hanya izinkan angka dan slash
  value = value.replace(/[^\d\/]/g, '');
  
  // Batasi hanya 1 slash
  const slashCount = (value.match(/\//g) || []).length;
  if (slashCount > 1) {
    value = value.substring(0, value.lastIndexOf('/'));
  }
  
  this.value = value;
});

// Blur event - format otomatis dengan leading zeros
document.getElementById('rt_rw').addEventListener('blur', function() {
  let value = this.value.trim();
  
  if (!value) return;
  
  // Pisahkan RT dan RW
  let parts = value.split('/');
  let rt = parts[0] ? parts[0].replace(/\D/g, '') : '';
  let rw = parts[1] ? parts[1].replace(/\D/g, '') : '';
  
  // Jika ada input
  if (rt) {
    // Batasi RT maksimal 3 digit
    rt = rt.substring(0, 3);
    // Tambah leading zeros untuk RT
    rt = rt.padStart(3, '0');
    
    // Jika RW tidak ada, set default 001
    if (!rw) {
      rw = '001';
    } else {
      // Batasi RW maksimal 3 digit
      rw = rw.substring(0, 3);
      // Tambah leading zeros untuk RW
      rw = rw.padStart(3, '0');
    }
    
    // Set format final
    this.value = rt + '/' + rw;
  } else {
    // Jika RT kosong, kosongkan field
    this.value = '';
  }
});

// Update nama file dan preview
function updateFileName() {
  const fileInput = document.getElementById('fileKTP');
  const fileName = document.getElementById('fileName');
  const previewWrapper = document.getElementById('ktpPreviewContainer');
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
    fileName.style.color = '#28a745';
    fileName.style.fontWeight = '500';
    
    const fileSizeKB = (file.size / 1024).toFixed(0);
    fileSizeInfo.textContent = `✓ File valid (${fileSizeKB} KB)`;

    // Tampilkan preview wrapper
    previewWrapper.classList.add('show');

    if (file.type === 'application/pdf') {
      // Preview PDF
      previewImg.classList.remove('show');
      pdfPreviewBox.classList.add('show');
      pdfFileName.textContent = file.name;
    } else {
      // Preview gambar
      pdfPreviewBox.classList.remove('show');
      previewImg.classList.add('show');

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
  const previewWrapper = document.getElementById('ktpPreviewContainer');
  const previewImg = document.getElementById('ktpPreviewImg');
  const pdfPreviewBox = document.getElementById('pdfPreviewBox');
  const fileSizeInfo = document.getElementById('fileSizeInfo');

  fileInput.value = '';
  fileName.textContent = 'Tidak ada file yang dipilih.';
  fileName.style.color = '#666';
  fileName.style.fontWeight = 'normal';
  
  previewImg.src = '';
  previewImg.classList.remove('show');
  pdfPreviewBox.classList.remove('show');
  previewWrapper.classList.remove('show');
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