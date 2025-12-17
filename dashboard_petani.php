<?php
// =====================================================================
// dashboard_petani.php - Dashboard Modern untuk Petani
// Disesuaikan agar menggunakan kolom 'jumlah_panen_kg'
// =====================================================================
session_start();

// 1. CEK OTORISASI
// Role 'petani' atau 'petugas' biasanya memiliki akses ke input panen.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['petani', 'petugas'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Pengguna'; // Default jika nama tidak ada
$user_role = $_SESSION['user_role'] ?? 'petani'; // Default role

// 2. KONFIGURASI DATABASE
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'admin', // Sesuaikan
    'name' => 'agridata_aceh'
];

// 3. KONEKSI DATABASE (Menggunakan PDO)
try {
    $db = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Koneksi database gagal. Silakan hubungi administrator.");
}

// 4. AMBIL DATA RINGKASAN
$ringkasan = [
    'total_panen_tahun_ini' => 0,
    'komoditas_terbanyak' => 'Belum Ada Data',
    'jumlah_panen_bulan_ini' => 0,
    'total_berat_bulan_ini' => 0,
    'rata_rata_panen' => 0
];
$data_grafik_json = '[]';
$aktivitas_terbaru = [];

try {
    $tahun_sekarang = date('Y');
    $bulan_sekarang = date('Y-m');
    
    // A. Total Panen Tahun Ini (Kg)
    $stmt_total = $db->prepare("
        SELECT COALESCE(SUM(jumlah_panen_kg), 0) as total 
        FROM panen 
        WHERE user_id = :user_id AND YEAR(tanggal_panen) = :tahun
    ");
    $stmt_total->execute([':user_id' => $user_id, ':tahun' => $tahun_sekarang]);
    $ringkasan['total_panen_tahun_ini'] = round((float)($stmt_total->fetchColumn()), 2);

    // B. Komoditas Terbanyak (Kg)
    $stmt_komoditas = $db->prepare("
        SELECT komoditas, SUM(jumlah_panen_kg) as total 
        FROM panen 
        WHERE user_id = :user_id 
        GROUP BY komoditas 
        ORDER BY total DESC 
        LIMIT 1
    ");
    $stmt_komoditas->execute([':user_id' => $user_id]);
    $result_komoditas = $stmt_komoditas->fetch();
    if ($result_komoditas) {
        $ringkasan['komoditas_terbanyak'] = htmlspecialchars($result_komoditas['komoditas']);
    }

    // C & D. Jumlah & Rata-rata Panen Bulan Ini (Kg)
    $stmt_bulan = $db->prepare("
        SELECT COUNT(id_panen) as jumlah, COALESCE(SUM(jumlah_panen_kg), 0) as total
        FROM panen 
        WHERE user_id = :user_id AND DATE_FORMAT(tanggal_panen, '%Y-%m') = :bulan
    ");
    $stmt_bulan->execute([':user_id' => $user_id, ':bulan' => $bulan_sekarang]);
    $result_bulan = $stmt_bulan->fetch();
    
    $ringkasan['jumlah_panen_bulan_ini'] = (int)($result_bulan['jumlah']);
    $ringkasan['total_berat_bulan_ini'] = (float)($result_bulan['total']);
    
    if ($ringkasan['jumlah_panen_bulan_ini'] > 0) {
        $ringkasan['rata_rata_panen'] = round($ringkasan['total_berat_bulan_ini'] / $ringkasan['jumlah_panen_bulan_ini'], 2);
    }
    
    // E. Data Grafik (Kg per Bulan)
    $stmt_grafik = $db->prepare("
        SELECT 
            MONTH(tanggal_panen) as bulan, 
            COALESCE(SUM(jumlah_panen_kg), 0) as total 
        FROM panen 
        WHERE user_id = :user_id AND YEAR(tanggal_panen) = :tahun
        GROUP BY bulan
        ORDER BY bulan ASC
    ");
    $stmt_grafik->execute([':user_id' => $user_id, ':tahun' => $tahun_sekarang]);
    $data_grafik_raw = $stmt_grafik->fetchAll();

    $data_grafik = [];
    $nama_bulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
    
    foreach ($data_grafik_raw as $row) {
        $data_grafik[] = [
            'month' => $nama_bulan[(int)$row['bulan']],
            'value' => round((float)$row['total'], 2)
        ];
    }
    $data_grafik_json = json_encode($data_grafik);

    // F. Aktivitas Terbaru (MENGGUNAKAN jumlah_panen_kg)
    $stmt_aktivitas = $db->prepare("
        SELECT komoditas, jumlah_panen_kg, tanggal_panen 
        FROM panen 
        WHERE user_id = :user_id 
        ORDER BY tanggal_panen DESC 
        LIMIT 5
    ");
    $stmt_aktivitas->execute([':user_id' => $user_id]);
    $aktivitas_terbaru = $stmt_aktivitas->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard Data Fetch Error: " . $e->getMessage());
    // Fallback: Jika terjadi error, pastikan variabel tetap diinisialisasi
}

// Helper function untuk format tanggal Indonesia
function formatTanggalIndo($tanggal) {
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    // Gunakan fungsi PHP untuk parsing tanggal
    try {
        $date = new DateTime($tanggal);
        return $date->format('d') . ' ' . $bulan[(int)$date->format('m')] . ' ' . $date->format('Y');
    } catch (Exception $e) {
        return $tanggal; // Fallback
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Petani | AgriData Aceh</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        /* ... (CSS Anda yang sudah bagus) ... */
        :root {
            --primary: #28a745;
            --primary-dark: #1e7e34;
            --primary-light: #d4edda;
            --secondary: #6c757d;
            --background: #f8f9fa;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --hover-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background);
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, var(--primary-dark) 0%, #155724 100%);
            color: white;
            padding: 25px 0;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: all 0.3s ease;
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

        .nav-menu {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .nav-menu li a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            position: relative;
        }

        .nav-menu li a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-menu li a.active::before {
            transform: scaleY(1);
        }

        .nav-menu li a i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 20px 25px;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }

        /* HEADER */
        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h2 {
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 1.8rem;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }

        .user-details p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--secondary);
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(40, 167, 69, 0.2) 100%);
            color: var(--primary-dark);
        }

        .stat-card h6 {
            color: var(--secondary);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .stat-card h2 {
            color: var(--primary-dark);
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: var(--secondary);
            font-size: 0.85rem;
            margin: 0;
        }

        /* CHART SECTION */
        .chart-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--background);
        }

        .chart-header h4 {
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chart-header i {
            color: var(--primary);
        }

        /* ACTIVITY SECTION */
        .activity-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--background);
        }

        .activity-header h4 {
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0;
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 10px;
            transition: background 0.3s ease;
            margin-bottom: 10px;
        }

        .activity-item:hover {
            background: var(--background);
        }

        .activity-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }

        .activity-details {
            flex: 1;
        }

        .activity-details h6 {
            margin: 0 0 5px 0;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .activity-details p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.85rem;
        }

        .activity-value {
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 1.1rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 15px;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .chart-section,
            .activity-section {
                padding: 20px;
            }

            .sidebar-logo {
                width: 70px;
                height: 70px;
            }
        }

        /* MOBILE MENU TOGGLE */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: var(--card-shadow);
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
        }
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
        <li><a href="dashboard_petani.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="input_panen.php"><i class="fas fa-tractor"></i> Input Panen</a></li>
        <li><a href="laporan_panen.php"><i class="fas fa-file-alt"></i> Laporan Panen</a></li>
    </ul>
    
    <div class="nav-divider"></div>
    
    <ul class="nav-menu">
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="main-content">
    
    <div class="page-header">
        <div>
            <h2>Dashboard <?php echo ucwords($user_role); ?></h2>
            <p class="text-muted mb-0">Selamat datang kembali! Berikut ringkasan data panen Anda.</p>
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

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-balance-scale"></i>
            </div>
            <h6>Total Panen Tahun Ini</h6>
            <h2><?php echo number_format($ringkasan['total_panen_tahun_ini'], 0, ',', '.'); ?> <small style="font-size: 0.5em;">Kg</small></h2>
            <p>Tahun <?php echo date('Y'); ?></p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-seedling"></i>
            </div>
            <h6>Komoditas Utama</h6>
            <h2 style="font-size: 1.5rem;"><?php echo $ringkasan['komoditas_terbanyak']; ?></h2>
            <p>Komoditas dengan total terbanyak</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h6>Entri Panen Bulan Ini</h6>
            <h2><?php echo number_format($ringkasan['jumlah_panen_bulan_ini'], 0, ',', '.'); ?> <small style="font-size: 0.5em;">kali</small></h2>
            <p>Total <?php echo number_format($ringkasan['total_berat_bulan_ini'], 0, ',', '.'); ?> Kg</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h6>Rata-rata Panen</h6>
            <h2><?php echo number_format($ringkasan['rata_rata_panen'], 0, ',', '.'); ?> <small style="font-size: 0.5em;">Kg</small></h2>
            <p>Per entri bulan ini</p>
        </div>
    </div>
    <div class="chart-section">
        <div class="chart-header">
            <h4><i class="fas fa-chart-area"></i> Tren Panen Bulanan</h4>
            <span class="badge badge-success">Tahun <?php echo date('Y'); ?></span>
        </div>
        
        <canvas id="panenChart" height="80"></canvas>
    </div>
    <div class="activity-section">
        <div class="activity-header">
            <h4><i class="fas fa-history mr-2"></i> Aktivitas Terbaru</h4>
            <a href="laporan_panen.php" class="btn btn-sm btn-outline-success">Lihat Semua</a>
        </div>
        
        <?php if (count($aktivitas_terbaru) > 0): ?>
            <ul class="activity-list">
                <?php foreach ($aktivitas_terbaru as $item): ?>
                    <li class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-tractor"></i>
                        </div>
                        <div class="activity-details">
                            <h6><?php echo htmlspecialchars($item['komoditas']); ?></h6>
                            <p><i class="far fa-calendar mr-1"></i> <?php echo formatTanggalIndo($item['tanggal_panen']); ?></p>
                        </div>
                        <div class="activity-value">
                            <?php echo number_format($item['jumlah_panen_kg'], 0, ',', '.'); ?> Kg
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h5>Belum Ada Aktivitas</h5>
                <p>Mulai input data panen Anda untuk melihat aktivitas di sini.</p>
                <a href="input_panen.php" class="btn btn-success mt-3">
                    <i class="fas fa-plus mr-2"></i>Input Panen Sekarang
                </a>
            </div>
        <?php endif; ?>
    </div>
    </div>

<script>
// Toggle Sidebar (Mobile)
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Chart Data
const dataPanen = <?php echo $data_grafik_json; ?>;

// Initialize all months
const allMonths = {
    'Jan': 0, 'Feb': 0, 'Mar': 0, 'Apr': 0, 'Mei': 0, 'Jun': 0, 
    'Jul': 0, 'Agu': 0, 'Sep': 0, 'Okt': 0, 'Nov': 0, 'Des': 0
};

// Fill existing data and sort by month index
dataPanen.forEach(item => {
    allMonths[item.month] = item.value;
});

const labels = Object.keys(allMonths);
const values = Object.values(allMonths);

// Create Chart
const ctx = document.getElementById('panenChart').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(40, 167, 69, 0.3)');
gradient.addColorStop(1, 'rgba(40, 167, 69, 0.05)');

const panenChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Berat Panen (Kg)',
            data: values,
            backgroundColor: gradient,
            borderColor: '#28a745',
            borderWidth: 3,
            pointBackgroundColor: '#1e7e34',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: {
                    size: 14,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 13
                },
                callbacks: {
                    label: function(context) {
                        // Menggunakan toLocaleString untuk format angka Indonesia (misal: 1.000)
                        return ' ' + context.parsed.y.toLocaleString('id-ID') + ' Kg';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)',
                    drawBorder: false
                },
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('id-ID') + ' Kg';
                    },
                    font: {
                        size: 12
                    }
                }
            },
            x: {
                grid: {
                    display: false,
                    drawBorder: false
                },
                ticks: {
                    font: {
                        size: 12
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>