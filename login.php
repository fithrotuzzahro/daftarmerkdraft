<?php
session_start();
include 'process/config_db.php';

// Redirect jika sudah login
if (isset($_SESSION['NIK_NIP'])) {
    header("Location: home.php");
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validasi input
    if (empty($email) || empty($password)) {
        $error_message = "Email dan password harus diisi!";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal 6 karakter!";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM user WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Cek verifikasi akun (jika ada kolom is_verified)
                if (isset($user['is_verified']) && $user['is_verified'] == 0) {
                    // Tampilkan modal OTP
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            document.getElementById('otpEmail').value = '" . htmlspecialchars($email, ENT_QUOTES) . "';
                            document.getElementById('emailDisplay').textContent = '" . htmlspecialchars($email, ENT_QUOTES) . "';
                            var modal = new bootstrap.Modal(document.getElementById('otpModal'));
                            modal.show();
                        });
                    </script>";
                    $error_message = "Akun Anda belum terverifikasi!";
                } else {
                    // Login berhasil - PERBAIKAN: Simpan NIK ke session
                    $_SESSION['NIK_NIP'] = $user['NIK_NIP']; // ✅ INI YANG PENTING
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['no_wa'] = $user['no_wa'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect berdasarkan role
                    if ($user['role'] == 'Admin') {
                        header("Location: dashboard-admin.php");
                    } else {
                        header("Location: home.php");
                    }
                    exit;
                }
            } else {
                $error_message = "Email atau password salah!";
            }
        } catch (PDOException $e) {
            $error_message = "Terjadi kesalahan sistem. Silakan coba lagi.";
            error_log("Login Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Disperindag Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include 'navbar.php' ?>

    <!-- Login Section -->
    <div class="login-container">
        <div class="login-card">
            <h2 class="login-title">Login</h2>
            <p class="login-subtitle">
                Jika belum mempunyai akun silahkan tekan <strong>registrasi</strong> terlebih dulu
            </p>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="email@example.com" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <small class="text-muted">Minimal 6 karakter</small>
                </div>

                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <a href="registrasi.php" class="btn-register">Registrasi</a>
                    <button type="submit" name="login" class="login">
                        Masuk
                    </button>
                </div>
            </form>
        </div>
    </div>

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
                    <h5 class="modal-title" id="otpModalLabel">Verifikasi Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>⚠️ Akun belum terverifikasi!</strong>
                        <p class="mb-0 mt-2">Kami telah mengirim ulang kode OTP ke email <strong id="emailDisplay"></strong></p>
                    </div>
                    <p class="text-muted small">Silakan cek inbox atau folder spam Anda.</p>
                    <input type="hidden" id="otpEmail">
                    <input type="text" id="otpCode" class="form-control text-center mb-3" maxlength="6" placeholder="Masukkan kode 6 digit OTP" required>
                    <div id="otpAlert" class="alert d-none mb-2"></div>
                    <button class="btn btn-primary w-100 mb-2" id="verifyOtpBtn">
                        Verifikasi
                        <span class="loading-spinner" id="loadingSpinnerOtp"></span>
                    </button>
                    <button class="btn btn-outline-secondary w-100" id="resendOtpBtn">
                        Kirim Ulang OTP
                    </button>
                    <small class="text-muted d-block text-center mt-2">Kode OTP berlaku selama 10 menit</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
                    showAlert('success', "Verifikasi berhasil. Silakan login kembali.");
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('danger', result.message || "OTP salah atau sudah kedaluwarsa.");
                    document.getElementById('otpCode').value = '';
                    document.getElementById('otpCode').focus();
                }
            } catch (err) {
                console.error('Error:', err);
                showAlert('danger', "Gagal verifikasi: " + err.message);
            } finally {
                btnVerify.disabled = false;
                btnVerify.classList.remove('btn-disabled');
                spinner.style.display = 'none';
            }
        });

        // Resend OTP
        document.getElementById('resendOtpBtn').addEventListener('click', async () => {
            const email = document.getElementById('otpEmail').value;
            const btnResend = document.getElementById('resendOtpBtn');

            btnResend.disabled = true;
            btnResend.textContent = 'Mengirim...';

            try {
                const response = await fetch('process/resend_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        email: email
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', "Kode OTP baru telah dikirim ke email Anda.");
                    let countdown = 60;
                    const interval = setInterval(() => {
                        btnResend.textContent = `Kirim Ulang (${countdown}s)`;
                        countdown--;
                        if (countdown < 0) {
                            clearInterval(interval);
                            btnResend.disabled = false;
                            btnResend.textContent = 'Kirim Ulang OTP';
                        }
                    }, 1000);
                } else {
                    showAlert('danger', result.message || "Gagal mengirim ulang OTP.");
                    btnResend.disabled = false;
                    btnResend.textContent = 'Kirim Ulang OTP';
                }
            } catch (err) {
                console.error('Error:', err);
                showAlert('danger', "Gagal mengirim ulang OTP.");
                btnResend.disabled = false;
                btnResend.textContent = 'Kirim Ulang OTP';
            }
        });

        // Helper function untuk menampilkan alert
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
            document.getElementById('otpCode').value = '';
            document.getElementById('otpCode').focus();
            document.getElementById('otpAlert').classList.add('d-none');
        });
    </script>

</body>
</html>