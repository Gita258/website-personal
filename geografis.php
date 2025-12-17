<?php
// =====================================================================
// geografis.php - Halaman Peta Sebaran Data Pertanian
// STATUS: FINAL - Menampilkan lokasi panen dengan status "Diterima"
// =====================================================================

session_start();

// Autentikasi hanya untuk admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$title = "Peta Sebaran Data Pertanian - AgriData Aceh";
$username = $_SESSION['user_name'] ?? 'Admin';

// --- Koneksi Database ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'admin';
$db_name = 'agridata_aceh';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// =====================================================================
// Ambil data lokasi panen (status Diterima)
// =====================================================================
$sql = "SELECT 
            p.id_panen, 
            p.komoditas, 
            p.jumlah_panen_kg,
            p.tanggal_panen,
            u.nama AS nama_petani,
            p.latitude, 
            p.longitude
        FROM panen p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'Diterima' 
          AND p.latitude IS NOT NULL 
          AND p.longitude IS NOT NULL";

$result = $conn->query($sql);
$locations = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        
        /* SIDEBAR */
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
        
        /* CONTENT & HEADER */
        .content { margin-left: 260px; padding: 30px; }
        .header { background: white; padding: 25px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.6rem; color: #2c3e50; font-weight: 600; }
        .user-badge { background: linear-gradient(135deg, #95e989ff 0%, #4ba24bff 100%); color: white; padding: 10px 20px; border-radius: 25px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }

        /* KONTEN UTAMA (Peta) */
        .page-content { 
            background: white; 
            padding: 25px; 
            border-radius: 15px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
        }
        #map { 
            height: 75vh;
            width: 100%; 
            border-radius: 10px; 
        }

        /* RESPONSIVE */
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
            <li><a href="verifikasi.php"><i class="fas fa-check-double"></i><span>Verifikasi Panen</span></a></li>
            <li><a href="laporan.php"><i class="fas fa-chart-bar"></i><span>Laporan Analisis</span></a></li>
            <li><a href="geografis.php" class="active"><i class="fas fa-map"></i><span>Geografis</span></a></li>
            <li><a href="pengaturan.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
            <li style="margin-top: 20px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="header">
            <h1>Peta Sebaran Data Pertanian 🗺️</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>Administrator
            </div>
        </div>

        <div class="page-content">
            <h3 style="margin-bottom: 10px; color: #2c3e50;">Lokasi Panen Terverifikasi</h3>
            <p style="margin-bottom: 20px; color: #7f8c8d; font-size: 0.9rem;">Visualisasi titik koordinat data panen yang sudah disetujui.</p>
            <div id="map"></div>
        </div>
        
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Koordinat tengah Aceh (Banda Aceh)
        var acehCenter = [4.695135, 96.749397];
        
        // Inisialisasi peta
        var map = L.map('map').setView(acehCenter, 8);

        // Tambahkan layer peta dasar (OpenStreetMap)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        // Data lokasi dari PHP
        var dataLokasi = <?php echo json_encode($locations); ?>;

        if (dataLokasi.length === 0) {
            // Jika tidak ada data, tampilkan pesan di atas peta
            document.getElementById('map').innerHTML = '<div style="height: 100%; display: flex; align-items: center; justify-content: center; color: #7f8c8d; font-size: 1.1rem;"><i class="fas fa-map-marker-alt" style="margin-right: 10px;"></i>Belum ada data panen terverifikasi dengan koordinat yang valid.</div>';
            document.getElementById('map').style.backgroundColor = '#ecf0f1';
        } else {
            // Buat grup layer untuk markers agar mudah diatur zoom-nya
            var markerGroup = L.featureGroup();

            dataLokasi.forEach(function(item) {
                // Pastikan latitude dan longitude adalah angka valid
                var lat = parseFloat(item.latitude);
                var lon = parseFloat(item.longitude);

                if (!isNaN(lat) && !isNaN(lon)) {
                    var marker = L.marker([lat, lon]);
                    marker.bindPopup(
                        "<b>" + item.nama_petani + "</b><br>" +
                        "Komoditas: " + item.komoditas + "<br>" +
                        "Jumlah: " + item.jumlah_panen_kg + " Kg<br>" +
                        "Tanggal: " + new Date(item.tanggal_panen).toLocaleDateString('id-ID')
                    );
                    markerGroup.addLayer(marker);
                }
            });
            
            markerGroup.addTo(map);

            // Opsional: Zoom peta agar mencakup semua marker
            try {
                map.fitBounds(markerGroup.getBounds().pad(0.1));
            } catch (e) {
                // Jika hanya ada 1 marker, getBounds bisa error, biarkan default view
                console.warn("Gagal mengatur zoomBounds:", e);
            }
        }
    </script>
</body>
</html>