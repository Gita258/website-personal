<?php
// =====================================================================
// master_komoditas.php - CRUD Master Data Komoditas
// =====================================================================

session_start();

// Autentikasi dan Otorisasi
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$title = "Kelola Komoditas - AgriData Aceh Barat";
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
$komoditas_data = [
    'id' => null,
    'nama_komoditas' => '',
    'satuan_panen' => 'Kg', // Default satuan
];


// =====================================================================
// 1. LOGIKA CRUD
// =====================================================================

// --- A. TAMBAH/UPDATE (INSERT & EDIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_komoditas'])) {
    $nama = trim($_POST['nama_komoditas']);
    $satuan = trim($_POST['satuan_panen']);
    $id_komoditas = $_POST['id_komoditas'] ?? null;

    // Validation
    if (empty($nama) || empty($satuan)) {
        $message = '<div class="alert error"><i class="fas fa-exclamation-triangle"></i> Nama Komoditas dan Satuan tidak boleh kosong.</div>';
    } else {
        if (!empty($id_komoditas)) {
            // UPDATE
            $sql = "UPDATE komoditas SET nama_komoditas = ?, satuan_panen = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssi', $nama, $satuan, $id_komoditas);
            $action = 'diperbarui';
        } else {
            // INSERT (CREATE)
            $sql = "INSERT INTO komoditas (nama_komoditas, satuan_panen) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $nama, $satuan);
            $action = 'ditambahkan';
        }

        if ($stmt->execute()) {
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Komoditas berhasil ' . $action . '.</div>';
        } else {
            $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal ' . $action . ' komoditas: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}

// --- B. HAPUS (DELETE) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = (int)$_GET['id'];
    
    // PERHATIAN: Hapus data hanya jika TIDAK digunakan di tabel 'panen' atau 'komoditas_lokasi'
    
    // 1. Periksa apakah komoditas sedang digunakan di tabel 'panen'
    $check_panen_sql = "SELECT COUNT(*) FROM panen WHERE komoditas = (SELECT nama_komoditas FROM komoditas WHERE id = ?)";
    $stmt_check_panen = $conn->prepare($check_panen_sql);
    $stmt_check_panen->bind_param('i', $id_to_delete);
    $stmt_check_panen->execute();
    $stmt_check_panen->bind_result($panen_count);
    $stmt_check_panen->fetch();
    $stmt_check_panen->close();

    // 2. Periksa apakah komoditas sedang digunakan di tabel 'komoditas_lokasi'
    $check_komlok_sql = "SELECT COUNT(*) FROM komoditas_lokasi WHERE komoditas = (SELECT nama_komoditas FROM komoditas WHERE id = ?)";
    $stmt_check_komlok = $conn->prepare($check_komlok_sql);
    $stmt_check_komlok->bind_param('i', $id_to_delete);
    $stmt_check_komlok->execute();
    $stmt_check_komlok->bind_result($komlok_count);
    $stmt_check_komlok->fetch();
    $stmt_check_komlok->close();


    if ($panen_count > 0 || $komlok_count > 0) {
        $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal menghapus komoditas. Terdapat **' . $panen_count . '** data panen dan **' . $komlok_count . '** data Komoditas-Lokasi yang terkait.</div>';
    } else {
        // Lanjutkan menghapus
        $delete_sql = "DELETE FROM komoditas WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param('i', $id_to_delete);

        if ($stmt->execute()) {
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Komoditas berhasil dihapus.</div>';
        } else {
            $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal menghapus komoditas. Error: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
    
    // Redirect untuk menampilkan pesan setelah aksi
    $_SESSION['master_komoditas_message'] = $message;
    header("Location: master_komoditas.php");
    exit;
}

// Ambil pesan dari session jika ada setelah redirect
if (isset($_SESSION['master_komoditas_message'])) {
    $message = $_SESSION['master_komoditas_message'];
    unset($_SESSION['master_komoditas_message']);
}


// =====================================================================
// 2. LOGIKA EDIT (mengisi form)
// =====================================================================

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id_to_edit = (int)$_GET['id'];
    $sql_edit = "SELECT id, nama_komoditas, satuan_panen FROM komoditas WHERE id = ?";
    $stmt_edit = $conn->prepare($sql_edit);
    $stmt_edit->bind_param('i', $id_to_edit);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();

    if ($result_edit->num_rows === 1) {
        $komoditas_data = $result_edit->fetch_assoc();
        $edit_mode = true;
        $message = '<div class="alert info"><i class="fas fa-edit"></i> Anda sedang mengedit **' . htmlspecialchars($komoditas_data['nama_komoditas']) . '**.</div>';
    } else {
        $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Data komoditas tidak ditemukan.</div>';
    }
    $stmt_edit->close();
}


// =====================================================================
// 3. AMBIL DATA KOMODITAS (READ) untuk Tabel Tampilan
// =====================================================================

$komoditas_list = [];
$sql_read = "SELECT id, nama_komoditas, satuan_panen FROM komoditas ORDER BY nama_komoditas ASC";
$result_read = $conn->query($sql_read);
if ($result_read->num_rows > 0) {
    while ($row = $result_read->fetch_assoc()) {
        $komoditas_list[] = $row;
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
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #4CAF50; }
        .nav-links i { width: 20px; font-size: 1.1rem; }
        .content { margin-left: 260px; padding: 30px; }
        .header { background: white; padding: 25px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.6rem; color: #2c3e50; font-weight: 600; }
        .user-badge { background: linear-gradient(135deg, #95e989ff 0%, #4ba24bff 100%); color: white; padding: 10px 20px; border-radius: 25px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .page-content { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        .crud-container { display: flex; gap: 20px; }
        .crud-form-column { flex: 0 0 350px; }
        .crud-form-card { background: #f9f9f9; padding: 20px; border-radius: 10px; border-left: 5px solid #1e7e34; }
        .crud-table-card { flex: 1; padding: 0; }
        
        /* Form Styling */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-group input[type="text"], .form-group select {
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
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
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
            <li><a href="master_komoditas.php" class="active"><i class="fas fa-seedling"></i><span>Master Komoditas</span></a></li>
            <li><a href="master_lokasi.php"><i class="fas fa-map-marker-alt"></i><span>Master Lokasi</span></a></li>
            <li><a href="pengaturan.php"><i class="fas fa-cog"></i><span>Pengaturan</span></a></li>
            <li style="margin-top: 20px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="header">
            <h1>Kelola Master Komoditas 🌾</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>Administrator
            </div>
        </div>

        <div class="page-content">
            <?php echo $message; ?>
            
            <div class="crud-container">
                
                <div class="crud-form-column">
                    <div class="crud-form-card">
                        <h3><i class="fas fa-plus-circle"></i> <?php echo ($edit_mode) ? 'Edit Komoditas' : 'Tambah Komoditas Baru'; ?></h3>
                        
                        <form method="POST" action="master_komoditas.php">
                            <input type="hidden" name="id_komoditas" value="<?php echo htmlspecialchars($komoditas_data['id'] ?? ''); ?>">
                            
                            <div class="form-group">
                                <label for="nama_komoditas">Nama Komoditas</label>
                                <input type="text" 
                                    id="nama_komoditas" 
                                    name="nama_komoditas" 
                                    value="<?php echo htmlspecialchars($komoditas_data['nama_komoditas']); ?>"
                                    required>
                            </div>
                            
                            <div class="form-group">
                                <label for="satuan_panen">Satuan Panen (Contoh: Kg, Ton, Kuintal)</label>
                                <input type="text" 
                                    id="satuan_panen" 
                                    name="satuan_panen" 
                                    value="<?php echo htmlspecialchars($komoditas_data['satuan_panen']); ?>"
                                    required>
                            </div>
                            
                            <button type="submit" name="submit_komoditas" class="btn-submit">
                                <i class="fas fa-<?php echo ($edit_mode) ? 'save' : 'plus'; ?>"></i> <?php echo ($edit_mode) ? 'Simpan Perubahan' : 'Tambah Komoditas'; ?>
                            </button>
                             <?php if ($edit_mode): ?>
                                <a href="master_komoditas.php" class="btn-submit btn-reset" style="display: block; text-align: center;">Batalkan Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="crud-table-card">
                    <h3>Daftar Komoditas Aktif</h3>
                    
                    <?php if (count($komoditas_list) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Komoditas</th>
                                    <th>Satuan Panen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($komoditas_list as $kom): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($kom['nama_komoditas']); ?></td>
                                        <td><?php echo htmlspecialchars($kom['satuan_panen']); ?></td>
                                        <td class="action-links">
                                            <a href="master_komoditas.php?action=edit&id=<?php echo $kom['id']; ?>" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="master_komoditas.php?action=delete&id=<?php echo $kom['id']; ?>" 
                                               onclick="return confirm('PERINGATAN! Yakin hapus Komoditas <?php echo htmlspecialchars($kom['nama_komoditas']); ?>? Ini akan mengecek keterkaitan di data panen dan komoditas lokasi.');" 
                                               title="Hapus" class="delete"><i class="fas fa-trash"></i> Hapus</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Belum ada data Komoditas yang dimasukkan. Silakan tambah komoditas baru.</p>
                    <?php endif; ?>
                </div>

            </div>
            
        </div>

    </div>
    
</body>
</html>