<?php
session_start();
require_once 'process/config_db.php';

// Cek apakah user sudah login
if (!isset($_SESSION['NIK_NIP'])) {
    // Jika belum login, arahkan ke login.php
    header("Location: login.php");
    exit();
}

$user_nik = $_SESSION['NIK_NIP'];

// Ambil data user menggunakan PDO
$stmt = $pdo->prepare("SELECT * FROM user WHERE NIK_NIP = ?");
$stmt->execute([$user_nik]);
$user = $stmt->fetch();

if (!$user) {
    // Jika data user tidak ditemukan di database
    echo "Data user tidak ditemukan.";
    exit;
}
?>



<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil Pemohon - Disperindag Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/registrasi.css">
    <style>
        .preview-container {
            margin-top: 10px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
            display: none;
        }
        .preview-container.show {
            display: block;
        }
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .btn-remove-preview {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-remove-preview:hover {
            background: #c82333;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 5px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include 'navbar-login.php' ?>

    <!-- Registrasti Section -->
    <section class="hero-section">
        <div class="registration-card">
            <h2>Edit Profil - Pemohon</h2>
            <form id="editProfilForm" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="namaPemilik" class="form-label">Nama Pemilik</label>
                    <input type="text" name="namaPemilik" class="form-control" id="namaPemilik" value="<?= htmlspecialchars($user['nama_lengkap']); ?>">
                </div>

                <div class="mb-3">
                    <label for="nik" class="form-label">NIK</label>
                    <input type="text" name="NIK_NIP" class="form-control" id="nik" value="<?= htmlspecialchars($user['NIK_NIP']); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Alamat Pemilik</label>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="rt_rw" class="form-label small">RT/RW</label>
                            <input type="text" class="form-control" name="rt_rw" id="rt_rw" placeholder="001/002" value="<?= htmlspecialchars($user['rt_rw']); ?>" maxlength="7">
                            <small class="text-muted">Format: XXX/XXX</small>
                        </div>
                        <div class="col-md-6">
                            <label for="kel_desa" class="form-label small">Kelurahan/Desa</label>
                            <input type="text" class="form-control" name="kel_desa" id="kel_desa" value="<?= htmlspecialchars($user['kel_desa']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="kecamatan" class="form-label">Kecamatan</label>
                    <input type="text" class="form-control" name="kecamatan" id="kecamatan" value="<?= htmlspecialchars($user['kecamatan']); ?>">
                </div>

                <div class="mb-3">
                    <label for="telepon" class="form-label">Nomor WhatsApp</label>
                    <input type="tel" class="form-control" name="telepon" id="telepon" value="<?= htmlspecialchars($user['no_wa']); ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($user['email']); ?>">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" id="password" placeholder="Kosongkan jika tidak ingin mengubah">
                </div>

                <div class="mb-3">
                    <label for="fileKTP" class="form-label">Upload KTP (opsional)</label>
                    <input type="file" id="fileKTP" name="fileKTP" accept=".pdf,.jpg,.jpeg,.png" class="form-control">
                    
                    <!-- Preview KTP Saat Ini -->
                    <?php if (!empty($user['foto_ktp'])): ?>
                        <div id="currentKtpPreview" class="preview-container show" style="display: block;">
                            <div class="preview-header">
                                <strong>KTP Saat Ini:</strong>
                                <a href="<?= htmlspecialchars($user['foto_ktp']); ?>" target="_blank" class="btn btn-sm btn-primary">Buka di Tab Baru</a>
                            </div>
                            <?php 
                            $file_extension = strtolower(pathinfo($user['foto_ktp'], PATHINFO_EXTENSION));
                            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): 
                            ?>
                                <img src="<?= htmlspecialchars($user['foto_ktp']); ?>" alt="KTP Saat Ini" class="preview-image">
                            <?php else: ?>
                                <div class="text-center p-3">
                                    <i class="bi bi-file-earmark-pdf" style="font-size: 48px;"></i>
                                    <p class="mb-0 mt-2"><strong>File PDF:</strong> <?= basename($user['foto_ktp']); ?></p>
                                    <small class="text-muted">Klik "Buka di Tab Baru" untuk melihat file</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Preview Container untuk KTP Baru -->
                    <div id="previewContainer" class="preview-container">
                        <div class="preview-header">
                            <strong>Preview KTP Baru:</strong>
                            <button type="button" class="btn-remove-preview" onclick="removePreview()">Hapus</button>
                        </div>
                        <img id="previewImage" src="" alt="Preview KTP" class="preview-image">
                        <div id="pdfPreview" style="display:none;">
                            <p class="mb-0"><strong>File PDF:</strong> <span id="pdfFileName"></span></p>
                            <small class="text-muted">Preview PDF tidak tersedia. File akan diupload saat Anda menyimpan.</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-kembali" onclick="window.history.back()">Kembali</button>
                    <button type="submit" class="btn btn-registrasi">Edit Profil</button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Format RT/RW otomatis
        document.getElementById('rt_rw').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, ''); // Hapus semua selain angka
            
            if (value.length > 3) {
                // Format menjadi XXX/XXX
                value = value.substring(0, 3) + '/' + value.substring(3, 6);
            }
            
            e.target.value = value;
        });

        // Format saat halaman dimuat (untuk nilai yang sudah ada)
        window.addEventListener('DOMContentLoaded', function() {
            const rtRwInput = document.getElementById('rt_rw');
            let currentValue = rtRwInput.value.replace(/[^0-9]/g, '');
            
            if (currentValue.length > 3) {
                rtRwInput.value = currentValue.substring(0, 3) + '/' + currentValue.substring(3, 6);
            }
        });

        // Preview KTP Baru
        document.getElementById('fileKTP').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewContainer = document.getElementById('previewContainer');
            const currentKtpPreview = document.getElementById('currentKtpPreview');
            const previewImage = document.getElementById('previewImage');
            const pdfPreview = document.getElementById('pdfPreview');
            const pdfFileName = document.getElementById('pdfFileName');

            if (file) {
                // Validasi ukuran file (1MB)
                if (file.size > 1024 * 1024) {
                    alert('File terlalu besar. Maksimal 1 MB.');
                    e.target.value = '';
                    previewContainer.classList.remove('show');
                    return;
                }

                // Sembunyikan preview KTP lama jika ada
                if (currentKtpPreview) {
                    currentKtpPreview.style.display = 'none';
                }

                const fileType = file.type;

                // Jika file adalah gambar
                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        previewImage.src = event.target.result;
                        previewImage.style.display = 'block';
                        pdfPreview.style.display = 'none';
                        previewContainer.classList.add('show');
                    };
                    reader.readAsDataURL(file);
                }
                // Jika file adalah PDF
                else if (fileType === 'application/pdf') {
                    pdfFileName.textContent = file.name;
                    previewImage.style.display = 'none';
                    pdfPreview.style.display = 'block';
                    previewContainer.classList.add('show');
                }
            } else {
                previewContainer.classList.remove('show');
                // Tampilkan kembali KTP lama jika ada
                if (currentKtpPreview) {
                    currentKtpPreview.style.display = 'block';
                }
            }
        });

        // Fungsi untuk menghapus preview KTP baru
        function removePreview() {
            const currentKtpPreview = document.getElementById('currentKtpPreview');
            document.getElementById('fileKTP').value = '';
            document.getElementById('previewContainer').classList.remove('show');
            
            // Tampilkan kembali KTP lama jika ada
            if (currentKtpPreview) {
                currentKtpPreview.style.display = 'block';
            }
        }

        // Submit form
        document.getElementById('editProfilForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('process/proses_edit_profil.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message || 'Profil berhasil diperbarui!');
                    window.location.reload();
                } else {
                    alert(result.message || 'Gagal memperbarui profil.');
                }
            } catch (error) {
                console.error(error);
                alert('Terjadi kesalahan pada server.');
            }
        });
    </script>
</body>

</html>