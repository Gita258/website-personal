<?php
// =====================================================================
// master_lokasi.php - CRUD Master Data Lokasi (Kecamatan & Desa Aceh Barat)
// STATUS: FINAL DAN LENGKAP
// =====================================================================

session_start();

// Autentikasi dan Otorisasi
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$title = "Kelola Lokasi - AgriData Aceh Barat";
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
$lokasi_data = [
    'id' => null,
    'nama_lokasi' => '',
    'jenis' => 'kecamatan', // Default saat pertama buka form
    'parent_id' => null,
];


// =====================================================================
// 1. LOGIKA CRUD
// =====================================================================

// --- A. TAMBAH/UPDATE (KECAMATAN & DESA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_lokasi'])) {
    $nama = trim($_POST['nama_lokasi']);
    $jenis = $_POST['jenis_lokasi'];
    // Gunakan ternary operator untuk memastikan parent_id adalah NULL jika kosong
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : NULL; 
    $id_lokasi = $_POST['id_lokasi'] ?? null;

    // Validation
    if (empty($nama)) {
        $message = '<div class="alert error"><i class="fas fa-exclamation-triangle"></i> Nama Lokasi tidak boleh kosong.</div>';
    } elseif ($jenis == 'desa' && empty($parent_id)) {
        $message = '<div class="alert error"><i class="fas fa-exclamation-triangle"></i> Desa harus memiliki Kecamatan Induk (Parent).</div>';
    } else {
        if (!empty($id_lokasi)) {
            // UPDATE
            $sql = "UPDATE lokasi SET nama_lokasi = ?, jenis = ?, parent_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            // Penanganan parent_id NULL pada bind_param (memerlukan penanganan khusus jika di MySQLi strict)
            if ($parent_id === NULL) {
                $sql_update = "UPDATE lokasi SET nama_lokasi = ?, jenis = ?, parent_id = NULL WHERE id = ?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param('ssi', $nama, $jenis, $id_lokasi);
            } else {
                 $stmt->bind_param('ssii', $nama, $jenis, $parent_id, $id_lokasi);
            }
            $action = 'diperbarui';
        } else {
            // INSERT (CREATE)
            $sql = "INSERT INTO lokasi (nama_lokasi, jenis, parent_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($parent_id === NULL) {
                // Untuk kasus Kecamatan (parent_id selalu NULL)
                $stmt->bind_param('ssi', $nama, $jenis, $parent_id); // 'i' untuk NULL tetap diterima
            } else {
                $stmt->bind_param('ssi', $nama, $jenis, $parent_id);
            }
            $action = 'ditambahkan';
        }

        if ($stmt && $stmt->execute()) {
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Lokasi berhasil ' . $action . '.</div>';
        } else if ($stmt) {
            $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal ' . $action . ' lokasi: ' . $stmt->error . '</div>';
        } else {
             $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal menyiapkan statement SQL.</div>';
        }
        $stmt->close();
    }
}

// --- B. HAPUS (DELETE) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = (int)$_GET['id'];
    
    // 1. Periksa apakah lokasi sedang digunakan di tabel 'panen'
    $check_panen_sql = "SELECT COUNT(*) FROM panen WHERE lokasi_id = ?";
    $stmt_check_panen = $conn->prepare($check_panen_sql);
    $stmt_check_panen->bind_param('i', $id_to_delete);
    $stmt_check_panen->execute();
    $stmt_check_panen->bind_result($panen_count);
    $stmt_check_panen->fetch();
    $stmt_check_panen->close();

    if ($panen_count > 0) {
        $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal menghapus lokasi. Ada **' . $panen_count . '** data panen yang terkait dengan lokasi ini.</div>';
    } else {
        // 2. Periksa apakah ada lokasi anak (desa) yang bergantung padanya (hanya berlaku untuk Kecamatan)
        $check_children_sql = "SELECT COUNT(*) FROM lokasi WHERE parent_id = ?";
        $stmt_check = $conn->prepare($check_children_sql);
        $stmt_check->bind_param('i', $id_to_delete);
        $stmt_check->execute();
        $stmt_check->bind_result($child_count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($child_count > 0) {
            $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal menghapus lokasi. Hapus semua Desa yang terkait terlebih dahulu.</div>';
        } else {
            // Lanjutkan menghapus
            $delete_sql = "DELETE FROM lokasi WHERE id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param('i', $id_to_delete);

            if ($stmt->execute()) {
                $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Lokasi berhasil dihapus.</div>';
            } else {
                $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Gagal menghapus lokasi. Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
    
    // Redirect untuk menghilangkan parameter GET dari URL dan menampilkan pesan
    // Simpan pesan di session agar tetap muncul setelah redirect
    $_SESSION['master_lokasi_message'] = $message;
    header("Location: master_lokasi.php");
    exit;
}

// Ambil pesan dari session jika ada setelah redirect (Delete/Update)
if (isset($_SESSION['master_lokasi_message'])) {
    $message = $_SESSION['master_lokasi_message'];
    unset($_SESSION['master_lokasi_message']);
}


// =====================================================================
// 2. LOGIKA EDIT (mengisi form)
// =====================================================================

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id_to_edit = (int)$_GET['id'];
    $sql_edit = "SELECT id, nama_lokasi, jenis, parent_id FROM lokasi WHERE id = ?";
    $stmt_edit = $conn->prepare($sql_edit);
    $stmt_edit->bind_param('i', $id_to_edit);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();

    if ($result_edit->num_rows === 1) {
        $lokasi_data = $result_edit->fetch_assoc();
        $edit_mode = true;
        $message = '<div class="alert info"><i class="fas fa-edit"></i> Anda sedang mengedit **' . htmlspecialchars($lokasi_data['nama_lokasi']) . '**. Silakan gunakan form yang relevan di bawah.</div>';
    } else {
        $message = '<div class="alert error"><i class="fas fa-times-circle"></i> Data lokasi tidak ditemukan.</div>';
    }
    $stmt_edit->close();
}


// =====================================================================
// 3. AMBIL DATA LOKASI (READ) untuk Tabel Tampilan
// =====================================================================

// Ambil semua Kecamatan (jenis='kecamatan')
$kecamatan_list = [];
$sql_kec = "SELECT id, nama_lokasi FROM lokasi WHERE jenis = 'kecamatan' ORDER BY nama_lokasi ASC";
$result_kec = $conn->query($sql_kec);
if ($result_kec->num_rows > 0) {
    while ($row = $result_kec->fetch_assoc()) {
        $kecamatan_list[$row['id']] = $row;
    }
}

// Ambil semua Desa dan kelompokkan berdasarkan Kecamatan (parent_id)
$desa_by_kecamatan = [];
$sql_desa = "SELECT id, nama_lokasi, parent_id FROM lokasi WHERE jenis = 'desa' ORDER BY parent_id, nama_lokasi ASC";
$result_desa = $conn->query($sql_desa);
if ($result_desa->num_rows > 0) {
    while ($row = $result_desa->fetch_assoc()) {
        $desa_by_kecamatan[$row['parent_id']][] = $row;
    }
}

// Data semua kecamatan untuk form dropdown desa
$all_kecamatan = $kecamatan_list;

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
        /* CSS yang konsisten dengan halaman sebelumnya */
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
        .crud-form-column { flex: 0 0 350px; display: flex; flex-direction: column; gap: 20px; }
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

        /* Lokasi Hierarchy Style */
        .location-list { list-style: none; padding: 0; }
        .kecamatan-item { background: #e8f5e9; padding: 15px; margin-bottom: 10px; border-radius: 8px; border-left: 5px solid #1e7e34; }
        .kecamatan-item strong { font-size: 1.1rem; color: #1e7e34; }
        .desa-list { list-style: none; padding-left: 20px; margin-top: 10px; }
        .desa-item { padding: 8px 0; border-bottom: 1px dashed #ccc; display: flex; justify-content: space-between; align-items: center; }
        .action-links a { margin-left: 10px; color: #2980b9; text-decoration: none; font-size: 0.9rem; }
        .action-links a:hover { text-decoration: underline; }
        .action-links .delete { color: #c0392b; }

        /* Alert Messages */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; }
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
            <li><a href="master_lokasi.php" class="active"><i class="fas fa-map-marker-alt"></i><span>Master Lokasi</span></a></li>
            <li style="margin-top: 20px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="header">
            <h1>Kelola Master Lokasi (Aceh Barat) 🗺️</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>Administrator
            </div>
        </div>

        <div class="page-content">
            <?php echo $message; ?>
            
            <div class="crud-container">
                
                <div class="crud-form-column">
                    <div class="crud-form-card">
                        <h3><i class="fas fa-city"></i> <?php echo ($edit_mode && $lokasi_data['jenis'] == 'kecamatan') ? 'Edit Kecamatan' : 'Tambah Kecamatan Baru'; ?></h3>
                        
                        <form method="POST" action="master_lokasi.php">
                            <input type="hidden" name="jenis_lokasi" value="kecamatan">
                            <input type="hidden" name="id_lokasi" value="<?php echo ($edit_mode && $lokasi_data['jenis'] == 'kecamatan') ? $lokasi_data['id'] : ''; ?>">
                            
                            <div class="form-group">
                                <label for="nama_kecamatan">Nama Kecamatan</label>
                                <input type="text" 
                                    id="nama_kecamatan" 
                                    name="nama_lokasi" 
                                    value="<?php echo ($edit_mode && $lokasi_data['jenis'] == 'kecamatan') ? htmlspecialchars($lokasi_data['nama_lokasi']) : ''; ?>"
                                    required>
                            </div>
                            
                            <button type="submit" name="submit_lokasi" class="btn-submit">
                                <i class="fas fa-<?php echo ($edit_mode && $lokasi_data['jenis'] == 'kecamatan') ? 'save' : 'plus'; ?>"></i> <?php echo ($edit_mode && $lokasi_data['jenis'] == 'kecamatan') ? 'Simpan Perubahan' : 'Tambah Kecamatan'; ?>
                            </button>
                             <?php if ($edit_mode && $lokasi_data['jenis'] == 'kecamatan'): ?>
                                <a href="master_lokasi.php" class="btn-submit btn-reset" style="display: block; text-align: center;">Batalkan Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="crud-form-card">
                        <h3><i class="fas fa-home"></i> <?php echo ($edit_mode && $lokasi_data['jenis'] == 'desa') ? 'Edit Desa/Gampong' : 'Tambah Desa/Gampong Baru'; ?></h3>
                        
                        <form method="POST" action="master_lokasi.php">
                            <input type="hidden" name="jenis_lokasi" value="desa">
                            <input type="hidden" name="id_lokasi" value="<?php echo ($edit_mode && $lokasi_data['jenis'] == 'desa') ? $lokasi_data['id'] : ''; ?>">

                            <div class="form-group">
                                <label for="parent_kecamatan">Pilih Kecamatan Induk</label>
                                <select id="parent_kecamatan" name="parent_id" required>
                                    <option value="">-- Pilih Kecamatan --</option>
                                    <?php foreach ($all_kecamatan as $id => $kec): ?>
                                        <option value="<?php echo $id; ?>"
                                            <?php echo ($edit_mode && $lokasi_data['jenis'] == 'desa' && $lokasi_data['parent_id'] == $id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kec['nama_lokasi']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="nama_desa">Nama Desa/Gampong</label>
                                <input type="text" 
                                    id="nama_desa" 
                                    name="nama_lokasi" 
                                    value="<?php echo ($edit_mode && $lokasi_data['jenis'] == 'desa') ? htmlspecialchars($lokasi_data['nama_lokasi']) : ''; ?>"
                                    required>
                            </div>
                            
                            <button type="submit" name="submit_lokasi" class="btn-submit">
                                <i class="fas fa-<?php echo ($edit_mode && $lokasi_data['jenis'] == 'desa') ? 'save' : 'plus'; ?>"></i> <?php echo ($edit_mode && $lokasi_data['jenis'] == 'desa') ? 'Simpan Perubahan' : 'Tambah Desa'; ?>
                            </button>
                            <?php if ($edit_mode && $lokasi_data['jenis'] == 'desa'): ?>
                                <a href="master_lokasi.php" class="btn-submit btn-reset" style="display: block; text-align: center;">Batalkan Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="crud-table-card">
                    <h3>Struktur Lokasi (Kecamatan & Desa)</h3>
                    
                    <?php if (count($kecamatan_list) > 0): ?>
                        <ul class="location-list">
                            <?php foreach ($kecamatan_list as $kec_id => $kec): ?>
                                <li class="kecamatan-item">
                                    <strong><i class="fas fa-map-marker-alt"></i> Kecamatan <?php echo htmlspecialchars($kec['nama_lokasi']); ?> (ID: <?php echo $kec_id; ?>)</strong>
                                    
                                    <span class="action-links">
                                        <a href="master_lokasi.php?action=edit&id=<?php echo $kec_id; ?>" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="master_lokasi.php?action=delete&id=<?php echo $kec_id; ?>" 
                                           onclick="return confirm('PERINGATAN! Yakin hapus Kecamatan <?php echo htmlspecialchars($kec['nama_lokasi']); ?>? Semua Desa dan data panen terkait (jika tidak ada) harus dihapus terlebih dahulu.');" 
                                           title="Hapus" class="delete"><i class="fas fa-trash"></i> Hapus</a>
                                    </span>

                                    <?php if (isset($desa_by_kecamatan[$kec_id]) && count($desa_by_kecamatan[$kec_id]) > 0): ?>
                                        <ul class="desa-list">
                                            <?php foreach ($desa_by_kecamatan[$kec_id] as $desa): ?>
                                                <li class="desa-item">
                                                    <span><?php echo htmlspecialchars($desa['nama_lokasi']); ?> (ID: <?php echo $desa['id']; ?>)</span>
                                                    <span class="action-links">
                                                        <a href="master_lokasi.php?action=edit&id=<?php echo $desa['id']; ?>" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                                        <a href="master_lokasi.php?action=delete&id=<?php echo $desa['id']; ?>" 
                                                           onclick="return confirm('Yakin hapus Desa <?php echo htmlspecialchars($desa['nama_lokasi']); ?>? Data panen terkait akan di cek.');" 
                                                           title="Hapus" class="delete"><i class="fas fa-trash"></i> Hapus</a>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p style="padding-left: 20px; font-size: 0.9rem; color: #888;">Belum ada Desa/Gampong terdaftar di Kecamatan ini.</p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Belum ada data Kecamatan yang dimasukkan. Silakan tambah Kecamatan terlebih dahulu menggunakan form di sebelah kiri.</p>
                    <?php endif; ?>
                </div>

            </div>
            
        </div>

    </div>
    
</body>
</html>