<?php
// =====================================================================
// lupa_password_single_step.php - Pemulihan Password Satu Langkah
// Verifikasi menggunakan Email, Jawaban Keamanan, dan Atur Ulang Password Baru.
// CATATAN PENTING: Penyimpanan password diubah menjadi PLAIN TEXT (TIDAK AMAN).
// =====================================================================

session_start();

// ---------------------------------------------------------------------
// 1. KONFIGURASI DAN KONEKSI DATABASE
// ---------------------------------------------------------------------
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'admin', // Ganti dengan password database Anda
    'name' => 'agridata_aceh'
];

try {
    $db = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
        $db_config['user'],
        $db_config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal. Silakan hubungi administrator.");
}

$pesan_error = "";
$pesan_sukses = "";
$email_input = '';
$pertanyaan_aman_user = 'Apa nama hewan peliharaan pertama Anda?'; // Pertanyaan default dari form

// ---------------------------------------------------------------------
// 2. LOGIKA PROSES PEMULIHAN (NON-HASHING)
// ---------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_input = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $jawaban_aman_input = trim($_POST['jawaban_aman'] ?? '');
    $password_baru = $_POST['password_baru'] ?? '';
    $ulangi_password = $_POST['ulangi_password'] ?? '';

    // Validasi input wajib
    if (empty($email_input) || empty($jawaban_aman_input) || empty($password_baru) || empty($ulangi_password)) {
        $pesan_error = "Semua kolom (Email, Jawaban, dan Password Baru) wajib diisi.";
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = "Format email tidak valid.";
    } elseif ($password_baru !== $ulangi_password) {
        $pesan_error = "Konfirmasi password tidak cocok.";
    } elseif (strlen($password_baru) < 6) {
        $pesan_error = "Password baru minimal 6 karakter.";
    } else {
        try {
            // 1. Cek User, Pertanyaan, dan Jawaban Keamanan dalam SATU query
            // CATATAN: Jawaban keamanan (jawaban_aman) diasumsikan disimpan dalam plain text di DB
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND jawaban_aman = ?");
            $stmt->execute([$email_input, $jawaban_aman_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Jawaban dan Email Benar
                $user_id = $user['id'];
                
                // 2. UPDATE PASSWORD DI DATABASE (TANPA HASHING)
                // ---------------------------------------------------
                // ⚠️ PERINGATAN: Password disimpan sebagai string plain text di kolom password.
                // ---------------------------------------------------
                $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_success = $update_stmt->execute([$password_baru, $user_id]);

                if ($update_success) {
                    $pesan_sukses = "Password Anda berhasil diubah! Silakan <a href='login.php' class='alert-link font-weight-bold'>Login</a> dengan password baru Anda.";
                } else {
                    $pesan_error = "Gagal memperbarui password karena kesalahan database. Coba lagi.";
                }
            } else {
                // Gagal, kemungkinan Email atau Jawaban Keamanan salah
                $pesan_error = "Email atau Jawaban Keamanan tidak cocok. Mohon periksa kembali.";
            }

        } catch (Exception $e) {
            $pesan_error = "Terjadi kesalahan sistem: " . $e->getMessage();
            error_log("Password Reset Error: " . $e->getMessage());
        }
    }
}

// ---------------------------------------------------------------------
// 3. TAMPILAN HTML
// ---------------------------------------------------------------------
// (Kode HTML/CSS tetap sama)

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password | AgriData - Aceh</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Style yang sama dengan lupa_password.php sebelumnya */
        :root { --primary-green: #28a745; --dark-green: #1e7e34; }
        body { 
            background: linear-gradient(135deg, #e8f5e9 0%, #a8e6cf 100%);
            display: flex; align-items: center; justify-content: center; min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .form-card {
            max-width: 450px; 
            width: 100%;
            background-color: #fff; 
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            padding: 40px;
        }
        .form-header { text-align: center; margin-bottom: 35px; }
        .form-header h3 { font-size: 1.8rem; font-weight: 800; color: var(--dark-green); margin-bottom: 10px; }
        .form-header p { color: #666; font-size: 0.95rem; }
        .form-control { border: 2px solid #e0e0e0; border-radius: 10px; padding: 12px 18px; height: 48px; }
        .form-control:focus { border-color: var(--primary-green); box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15); }
        .btn-primary { 
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            border: none; border-radius: 10px; padding: 14px 30px; font-weight: 700; width: 100%; 
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4); }
        .alert { border-radius: 10px; border: none; padding: 15px 20px; margin-bottom: 25px; }
        .alert-danger { background-color: #fee; color: #c33; border-left: 4px solid #dc3545; }
        .alert-success { background-color: #e8f5e9; color: #2e7d32; border-left: 4px solid var(--primary-green); }
        .back-link { display: block; text-align: center; margin-top: 20px; color: var(--primary-green); }
        .input-group { position: relative; }
        .input-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #999; z-index: 10; pointer-events: none; }
        .form-control.with-icon { padding-left: 45px; }
        .password-toggle { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; z-index: 10; transition: color 0.3s; }
        .password-toggle:hover { color: var(--primary-green); }
        .security-question-display {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        .security-question-display strong {
            color: var(--dark-green);
        }
    </style>
</head>
<body>

<div class="form-card">
    <div class="form-header">
        <h3><i class="fas fa-key mr-2"></i>Pemulihan Password</h3>
        <p>Masukkan email dan jawab pertanyaan keamanan Anda untuk mereset password.</p>
    </div>

    <?php if ($pesan_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($pesan_error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($pesan_sukses): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle mr-2"></i> <?php echo $pesan_sukses; ?>
        </div>
        <a href="login.php" class="back-link font-weight-bold"><i class="fas fa-sign-in-alt mr-1"></i> Kembali ke Halaman Login</a>
    <?php else: ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Akun Anda</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control with-icon" id="email" name="email" 
                            placeholder="Masukkan email terdaftar" required value="<?php echo htmlspecialchars($email_input); ?>">
                </div>
            </div>
            
            <div class="security-question-display">
                <i class="fas fa-shield-alt mr-2 text-info"></i> Pertanyaan Keamanan: <br>
                <strong><?php echo htmlspecialchars($pertanyaan_aman_user); ?></strong> 
                <small class="text-muted d-block mt-1">*(Anda harus mengingat jawaban saat mendaftar)*</small>
            </div>
            
            <div class="form-group">
                <label for="jawaban_aman">Jawaban Keamanan</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-key"></i></span>
                    <input type="text" class="form-control with-icon" id="jawaban_aman" name="jawaban_aman" 
                            placeholder="Masukkan Jawaban Keamanan" required>
                </div>
            </div>

            <hr>

            <div class="form-group">
                <label for="password_baru">Password Baru</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control with-icon" id="password_baru" name="password_baru" 
                            placeholder="Minimal 6 karakter" required>
                    <span class="password-toggle" onclick="togglePassword('password_baru')">
                        <i class="fas fa-eye" id="password_baru-eye"></i>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label for="ulangi_password">Ulangi Password Baru</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control with-icon" id="ulangi_password" name="ulangi_password" 
                            placeholder="Ketik ulang password baru" required>
                    <span class="password-toggle" onclick="togglePassword('ulangi_password')">
                        <i class="fas fa-eye" id="ulangi_password-eye"></i>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">
                <i class="fas fa-redo-alt mr-2"></i> Atur Ulang Password
            </button>
        </form>

        <a href="login.php" class="back-link"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Login</a>
        
    <?php endif; ?>

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
</script>
</body>
</html>