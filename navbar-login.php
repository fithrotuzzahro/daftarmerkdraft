<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<style>
    * {
        font-family: 'Montserrat', sans-serif;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    .navbar {
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 100000;
    }

    .navbar-brand img {
        height: 40px;
    }

    .navbar-nav .nav-link {
        position: relative;
        display: inline-block;
        margin: 0 1rem;
        font-weight: 500;
        color: #161616;
        transition: color 0.3s ease;
    }

    .navbar-nav .nav-link::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background-color: #161616;
        transition: width 0.3s ease;
    }

    .navbar-nav .nav-link:hover {
        color: #000;
    }

    .navbar-nav .nav-link:hover::after {
        width: 100%;
    }

    .btn-login {
        background-color: #161616;
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 500;
    }

    .btn-login:hover {
        background-color: #555;
        color: white;
    }

    /* Panel Notifikasi */
    .notif-panel {
        position: fixed;
        top: 0;
        right: -100%;
        height: 100vh;
        background: #fff;
        box-shadow: -2px 0 8px rgba(0, 0, 0, 0.2);
        z-index: 1050;
        transition: right 0.3s ease-in-out;
        overflow-y: auto;
    }

    @media (min-width: 768px) {
        .notif-panel {
            width: 25%;
        }
    }

    @media (max-width: 767.98px) {
        .notif-panel {
            width: 75%;
        }
    }

    .notif-panel.active {
        right: 0;
    }

    /* Mobile Menu */
    .mobile-menu {
        position: fixed;
        top: 56px;
        right: -300px;
        width: 250px;
        height: calc(100% - 56px);
        background: #fff;
        transition: right 0.3s ease;
        z-index: 1045;
        box-shadow: -2px 0 8px rgba(0, 0, 0, 0.2);
        overflow-y: auto;
    }

    .mobile-menu.active {
        right: 0;
    }

    #overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: 1040;
        display: none;
    }

    #overlay.active {
        display: block;
    }

    .btn-notif-mobile {
        padding: 0.25rem 0.5rem;
        height: 40px;
        width: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .section-title {
            font-size: 1rem;
        }

        h2 {
            font-size: 1.2rem;
        }

        h3 {
            font-size: 1rem;
        }

        p, li {
            font-size: 0.7rem;
        }

        .btn-login {
            margin-top: 10px;
        }
    }
</style>

<!-- Navigasi -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="home.php">
            <img src="assets/img/logo.png" alt="Logo" class="me-2" style="height: 40px;">
            <div class="d-none d-lg-block">
                <div style="font-size: 0.8rem; font-weight: 600; color: #161616;">
                    DINAS PERINDUSTRIAN DAN PERDAGANGAN
                </div>
                <div style="font-size: 0.7rem; color: #666;">KABUPATEN SIDOARJO</div>
            </div>
        </a>

        <!-- Desktop Menu -->
        <div class="collapse navbar-collapse d-none d-lg-block" id="navbarNav">
            <ul class="navbar-nav ms-auto me-3 gap-2">
                <li class="nav-item"><a class="nav-link" href="home.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link" href="editprofil.php">EDIT PROFIL PEMOHON</a></li>
            </ul>
            
            <div class="d-flex align-items-center gap-3">
                <a class="btn btn-outline-dark position-relative" id="notifBtn">
                    <i class="bi bi-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        3
                    </span>
                </a>

                <a href="logout.php" class="btn btn-dark">
                    <i class="bi bi-box-arrow-left pe-1"></i> Keluar
                </a>
            </div>
        </div>

        <!-- Mobile buttons (notif & menu) -->
        <div class="d-flex d-lg-none align-items-center gap-2 ms-auto">
            <a class="btn btn-outline-dark btn-sm position-relative btn-notif-mobile" id="notifBtnMobile">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    3
                </span>
            </a>
            
            <button class="navbar-toggler" type="button" id="menuToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Menu (Offcanvas) -->
<div id="mobileMenu" class="mobile-menu d-lg-none">
    <ul class="navbar-nav p-3">
        <li class="nav-item"><a class="nav-link" href="home.php">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="editprofil.php">EDIT PROFIL PEMOHON</a></li>
        <li class="mt-3">
            <a href="logout.php" class="btn btn-dark w-100">
                <i class="bi bi-box-arrow-left pe-1"></i> Keluar
            </a>
        </li>
    </ul>
</div>

<!-- Panel Notifikasi -->
<div id="notifPanel" class="notif-panel">
    <div class="notif-header d-flex justify-content-between align-items-center p-3 border-bottom">
        <h5 class="mb-0">Notifikasi</h5>
        <button id="closeNotif" class="btn-close"></button>
    </div>
    <div class="notif-body p-3">
        <div class="notif-item d-flex mb-3">
            <i class="bi bi-bell me-2 mt-1"></i>
            <div>
                <strong>Merek dalam Proses Pengecekan Berkas</strong>
                <p class="mb-1">Anda baru saja mengajukan permohonan merek, sekarang merek dalam proses pengecekan berkas.</p>
                <small class="text-muted fst-italic">2025-09-28 14:30:00</small>
            </div>
        </div>
        <hr>
    </div>
</div>

<!-- Overlay -->
<div id="overlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('overlay');
    const notifBtn = document.getElementById('notifBtn');
    const notifBtnMobile = document.getElementById('notifBtnMobile');
    const notifPanel = document.getElementById('notifPanel');
    const closeNotif = document.getElementById('closeNotif');
    const navbar = document.querySelector('.navbar');

    // Mobile menu toggle
    menuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('active');
        overlay.classList.toggle('active');
        notifPanel.classList.remove('active');
    });

    // Notification panel toggle (desktop)
    if (notifBtn) {
        notifBtn.addEventListener('click', () => {
            notifPanel.classList.add('active');
            overlay.classList.add('active');
            mobileMenu.classList.remove('active');
        });
    }

    // Notification panel toggle (mobile)
    if (notifBtnMobile) {
        notifBtnMobile.addEventListener('click', () => {
            notifPanel.classList.add('active');
            overlay.classList.add('active');
            mobileMenu.classList.remove('active');
        });
    }

    // Close notification panel
    closeNotif.addEventListener('click', () => {
        notifPanel.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Overlay click to close everything
    overlay.addEventListener('click', () => {
        mobileMenu.classList.remove('active');
        notifPanel.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Set mobile menu position based on navbar height
    function setMobileMenuPosition() {
        const navHeight = navbar.offsetHeight;
        mobileMenu.style.top = navHeight + 'px';
        mobileMenu.style.height = `calc(100vh - ${navHeight}px)`;
    }
    
    setMobileMenuPosition();
    window.addEventListener('resize', setMobileMenuPosition);
</script>