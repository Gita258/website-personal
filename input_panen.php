<?php
// =====================================================================
// input_panen.php - Form Input Data Panen untuk Petani/Petugas (DENGAN GPS)
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
    $message = '<div class="alert alert-danger" role="alert"><i class="fas fa-database mr-1"></i> Koneksi database gagal.</div>';
}

// 3. PENGAMBILAN DATA MASTER KOMODITAS
$komoditas_list = [];
if (empty($message)) {
    try {
        $stmt_komoditas = $db->query("SELECT nama FROM komoditas ORDER BY nama ASC");
        $komoditas_list = $stmt_komoditas->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {
        error_log("Master Komoditas Data Fetch Error: " . $e->getMessage());
        $komoditas_list = ['Padi', 'Jagung', 'Kedelai', 'Sawit', 'Kopi']; 
    }
}

// Fungsi helper untuk memecah lokasi (Desa dan Kecamatan)
function parse_lokasi($lokasi_lengkap) {
    $desa = $lokasi_lengkap;
    $kecamatan = '';
    if (preg_match('/\((Kec\. (.+?))\)/', $lokasi_lengkap, $matches)) {
        $kecamatan = trim($matches[2]);
        $desa = trim(str_replace($matches[0], '', $lokasi_lengkap));
    }
    return ['desa' => $desa, 'kecamatan' => $kecamatan];
}

// 4. LOGIKA SUBMIT FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $komoditas = filter_input(INPUT_POST, 'komoditas', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $tanggal_panen = filter_input(INPUT_POST, 'tanggal_panen', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $jumlah_panen_kg = filter_input(INPUT_POST, 'jumlah_panen_kg', FILTER_VALIDATE_FLOAT);
    $lokasi_lengkap = filter_input(INPUT_POST, 'lokasi_desa', FILTER_SANITIZE_FULL_SPECIAL_CHARS); 
    $catatan = filter_input(INPUT_POST, 'catatan', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT); 
    $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT); 
    
    $parsed_lokasi = parse_lokasi($lokasi_lengkap);
    $lokasi_desa = $parsed_lokasi['desa'];
    $lokasi_kecamatan = $parsed_lokasi['kecamatan'];

    if (empty($komoditas) || empty($tanggal_panen) || $jumlah_panen_kg <= 0 || empty($lokasi_desa) || $latitude === false || $longitude === false) {
        $message = '<div class="alert alert-warning" role="alert"><i class="fas fa-exclamation-triangle mr-1"></i> Mohon lengkapi semua bidang wajib, Berat Panen harus > 0, dan **koordinat harus berhasil terambil dan berupa angka**.</div>';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO panen (
                    user_id, komoditas, tanggal_panen, jumlah_panen_kg, 
                    lokasi_desa, lokasi_kecamatan, latitude, longitude, 
                    catatan, status, submitted_at
                ) VALUES (
                    :user_id, :komoditas, :tanggal_panen, :jumlah_panen_kg, 
                    :lokasi_desa, :lokasi_kecamatan, :latitude, :longitude,
                    :catatan, 'Pending', NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $user_id,
                ':komoditas' => $komoditas,
                ':tanggal_panen' => $tanggal_panen,
                ':jumlah_panen_kg' => $jumlah_panen_kg,
                ':lokasi_desa' => $lokasi_desa,
                ':lokasi_kecamatan' => $lokasi_kecamatan,
                ':latitude' => $latitude,      
                ':longitude' => $longitude,    
                ':catatan' => $catatan
            ]);

            $message = '<div class="alert alert-success" role="alert">✅ **Data panen berhasil disimpan!** Menunggu verifikasi.</div>';
            $_POST = []; 

        } catch (PDOException $e) {
            error_log("Insert Panen Error: " . $e->getMessage());
            $message = '<div class="alert alert-danger" role="alert"><i class="fas fa-times-circle mr-1"></i> Gagal menyimpan data panen. **Error DB:** ' . htmlspecialchars($e->getMessage()) . '</div>'; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Panen | AgriData Aceh</title>
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

        /* FORM CARD */
        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            padding: 30px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
        }
        
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
        .gps-input { background-color: #f0f0f0; }
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
        <li><a href="input_panen.php" class="active"><i class="fas fa-tractor"></i> Input Panen</a></li>
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
            <h2>Input Data Panen</h2>
            <p class="text-muted mb-0">Masukkan data hasil panen Anda ke dalam sistem.</p>
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

    <div class="form-card">
        <div class="mb-4">
            <?php echo $message; ?>
        </div>
        
        <form method="POST" action="input_panen.php">
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="komoditas"><i class="fas fa-seedling mr-1"></i> Komoditas <span class="text-danger">*</span></label>
                    <select id="komoditas" name="komoditas" class="form-control" required>
                        <option value="" disabled selected>Pilih Komoditas</option>
                        <?php foreach ($komoditas_list as $komoditas_opt): ?>
                            <option value="<?php echo htmlspecialchars($komoditas_opt); ?>" 
                                <?php echo (isset($_POST['komoditas']) && $_POST['komoditas'] == $komoditas_opt) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($komoditas_opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-md-6">
                    <label for="tanggal_panen"><i class="fas fa-calendar-alt mr-1"></i> Tanggal Panen <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="tanggal_panen" name="tanggal_panen" required 
                           max="<?php echo date('Y-m-d'); ?>" 
                           value="<?php echo htmlspecialchars($_POST['tanggal_panen'] ?? date('Y-m-d')); ?>">
                    <small class="form-text text-muted">Tanggal tidak boleh lebih dari hari ini.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="jumlah_panen_kg"><i class="fas fa-balance-scale mr-1"></i> Berat Panen (Kg) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="jumlah_panen_kg" name="jumlah_panen_kg" placeholder="Mis: 1500.50" required 
                            value="<?php echo htmlspecialchars($_POST['jumlah_panen_kg'] ?? ''); ?>">
                </div>
                
                <div class="form-group col-md-6">
                    <label for="lokasi_desa"><i class="fas fa-map-marker-alt mr-1"></i> Desa/Lokasi Panen <span class="text-danger">*</span></label>
                    <select id="lokasi_desa" name="lokasi_desa" class="form-control" required disabled>
                        <option value="" selected>Pilih Komoditas dahulu</option>
                    </select>
                    <small class="form-text text-muted" id="lokasi-loading-msg">Pilih komoditas di atas untuk memuat daftar lokasi.</small>
                </div>
            </div>
            
            <hr>
            
            <h5 class="mb-3 text-success"><i class="fas fa-globe-asia mr-2"></i> Koordinat Lokasi Panen (Otomatis)</h5>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="latitude"><i class="fas fa-compass mr-1"></i> Latitude (Lintang) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control gps-input" id="latitude" name="latitude" readonly required placeholder="Otomatis terisi dari GPS">
                </div>
                <div class="form-group col-md-6">
                    <label for="longitude"><i class="fas fa-compass mr-1"></i> Longitude (Bujur) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control gps-input" id="longitude" name="longitude" readonly required placeholder="Otomatis terisi dari GPS">
                </div>
            </div>
            
            <div id="gps-status" class="alert alert-info d-flex align-items-center"><i class="fas fa-location-arrow mr-2"></i> <span>Menunggu lokasi dari GPS...</span></div>
            <hr>

            <div class="form-group">
                <label for="catatan"><i class="fas fa-sticky-note mr-1"></i> Catatan (Opsional)</label>
                <textarea class="form-control" id="catatan" name="catatan" rows="3" placeholder="Contoh: Panen ke-2 di lahan sawah bagian barat."><?php echo htmlspecialchars($_POST['catatan'] ?? ''); ?></textarea>
            </div>
            
            <hr>

            <button type="submit" class="btn btn-success btn-lg btn-block">
                <i class="fas fa-cloud-upload-alt mr-2"></i> Submit Data Panen
            </button>
            
        </form>
    </div>
    
</div>

<script>
// Toggle Sidebar (Mobile)
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// === LOGIKA GPS OTOMATIS ===
function ambilLokasi() {
    const $lat = $('#latitude');
    const $lng = $('#longitude');
    const $status = $('#gps-status');

    $status.attr('class','alert alert-info d-flex align-items-center').html('<i class="fas fa-location-arrow mr-2"></i> <span>Mencoba mengambil lokasi dari **GPS Anda**...</span>');
    $lat.val('');
    $lng.val('');

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            
            $lat.val(lat);
            $lng.val(lng);
            $status.attr('class','alert alert-success d-flex align-items-center').html('✅ Lokasi berhasil diambil dari **GPS Anda**: (' + lat + ', ' + lng + ')');
        }, function(error) {
            let errorMessage = '❌ Gagal mengambil lokasi GPS.';
            if (error.code === error.PERMISSION_DENIED) {
                errorMessage = '❌ Izin lokasi ditolak. Silakan izinkan lokasi di pengaturan browser Anda.';
            } else if (error.code === error.TIMEOUT) {
                errorMessage = '❌ Waktu pengambilan lokasi habis. Coba lagi atau pindah tempat.';
            }
            $status.attr('class','alert alert-danger d-flex align-items-center').html('<i class="fas fa-times-circle mr-2"></i> <span>' + errorMessage + '</span>');
        }, {
            enableHighAccuracy: true,
            timeout: 7000,
            maximumAge: 0
        });
    } else {
        $status.attr('class','alert alert-danger d-flex align-items-center').html('<i class="fas fa-times-circle mr-2"></i> <span>❌ Browser tidak mendukung GPS.</span>');
    }
}

// === LOGIKA AJAX UNTUK DEPENDENT DROPDOWN ===
$(document).ready(function() {
    const $komoditas = $('#komoditas');
    const $lokasi = $('#lokasi_desa');
    const $msg = $('#lokasi-loading-msg');
    
    ambilLokasi();
    
    function loadLokasi(komoditas) {
        if (!komoditas) {
            $lokasi.html('<option value="" selected>Pilih Komoditas dahulu</option>').prop('disabled', true);
            $msg.text('Pilih komoditas di atas untuk memuat daftar lokasi.').removeClass('text-danger text-info').addClass('text-muted');
            return;
        }

        $lokasi.html('<option value="" selected disabled>Memuat...</option>').prop('disabled', true);
        $msg.html('<i class="fas fa-sync-alt fa-spin mr-1"></i> Memuat lokasi panen untuk <strong>' + komoditas + '</strong>...').removeClass('text-muted text-danger').addClass('text-info');

        $.ajax({
            url: 'fetch_lokasi.php',
            type: 'GET',
            data: { komoditas: komoditas },
            dataType: 'json',
            success: function(data) {
                $lokasi.empty();
                if (data.length > 0) {
                    $lokasi.append('<option value="" disabled selected>Pilih Desa/Lokasi</option>');
                    data.forEach(function(item) {
                        $lokasi.append('<option value="' + item.value + '">' + item.text + '</option>');
                    });
                    $lokasi.prop('disabled', false);
                    $msg.text('Daftar lokasi siap dipilih.').removeClass('text-info text-danger').addClass('text-muted');
                } else {
                    $lokasi.append('<option value="" selected disabled>Tidak ada lokasi tersedia</option>');
                    $msg.text('Tidak ada lokasi panen yang terdata untuk komoditas ini.').removeClass('text-info text-muted').addClass('text-danger');
                    $lokasi.prop('disabled', true);
                }

                const oldLokasi = "<?php echo htmlspecialchars($_POST['lokasi_desa'] ?? ''); ?>";
                if (oldLokasi) {
                    $lokasi.val(oldLokasi);
                }
            },
            error: function(xhr, status, error) {
                $lokasi.html('<option value="" selected disabled>Gagal memuat lokasi</option>').prop('disabled', true);
                $msg.html('<i class="fas fa-exclamation-triangle mr-1"></i> Gagal memuat data lokasi. Cek file <strong>fetch_lokasi.php</strong>.').removeClass('text-info text-muted').addClass('text-danger');
                console.error("AJAX Error: " + status + " - " + error);
            }
        });
    }

    $komoditas.on('change', function() {
        loadLokasi($(this).val());
    });

    const initialKomoditas = $komoditas.val();
    if (initialKomoditas) {
        loadLokasi(initialKomoditas);
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>