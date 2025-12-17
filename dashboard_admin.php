<?php
// =====================================================================
// dashboard_admin.php - Dashboard Administrator AgriData Aceh
// =====================================================================

session_start();

// =====================================================================
// AUTENTIKASI DAN OTORISASI
// =====================================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Cek apakah user adalah admin
if ($_SESSION['user_role'] !== 'admin') {
    // Redirect ke dashboard yang sesuai dengan role
    if ($_SESSION['user_role'] === 'petani' || $_SESSION['user_role'] === 'petugas') {
        header("Location: dashboard_petani.php");
    } else {
        header("Location: login.php");
    }
    exit;
}

$username = $_SESSION['user_name'] ?? 'Admin';
$title = "Dashboard Admin - AgriData Aceh";

// =====================================================================
// KONEKSI DATABASE
// =====================================================================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'admin';
$db_name = 'agridata_aceh';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// =====================================================================
// PENGAMBILAN DATA STATISTIK
// =====================================================================

// Inisialisasi variabel statistik
$total_petani = 0;
$total_data_panen = 0;
$total_lokasi = 0;
$total_volume_ton = 0;
$total_pending = 0;

// Query 1: Total Pengguna (Petani & Petugas)
$sql_users = "SELECT COUNT(id) AS total_users FROM users WHERE role IN ('petani', 'petugas')";
$result_users = $conn->query($sql_users);
if ($result_users && $row = $result_users->fetch_assoc()) {
    $total_petani = number_format($row['total_users']);
}

// Query 2 & 4: Total Data Panen, Volume, dan Pending
$sql_panen = "
    SELECT 
        COUNT(id_panen) AS total_panen, 
        SUM(jumlah_panen_kg) AS total_volume,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS total_pending
    FROM panen
    WHERE status IN ('Diterima', 'Pending')
";

$result_panen = $conn->query($sql_panen);
if ($result_panen && $row = $result_panen->fetch_assoc()) {
    $total_data_panen = number_format($row['total_panen']);
    $total_pending = number_format($row['total_pending']);
    
    $volume_kg = $row['total_volume'] ?? 0;
    $total_volume_ton = number_format($volume_kg / 1000, 2, ',', '.');
}

// Query 3: Total Lokasi (Kecamatan Unik)
$sql_lokasi = "
    SELECT COUNT(DISTINCT lokasi_kecamatan) AS total_lokasi 
    FROM panen 
    WHERE lokasi_kecamatan IS NOT NULL AND lokasi_kecamatan != ''
";
$result_lokasi = $conn->query($sql_lokasi);
if ($result_lokasi && $row = $result_lokasi->fetch_assoc()) {
    $total_lokasi = number_format($row['total_lokasi']);
}

// Struktur data statistik untuk tampilan kartu
$admin_stats = [
    [
        'label' => 'Total Pengguna Aktif',
        'value' => $total_petani,
        'icon' => 'fa-users',
        'color' => '#4CAF50'
    ],
    [
        'label' => 'Data Menunggu Verifikasi',
        'value' => $total_pending,
        'icon' => 'fa-clock',
        'color' => '#FFC107'
    ],
    [
        'label' => 'Total Data Panen',
        'value' => $total_data_panen,
        'icon' => 'fa-clipboard-list',
        'color' => '#FF9800'
    ],
    [
        'label' => 'Total Volume (Ton)',
        'value' => $total_volume_ton,
        'icon' => 'fa-truck-loading',
        'color' => '#9C27B0'
    ]
];

// =====================================================================
// PENGAMBILAN DATA AKTIVITAS TERBARU (5 DATA TERBARU)
// =====================================================================

$recent_activities = [];
$sql_activity = "
    SELECT 
        p.submitted_at, 
        p.komoditas, 
        p.jumlah_panen_kg,
        p.lokasi_desa,
        p.lokasi_kecamatan,
        p.status
    FROM panen p
    ORDER BY p.submitted_at DESC
    LIMIT 5
";

$result_activity = $conn->query($sql_activity);

if ($result_activity && $result_activity->num_rows > 0) {
    while ($row = $result_activity->fetch_assoc()) {
        $timestamp = strtotime($row['submitted_at']);
        $time_format = date('d M Y H:i', $timestamp);
        
        // Ikon status
        $status_icon = match ($row['status']) {
            'Pending' => '<i class="fas fa-hourglass-half text-warning"></i>',
            'Diterima' => '<i class="fas fa-check-circle text-success"></i>',
            default => '<i class="fas fa-times-circle text-danger"></i>',
        };
        
        // Format lokasi
        $lokasi_tampil = htmlspecialchars($row['lokasi_desa'] ?? '');
        
        if (!empty($row['lokasi_kecamatan'])) {
            $lokasi_tampil .= ' (Kec. ' . htmlspecialchars($row['lokasi_kecamatan']) . ')';
        }
        
        if (empty($row['lokasi_desa']) && empty($row['lokasi_kecamatan'])) {
            $lokasi_tampil = 'Lokasi Tidak Diketahui';
        } elseif (empty($row['lokasi_desa'])) {
            $lokasi_tampil = '(Kec. ' . htmlspecialchars($row['lokasi_kecamatan']) . ')';
        }

        $activity_text = sprintf(
            "%s Panen **%s** (%.2f Kg) di %s",
            $status_icon,
            htmlspecialchars($row['komoditas']),
            $row['jumlah_panen_kg'],
            $lokasi_tampil
        );

        $recent_activities[] = [
            'time' => $time_format,
            'icon' => 'fa-seedling',
            'activity' => $activity_text
        ];
    }
}

// =====================================================================
// PENGAMBILAN DATA TREN BULANAN (Untuk Chart.js)
// =====================================================================

$trend_data = [
    'labels' => [],
    'data' => []
];

$sql_trend = "
    SELECT 
        DATE_FORMAT(tanggal_panen, '%Y-%m') AS month_year,
        SUM(jumlah_panen_kg) AS monthly_volume
    FROM panen
    WHERE status = 'Diterima' AND tanggal_panen IS NOT NULL
    GROUP BY month_year
    ORDER BY month_year DESC
    LIMIT 6
";

$result_trend = $conn->query($sql_trend);

if ($result_trend && $result_trend->num_rows > 0) {
    $rows = [];
    while ($row = $result_trend->fetch_assoc()) {
        $rows[] = $row;
    }
    $rows = array_reverse($rows);
    
    $bulan_indo = [
        '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
        '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
        '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
    ];
    
    foreach ($rows as $row) {
        $parts = explode('-', $row['month_year']);
        $label = $bulan_indo[$parts[1]] . ' ' . substr($parts[0], 2);
        
        $trend_data['labels'][] = $label;
        $trend_data['data'][] = (float)$row['monthly_volume'] / 1000;
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    
    <style>
        /* ============================================================= */
        /* RESET & BASE */
        /* ============================================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        /* ============================================================= */
        /* SIDEBAR */
        /* ============================================================= */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #1e7e34 0%, #155724 100%);
            padding: 20px 0;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .brand {
            padding: 0 25px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .brand-logo {
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
        
        .brand h2 {
            color: white;
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
        }

        .brand p {
            color: rgba(255,255,255,0.8);
            font-size: 0.85rem;
            margin: 5px 0 0 0;
        }
        
        .nav-links {
            list-style: none;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 4px solid #4CAF50;
        }
        
        .nav-links i {
            width: 20px;
            font-size: 1.1rem;
        }
        
        /* ============================================================= */
        /* CONTENT AREA */
        /* ============================================================= */
        .content {
            margin-left: 260px;
            padding: 30px;
        }
        
        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.6rem;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .user-badge {
            background: linear-gradient(135deg, #95e989ff 0%, #4ba24bff 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* ============================================================= */
        /* STATISTICS CARDS */
        /* ============================================================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            background: var(--card-color);
            opacity: 0.9;
        }
        
        /* ============================================================= */
        /* CARDS (CHART & ACTIVITY) */
        /* ============================================================= */
        .cards-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header h3 {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .card-body canvas {
            height: 350px !important;
        }
        
        /* ============================================================= */
        /* CHART */
        /* ============================================================= */
        #volumeChart {
            max-height: 380px;
        }
        
        .chart-placeholder {
            height: 380px;
            background: linear-gradient(135deg, #f5f7fa 0%, #ecf0f1 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #95a5a6;
            font-size: 0.95rem;
            border: 2px dashed #bdc3c7;
        }
        
        /* ============================================================= */
        /* ACTIVITY LIST */
        /* ============================================================= */
        .activity-item {
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-left: 4px solid #4CAF50;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }
        
        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.15);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .activity-item:last-child {
            margin-bottom: 0;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #95a5a6;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        
        .activity-time::before {
            content: '🕐';
            font-size: 0.85rem;
        }
        
        .activity-text {
            color: #2c3e50;
            font-size: 0.9rem;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .activity-text i {
            margin-top: 2px;
            font-size: 1.1rem;
        }
        
        .activity-text strong {
            color: #1e7e34;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }
        
        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 15px;
            opacity: 0.4;
            color: #bdc3c7;
        }
        
        .empty-state p {
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        /* ============================================================= */
        /* UTILITY CLASSES */
        /* ============================================================= */
        .text-warning {
            color: #FFC107;
        }
        
        .text-success {
            color: #4CAF50;
        }
        
        .text-danger {
            color: #E53935;
        }
        
        /* ============================================================= */
        /* RESPONSIVE */
        /* ============================================================= */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .content {
                margin-left: 70px;
                padding: 15px;
            }
            
            .brand-logo {
                width: 50px;
                height: 50px;
            }

            .brand h2,
            .brand p,
            .nav-links span {
                display: none;
            }
            
            .cards-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- ============================================================= -->
    <!-- SIDEBAR -->
    <!-- ============================================================= -->
    <div class="sidebar">
        <div class="brand">
            <img src="logo.png" alt="AgriData Aceh Logo" class="brand-logo">
            <h2>AgriData Aceh</h2>
            <p>Admin Dashboard</p>
        </div>
        
        <ul class="nav-links">
            <li>
                <a href="dashboard_admin.php" class="active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="kelola_pengguna.php">
                    <i class="fas fa-users"></i>
                    <span>Pengguna</span>
                </a>
            </li>
            <li>
                <a href="verifikasi.php">
                    <i class="fas fa-check-double"></i>
                    <span>Verifikasi Panen</span>
                </a>
            </li>
            <li>
                <a href="laporan.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Laporan Analisis</span>
                </a>
            </li>
            <li>
                <a href="geografis.php">
                    <i class="fas fa-map"></i>
                    <span>Geografis</span>
                </a>
            </li>
            <li>
                <a href="pengaturan.php">
                    <i class="fas fa-cog"></i>
                    <span>Pengaturan</span>
                </a>
            </li>
            <li style="margin-top: 20px;">
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- ============================================================= -->
    <!-- CONTENT AREA -->
    <!-- ============================================================= -->
    <div class="content">
        <!-- Header -->
        <div class="header">
            <h1>Selamat Datang, <?php echo htmlspecialchars($username); ?>! 👋</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>
                Administrator
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <?php foreach ($admin_stats as $stat): ?>
            <div class="stat-card" style="--card-color: <?php echo $stat['color']; ?>">
                <div class="stat-info">
                    <h3><?php echo $stat['value']; ?></h3>
                    <p><?php echo $stat['label']; ?></p>
                </div>
                <div class="stat-icon" style="background: <?php echo $stat['color']; ?>">
                    <i class="fas <?php echo $stat['icon']; ?>"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Charts & Activity Row -->
        <div class="cards-row">
            <!-- Chart Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line" style="color: #2196F3;"></i>
                    <h3>Tren Volume Panen (Ton/Bulan)</h3>
                </div>
                <div class="card-body">
                    <canvas id="volumeChart"></canvas>
                </div>
            </div>

            <!-- Activity Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history" style="color: #FF9800;"></i>
                    <h3>Aktivitas Terbaru</h3>
                </div>
                <div class="card-body" style="max-height: 450px; overflow-y: auto;">
                    <?php if (empty($recent_activities)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Tidak ada aktivitas panen terbaru.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-time"><?php echo $activity['time']; ?></div>
                            <div class="activity-text">
                                <div>
                                <?php
                                $display_activity = str_replace(
                                    ['**', 'text-warning', 'text-success', 'text-danger'],
                                    ['<strong>', '<span style="color:#FFC107;">', '<span style="color:#4CAF50;">', '<span style="color:#E53935;">'],
                                    $activity['activity']
                                );
                                $display_activity .= '</span>';
                                echo str_replace('</strong>', '</strong></span>', $display_activity);
                                ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================================= -->
    <!-- JAVASCRIPT - CHART INITIALIZATION -->
    <!-- ============================================================= -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const trendData = <?php echo json_encode($trend_data); ?>;
        const volumeChartElement = document.getElementById('volumeChart');
        
        if (!volumeChartElement) return;

        if (trendData.data.length > 0) {
            const ctx = volumeChartElement.getContext('2d');
            
            // Gradient untuk area chart
            const gradient = ctx.createLinearGradient(0, 0, 0, 380);
            gradient.addColorStop(0, 'rgba(76, 175, 80, 0.4)');
            gradient.addColorStop(0.5, 'rgba(76, 175, 80, 0.2)');
            gradient.addColorStop(1, 'rgba(76, 175, 80, 0.05)');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trendData.labels,
                    datasets: [{
                        label: 'Volume Panen',
                        data: trendData.data,
                        backgroundColor: gradient,
                        borderColor: '#4CAF50',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#4CAF50',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointHoverBackgroundColor: '#4CAF50',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 12,
                                    family: "'Segoe UI', sans-serif"
                                },
                                color: '#7f8c8d',
                                callback: function(value) {
                                    return value.toFixed(1) + ' Ton';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 12,
                                    family: "'Segoe UI', sans-serif",
                                    weight: '500'
                                },
                                color: '#7f8c8d'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: {
                                    size: 13,
                                    family: "'Segoe UI', sans-serif",
                                    weight: '600'
                                },
                                color: '#2c3e50'
                            }
                        },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'rgba(44, 62, 80, 0.95)',
                            titleFont: {
                                size: 14,
                                weight: '600',
                                family: "'Segoe UI', sans-serif"
                            },
                            bodyFont: {
                                size: 13,
                                family: "'Segoe UI', sans-serif"
                            },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.parsed.y.toFixed(2) + ' Ton';
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 2000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        } else {
            const chartContainer = volumeChartElement.parentNode;
            chartContainer.innerHTML = '<div class="chart-placeholder" style="height: 380px;"><i class="fas fa-exclamation-circle"></i><span style="margin-left: 10px;">Tidak ada data panen yang Diterima untuk tren.</span></div>';
        }
    });
    </script>
</body>
</html>