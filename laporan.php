<?php
// =====================================================================
// laporan.php - Halaman Laporan Data Pertanian (Menggunakan Tabel 'panen')
// STATUS: FINAL, DENGAN FILTER HANYA DATA 'DITERIMA'
// =====================================================================

session_start();

// Autentikasi dan Otorisasi
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['user_name'] ?? 'Admin';
$title = "Laporan Data Pertanian - AgriData Aceh";
$reports = [];
$farmers = [];
$selected_farmer_id = 0; 
$summary = ['total_laporan' => 0, 'total_luas' => 0]; 

// --- KONFIGURASI DAN KONEKSI DATABASE ---
$db_host    = 'localhost';
$db_user    = 'root';
$db_pass    = 'admin';
$db_name    = 'agridata_aceh';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// =====================================================================
// 1. Ambil Daftar Semua Petani untuk Filter
// =====================================================================
$sql_farmers = "SELECT id, nama FROM users WHERE role = 'petani' ORDER BY nama ASC";
$result_farmers = $conn->query($sql_farmers);

if ($result_farmers) {
    while ($row = $result_farmers->fetch_assoc()) {
        $farmers[] = $row;
    }
    $result_farmers->free();
} else {
    error_log("Query Error (Farmers): " . $conn->error);
}

// =====================================================================
// 2. LOGIKA PENGAMBILAN DATA LAPORAN (READ) - HANYA DATA DITERIMA
// =====================================================================

// Cek filter
if (isset($_GET['petani']) && is_numeric($_GET['petani'])) {
    $selected_farmer_id = intval($_GET['petani']);
}

// Query Utama
$sql_read = "SELECT 
                dp.id_panen AS id, 
                dp.komoditas, 
                dp.jumlah_panen_kg AS jumlah_panen,
                dp.tanggal_panen AS tanggal_input, 
                u.nama AS nama_petani,
                u.created_at AS tanggal_daftar_petani 
              FROM panen dp
              JOIN users u ON dp.user_id = u.id 
              WHERE dp.status = 'Diterima' ";

$params = [];
$types = '';

if ($selected_farmer_id > 0) {
    $sql_read .= " AND dp.user_id = ?";
    $types .= 'i';
    $params[] = &$selected_farmer_id; 
}

$sql_read .= " ORDER BY dp.tanggal_panen DESC";

// Jalankan prepared statement
$stmt = $conn->prepare($sql_read);

if ($stmt === false) {
    error_log("Prepare Error (Reports): " . $conn->error);
    $conn->close();
    die("Kesalahan internal database.");
}

if ($selected_farmer_id > 0) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $total_berat_panen = 0; 
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
        $total_berat_panen += floatval($row['jumlah_panen']); 
    }
    $result->free();

    // Hitung ringkasan
    $summary['total_laporan'] = count($reports);
    $summary['total_luas'] = number_format($total_berat_panen, 2, ',', '.') . ' Kg'; 
} else {
    error_log("Query Error (Reports): " . $stmt->error);
}
$stmt->close();
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        
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
        .action-bar { margin-bottom: 20px; display: flex; justify-content: flex-start; align-items: center; gap: 15px; } 
        .btn-primary { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: background-color 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary:hover { background-color: #45a049; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { padding: 15px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .report-table th { background-color: #f8f9fa; color: #2c3e50; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }
        .report-table tbody tr:hover { background-color: #f2f4f6; }
        
        .summary-cards { display: flex; gap: 20px; margin-bottom: 30px; }
        .card { flex: 1; background: #f3f9f3; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 5px solid #4CAF50; }
        .card h4 { margin-top: 0; color: #1e7e34; font-size: 1rem; font-weight: 500; }
        .card p { font-size: 2rem; font-weight: 700; color: #2c3e50; margin: 5px 0 0; }
        
        .filter-group { display: flex; align-items: center; gap: 10px; }
        .filter-group select { padding: 10px; border: 1px solid #ccc; border-radius: 8px; }

        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .content { margin-left: 70px; padding: 15px; }
            .brand-logo { width: 50px; height: 50px; }
            .brand h2, .brand p, .nav-links span { display: none; }
            .summary-cards { flex-direction: column; }
            .report-table thead { display: none; }
            .report-table, .report-table tbody, .report-table tr, .report-table td { display: block; width: 100%; }
            .report-table tr { margin-bottom: 15px; border: 1px solid #ecf0f1; border-radius: 8px; box-shadow: 0 1px 5px rgba(0,0,0,0.05); }
            .report-table td { text-align: right; padding-left: 50%; position: relative; border: none; border-bottom: 1px dashed #ecf0f1; }
            .report-table td:last-child { border-bottom: none; }
            .report-table td::before { content: attr(data-label); position: absolute; left: 0; width: 45%; padding-left: 15px; font-weight: 600; color: #7f8c8d; text-align: left; }
        }
        .btn-action { background: none; border: 1px solid #ccc; color: #3498db; padding: 5px 10px; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; }
        .btn-action:hover { background-color: #ecf0f1; }
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
            <li><a href="dashboard_admin.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a></li>
            <li><a href="kelola_pengguna.php">
                <i class="fas fa-users"></i>
                <span>Pengguna</span>
            </a></li>
            <li><a href="verifikasi.php"> 
                <i class="fas fa-check-double"></i>
                <span>Verifikasi Panen</span>
            </a></li>
            <li><a href="laporan.php" class="active">
                <i class="fas fa-chart-bar"></i>
                <span>Laporan Analisis</span>
            </a></li>
            <li><a href="geografis.php">
                <i class="fas fa-map"></i>
                <span>Geografis</span>
            </a></li>
            <li><a href="pengaturan.php">
                <i class="fas fa-cog"></i>
                <span>Pengaturan</span>
            </a></li>
            <li style="margin-top: 20px;"><a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a></li>
        </ul>
        </div>

    <div class="content">
        <div class="header">
            <h1>Laporan Data Pertanian 📊</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>Administrator
            </div>
        </div>

        <div class="page-content">
            
            <div class="summary-cards">
                <div class="card">
                    <h4>Total Data Panen Diterima</h4>
                    <p><?php echo htmlspecialchars($summary['total_laporan']); ?></p>
                </div>
                <div class="card">
                    <h4>Total Jumlah Panen (Kg)</h4>
                    <p><?php echo htmlspecialchars($summary['total_luas']); ?></p>
                </div>
                <div class="card">
                    <h4>Filter Petani</h4>
                    <p style="font-size: 1.5rem;">
                        <?php 
                            if ($selected_farmer_id > 0) {
                                $selected_name = array_filter($farmers, function($f) use ($selected_farmer_id) {
                                    return $f['id'] == $selected_farmer_id;
                                });
                                echo htmlspecialchars(reset($selected_name)['nama'] ?? 'Petani Tidak Ditemukan');
                            } else {
                                echo "**Semua Petani**";
                            }
                        ?>
                    </p>
                </div>
            </div>

            <div class="action-bar">
                <form method="GET" action="laporan.php" class="filter-group">
                    <label for="petani"><i class="fas fa-filter"></i> Filter Petani:</label>
                    <select name="petani" id="petani" onchange="this.form.submit()">
                        <option value="0">--- Semua Petani ---</option>
                        <?php foreach ($farmers as $farmer): ?>
                            <option value="<?php echo htmlspecialchars($farmer['id']); ?>" 
                                <?php echo ($farmer['id'] == $selected_farmer_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($farmer['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <a href="ekspor_pdf.php?petani=<?php echo htmlspecialchars($selected_farmer_id); ?>" 
                    class="btn-primary" 
                    target="_blank">
                    <i class="fas fa-file-pdf"></i> Unduh Laporan PDF
                </a>
            </div>

            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>ID Panen</th>
                            <th>Tanggal Panen</th>
                            <th>Petani</th>
                            <th>Tgl. Daftar Petani</th>
                            <th>Komoditas</th>
                            <th>Jumlah Panen (Kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($reports)): ?>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td data-label="ID Panen"><?php echo htmlspecialchars($report['id']); ?></td>
                                <td data-label="Tanggal Panen"><?php echo date('d-m-Y', strtotime(htmlspecialchars($report['tanggal_input']))); ?></td>
                                <td data-label="Petani"><?php echo htmlspecialchars($report['nama_petani']); ?></td>
                                <td data-label="Tgl. Daftar Petani"><?php echo date('d-m-Y', strtotime(htmlspecialchars($report['tanggal_daftar_petani']))); ?></td>
                                <td data-label="Komoditas"><?php echo htmlspecialchars($report['komoditas']); ?></td>
                                <td data-label="Jumlah Panen (Kg)"><?php echo htmlspecialchars($report['jumlah_panen']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #7f8c8d;">Tidak ada data laporan **Diterima** ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>