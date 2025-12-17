<?php
// =====================================================================
// master_komoditas_lokasi.php - CRUD Data Master Komoditas-Lokasi (Tabel komoditas_lokasi)
// =====================================================================

session_start();

// Autentikasi dan Otorisasi
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$title = "Kelola Komoditas Lokasi - AgriData Aceh Barat";
$message = '';

// --- KONFIGURASI DAN KONEKSI DATABASE ---
$db_host    = 'localhost';
$db_user    = 'root';
$db_pass    = 'admin';
$db_name    = 'agridata_aceh';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Data default untuk Form (akan di-override jika mode edit)
$edit_mode = false;
$data_edit = [
    'id' => null,
    'komoditas' => '',
    'lokasi_desa' => '',
    'lokasi_kecamatan' => '',
];

// =====================================================================
// 1. Ambil Data Master Pendukung (untuk Dropdown)
// =====================================================================

// Ambil daftar Desa (dari tabel 'lokasi' jenis='desa')
$desa_list = [];
$sql_desa = "SELECT l1.nama_lokasi AS desa, l2.nama_lokasi AS kecamatan 
             FROM lokasi l1 
             JOIN lokasi l2 ON l1.parent_id = l2.id 
             WHERE l1.jenis = 'desa' 
             ORDER BY l2.nama_lokasi, l1.nama_lokasi ASC";
$result_desa = $conn->query($sql_desa);
if ($result_desa->num_rows > 0) {
    while ($row = $result_desa->fetch_assoc()) {
        $desa_list[] = $row;
    }
}

// Ambil daftar Komoditas (dari tabel 'komoditas')
$komoditas_list = [];
$sql_komoditas = "SELECT nama_komoditas FROM komoditas ORDER BY nama_komoditas ASC";
$result_komoditas = $conn->query($sql_komoditas);
if ($result_komoditas->num_rows > 0) {
    while ($row = $result_komoditas->fetch_assoc()) {
        $komoditas_list[] = $row['nama_komoditas'];
    }
}

// =====================================================================
// 2. LOGIKA CRUD
// =====================================================================

// --- C. HAPUS (DELETE) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = (int)$_GET['id'];
    
    // Hapus data
    $delete_sql = "DELETE FROM komoditas_lokasi WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param('i', $id_to_delete);

    if ($stmt->execute()) {
        $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Data Komoditas-Lokasi berhasil dihapus.</div>';
    } else {
        $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal menghapus data: ' . $stmt->error . '</div>';
    }
    $stmt->close();
    
    $_SESSION['kom_lok_message'] = $message;
    header("Location: master_komoditas_lokasi.php");
    exit;
}

// --- A. TAMBAH/UPDATE (INSERT & EDIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_kom_lokasi'])) {
    $komoditas = trim($_POST['komoditas']);
    
    // Pisahkan Desa dan Kecamatan dari nilai dropdown 'lokasi_full'
    $lokasi_full = explode(" | ", $_POST['lokasi_full']);
    $lokasi_desa = trim($lokasi_full[0] ?? '');
    $lokasi_kecamatan = trim($lokasi_full[1] ?? '');

    $id_kom_lokasi = $_POST['id_kom_lokasi'] ?? null;

    if (empty($komoditas) || empty($lokasi_desa) || empty($lokasi_kecamatan)) {
        $message = '<div class="alert error"><i class="fas fa-exclamation-triangle"></i> Semua kolom wajib diisi.</div>';
    } else {
        if (!empty($id_kom_lokasi)) {
            // UPDATE
            $sql = "UPDATE komoditas_lokasi SET komoditas = ?, lokasi_desa = ?, lokasi_kecamatan = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssi', $komoditas, $lokasi_desa, $lokasi_kecamatan, $id_kom_lokasi);
            $action = 'diperbarui';
        } else {
            // INSERT (CREATE)
            $sql = "INSERT INTO komoditas_lokasi (komoditas, lokasi_desa, lokasi_kecamatan) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $komoditas, $lokasi_desa, $lokasi_kecamatan);
            $action = 'ditambahkan';
        }

        if ($stmt->execute()) {
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Data berhasil ' . $action . '.</div>';
        } else {
            $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal ' . $action . ' data: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}

// Ambil pesan dari session jika ada setelah redirect
if (isset($_SESSION['kom_lok_message'])) {
    $message = $_SESSION['kom_lok_message'];
    unset($_SESSION['kom_lok_message']);
}

// --- LOGIKA EDIT (mengisi form) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id_to_edit = (int)$_GET['id'];
    $sql_edit = "SELECT id, komoditas, lokasi_desa, lokasi_kecamatan FROM komoditas_lokasi WHERE id = ?";
    $stmt_edit = $conn->prepare($sql_edit);
    $stmt_edit->bind_param('i', $id_to_edit);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();

    if ($result_edit->num_rows === 1) {
        $data_edit = $result_edit->fetch_assoc();
        $edit_mode = true;
        $message = '<div class="alert info"><i class="fas fa-edit"></i> Anda sedang mengedit data Komoditas-Lokasi.</div>';
    }
    $stmt_edit->close();
}


// =====================================================================
// 3. AMBIL SEMUA DATA KOMODITAS-LOKASI (READ)
// =====================================================================
$komoditas_lokasi_data = [];
$sql_read = "SELECT id, komoditas, lokasi_desa, lokasi_kecamatan FROM komoditas_lokasi ORDER BY komoditas, lokasi_kecamatan ASC";
$result_read = $conn->query($sql_read);
if ($result_read->num_rows > 0) {
    while ($row = $result_read->fetch_assoc()) {
        $komoditas_lokasi_data[] = $row;
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
        /* CSS yang konsisten */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 260px; background: linear-gradient(180deg, #1e7e34 0%, #155724 100%); padding: 20px 0; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 1000; }
        .brand { padding: 0 25px 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .brand h2 { color: white; font-size: 1.4rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .brand-icon { width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .nav-links { list-style: none; }
        .nav-links a { display: flex; align-items: center; gap: 15px; padding: 15px 25px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; font-size: 0.95rem; }
        .nav-links a:hover { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #4CAF50; }
        .nav-links a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #4CAF50; }
        .nav-links i { width: 20px; font-size: 1.1rem; }
        .content { margin-left: 260px; padding: 30px; }
        .header { background: white; padding: 25px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.6rem; color: #2c3e50; font-weight: 600; }
        .user-badge { background: linear-gradient(135deg, #95e989ff 0%, #4ba24bff 100%); color: white; padding: 10px 20px; border-radius: 25px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .page-content { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        .crud-container { display: flex; gap: 20px; }
        .crud-form-column { flex: 0 0 350px; }
        .crud-form-card { background: #f9f9f9; padding: 20px; border-radius: 10px; border-left: 5px solid #1e7e34; margin-bottom: 20px; }
        .crud-table-card { flex: 1; padding: 0; }
        
        /* Form Styling */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; 
        }
        .btn-submit { background-color: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: background-color 0.3s; width: 100%; }
        .btn-submit:hover { background-color: #45a049; }
        .btn-reset { margin-top: 10px; background-color: #7f8c8d; }
        .btn-reset:hover { background-color: #6c7a7e; }

        /* Table Styling */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; font-size: 0.9rem; }
        .data-table th { background-color: #1e7e34; color: white; font-weight: 600; }
        .data-table tr:nth-child(even) { background-color: #f8f8f8; }
        .data-table tr:hover { background-color: #e6e6e6; }
        .data-table .action-links a { margin-left: 10px; color: #2980b9; text-decoration: none; }
        .data-table .action-links .delete { color: #c0392b; }

        /* Alert Messages */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 10px;}
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert.info { background-color: #ebf5ff; color: #31708f; border: 1px solid #bce8f1; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">
            <h2><div class="brand-icon"><i class="fas fa-leaf"></i></div><span>AgriData</span></h2>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard_admin.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="kelola_pengguna.php"><i class="fas fa-users"></i><span>Pengguna</span></a></li>
            <li><a href="verifikasi.php"><i class="fas fa-check-double"></i><span>Verifikasi Panen</span></a></li>
            <li><a href="laporan.php"><i class="fas fa-chart-bar"></i><span>Laporan</span></a></li>
            <li><a href="geografis.php"><i class="fas fa-map"></i><span>Geografis</span></a></li>
            <li><a href="master_komoditas.php"><i class="fas fa-seedling"></i><span>Master Komoditas</span></a></li>
            <li><a href="master_lokasi.php"><i class="fas fa-map-marker-alt"></i><span>Master Lokasi</span></a></li>
            <li><a href="master_komoditas_lokasi.php" class="active"><i class="fas fa-link"></i><span>Komoditas Lokasi</span></a></li>
            <li style="margin-top: 20px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="header">
            <h1>Kelola Master Komoditas - Lokasi 🔗</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>Administrator
            </div>
        </div>

        <div class="page-content">
            <?php echo $message; ?>
            
            <div class="crud-container">
                
                <div class="crud-form-column">
                    <div class="crud-form-card">
                        <h3><i class="fas fa-plus-circle"></i> <?php echo ($edit_mode) ? 'Edit Data Terangkum' : 'Tambah Data Komoditas-Lokasi'; ?></h3>
                        
                        <form method="POST" action="master_komoditas_lokasi.php">
                            <input type="hidden" name="id_kom_lokasi" value="<?php echo htmlspecialchars($data_edit['id'] ?? ''); ?>">
                            
                            <div class="form-group">
                                <label for="komoditas">Pilih Komoditas</label>
                                <select id="komoditas" name="komoditas" required>
                                    <option value="">-- Pilih Komoditas --</option>
                                    <?php foreach ($komoditas_list as $kom): ?>
                                        <option value="<?php echo htmlspecialchars($kom); ?>" 
                                            <?php echo ($data_edit['komoditas'] == $kom) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kom); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="lokasi_full">Pilih Desa (dan Kecamatan)</label>
                                <select id="lokasi_full" name="lokasi_full" required>
                                    <option value="">-- Pilih Desa --</option>
                                    <?php 
                                    $current_full_lokasi = $data_edit['lokasi_desa'] . ' | ' . $data_edit['lokasi_kecamatan'];
                                    foreach ($desa_list as $lok): 
                                        $full_lokasi = htmlspecialchars($lok['desa']) . ' | ' . htmlspecialchars($lok['kecamatan']);
                                    ?>
                                        <option value="<?php echo $full_lokasi; ?>" 
                                            <?php echo ($current_full_lokasi == $full_lokasi) ? 'selected' : ''; ?>>
                                            <?php echo $full_lokasi; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="submit_kom_lokasi" class="btn-submit">
                                <i class="fas fa-<?php echo ($edit_mode) ? 'save' : 'plus'; ?>"></i> <?php echo ($edit_mode) ? 'Simpan Perubahan' : 'Tambah Data'; ?>
                            </button>

                            <?php if ($edit_mode): ?>
                                <a href="master_komoditas_lokasi.php" class="btn-submit btn-reset" style="display: block; text-align: center;">Batalkan Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="crud-table-card">
                    <h3>Daftar Komoditas per Lokasi (Tabel `komoditas_lokasi`)</h3>
                    
                    <?php if (count($komoditas_lokasi_data) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Komoditas</th>
                                    <th>Lokasi Desa</th>
                                    <th>Lokasi Kecamatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($komoditas_lokasi_data as $data): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($data['komoditas']); ?></td>
                                        <td><?php echo htmlspecialchars($data['lokasi_desa']); ?></td>
                                        <td><?php echo htmlspecialchars($data['lokasi_kecamatan']); ?></td>
                                        <td class="action-links">
                                            <a href="master_komoditas_lokasi.php?action=edit&id=<?php echo $data['id']; ?>" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="master_komoditas_lokasi.php?action=delete&id=<?php echo $data['id']; ?>" 
                                               onclick="return confirm('Yakin hapus data ini?');" 
                                               title="Hapus" class="delete"><i class="fas fa-trash"></i> Hapus</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Belum ada data Komoditas-Lokasi yang dimasukkan.</p>
                    <?php endif; ?>
                </div>

            </div>
            
        </div>

    </div>
    
</body>
</html>