<?php
session_start();
require_once 'process/config_db.php';

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Query untuk menghitung total data
    $query_count = "SELECT COUNT(DISTINCT u.NIK_NIP) as total 
                    FROM user u
                    LEFT JOIN pendaftaran p ON u.NIK_NIP = p.NIK
                    WHERE u.role = 'Pemohon'";
    
    if (!empty($search)) {
        $query_count .= " AND (u.nama_lengkap LIKE :search OR u.NIK_NIP LIKE :search)";
    }

    $stmt_count = $pdo->prepare($query_count);
    
    if (!empty($search)) {
        $search_param = "%{$search}%";
        $stmt_count->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    $stmt_count->execute();
    $total_rows = $stmt_count->fetch()['total'];
    $total_pages = ceil($total_rows / $limit);

    // Query untuk mengambil data pemohon dengan status fasilitasi
    $query = "SELECT 
        u.NIK_NIP,
        u.nama_lengkap,
        u.email,
        u.no_wa,
        u.tanggal_buat,
        COUNT(p.id_pendaftaran) as total_pendaftaran,
        MAX(p.tgl_daftar) as tgl_pendaftaran_terakhir,
        (SELECT p2.status_validasi 
         FROM pendaftaran p2 
         WHERE p2.NIK = u.NIK_NIP 
         ORDER BY p2.tgl_daftar DESC 
         LIMIT 1) as status_terakhir,
        (SELECT p2.id_pendaftaran 
         FROM pendaftaran p2 
         WHERE p2.NIK = u.NIK_NIP 
         ORDER BY p2.tgl_daftar DESC 
         LIMIT 1) as id_pendaftaran_terakhir
    FROM user u
    LEFT JOIN pendaftaran p ON u.NIK_NIP = p.NIK
    WHERE u.role = 'Pemohon'";

    if (!empty($search)) {
        $query .= " AND (u.nama_lengkap LIKE :search OR u.NIK_NIP LIKE :search)";
    }

    $query .= " GROUP BY u.NIK_NIP, u.nama_lengkap, u.email, u.no_wa, u.tanggal_buat
                ORDER BY u.tanggal_buat DESC
                LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $pemohon_list = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Function untuk menentukan badge status
function getStatusBadge($status) {
    if (empty($status)) {
        return '<span class="badge text-bg-secondary">BELUM MENDAFTAR</span>';
    }
    
    switch ($status) {
        case 'Menunggu':
            return '<span class="badge text-bg-warning">PROSES PENGECEKAN</span>';
        case 'Bisa Difasilitasi':
            return '<span class="badge text-bg-info">KONFIRMASI LANJUT</span>';
        case 'Tidak Bisa Difasilitasi':
            return '<span class="badge text-bg-danger">TIDAK DIFASILITASI</span>';
        case 'Menunggu Surat Keterangan':
            return '<span class="badge text-bg-primary">MELENGKAPI SURAT</span>';
        case 'Menunggu Bukti Pendaftaran':
        case 'Diajukan ke Kementerian':
            return '<span class="badge text-bg-primary">SEDANG DIPROSES</span>';
        case 'Selesai':
        case 'Surat Keterangan Difasilitasi':
            return '<span class="badge text-bg-success">SUDAH DIFASILITASI</span>';
        default:
            return '<span class="badge text-bg-secondary">' . strtoupper($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Pemohon - Pendaftaran Merek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body>
    <?php include 'navbar-admin.php' ?>

    <main class="container-xxl main-container">
        <div class="col-12 col-lg-9">
            <!-- Kelola Data -->
            <div class="mb-4">
                <h6 class="section-title">Data Akun Terdaftar</h6>
                <p class="text-muted small mb-3">
                    Gunakan fitur pencarian untuk menemukan data pengguna. Total: <strong><?php echo $total_rows; ?></strong> pemohon terdaftar.
                </p>

                <form method="GET" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-9">
                            <label for="search" class="form-label small fw-semibold">Pencarian</label>
                            <div class="input-group">
                                <span class="input-group-text" id="search-icon"><i class="bi bi-search"></i></span>
                                <input id="search" name="search" type="search" class="form-control" 
                                       placeholder="Cari berdasarkan nama atau NIK" 
                                       aria-describedby="search-icon"
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-dark" type="submit" aria-label="Cari">
                                    <i class="bi bi-arrow-right"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                <a href="kelola-data-pemohon.php" class="btn btn-outline-secondary" aria-label="Reset">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:48px;">
                                    <input class="form-check-input" type="checkbox" id="selectAll" aria-label="Pilih semua baris">
                                </th>
                                <th>NIK</th>
                                <th>Nama</th>
                                <th>Kontak</th>
                                <th>Status Fasilitasi</th>
                                <th>Tgl Daftar Akun</th>
                                <th>Tgl Daftar Merek</th>
                                <th class="text-center" style="width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pemohon_list)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-inbox fs-1 text-muted"></i>
                                    <p class="text-muted mb-0">
                                        <?php echo !empty($search) ? 'Tidak ada data yang ditemukan' : 'Belum ada data pemohon'; ?>
                                    </p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($pemohon_list as $pemohon): ?>
                            <tr>
                                <td>
                                    <input class="form-check-input row-checkbox" type="checkbox" 
                                           value="<?php echo $pemohon['NIK_NIP']; ?>" 
                                           aria-label="Pilih baris">
                                </td>
                                <td class="text-nowrap"><?php echo htmlspecialchars($pemohon['NIK_NIP']); ?></td>
                                <td class="text-nowrap">
                                    <strong><?php echo htmlspecialchars(strtoupper($pemohon['nama_lengkap'])); ?></strong>
                                    <?php if ($pemohon['total_pendaftaran'] > 0): ?>
                                    <br><small class="text-muted"><?php echo $pemohon['total_pendaftaran']; ?> pendaftaran</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <small>
                                        <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($pemohon['email']); ?><br>
                                        <i class="bi bi-phone me-1"></i><?php echo htmlspecialchars($pemohon['no_wa']); ?>
                                    </small>
                                </td>
                                <td class="text-nowrap">
                                    <?php echo getStatusBadge($pemohon['status_terakhir']); ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php 
                                    // Menampilkan tanggal pembuatan akun
                                    if ($pemohon['tanggal_buat']) {
                                        echo date('d/m/Y H:i', strtotime($pemohon['tanggal_buat']));
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php 
                                    // Menampilkan tanggal pendaftaran merek terakhir
                                    if ($pemohon['tgl_pendaftaran_terakhir']) {
                                        echo date('d/m/Y H:i', strtotime($pemohon['tgl_pendaftaran_terakhir']));
                                    } else {
                                        echo '<span class="text-muted">Belum daftar</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($pemohon['id_pendaftaran_terakhir']): ?>
                                        <a href="detail-pendaftar.php?id=<?php echo $pemohon['id_pendaftaran_terakhir']; ?>" 
                                           class="btn btn-primary btn-icon" 
                                           title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-secondary btn-icon" disabled title="Belum ada pendaftaran">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger btn-icon btn-delete" 
                                                data-nik="<?php echo htmlspecialchars($pemohon['NIK_NIP']); ?>"
                                                data-nama="<?php echo htmlspecialchars($pemohon['nama_lengkap']); ?>"
                                                title="Hapus Data">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                    <!-- Info -->
                    <div class="text-muted small">
                        Menampilkan <?php echo min($offset + 1, $total_rows); ?> - <?php echo min($offset + $limit, $total_rows); ?> dari <?php echo $total_rows; ?> data
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Navigasi halaman">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Previous -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   aria-label="Sebelumnya">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <?php
                            // Pagination logic
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = $i == $page ? 'active' : '';
                                echo '<li class="page-item ' . $active . '">';
                                if ($i == $page) {
                                    echo '<span class="page-link">' . $i . '</span>';
                                } else {
                                    echo '<a class="page-link" href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $i . '</a>';
                                }
                                echo '</li>';
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <!-- Next -->
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   aria-label="Berikutnya">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- Footer  -->
    <footer class="footer">
        <div class="container">
            <p>Copyright © 2025. All Rights Reserved.</p>
            <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data pemohon:</p>
                    <p class="fw-bold" id="deleteNama"></p>
                    <p class="text-muted small">NIK: <span id="deleteNIK"></span></p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Tindakan ini akan menghapus semua data pendaftaran yang terkait dan tidak dapat dibatalkan!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Hapus</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select All Checkbox
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Delete functionality
        let deleteNIK = '';
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                deleteNIK = this.dataset.nik;
                const nama = this.dataset.nama;
                
                document.getElementById('deleteNIK').textContent = deleteNIK;
                document.getElementById('deleteNama').textContent = nama;
                
                deleteModal.show();
            });
        });

        document.getElementById('confirmDelete')?.addEventListener('click', function() {
            if (!deleteNIK) return;

            const formData = new FormData();
            formData.append('action', 'delete_pemohon');
            formData.append('nik', deleteNIK);

            fetch('process/delete_pemohon.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Data berhasil dihapus');
                    location.reload();
                } else {
                    alert(data.message || 'Gagal menghapus data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus data');
            })
            .finally(() => {
                deleteModal.hide();
            });
        });

        // Auto-submit search on enter
        document.getElementById('search')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    </script>
</body>

</html>