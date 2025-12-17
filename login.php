<?php
// =====================================================================
// login.php - Halaman Login AgriData (Multi-Tabel TANPA HASHING)
// =====================================================================
session_start();

// --- KONFIGURASI DATABASE ---
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'admin', 
    'name' => 'agridata_aceh'
];

$db = null;
$pesan_error = "";
$koneksi_error = false;

try {
    // Koneksi menggunakan PDO
    $db = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
        $db_config['user'], $db_config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    // Hanya menampilkan pesan koneksi gagal (koneksi awal)
    $pesan_error = "❌ **Koneksi Database Gagal.** Cek kredensial DB Anda di kode.";
    $koneksi_error = true;
}

// Redirect jika sudah login
if (!$koneksi_error && isset($_SESSION['user_id'])) {
    $target = ($_SESSION['user_role'] === 'admin') ? "dashboard_admin.php" : "dashboard_petani.php";
    header("Location: " . $target);
    exit();
}

// --- PROSES LOGIN (TIDAK MENGGUNAKAN HASHING, Multi-Tabel) ---
if (!$koneksi_error && $_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password_input = $_POST['password'];
    $user = null; // Inisialisasi variabel pengguna

    if (empty($email) || empty($password_input)) {
        $pesan_error = "Email dan password wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = "Format email tidak valid.";
    } else {
        try {
            
            // 1. --- Coba Login di Tabel 1: users (Petani/Users Biasa) ---
            $stmt = $db->prepare("SELECT id, nama, email, role, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $password_input === $user['password']) {
                // Login Petani Berhasil. Variabel $user sudah terisi.
            } else {
                // 2. --- Coba Login di Tabel 2: admin_users (Admin) ---
                // Menggunakan alias SQL: username AS nama untuk konsistensi
                $stmt = $db->prepare("SELECT 
                    id, 
                    username AS nama, /* Sesuaikan kolom username menjadi nama */
                    email, 
                    password, 
                    role 
                FROM admin_users 
                WHERE email = ?");
                $stmt->execute([$email]);
                $admin_user = $stmt->fetch();

                if ($admin_user && $password_input === $admin_user['password']) {
                    $user = $admin_user; // Ganti variabel user dengan data admin
                } else {
                    $user = null; // Jika tidak ditemukan di kedua tabel
                }
            }

            // --- VERIFIKASI AKHIR DAN REDIRECT ---
            if ($user) {
                // Login berhasil
                session_regenerate_id(true); 
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nama'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                $target_dashboard = ($user['role'] === 'admin') ? "dashboard_admin.php" : "dashboard_petani.php";
                
                header("Location: " . $target_dashboard);
                exit();
            } else {
                // Pesan error jika tidak ditemukan di kedua tabel
                $pesan_error = "Email atau password salah.";
            }
        } catch (Exception $e) {
            // Error log untuk debugging, tapi tampilkan pesan umum ke user
            $pesan_error = "Terjadi kesalahan sistem saat memproses login. Coba lagi.";
            error_log("Login Process Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AgriData Aceh</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-green: #28a745;
            --dark-green: #1e7e34;
            --light-green: #e8f5e9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            background: linear-gradient(135deg, #e8f5e9 0%, #a8e6cf 100%);
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .register-wrapper { 
            max-width: 900px;
            width: 100%;
        }

        .register-container { 
            background-color: #fff; 
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            display: flex;
            flex-wrap: wrap;
        }

        .register-left {
            flex: 1;
            min-width: 300px;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .register-left .logo-container {
            margin-bottom: 30px;
        }

        .register-left .logo-container img {
            max-width: 150px;
            height: auto;
            background: #28a745;
            border-radius: 50%;
            padding: 10px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .register-left h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .register-left p {
            font-size: 1.1rem;
            line-height: 1.8;
            opacity: 0.95;
        }

        .register-left .features {
            margin-top: 30px;
            width: 100%;
        }

        .register-left .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            justify-content: center;
        }

        .register-left .feature-item i {
            font-size: 1.5rem;
            margin-right: 15px;
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-right {
            flex: 1;
            min-width: 300px;
            padding: 50px 40px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .register-header h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--dark-green);
            margin-bottom: 10px;
        }

        .register-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 1rem;
            transition: all 0.3s ease;
            height: 48px;
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            z-index: 10;
            pointer-events: none;
        }

        .form-control.with-icon {
            padding-left: 45px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border: none;
            border-radius: 10px;
            padding: 14px 30px;
            font-size: 1.1rem;
            font-weight: 700;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-danger {
            background-color: #fee;
            color: #c33;
            border-left: 4px solid #dc3545;
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #ddd;
        }

        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #999;
            font-size: 0.9rem;
        }

        .footer-links {
            text-align: center;
            margin-top: 25px;
        }

        .footer-links a {
            color: var(--primary-green);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--dark-green);
            text-decoration: underline;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            color: #666;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .back-home:hover {
            color: var(--primary-green);
            transform: translateX(-5px);
        }

        .back-home i {
            margin-right: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .register-left {
                padding: 40px 30px;
            }

            .register-left .logo-container img {
                max-width: 120px;
            }

            .register-left h2 {
                font-size: 1.6rem;
            }

            .register-left .features {
                display: none;
            }

            .register-right {
                padding: 40px 30px;
            }

            .register-header h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="register-wrapper">
    <div class="register-container">
        <div class="register-left">
            <div class="logo-container">
                <img src="logo.png" alt="AgriData Aceh Logo">
            </div>
            <div>
                <h2>AgriData Aceh</h2>
                <p>Masuk untuk mengakses data pertanian Aceh Barat secara real-time. Dapatkan laporan panen dan kelola tim lapangan dengan mudah.</p>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Akses Data & Laporan Real-Time</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-desktop"></i>
                        <span>Antarmuka Mudah Digunakan</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Kelola Tim Lapangan</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="register-right">
            <div class="register-header">
                <h3>Masuk ke Sistem</h3>
                <p>Gunakan akun yang telah Anda daftarkan</p>
            </div>
            
            <?php if ($pesan_error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($pesan_error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope mr-2"></i>Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input 
                            type="email" 
                            class="form-control with-icon" 
                            id="email" 
                            name="email" 
                            placeholder="nama@email.com"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            required 
                            autofocus
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock mr-2"></i>Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input 
                            type="password" 
                            class="form-control with-icon" 
                            id="password" 
                            name="password" 
                            placeholder="Masukkan password Anda"
                            required
                        >
                    </div>
                </div>
                
                <div class="text-right mb-4">
                    <a href="lupa_password.php" class="text-secondary font-weight-bold" style="font-size: 0.9rem;">
                        <i class="fas fa-question-circle mr-1"></i> Lupa Password?
                    </a>
                </div>
                <button type="submit" class="btn btn-primary mt-3">
                    <i class="fas fa-sign-in-alt mr-2"></i> Masuk ke Sistem
                </button>
            </form>
            
            <div class="divider">
                <span>atau</span>
            </div>
            
            <div class="footer-links">
                <p class="mb-2">Belum punya akun? <a href="register.php">Daftar Sekarang</a></p>
                <a href="index.php" class="back-home">
                    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>