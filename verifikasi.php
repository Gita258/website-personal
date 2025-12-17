<?php
// =====================================================================
// verifikasi.php - Halaman Verifikasi Data Panen (Admin)
// =====================================================================

session_start();

// Autentikasi dan Otorisasi Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$title = "Verifikasi Data Panen Petani";
$user_name = $_SESSION['user_name'] ?? 'Admin';
$message = '';
$panen_data = [];

// --- KONFIGURASI DAN KONEKSI DATABASE ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'admin'; 
$db_name = 'agridata_aceh';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// =====================================================================
// 1. LOGIKA VERIFIKASI/TOLAK DATA PANEN
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['panen_id']) && isset($_POST['action'])) {
    $panen_id = (int)$_POST['panen_id'];
    $action = $_POST['action'];
    
    $status_value = ($action === 'verify') ? 'Diterima' : 'Ditolak';
    $log_message = ($action === 'verify') ? 'diterima' : 'ditolak';

    $sql_update = "UPDATE panen SET status = ? WHERE id_panen = ?";
    $stmt_update = $conn->prepare($sql_update);
    
    if ($stmt_update === false) {
         $message = '<div class="alert error">Gagal menyiapkan statement: ' . $conn->error . '</div>';
    } else {
        $stmt_update->bind_param('si', $status_value, $panen_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['message'] = '<div class="alert success">Data panen ID **' . $panen_id . '** berhasil **' . $log_message . '**.</div>';
            header("Location: verifikasi.php");
            exit;
        } else {
            $message = '<div class="alert error">Gagal memperbarui status data panen: ' . $stmt_update->error . '</div>';
        }

        $stmt_update->close();
    }
}

// Ambil pesan dari session setelah redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// =====================================================================
// 2. AMBIL DATA PANEN YANG PERLU DIVERIFIKASI (Hanya Pending)
// =====================================================================

$sql_fetch = "SELECT 
                p.id_panen, 
                p.tanggal_panen,
                p.komoditas,
                p.jumlah_panen_kg,
                p.status,
                p.latitude,
                p.longitude,
                u.nama AS nama_petani,
                l_desa.nama_lokasi AS nama_desa,
                l_kec.nama_lokasi AS nama_kecamatan
             FROM panen p
             LEFT JOIN users u ON p.user_id = u.id 
             LEFT JOIN lokasi l_desa ON p.lokasi_id = l_desa.id 
             LEFT JOIN lokasi l_kec ON l_desa.parent_id = l_kec.id
             WHERE p.status = 'Pending' 
             ORDER BY p.tanggal_panen ASC";

$result = $conn->query($sql_fetch);

if ($result === FALSE) {
     $message = '<div class="alert error">Kesalahan Kueri SQL: ' . $conn->error . '</div>';
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['id'] = $row['id_panen']; 
        $panen_data[] = $row;
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; min-height: 100vh; }
        
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 260px; background: linear-gradient(180deg, #1e7e34 0%, #155724 100%); padding: 20px 0; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 1000; }
        
        .brand { padding: 0 25px 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; display: flex; flex-direction: column; align-items: center; text-align: center; }
        
        .brand-logo { width: 80px; height: 80px; margin-bottom: 15px; background: #28a745; border-radius: 50%; padding: 8px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2)); animation: float 3s ease-in-out infinite; }
        
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-5px); } }
        
        .brand h2 { color: white; font-size: 1.4rem; font-weight: 600; margin: 0; }
        
        .brand p { color: rgba(255,255,255,0.8); font-size: 0.85rem; margin: 5px 0 0 0; }
        
        .nav-links { list-style: none; }
        .nav-links a { display: flex; align-items: center; gap: 15px; padding: 15px 25px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; font-size: 0.95rem; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #4CAF50; }
        .nav-links i { width: 20px; font-size: 1.1rem; }
        .content { margin-left: 260px; padding: 30px; }
        .header { background: white; padding: 25px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.6rem; color: #2c3e50; font-weight: 600; }
        .user-badge { background: linear-gradient(135deg, #95e989ff 0%, #4ba24bff 100%); color: white; padding: 10px 20px; border-radius: 25px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .page-content { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }

        /* Table Styling */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 12px 15px; border: 1px solid #ddd; text-align: left; font-size: 0.9rem; }
        .data-table th { background-color: #f2f2f2; color: #333; font-weight: 600; }
        .data-table tr:nth-child(even) { background-color: #f9f9f9; }
        
        /* Status Badges */
        .status-badge { padding: 5px 10px; border-radius: 4px; font-weight: 600; font-size: 0.8rem; }
        .status-badge.waiting { background-color: #ffe0b2; color: #e65100; }
        .status-badge.verified { background-color: #c8e6c9; color: #2e7d32; }
        .status-badge.rejected { background-color: #ffcdd2; color: #b71c1c; }
        
        /* Action Buttons */
        .action-form { display: inline-block; margin: 0 2px; }
        .btn-verify { background-color: #4CAF50; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s; font-size: 0.85rem;}
        .btn-verify:hover { background-color: #45a049; }
        .btn-reject { background-color: #f44336; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s; font-size: 0.85rem;}
        .btn-reject:hover { background-color: #d32f2f; }
        
        /* Alert Messages */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Map Link Styling */
        .map-link { color: #3498db; text-decoration: none; font-weight: 500;}
        .map-link:hover { text-decoration: underline; }
        .lokasi-detail { font-size: 0.85rem; color: #666; display: block; margin-top: 2px;}

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .content { margin-left: 70px; padding: 15px; }
            .brand-logo { width: 50px; height: 50px; }
            .brand h2, .brand p, .nav-links span { display: none; }
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
            <li><a href="verifikasi.php" class="active"><i class="fas fa-check-double"></i><span>Verifikasi Panen</span></a></li>
            <li><a href="laporan.php"><i class="fas fa-chart-bar"></i><span>Laporan Analisis</span></a></li>
            <li><a href="geografis.php"><i class="fas fa-map"></i><span>Geografis</span></a></li>
            <li><a href="pengaturan.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
            <li style="margin-top: 20px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="header">
            <h1>Verifikasi Data Panen 📝✅</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>Administrator
            </div>
        </div>

        <div class="page-content">
            <?php echo $message; ?>
            
            <p style="margin-bottom: 20px; color: #555;">
                Data di bawah adalah data panen yang perlu ditinjau dan diverifikasi oleh Administrator. Data yang **Diterima** akan dimasukkan ke dalam laporan analisis dan peta geografis.
            </p>

            <?php if (count($panen_data) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Petani</th>
                            <th>Komoditas</th>
                            <th>Hasil (Kg)</th>
                            <th>Lokasi</th>
                            <th>Bukti Lokasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($panen_data as $data): ?>
                        <tr>
                            <td><?php echo $data['id']; ?></td>
                            <td><?php echo htmlspecialchars($data['tanggal_panen']); ?></td>
                            <td><?php echo htmlspecialchars($data['nama_petani'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($data['komoditas']); ?></td>
                            <td><?php echo number_format($data['jumlah_panen_kg'], 2, ',', '.'); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($data['nama_desa'] ?? 'N/A'); ?></strong>
                                <span class="lokasi-detail">(Kec. <?php echo htmlspecialchars($data['nama_kecamatan'] ?? 'N/A'); ?>)</span>
                            </td>
                            <td>
                                <?php if (!empty($data['latitude']) && !empty($data['longitude'])): ?>
                                    <a href="geografis.php?lat=<?php echo $data['latitude']; ?>&lng=<?php echo $data['longitude']; ?>" target="_blank" class="map-link">
                                        <i class="fas fa-map-pin"></i> Lihat Koordinat
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">Tidak Ada Koordinat</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $status_class = '';
                                    if ($data['status'] == 'Diterima') {
                                        $status_class = 'verified';
                                    } elseif ($data['status'] == 'Ditolak') {
                                        $status_class = 'rejected';
                                    } else {
                                        $status_class = 'waiting';
                                    }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($data['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($data['status'] == 'Pending' || $data['status'] == 'Ditolak'): ?>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="panen_id" value="<?php echo $data['id']; ?>">
                                        <input type="hidden" name="action" value="verify">
                                        <button type="submit" class="btn-verify" title="Setujui Data" onclick="return confirm('Apakah Anda yakin ingin MENERIMA data panen ini?');"><i class="fas fa-check"></i> Terima</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($data['status'] == 'Pending' || $data['status'] == 'Diterima'): ?>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="panen_id" value="<?php echo $data['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject" title="Tolak Data" onclick="return confirm('Apakah Anda yakin ingin MENOLAK data panen ini?');"><i class="fas fa-times"></i> Tolak</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i> Semua data panen sudah diterima atau tidak ada data yang menunggu peninjauan.
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>