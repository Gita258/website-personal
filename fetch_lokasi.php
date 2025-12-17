<?php
// =====================================================================
// fetch_lokasi.php - Endpoint AJAX untuk mengambil lokasi berdasarkan komoditas
// =====================================================================

header('Content-Type: application/json');

// Konfigurasi Database (Sesuaikan dengan setting Anda)
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'admin', 
    'name' => 'agridata_aceh'
];

$komoditas = $_GET['komoditas'] ?? '';
$lokasi_data = [];

if (empty($komoditas)) {
    echo json_encode([]);
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
        $db_config['user'],
        $db_config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Ambil Lokasi berdasarkan Komoditas dari tabel komoditas_lokasi
    // Asumsi: Tabel 'komoditas_lokasi' memiliki kolom 'komoditas', 'lokasi_desa', 'lokasi_kecamatan'
    $stmt = $db->prepare("
        SELECT lokasi_desa, lokasi_kecamatan 
        FROM komoditas_lokasi 
        WHERE komoditas = ? 
        ORDER BY lokasi_desa ASC
    ");
    $stmt->execute([$komoditas]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
        // **PERBAIKAN LOGIKA FORMAT OUTPUT**
        // Kita harus mengembalikan format yang dapat di-parse oleh fungsi parse_lokasi di input_panen.php
        $desa = htmlspecialchars($row['lokasi_desa']);
        $kecamatan = htmlspecialchars($row['lokasi_kecamatan']);
        
        // Format yang DIHARAPKAN oleh input_panen.php: "Nama Desa (Kec. Nama Kecamatan)"
        $formatted_value = $desa . ' (Kec. ' . $kecamatan . ')';

        $lokasi_data[] = [
            'value' => $formatted_value, // Ini yang akan disimpan ke database
            'text' => $formatted_value   // Ini yang akan ditampilkan di dropdown
        ];
    }

} catch (PDOException $e) {
    // Tangani error database jika terjadi
    error_log("AJAX Lokasi Error: " . $e->getMessage());
    // Kirim array kosong agar JS tidak error
    echo json_encode([]);
    exit;
}

echo json_encode($lokasi_data);
?>