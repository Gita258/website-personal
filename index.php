<?php
// =====================================================================
// index.php - Halaman Utama AgriData - Aceh (Enhanced Dynamic Navigation)
// =====================================================================

// 1. Mulai Sesi di Awal File
session_start(); 

// 2. Tentukan status login dan peran (role)
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $is_logged_in ? $_SESSION['user_role'] : null;

// Tentukan tautan dan label tombol utama berdasarkan status login
if ($is_logged_in) {
    // Jika sudah login, tentukan halaman dashboard yang sesuai
    if ($user_role === 'petani') {
        $login_link = 'dashboard_petani.php';
    } else {
        // Asumsi jika bukan 'petani', diarahkan ke dashboard umum/admin
        $login_link = 'dashboard_admin.php'; 
    }
    $login_label = 'Dashboard';
    $login_btn_class = 'btn-primary';
    $logout_visible = true;
} else {
    // Jika belum login (atau sudah logout)
    $login_link = 'login.php'; 
    $login_label = 'Login';
    $login_btn_class = 'btn-light text-success';
    $logout_visible = false;
}


// --- Kode Konfigurasi Lainnya (Sudah dibersihkan dari karakter aneh) ---
$db_host = "localhost"; 
$db_user = "root"; 
$db_pass = "admin"; 
$db_name = "agridata_aceh"; 

$title = "AgriData - Aceh | Sistem Informasi Pendataan Hasil Pertanian";
$startup_name = "Trita Agro";
$current_year = date("Y"); 

function get_public_stats() {
    return [
        ['label' => 'Total Desa Terintegrasi', 'value' => '10', 'unit' => 'Desa', 'icon' => 'fa-map-marker-alt', 'color' => 'primary'],
        ['label' => 'Pengguna Aktif', 'value' => '200+', 'unit' => 'Users', 'icon' => 'fa-users', 'color' => 'success'],
        ['label' => 'Komoditas Terdata', 'value' => '5+', 'unit' => 'Jenis', 'icon' => 'fa-seedling', 'color' => 'warning'],
        ['label' => 'Efisiensi Pelaporan', 'value' => '70%', 'unit' => 'Faster', 'icon' => 'fa-chart-line', 'color' => 'info']
    ];
}

$stats = get_public_stats();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        :root {
            --primary-green: #28a745;
            --light-green: #e8f5e9;
            --dark-green: #1e7e34;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 70px;
            scroll-behavior: smooth;
            color: #333;
        }

        /* Navbar Styling */
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 0.5px;
        }

        .navbar-brand img {
            transition: transform 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            background: none !important;
            background-color: transparent !important;
        }

        .navbar-brand:hover img {
            transform: scale(1.1);
        }

        .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            transform: translateY(-2px);
        }

        .btn-outline-light {
            border: 2px solid white;
            font-weight: 600;
        }

        .btn-outline-light:hover {
            background: white;
            color: var(--primary-green) !important;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--light-green) 0%, #ffffff 100%);
            padding: 80px 0 60px 0;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: rgba(40, 167, 69, 0.05);
            border-radius: 50%;
        }

        .hero-logo {
            animation: fadeInDown 1s ease;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.15));
            background: none !important;
            background-color: transparent !important;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-title {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--dark-green);
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #555;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .stats-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .stats-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stats-unit {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stats-label {
            font-size: 1rem;
            color: #777;
            margin-top: 10px;
        }

        /* Feature Cards */
        .feature-card {
            padding: 40px 30px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: var(--light-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
        }

        .feature-icon i {
            color: var(--primary-green);
        }

        .feature-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }

        /* Section Styling */
        section {
            padding: 80px 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark-green);
            margin-bottom: 50px;
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-green);
            border-radius: 2px;
        }

        /* Contact Form */
        .contact-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .btn-primary {
            background: var(--primary-green);
            border: none;
            padding: 12px 40px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-lg {
            padding: 15px 50px;
            font-size: 1.1rem;
            border-radius: 30px;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #8bc599ff 0%, #28a745 100%);
            color: white;
            padding: 40px 0 20px 0;
        }

        .footer-logo {
            transition: transform 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(255,255,255,0.3));
            background: none !important;
            background-color: transparent !important;
        }

        .footer-logo:hover {
            transform: rotate(360deg);
        }

        /* Info List */
        .info-list {
            list-style: none;
            padding: 0;
        }

        .info-list li {
            background: white;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-radius: 10px;
            border-left: 4px solid var(--primary-green);
            transition: all 0.3s ease;
        }

        .info-list li:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        /* Team Section */
        .team-list {
            background: var(--light-green);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary-green);
        }

        .team-list li {
            padding: 10px 0;
            font-size: 1.1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            section {
                padding: 50px 0;
            }

            .navbar-brand img {
                height: 45px !important;
            }

            .hero-logo {
                max-width: 180px !important;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="logo.png" alt="AgriData Aceh Logo" style="height: 50px; margin-right: 10px;">
                AgriData Aceh
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#home">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="#statistik">Statistik</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tentang">Tentang</a></li>
                    <li class="nav-item"><a class="nav-link" href="#kontak">Kontak</a></li>
                    <li class="nav-item ml-3"><a class="nav-link btn btn-outline-light px-4" href="register.php">Daftar</a></li>
                    <li class="nav-item ml-2"><a class="nav-link btn btn-light text-success px-4" href="login.php"><b>Login</b></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <img src="logo.png" alt="AgriData Aceh Logo" class="mb-4 hero-logo" style="max-width: 250px; height: auto;">
                    <h1 class="hero-title">Digitalisasi Pertanian Aceh Barat</h1>
                    <p class="hero-subtitle">
                        Sistem informasi berbasis web untuk mencatat, mengelola, dan menganalisis hasil panen secara <strong>cepat, akurat, dan terintegrasi</strong>. Solusi inovatif dari <strong><?php echo $startup_name; ?></strong>.
                    </p>
                    <div class="mt-4">
                        <a href="#statistik" class="btn btn-primary btn-lg shadow">
                            <i class="fas fa-chart-line mr-2"></i>Lihat Data Publik
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Features Section -->
    <section id="manfaat" class="bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Tujuan Utama Sistem</h2>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-database fa-2x"></i>
                        </div>
                        <h4 class="feature-title">Data Terpusat & Akurat</h4>
                        <p>Mencatat hasil panen secara digital untuk meminimalisir kesalahan dan integrasi data antar-wilayah.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar fa-2x"></i>
                        </div>
                        <h4 class="feature-title">Analisis Visual</h4>
                        <p>Menyajikan data dalam bentuk grafik dan laporan otomatis untuk mendukung kebijakan berbasis data.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <h4 class="feature-title">Efisiensi Real-Time</h4>
                        <p>Menggantikan proses manual yang lambat dan meningkatkan kecepatan pengumpulan data antar kecamatan.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section id="statistik">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Statistik Pertanian Aceh Barat</h2>
                <p class="text-muted">Data publik terintegrasi dari Dinas Pertanian</p>
            </div>
            <div class="row">
                <?php foreach ($stats as $stat): ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card">
                        <i class="fas <?php echo $stat['icon']; ?> stats-icon text-<?php echo $stat['color']; ?>"></i>
                        <div class="stats-value text-<?php echo $stat['color']; ?>"><?php echo $stat['value']; ?></div>
                        <div class="stats-unit"><?php echo $stat['unit']; ?></div>
                        <div class="stats-label"><?php echo $stat['label']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="tentang" class="bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Tentang <?php echo $startup_name; ?></h2>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="text-center mb-4">
                        <blockquote class="blockquote">
                            <p class="mb-0" style="font-size: 1.3rem; font-style: italic; color: #555;">
                                "Mewujudkan pertanian Aceh Barat yang terdata, efisien, dan modern melalui teknologi digital."
                            </p>
                            <footer class="blockquote-footer mt-3">Visi Perusahaan</footer>
                        </blockquote>
                    </div>
                    <p class="lead text-center mb-5">
                        <strong>Trita Agro</strong> adalah startup teknologi pertanian asal Aceh Barat yang berfokus pada digitalisasi pendataan hasil panen untuk menjawab tantangan proses pendataan manual.
                    </p>
                    <div class="team-list">
                        <h5 class="text-center mb-4 text-success"><i class="fas fa-users"></i> Tim Inti Kami</h5>
                        <ul class="list-unstyled text-center">
                            <li><strong>Gita Cantika</strong> - CEO & CMO (Marketing)</li>
                            <li><strong>Putri Rizqa Maulidya</strong> - CMO (Marketing) & Developer</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="kontak">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Kontak & Dukungan</h2>
                <p class="text-muted">Hubungi kami untuk implementasi sistem atau kemitraan</p>
            </div>
            <div class="row">
                <div class="col-lg-5 mb-4">
                    <ul class="info-list">
                        <li><i class="fas fa-envelope fa-fw text-success mr-2"></i> <strong>Email:</strong> info@trita-agro.com</li>
                        <li><i class="fas fa-university fa-fw text-success mr-2"></i> <strong>Afiliasi:</strong> Universitas Teuku Umar</li>
                        <li><i class="fab fa-instagram fa-fw text-success mr-2"></i> <strong>Instagram:</strong> @AgriDataAceh</li>
                    </ul>
                </div>
                <div class="col-lg-7">
                    <div class="contact-card">
                        <h5 class="mb-4 text-center"><i class="fas fa-paper-plane mr-2"></i>Formulir Permintaan Demo</h5>
                        <form action="contact_submit.php" method="POST">
                            <div class="form-group">
                                <input type="text" class="form-control" name="nama" placeholder="Nama Instansi/Kelompok Tani" required>
                            </div>
                            <div class="form-group">
                                <input type="email" class="form-control" name="email" placeholder="Email Aktif" required>
                            </div>
                            <div class="form-group">
                                <textarea class="form-control" rows="4" name="pesan" placeholder="Ceritakan kebutuhan Anda..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-paper-plane mr-2"></i>Kirim Permintaan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center">
        <div class="container">
            <img src="logo.png" alt="AgriData Aceh Logo" class="mb-3 footer-logo" style="max-width: 100px; height: auto;">
            <p class="mb-2" style="font-size: 1.1rem; font-weight: 600;">
                <?php echo $startup_name; ?> - AgriData Aceh
            </p>
            <p class="mb-0">&copy; <?php echo $current_year; ?> All rights reserved.</p>
            <small class="d-block mt-2">
                <i class="fas fa-heart text-danger"></i> Mendukung Digitalisasi Pertanian Aceh Barat
            </small>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>