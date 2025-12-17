<?php
// =====================================================================
// register.php - Halaman Pendaftaran Petani (TANPA HASHING PASSWORD)
// =====================================================================

// ---------------------------------------------------------------------
// 1. KONFIGURASI DAN KONEKSI DATABASE
// ---------------------------------------------------------------------
$db_host = "localhost"; 
$db_user = "root"; 
$db_pass = "admin";
$db_name = "agridata_aceh"; 

try {
    // Koneksi menggunakan PDO dengan Prepared Statements
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi ke database gagal: " . $e->getMessage());
}

$pesan_error = "";
$pesan_sukses = "";
$role_default = "petani"; 

// ---------------------------------------------------------------------
// INICIALISASI VARIABEL INPUT
// ---------------------------------------------------------------------
$nama = '';
$email = '';
$pertanyaan_aman = '';
$jawaban_aman = ''; 

// ---------------------------------------------------------------------
// 2. LOGIKA PROSES PENDAFTARAN
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Mengambil dan membersihkan input
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; 
    $ulangi_password = $_POST['ulangi_password'] ?? '';
    $pertanyaan_aman = $_POST['pertanyaan_aman'] ?? ''; 
    $jawaban_aman = trim($_POST['jawaban_aman'] ?? ''); // Jawaban aman juga di-trim
    $role = $role_default; 

    // --- Validasi Input ---
    if (empty($nama) || empty($email) || empty($password) || empty($ulangi_password) || empty($pertanyaan_aman) || empty($jawaban_aman)) { 
        $pesan_error = "Semua kolom wajib diisi, termasuk pertanyaan keamanan.";
    } elseif ($password !== $ulangi_password) {
        $pesan_error = "Konfirmasi password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $pesan_error = "Password minimal 6 karakter.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = "Format email tidak valid.";
    } else {
        try {
            // --- 1. Cek Duplikasi Email ---
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $pesan_error = "Email ini sudah terdaftar. Silakan login.";
            } else {
                // --- 2. *TIDAK* Melakukan Hash Password (Sangat Tidak Disarankan!) ---
                $password_plaintext = $password; // Menyimpan password dalam teks biasa
                
                // --- 3. Simpan Data ke Database ---
                // Tetap menggunakan Prepared Statements untuk mencegah SQL Injection
                $sql = "INSERT INTO users (nama, email, password, role, pertanyaan_aman, jawaban_aman) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                // Jawaban aman juga disimpan dalam teks biasa di sini (tidak dienkripsi)
                $success = $stmt->execute([$nama, $email, $password_plaintext, $role, $pertanyaan_aman, $jawaban_aman]);

                if ($success) {
                    $pesan_sukses = "Pendaftaran akun <strong>" . htmlspecialchars($nama) . "</strong> berhasil! Anda terdaftar sebagai <strong>Petani</strong>. Silakan <a href='login.php' class='alert-link font-weight-bold'>Login</a>.";
                    // Reset form setelah sukses
                    $nama = $email = $pertanyaan_aman = $jawaban_aman = '';
                } else {
                    $pesan_error = "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.";
                }
            }
        } catch (Exception $e) {
            $pesan_error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Petani | AgriData - Aceh</title>
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
            box-shadow: 0 0 50px rgba(0,0,0,0.15);
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
            font-size: 0.95rem;
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
            outline: none;
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

        /* Security Question Section Styling */
        .security-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed var(--primary-green);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            position: relative;
        }

        .security-section::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 15px;
            z-index: -1;
            opacity: 0.1;
        }

        .security-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(40, 167, 69, 0.2);
        }

        .security-header i {
            font-size: 1.8rem;
            color: var(--primary-green);
            margin-right: 12px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .security-header h5 {
            margin: 0;
            color: var(--dark-green);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .security-header p {
            margin: 0;
            color: #666;
            font-size: 0.85rem;
        }

        .security-info {
            background: white;
            border-left: 4px solid var(--primary-green);
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #555;
        }

        .security-info i {
            color: var(--primary-green);
            margin-right: 8px;
        }

        /* Custom Select Styling */
        .custom-select-wrapper {
            position: relative;
        }

        .custom-select-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: white;
            cursor: pointer;
            padding-right: 40px;
        }

        .custom-select-wrapper::after {
            content: '\f078';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-green);
            pointer-events: none;
            font-size: 0.9rem;
        }

        .custom-select-wrapper select:focus {
            border-color: var(--primary-green);
        }

        .security-section .form-group {
            margin-bottom: 18px;
        }

        .security-section .form-group:last-child {
            margin-bottom: 0;
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

        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid var(--primary-green);
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

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            z-index: 10;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: var(--primary-green);
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

            .security-section {
                padding: 20px;
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
                <p>Bergabunglah dengan sistem digitalisasi pertanian terdepan di Aceh Barat. Kelola data panen Anda dengan mudah dan efisien.</p>
                
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Data Tersimpan Aman</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Laporan Real-Time</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Kolaborasi Tim</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="register-right">
            <div class="register-header">
                <h3>Daftar Akun Baru</h3>
                <p>Silakan isi formulir untuk mendaftar sebagai **Petani**</p>
            </div>

            <?php if ($pesan_error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $pesan_error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($pesan_sukses): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $pesan_sukses; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <input type="hidden" name="role" value="petani">

                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control with-icon" id="nama" name="nama" placeholder="Masukkan nama lengkap" required value="<?php echo htmlspecialchars($nama); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control with-icon" id="email" name="email" placeholder="contoh@email.com" required value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control with-icon" id="password" name="password" placeholder="Minimal 6 karakter" required>
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="ulangi_password">Ulangi Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control with-icon" id="ulangi_password" name="ulangi_password" placeholder="Ketik ulang password" required>
                        <span class="password-toggle" onclick="togglePassword('ulangi_password')">
                            <i class="fas fa-eye" id="ulangi_password-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="security-section">
                    <div class="security-header">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <h5>Pertanyaan Keamanan</h5>
                            <p>Untuk pemulihan akun jika lupa password</p>
                        </div>
                    </div>

                    <div class="security-info">
                        <i class="fas fa-info-circle"></i>
                        **Penting:** Jawaban ini akan digunakan untuk memverifikasi identitas Anda saat lupa password.
                    </div>

                    <div class="form-group">
                        <label for="pertanyaan_aman">Pilih Pertanyaan Rahasia</label>
                        <div class="custom-select-wrapper">
                            <select class="form-control" id="pertanyaan_aman" name="pertanyaan_aman" required>
                                <option value="" disabled <?php echo empty($pertanyaan_aman) ? 'selected' : ''; ?>>-- Pilih Pertanyaan --</option>
                                <option value="Nama Kelompok Tani Anda?" <?php echo $pertanyaan_aman == 'Nama Kelompok Tani Anda?' ? 'selected' : ''; ?>>
                                    🌾 Nama Kelompok Tani Anda?
                                </option>
                                <option value="Di Kecamatan mana Anda tinggal?" <?php echo $pertanyaan_aman == 'Di Kecamatan mana Anda tinggal?' ? 'selected' : ''; ?>>
                                    📍 Di Kecamatan mana Anda tinggal?
                                </option>
                                <option value="Nama Padi Varietas favorit Anda?" <?php echo $pertanyaan_aman == 'Nama Padi Varietas favorit Anda?' ? 'selected' : ''; ?>>
                                    🌾 Nama Padi Varietas favorit Anda?
                                </option>
                                <option value="Nama Hewan Peliharaan pertama Anda?" <?php echo $pertanyaan_aman == 'Nama Hewan Peliharaan pertama Anda?' ? 'selected' : ''; ?>>
                                    🐾 Nama Hewan Peliharaan pertama Anda?
                                </option>
                                <option value="Nama Ibu Kandung Anda?" <?php echo $pertanyaan_aman == 'Nama Ibu Kandung Anda?' ? 'selected' : ''; ?>>
                                    👤 Nama Ibu Kandung Anda?
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="jawaban_aman">Jawaban Anda</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-key"></i></span>
                            <input type="text" class="form-control with-icon" id="jawaban_aman" name="jawaban_aman" placeholder="Ketik jawaban yang mudah Anda ingat" required value="<?php echo htmlspecialchars($jawaban_aman); ?>">
                        </div>
                        <small class="form-text text-muted mt-2">
                            <i class="fas fa-lightbulb mr-1"></i>
                            Tips: Gunakan jawaban yang unik dan mudah Anda ingat
                        </small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4">
                    <i class="fas fa-user-plus mr-2"></i> Daftar Sekarang
                </button>

                <div class="divider">
                    <span>atau</span>
                </div>

                <div class="footer-links">
                    <p class="mb-2">Sudah punya akun? <a href="login.php">Login di sini</a></p>
                    <a href="index.php" class="back-home">
                        <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-eye');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Add animation when security section comes into view
document.addEventListener('DOMContentLoaded', function() {
    const securitySection = document.querySelector('.security-section');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'slideDown 0.5s ease';
            }
        });
    });
    
    if (securitySection) {
        observer.observe(securitySection);
    }
});
</script>

</body>
</html>