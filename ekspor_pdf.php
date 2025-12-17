<?php
// =====================================================================
// ekspor_pdf.php - Menghasilkan Laporan Data Pertanian dalam format PDF (Menggunakan Tabel 'panen')
// STATUS: PERBAIKAN FINAL NAMA KOLOM DARI SCREENSHOT
// =====================================================================

session_start();

// Autentikasi dan Otorisasi (sama seperti laporan.php)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Akses ditolak.");
}

// PENTING: Pastikan Anda telah menginstal Dompdf: composer require dompdf/dompdf
require 'dompdf/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// --- KONFIGURASI DAN KONEKSI DATABASE ---
$db_host    = 'localhost';
$db_user    = 'root';
$db_pass    = 'admin';
$db_name    = 'agridata_aceh';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// 2. Logika Pengambilan Data 
$selected_farmer_id = 0;
if (isset($_GET['petani']) && is_numeric($_GET['petani'])) {
    $selected_farmer_id = intval($_GET['petani']);
}

$farmer_name_filter = "Semua Petani";
if ($selected_farmer_id > 0) {
    $stmt_name = $conn->prepare("SELECT nama FROM users WHERE id = ?");
    $stmt_name->bind_param("i", $selected_farmer_id);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    if ($row_name = $result_name->fetch_assoc()) {
        $farmer_name_filter = $row_name['nama'];
    }
    $stmt_name->close();
}


// Query Utama: Menggunakan kolom: id_panen, komoditas, jumlah_panen_kg, tanggal_panen
$sql_read = "SELECT 
                dp.id_panen AS id, 
                dp.komoditas, 
                dp.jumlah_panen_kg AS luas_lahan, 
                dp.tanggal_panen AS tanggal_input, 
                u.nama AS nama_petani,
                u.created_at AS tanggal_daftar_petani 
             FROM panen dp 
             JOIN users u ON dp.user_id = u.id /* <-- PERBAIKAN: Gunakan u.id */
             WHERE 1=1 ";

$params = [];
$types = '';

if ($selected_farmer_id > 0) {
    $sql_read .= " AND dp.user_id = ?";
    $types .= 'i';
    $params[] = &$selected_farmer_id;
}

$sql_read .= " ORDER BY dp.tanggal_panen DESC";

$stmt = $conn->prepare($sql_read);

if ($selected_farmer_id > 0) {
    $stmt->bind_param($types, ...$params);
}

$reports = [];
$total_luas = 0;
if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
        $total_luas += floatval($row['luas_lahan']);
    }
    $result->free();
}
$stmt->close();
$conn->close();

// 3. Buat Konten HTML untuk PDF
$html = '
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        h1 { text-align: center; margin-bottom: 20px; color: #1e7e34; }
        .info { margin-bottom: 20px; padding: 10px; background-color: #f3f3f3; border: 1px solid #ddd;}
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .summary { margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Laporan Data Pertanian AgriData Aceh</h1>
    <div class="info">
        Laporan untuk Petani: <strong>' . htmlspecialchars($farmer_name_filter) . '</strong><br>
        Jumlah Data: <strong>' . count($reports) . '</strong><br>
        Total Jumlah Panen: <strong>' . number_format($total_luas, 2, ',', '.') . ' Kg</strong><br>
        Tanggal Cetak: ' . date('d F Y H:i:s') . '
    </div>
    
    <table>
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
        <tbody>';

if (empty($reports)) {
    $html .= '<tr><td colspan="6" style="text-align: center;">Tidak ada data ditemukan.</td></tr>';
} else {
    foreach ($reports as $report) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($report['id']) . '</td>
                <td>' . date('d-m-Y', strtotime(htmlspecialchars($report['tanggal_input']))) . '</td>
                <td>' . htmlspecialchars($report['nama_petani']) . '</td>
                <td>' . date('d-m-Y', strtotime(htmlspecialchars($report['tanggal_daftar_petani']))) . '</td> 
                <td>' . htmlspecialchars($report['komoditas']) . '</td>
                <td>' . htmlspecialchars($report['luas_lahan']) . '</td>
            </tr>';
    }
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

// 4. Inisialisasi dan Konfigurasi Dompdf
$options = new Options();
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

// 5. Muat HTML ke Dompdf
$dompdf->loadHtml($html);

// 6. Atur ukuran kertas dan orientasi
$dompdf->setPaper('A4', 'landscape'); 

// 7. Render HTML ke PDF
$dompdf->render();

// 8. Streaming hasil ke browser (Paksa Unduh)
$filename = 'Laporan_Pertanian_' . ($selected_farmer_id > 0 ? str_replace(' ', '_', $farmer_name_filter) : 'Semua') . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]); 

exit;