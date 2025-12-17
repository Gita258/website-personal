<?php
// =====================================================================
// laporan_panen.php - Menampilkan Laporan Data Panen
// =====================================================================
session_start();

// 1. CEK OTORISASI
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['petani', 'petugas'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$data_panen = [];
$message = ''; 

// 2. KONFIGURASI DAN KONEKSI DATABASE (PDO)
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'admin',
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
    error_log("Database Connection Error: " . $e->getMessage());
    $message = '<div class="alert alert-danger" role="alert">Koneksi database gagal.</div>';
}

// 3. LOGIKA PENGAMBILAN DATA PANEN BERDASARKAN PERAN
try {
    // PETANI: Hanya tampilkan data miliknya
    if ($user_role === 'petani') {
        $sql = "
            SELECT 
                p.id_panen, 
                p.komoditas, 
                p.tanggal_panen, 
                p.jumlah_panen_kg, 
                p.lokasi_desa,
                p.status,
                p.submitted_at
            FROM panen p
            WHERE p.user_id = :user_id
            ORDER BY p.submitted_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $data_panen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $laporan_title = "Laporan Data Panen Saya";

    // PETUGAS: Tampilkan semua data dari semua petani (JOIN untuk menampilkan nama petani)
    } elseif ($user_role === 'petugas') {
        $sql = "
            SELECT 
                p.id_panen, 
                p.komoditas, 
                p.tanggal_panen, 
                p.jumlah_panen_kg, 
                p.lokasi_desa,
                p.status,
                p.submitted_at,
                u.nama AS nama_petani
            FROM panen p
            JOIN users u ON p.user_id = u.id
            ORDER BY p.submitted_at DESC
        ";
        $stmt = $db->query($sql);
        $data_panen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $laporan_title = "Data Panen Seluruh Petani";
    }

} catch (Exception $e) {
    error_log("Data Panen Fetch Error: " . $e->getMessage());
    $message = '<div class="alert alert-danger" role="alert">Gagal memuat data panen: ' . $e->getMessage() . '</div>';
    $data_panen = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Panen | AgriData Aceh</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> 
    
    <style>
        :root {
            --primary: #28a745;
            --primary-dark: #1e7e34;
            --primary-light: #d4edda;
            --secondary: #6c757d;
            --background: #f8f9fa;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --hover-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background); overflow-x: hidden; }

        /* SIDEBAR */
        .sidebar {
            position: fixed; top: 0; left: 0; height: 100vh; width: 260px;
            background: linear-gradient(180deg, var(--primary-dark) 0%, #155724 100%);
            color: white; padding: 25px 0; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 25px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .sidebar-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            background: #28a745;
            border-radius: 50%;
            padding: 8px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }

        .sidebar-header h4 {
            font-weight: 700;
            font-size: 1.4rem;
            margin: 0;
        }

        .sidebar-header p {
            font-size: 0.85rem;
            opacity: 0.8;
            margin: 5px 0 0 0;
        }

        .nav-menu { padding: 0; margin: 0; list-style: none; }
        .nav-menu li a { 
            display: flex; align-items: center; gap: 15px; padding: 15px 25px; 
            color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s ease; 
            font-size: 0.95rem; position: relative; 
        }
        .nav-menu li a.active, .nav-menu li a:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-menu li a.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 4px; background: white; transform: scaleY(1); }
        .nav-menu li a i { width: 20px; text-align: center; font-size: 1.1rem; }
        .nav-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 20px 25px; }

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }

        /* HEADER */
        .page-header {
            background: white; padding: 25px 30px; border-radius: 15px;
            box-shadow: var(--card-shadow); margin-bottom: 30px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;
        }
        .page-header h2 { color: var(--primary-dark); font-weight: 700; font-size: 1.8rem; margin: 0; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { 
            width: 45px; height: 45px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 1.2rem;
        }
        .user-details h6 { margin: 0; font-weight: 600; color: #333; }
        .user-details p { margin: 0; font-size: 0.85rem; color: var(--secondary); }

        /* REPORT CARD */
        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            padding: 30px;
            overflow-x: auto;
        }
        
        .table thead th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        /* Status Badges */
        .badge-pending { background-color: #ffc107; color: #333; }
        .badge-diterima { background-color: var(--primary); color: white; }
        .badge-ditolak { background-color: #dc3545; color: white; }
        
        /* Responsive CSS */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 15px; }
            .page-header { padding: 20px; }
            .page-header h2 { font-size: 1.5rem; }
            .menu-toggle { display: block; }
            .sidebar-logo { width: 70px; height: 70px; }
        }
        .menu-toggle { display: none; position: fixed; top: 20px; left: 20px; z-index: 1001; background: var(--primary); color: white; border: none; width: 45px; height: 45px; border-radius: 10px; cursor: pointer; box-shadow: var(--card-shadow); }
    </style>
</head>
<body>

<button class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="logo.png" alt="AgriData Aceh Logo" class="sidebar-logo">
        <h4>AgriData Aceh</h4>
        <p>Sistem Pertanian Modern</p>
    </div>
    
    <ul class="nav-menu">
        <li><a href="dashboard_petani.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <?php if ($user_role === 'petani'): ?>
            <li><a href="input_panen.php"><i class="fas fa-tractor"></i> Input Panen</a></li>
            <li><a href="laporan_panen.php" class="active"><i class="fas fa-file-alt"></i> Laporan Panen</a></li>
        <?php elseif ($user_role === 'petugas'): ?>
            <li><a href="input_panen.php"><i class="fas fa-tractor"></i> Input Data</a></li>
            <li><a href="laporan_panen.php" class="active"><i class="fas fa-file-alt"></i> Verifikasi Panen</a></li>
        <?php endif; ?>
    </ul>
    
    <div class="nav-divider"></div>
    
    <ul class="nav-menu">
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="main-content">
    
    <div class="page-header">
        <div>
            <h2><?php echo htmlspecialchars($laporan_title); ?></h2>
            <p class="text-muted mb-0">Riwayat data panen yang telah dikirim.</p>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="user-details">
                <h6><?php echo htmlspecialchars($user_name); ?></h6>
                <p><i class="fas fa-user-tag mr-1"></i> <?php echo ucwords($user_role); ?></p>
            </div>
        </div>
    </div>

    <div class="report-card">
        <div class="mb-4">
            <?php echo $message; ?>
            
            <?php if ($user_role === 'petugas'): ?>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle mr-1"></i> Sebagai Petugas, Anda dapat melakukan verifikasi (Aksi) pada data di bawah ini.
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($data_panen)): ?>
            <div class="alert alert-warning text-center" role="alert">
                <i class="fas fa-exclamation-triangle mr-1"></i> Belum ada data panen yang tercatat.
            </div>
        <?php else: ?>

            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if ($user_role === 'petugas'): ?>
                            <th>Petani</th>
                        <?php endif; ?>
                        <th>Komoditas</th>
                        <th>Tanggal Panen</th>
                        <th>Berat (Kg)</th>
                        <th>Lokasi</th>
                        <th>Tgl. Submit</th>
                        <th>Status</th>
                        <?php if ($user_role === 'petugas'): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($data_panen as $panen): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <?php if ($user_role === 'petugas'): ?>
                                <td><?php echo htmlspecialchars($panen['nama_petani']); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($panen['komoditas']); ?></td>
                            <td><?php echo date('d M Y', strtotime($panen['tanggal_panen'])); ?></td>
                            <td><?php echo number_format($panen['jumlah_panen_kg'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($panen['lokasi_desa']); ?></td>
                            <td><?php echo date('d M Y H:i', strtotime($panen['submitted_at'])); ?></td>
                            <td>
                                <?php
                                    $status = htmlspecialchars($panen['status']);
                                    $badge_class = '';
                                    if ($status === 'Pending') {
                                        $badge_class = 'badge-pending';
                                    } elseif ($status === 'Diterima') {
                                        $badge_class = 'badge-diterima';
                                    } elseif ($status === 'Ditolak') {
                                        $badge_class = 'badge-ditolak';
                                    }
                                    echo "<span class='badge {$badge_class}'>{$status}</span>";
                                ?>
                            </td>
                            <?php if ($user_role === 'petugas'): ?>
                                <td>
                                    <?php if ($status === 'Pending'): ?>
                                        <form method="POST" action="verifikasi_panen.php" class="d-inline">
                                            <input type="hidden" name="id_panen" value="<?php echo $panen['id_panen']; ?>">
                                            <button type="submit" name="action" value="terima" class="btn btn-sm btn-success" title="Terima Data">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="submit" name="action" value="tolak" class="btn btn-sm btn-danger" title="Tolak Data">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <i class="fas fa-info-circle text-muted" title="Sudah diverifikasi"></i>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
        
    </div>
    
</div>

<script>
// Toggle Sidebar (Mobile)
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}
// Close sidebar when clicking outside (mobile)
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>