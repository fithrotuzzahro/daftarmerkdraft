<?php
session_start();
require_once 'process/config_db.php'; // Pastikan file config.php ada untuk koneksi database

// Cek apakah user sudah login dan merupakan admin
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

// Proses tambah admin baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nip = trim($_POST['nip']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    
    // Validasi input
    $errors = [];
    
    if (empty($nip)) {
        $errors[] = "NIP tidak boleh kosong";
    }
    
    if (empty($nama_lengkap)) {
        $errors[] = "Nama lengkap tidak boleh kosong";
    }
    
    if (empty($email)) {
        $errors[] = "Email tidak boleh kosong";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    if (empty($password)) {
        $errors[] = "Password tidak boleh kosong";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter";
    }
    
    // Cek NIP sudah terdaftar atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE NIK_NIP = ?");
        $stmt->execute([$nip]);
        if ($stmt->fetch()) {
            $errors[] = "NIP sudah terdaftar";
        }
    }
    
    // Cek email sudah terdaftar atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email sudah terdaftar";
        }
    }
    
    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'Admin';
        $is_verified = 1;
        $tanggal_buat = date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("INSERT INTO user (NIK_NIP, nama_lengkap, email, password, role, is_verified, tanggal_buat) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$nip, $nama_lengkap, $email, $hashed_password, $role, $is_verified, $tanggal_buat])) {
            $_SESSION['success_message'] = "Admin baru berhasil ditambahkan";
            header('Location: kelola-admin.php');
            exit();
        } else {
            $errors[] = "Gagal menambahkan admin";
        }
    }
    
    $_SESSION['errors'] = $errors;
}

// Proses hapus admin
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['nip'])) {
    $admin_nip = trim($_GET['nip']);
    
    // Tidak bisa hapus diri sendiri
    if ($admin_nip == $_SESSION['NIK_NIP']) {
        $_SESSION['errors'] = ["Anda tidak dapat menghapus akun Anda sendiri"];
    } else {
        $stmt = $pdo->prepare("DELETE FROM user WHERE NIK_NIP = ? AND role = 'Admin'");
        
        if ($stmt->execute([$admin_nip])) {
            $_SESSION['success_message'] = "Admin berhasil dihapus";
        } else {
            $_SESSION['errors'] = ["Gagal menghapus admin"];
        }
    }
    
    header('Location: kelola-admin.php');
    exit();
}

// Proses update admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $admin_nip = trim($_POST['admin_nip']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    
    $errors = [];
    
    if (empty($nama_lengkap)) {
        $errors[] = "Nama lengkap tidak boleh kosong";
    }
    
    if (empty($email)) {
        $errors[] = "Email tidak boleh kosong";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    // Cek email sudah digunakan admin lain atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE email = ? AND NIK_NIP != ?");
        $stmt->execute([$email, $admin_nip]);
        if ($stmt->fetch()) {
            $errors[] = "Email sudah digunakan oleh admin lain";
        }
    }
    
    if (empty($errors)) {
        $updated_at = date('Y-m-d H:i:s');
        
        if (!empty($password)) {
            // Update dengan password baru
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE user SET nama_lengkap = ?, email = ?, password = ?, updated_at = ? WHERE NIK_NIP = ? AND role = 'Admin'");
            $result = $stmt->execute([$nama_lengkap, $email, $hashed_password, $updated_at, $admin_nip]);
        } else {
            // Update tanpa mengubah password
            $stmt = $pdo->prepare("UPDATE user SET nama_lengkap = ?, email = ?, updated_at = ? WHERE NIK_NIP = ? AND role = 'Admin'");
            $result = $stmt->execute([$nama_lengkap, $email, $updated_at, $admin_nip]);
        }
        
        if ($result) {
            $_SESSION['success_message'] = "Data admin berhasil diupdate";
        } else {
            $errors[] = "Gagal mengupdate admin";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }
    
    header('Location: kelola-admin.php');
    exit();
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query untuk menghitung total admin
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user WHERE role = 'Admin' AND (email LIKE ? OR nama_lengkap LIKE ? OR NIK_NIP LIKE ?)");
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search_param]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user WHERE role = 'Admin'");
}
$total_result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_admins = $total_result['total'];
$total_pages = ceil($total_admins / $limit);

// Query untuk mengambil data admin
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT NIK_NIP, nama_lengkap, email, tanggal_buat FROM user WHERE role = 'Admin' AND (email LIKE ? OR nama_lengkap LIKE ? OR NIK_NIP LIKE ?) ORDER BY tanggal_buat DESC LIMIT ? OFFSET ?");
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search_param, $limit, $offset]);
} else {
    $stmt = $pdo->prepare("SELECT NIK_NIP, nama_lengkap, email, tanggal_buat FROM user WHERE role = 'Admin' ORDER BY tanggal_buat DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
}
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Kelola Data Admin - Pendaftaran Merek</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/kelola-akun-admin.css">
</head>

<body>
    <?php include 'navbar-admin.php' ?>

    <main class="container-xxl main-container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-7">
                <div class="mb-3">
                    <div class="section-title">Kelola Data Admin</div>
                    <div class="section-desc">Gunakan fitur pencarian untuk menemukan data admin. Klik aksi untuk mengedit atau menghapus.</div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo htmlspecialchars($_SESSION['success_message']); 
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['errors'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($_SESSION['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['errors']); ?>
                <?php endif; ?>

                <!-- Search  -->
                <form method="GET" action="kelola-admin.php" class="search-wrap mb-3 mb-md-4">
                    <div class="input-group">
                        <input id="searchAdmin" name="search" type="text" class="form-control" placeholder="Cari data admin (NIP, Nama, Email)" value="<?php echo htmlspecialchars($search); ?>" />
                        <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i></button>
                    </div>
                </form>

                <div class="admin-list d-flex flex-column gap-3">
                    <?php if (empty($admins)): ?>
                        <div class="card-lite p-4 text-center text-muted">
                            Tidak ada data admin<?php echo !empty($search) ? ' yang sesuai dengan pencarian' : ''; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <div class="card-lite p-3 p-md-4 admin-item" data-admin-nip="<?php echo htmlspecialchars($admin['NIK_NIP']); ?>">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($admin['nama_lengkap']); ?></h6>
                                        <div class="admin-meta mb-1">
                                            <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($admin['email']); ?>
                                        </div>
                                        <div class="admin-meta mb-1">
                                            <i class="bi bi-card-text me-1"></i>NIP: <?php echo htmlspecialchars($admin['NIK_NIP']); ?>
                                        </div>
                                        <div class="admin-meta">
                                            <i class="bi bi-calendar-check me-1"></i>Didaftarkan pada <?php echo date('d/m/Y H:i:s', strtotime($admin['tanggal_buat'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-success btn-edit" 
                                            data-nip="<?php echo htmlspecialchars($admin['NIK_NIP']); ?>"
                                            data-nama="<?php echo htmlspecialchars($admin['nama_lengkap']); ?>"
                                            data-email="<?php echo htmlspecialchars($admin['email']); ?>">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </button>
                                    <?php if ($admin['NIK_NIP'] != $_SESSION['NIK_NIP']): ?>
                                        <button class="btn btn-danger btn-delete" data-nip="<?php echo htmlspecialchars($admin['NIK_NIP']); ?>" data-nama="<?php echo htmlspecialchars($admin['nama_lengkap']); ?>">
                                            <i class="bi bi-trash3 me-1"></i> Hapus akun
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="bi bi-person-check me-1"></i> Akun Anda
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Navigasi halaman" class="mb-5">
                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Sebelumnya">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Berikutnya">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5 mb-5">
                <div class="card-lite p-3 p-md-4">
                    <h6 class="fw-bold mb-3" id="formTitle">Tambah Data Admin</h6>

                    <form method="POST" action="kelola-admin.php" id="adminForm">
                        <input type="hidden" name="action" value="add" id="formAction">
                        <input type="hidden" name="admin_nip" value="" id="adminNip">

                        <div class="mb-3">
                            <label for="nip" class="form-label">Nomor Induk Pegawai (NIP)</label>
                            <input type="text" class="form-control" id="nip" name="nip" placeholder="Masukkan NIP" required />
                            <small class="form-text text-muted">NIP akan digunakan sebagai identitas login</small>
                        </div>

                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required />
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Email" required />
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password <span id="passwordNote" class="text-muted small"></span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password (minimal 6 karakter)" />
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-submit1" id="submitBtn">
                                <i class="bi bi-person-plus me-1"></i> Daftar Akun
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;">
                                <i class="bi bi-x-circle me-1"></i> Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus akun admin:</p>
                    <p class="fw-bold mb-0" id="deleteAdminName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash3 me-1"></i> Hapus
                    </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Handle edit button
        document.querySelectorAll('.btn-edit').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const nip = this.dataset.nip;
                const nama = this.dataset.nama;
                const email = this.dataset.email;
                
                // Update form
                document.getElementById('formTitle').textContent = 'Edit Data Admin';
                document.getElementById('formAction').value = 'update';
                document.getElementById('adminNip').value = nip;
                document.getElementById('nip').value = nip;
                document.getElementById('nip').setAttribute('readonly', 'readonly');
                document.getElementById('nama_lengkap').value = nama;
                document.getElementById('email').value = email;
                document.getElementById('password').value = '';
                document.getElementById('password').removeAttribute('required');
                document.getElementById('passwordNote').textContent = '(Kosongkan jika tidak ingin mengubah)';
                document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle me-1"></i> Update Admin';
                document.getElementById('cancelBtn').style.display = 'inline-block';
                
                // Scroll to form
                document.getElementById('adminForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // Handle cancel button
        document.getElementById('cancelBtn').addEventListener('click', function() {
            resetForm();
        });

        // Handle delete button
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        let deleteNip = null;

        document.querySelectorAll('.btn-delete').forEach(function(btn) {
            btn.addEventListener('click', function() {
                deleteNip = this.dataset.nip;
                const nama = this.dataset.nama;
                document.getElementById('deleteAdminName').textContent = nama;
                deleteModal.show();
            });
        });

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (deleteNip) {
                window.location.href = 'kelola-admin.php?action=delete&nip=' + encodeURIComponent(deleteNip);
            }
        });

        // Reset form function
        function resetForm() {
            document.getElementById('formTitle').textContent = 'Tambah Data Admin';
            document.getElementById('formAction').value = 'add';
            document.getElementById('adminNip').value = '';
            document.getElementById('nip').value = '';
            document.getElementById('nip').removeAttribute('readonly');
            document.getElementById('nama_lengkap').value = '';
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password').setAttribute('required', 'required');
            document.getElementById('passwordNote').textContent = '';
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-person-plus me-1"></i> Daftar Akun';
            document.getElementById('cancelBtn').style.display = 'none';
        }

        // Auto dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>