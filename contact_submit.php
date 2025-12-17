<?php
// =====================================================================
// contact_submit.php - Proses Pengiriman Formulir Permintaan Demo
// =====================================================================

// Ambil data koneksi dari index.php (atau definisikan ulang dengan benar)
// Pastikan kredensial di bawah ini sesuai dengan yang Anda pakai (host, user, pass)
$db_host = "localhost"; 
$db_user = "root";      
$db_pass = "admin";     // Sesuai dengan setting di index.php Anda
$db_name = "agridata_aceh"; 

// ---------------------------------------------------------------------
// KONEKSI DATABASE
// ---------------------------------------------------------------------
try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Jika koneksi gagal, tampilkan pesan error yang jelas
    $error_message = "Koneksi database gagal: " . $e->getMessage();
    goto display_page; // Langsung ke bagian tampilan
}

$success_message = "";
$error_message = "";

// ---------------------------------------------------------------------
// PROSES PENYIMPANAN DATA
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil dan bersihkan data input (Pencegahan SQL Injection)
    $nama = htmlspecialchars(trim($_POST['nama']));
    $email = htmlspecialchars(trim($_POST['email']));
    $pesan = htmlspecialchars(trim($_POST['pesan']));

    if (empty($nama) || empty($email)) {
        $error_message = "Nama Instansi dan Email wajib diisi.";
    } else {
        try {
            // Persiapkan statement SQL untuk memasukkan data
            $stmt = $db->prepare("INSERT INTO permintaan_demo (nama_instansi, email, pesan) VALUES (?, ?, ?)");
            
            // Eksekusi statement
            $stmt->execute([$nama, $email, $pesan]);

            $success_message = "Permintaan demo Anda berhasil kami terima! Tim kami akan menghubungi Anda melalui email **{$email}** secepatnya. Terima kasih atas minat Anda pada AgriData - Aceh.";
            
        } catch (Exception $e) {
            $error_message = "Terjadi kesalahan saat menyimpan data ke database. Silakan coba lagi. Error: " . $e->getMessage();
        }
    }
} else {
    // Jika diakses langsung tanpa POST request
    header("Location: index.php");
    exit();
}

// ---------------------------------------------------------------------
// BAGIAN TAMPILAN (Jika terjadi error, kode PHP akan melompat ke sini)
// ---------------------------------------------------------------------
display_page:
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pengiriman</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .status-box { max-width: 600px; padding: 40px; border-radius: 10px; background-color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,.1); text-align: center; }
    </style>
</head>
<body>

<div class="status-box">
    <?php if ($success_message): ?>
        <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
        <h3 class="text-success">Pengiriman Berhasil!</h3>
        <p class="lead"><?php echo $success_message; ?></p>
        <a href="index.php" class="btn btn-success mt-3"><i class="fas fa-home"></i> Kembali ke Beranda</a>
    <?php elseif ($error_message): ?>
        <i class="fas fa-times-circle fa-5x text-danger mb-4"></i>
        <h3 class="text-danger">Pengiriman Gagal!</h3>
        <p class="lead"><?php echo $error_message; ?></p>
        <a href="index.php#kontak" class="btn btn-warning mt-3"><i class="fas fa-redo"></i> Coba Kirim Ulang</a>
    <?php endif; ?>
</div>

</body>
</html>