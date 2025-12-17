<?php
// =====================================================================
// pengaturan.php - Halaman Pengaturan Admin (Pengelolaan Lokasi Master)
// =====================================================================

session_start();

// --- Konfigurasi dan Autentikasi ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- KONFIGURASI DAN KONEKSI DATABASE ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'admin';
$db_name = 'agridata_aceh';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$success_message = '';
$error_message = '';

// =====================================================================
// *** Aksi: TAMBAH LOKASI MASTER BARU (ke tabel komoditas_lokasi) ***
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_master_location') {
    
    $komoditas = trim($_POST['new_komoditas'] ?? '');
    $lokasi_desa = trim($_POST['new_lokasi_desa'] ?? '');
    $lokasi_kecamatan = trim($_POST['new_lokasi_kecamatan'] ?? '');

    // Validasi input
    if (empty($komoditas) || empty($lokasi_desa) || empty($lokasi_kecamatan)) {
        $error_message = "Semua field Komoditas, Desa, dan Kecamatan harus diisi.";
    } else {
        // Cek duplikasi
        $sql_check = "SELECT id FROM komoditas_lokasi WHERE komoditas = ? AND lokasi_desa = ? LIMIT 1";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param('ss', $komoditas, $lokasi_desa);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Lokasi Master (" . htmlspecialchars($komoditas) . " di " . htmlspecialchars($lokasi_desa) . ") sudah ada.";
        } else {
            // Masukkan data baru
            $sql_insert = "INSERT INTO komoditas_lokasi (komoditas, lokasi_desa, lokasi_kecamatan) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param('sss', $komoditas, $lokasi_desa, $lokasi_kecamatan);
            
            if ($stmt_insert->execute()) {
                $success_message = "Lokasi Master baru berhasil ditambahkan.";
            } else {
                $error_message = "Gagal menambahkan lokasi master: " . htmlspecialchars($stmt_insert->error);
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

// =====================================================================
// Ambil Daftar Lokasi Master yang Sudah Ada untuk Ditampilkan
// =====================================================================
$master_list = [];
$sql_list = "SELECT id, komoditas, lokasi_desa, lokasi_kecamatan, latitude, longitude 
             FROM komoditas_lokasi 
             ORDER BY komoditas, lokasi_kecamatan, lokasi_desa ASC";
$result_list = $conn->query($sql_list);
if ($result_list) {
    while ($row = $result_list->fetch_assoc()) {
        $master_list[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Master - AgriData</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; min-height: 100vh; }
        
        .sidebar { 
            position: fixed; left: 0; top: 0; height: 100vh; width: 260px; 
            background: linear-gradient(180deg, #1e7e34 0%, #155724 100%); 
            padding: 20px 0; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 1000; 
            overflow-y: auto;
        }
        
        .brand { padding: 0 25px 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; display: flex; flex-direction: column; align-items: center; text-align: center; }
        
        .brand-logo { width: 80px; height: 80px; margin-bottom: 15px; background: #28a745; border-radius: 50%; padding: 8px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2)); animation: float 3s ease-in-out infinite; }
        
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-5px); } }
        
        .brand h2 { color: white; font-size: 1.4rem; font-weight: 600; margin: 0; }
        
        .brand p { color: rgba(255,255,255,0.8); font-size: 0.85rem; margin: 5px 0 0 0; }
        
        .nav-links { list-style: none; }
        .nav-links a { 
            display: flex; align-items: center; gap: 15px; padding: 15px 25px; 
            color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; font-size: 0.95rem; 
        }
        .nav-links a:hover, .nav-links a.active { 
            background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #4CAF50; 
        }
        
        .content { margin-left: 260px; padding: 30px; }
        
        .header { 
            background: white; padding: 25px 30px; border-radius: 15px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; 
            display: flex; justify-content: space-between; align-items: center; 
        }
        .header h1 { font-size: 1.8rem; color: #2c3e50; }
        .user-badge { 
            display: flex; align-items: center; gap: 8px; padding: 8px 16px; 
            background: #e8f5e9; color: #2e7d32; border-radius: 20px; font-size: 0.9rem; 
        }
        
        .page-content { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        /* Gaya Khusus Halaman Pengaturan */
        .setting-box {
            padding: 25px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            background: #fdfdfd;
            margin-bottom: 25px;
        }
        
        .setting-box h2 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .btn-submit {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 600;
        }
        
        .btn-submit:hover {
            background-color: #388e3c;
        }
        
        .alert { 
            padding: 12px 20px; margin-bottom: 15px; border-radius: 8px; 
            font-size: 0.95rem; display: flex; align-items: center; gap: 12px; 
        }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert i { font-size: 1.2rem; }

        /* Tabel List */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-size: 0.9rem;
        }
        .data-table th {
            background-color: #f2f2f2;
            color: #333;
        }
        .coord-status {
            font-weight: 600;
            font-size: 0.85rem;
            padding: 3px 8px;
            border-radius: 5px;
            display: inline-block;
        }
        .coord-status.set {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .coord-status.unset {
            background-color: #ffe0b2;
            color: #e65100;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .content { margin-left: 70px; padding: 15px; }
            .brand-logo { width: 50px; height: 50px; }
            .brand h2, .brand p, .nav-links span { display: none; }
            .form-row { flex-direction: column; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">
            <img src="logo.png" alt="AgriData Aceh Logo" class="brand-logo">
            <h2>AgriData Aceh</h2>
            <p>Admin Dashboard</p>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard_admin.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="kelola_pengguna.php"><i class="fas fa-users"></i><span>Pengguna</span></a></li>
            <li><a href="verifikasi.php"><i class="fas fa-check-double"></i><span>Verifikasi Panen</span></a></li>
            <li><a href="laporan.php"><i class="fas fa-chart-bar"></i><span>Laporan Analisis</span></a></li>
            <li><a href="geografis.php"><i class="fas fa-map"></i><span>Geografis</span></a></li>
            <li><a href="pengaturan.php" class="active"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
            <li style="margin-top: 20px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="header">
            <h1><i class="fas fa-cog"></i> Pengaturan Master Lokasi</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i> Administrator
            </div>
        </div>

        <div class="page-content">
            
            <?php 
            if ($success_message) {
                echo '<div class="alert success"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($success_message) . '</div>';
            }
            if ($error_message) {
                echo '<div class="alert error"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($error_message) . '</div>';
            }
            ?>

            <div class="setting-box">
                <h2><i class="fas fa-plus-circle"></i> Tambah Lokasi Master Baru</h2>
                <p>Tambahkan kombinasi Komoditas, Desa, dan Kecamatan yang baru. Koordinat Latitude/Longitude harus diisi melalui halaman Geografis.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_master_location">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_komoditas"><i class="fas fa-seedling"></i> Komoditas</label>
                            <input type="text" id="new_komoditas" name="new_komoditas" required placeholder="Contoh: Kopi">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_lokasi_desa"><i class="fas fa-home"></i> Nama Desa</label>
                            <input type="text" id="new_lokasi_desa" name="new_lokasi_desa" required placeholder="Contoh: Desa Ujung Drien">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_lokasi_kecamatan"><i class="fas fa-city"></i> Nama Kecamatan</label>
                            <input type="text" id="new_lokasi_kecamatan" name="new_lokasi_kecamatan" required placeholder="Contoh: Meureubo">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Tambah Lokasi</button>
                </form>
            </div>
            
            <div class="setting-box">
                <h2><i class="fas fa-list-ul"></i> Daftar Lokasi Master (<?php echo count($master_list); ?> entri)</h2>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Komoditas</th>
                            <th>Desa</th>
                            <th>Kecamatan</th>
                            <th>Koordinat</th>
                            <th>Status Koord.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($master_list)): ?>
                            <tr><td colspan="6">Belum ada data lokasi master.</td></tr>
                        <?php else: ?>
                            <?php foreach ($master_list as $item): 
                                $is_set = !empty($item['latitude']) && !empty($item['longitude']);
                                $status_class = $is_set ? 'set' : 'unset';
                                $status_text = $is_set ? 'Sudah Diatur' : 'Belum Diatur';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['id']); ?></td>
                                    <td><?php echo htmlspecialchars($item['komoditas']); ?></td>
                                    <td><?php echo htmlspecialchars($item['lokasi_desa']); ?></td>
                                    <td><?php echo htmlspecialchars($item['lokasi_kecamatan']); ?></td>
                                    <td>
                                        <?php echo $is_set ? htmlspecialchars(number_format($item['latitude'], 4) . ', ' . number_format($item['longitude'], 4)) : '-'; ?>
                                    </td>
                                    <td><span class="coord-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</body>
</html>