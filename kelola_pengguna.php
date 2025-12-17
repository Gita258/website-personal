<?php
// =====================================================================
// kelola_pengguna.php - Kelola Pengguna (Hanya Petani, Admin tidak ditampilkan)
// =====================================================================

session_start();

// Autentikasi dan Otorisasi
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['user_name'] ?? 'Admin';
$title = "Kelola Petani - AgriData Aceh";
$users = [];
$error_message = '';
$success_message = '';

// --- KONFIGURASI DAN KONEKSI DATABASE ---
$db_host    = 'localhost';
$db_user    = 'root';
$db_pass    = 'admin';            
$db_name    = 'agridata_aceh'; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// =====================================================================
// 1. LOGIKA TAMBAH PENGGUNA BARU (CREATE) - OTOMATIS ROLE 'petani'
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    
    $nama = $conn->real_escape_string($_POST['nama'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = 'petani';

    if (empty($nama) || empty($email) || empty($password)) {
        $error_message = "Nama, Email, dan Kata Sandi wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        $raw_password = $password; 
        $q_keamanan = "default";
        $j_keamanan = "default";
        
        $sql_insert = "INSERT INTO users (nama, email, password, role, pertanyaan_keamanan, jawaban_keamanan) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("ssssss", $nama, $email, $raw_password, $role, $q_keamanan, $j_keamanan);

        if ($stmt->execute()) {
            $success_message = "Petani **" . htmlspecialchars($nama) . "** berhasil ditambahkan!";
        } else {
            if ($conn->errno == 1062) {
                $error_message = "Gagal: Email sudah terdaftar.";
            } else {
                $error_message = "Error saat menambahkan pengguna: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// =====================================================================
// 2. LOGIKA EDIT PENGGUNA (UPDATE)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    
    $id = intval($_POST['edit_id'] ?? 0);
    $nama = $conn->real_escape_string($_POST['edit_nama'] ?? '');
    $email = $conn->real_escape_string($_POST['edit_email'] ?? '');

    if ($id > 0 && !empty($nama) && !empty($email)) {
        $sql_update = "UPDATE users SET nama = ?, email = ? WHERE id = ? AND role = 'petani'";
        
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssi", $nama, $email, $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                   $success_message = "Pengguna ID $id berhasil diupdate!";
            } else {
                   $error_message = "Gagal mengupdate. Pastikan ID ada dan perannya adalah Petani.";
            }
        } else {
            $error_message = "Error saat mengupdate pengguna: " . $stmt->error;
        }
        $stmt->close();
    }
}

// =====================================================================
// 3. LOGIKA HAPUS PENGGUNA (DELETE)
// =====================================================================
if (isset($_GET['action']) && $_GET['action'] === 'hapus') {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id > 0) {
        $sql_delete = "DELETE FROM users WHERE id = ? AND role = 'petani'";
        $stmt = $conn->prepare($sql_delete);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                   $success_message = "Pengguna ID $id berhasil dihapus.";
            } else {
                   $error_message = "Gagal menghapus. ID tidak ditemukan atau perannya bukan Petani.";
            }
            header("Location: kelola_pengguna.php?status=deleted");
            exit;
        } else {
            $error_message = "Error saat menghapus pengguna: " . $stmt->error;
        }
        $stmt->close();
    }
}

// =====================================================================
// 4. LOGIKA PENGAMBILAN DATA (READ) - HANYA ROLE 'petani'
// =====================================================================
$sql_read = "SELECT id, nama, email FROM users WHERE role = 'petani' ORDER BY id DESC";
$result = $conn->query($sql_read);

if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => $row['id'],
                'name' => $row['nama'],    
                'email' => $row['email'],
                'status' => 'Aktif' 
            ];
        }
    }
    $result->free(); 
} else {
    error_log("Query Error: " . $conn->error);
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        
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
        .content { margin-left: 260px; padding: 30px; }
        .header { background: white; padding: 25px 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.6rem; color: #2c3e50; font-weight: 600; }
        .user-badge { background: linear-gradient(135deg, #95e989ff 0%, #4ba24bff 100%); color: white; padding: 10px 20px; border-radius: 25px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .page-content { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .action-bar { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; } 
        .btn-primary { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: background-color 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary:hover { background-color: #45a049; }
        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th, .user-table td { padding: 15px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .user-table th { background-color: #f8f9fa; color: #2c3e50; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }
        .user-table tbody tr:hover { background-color: #f2f4f6; }
        .status-Aktif { background-color: #E8F5E9; color: #4CAF50; }
        .status-Nonaktif { background-color: #FFEBEE; color: #F44336; }
        .status-badge { padding: 5px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; display: inline-block; }
        .btn-action { padding: 8px; margin-right: 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 0.8rem; transition: background-color 0.3s; text-decoration: none; }
        .btn-edit { background-color: #2196F3; color: white; }
        .btn-delete { background-color: #F44336; color: white; }
        .btn-edit:hover { background-color: #1e88e5; }
        .btn-delete:hover { background-color: #e53935; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: none; justify-content: center; align-items: center; z-index: 1050; opacity: 0; transition: opacity 0.3s; }
        .modal-overlay.show { opacity: 1; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); transform: scale(0.9); transition: transform 0.3s; }
        .modal-overlay.show .modal-content { transform: scale(1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .modal-header h4 { margin: 0; font-weight: 600; color: #2c3e50; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #7f8c8d; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.95rem; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; }
        .btn-modal { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .btn-modal-primary { background-color: #4CAF50; color: white; }
        .btn-modal-secondary { background-color: #7f8c8d; color: white; margin-left: 10px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-error { background-color: #fceaeaea; color: #c0392b; border: 1px solid #e74c3c; }
        .alert-success { background-color: #e9f5e9; color: #27ae60; border: 1px solid #2ecc71; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .content { margin-left: 70px; padding: 15px; }
            .brand-logo { width: 50px; height: 50px; }
            .brand h2, .brand p, .nav-links span { display: none; }
            .user-table thead { display: none; }
            .user-table, .user-table tbody, .user-table tr, .user-table td { display: block; width: 100%; }
            .user-table tr { margin-bottom: 15px; border: 1px solid #ecf0f1; border-radius: 8px; box-shadow: 0 1px 5px rgba(0,0,0,0.05); }
            .user-table td { text-align: right; padding-left: 50%; position: relative; border: none; border-bottom: 1px dashed #ecf0f1; }
            .user-table td:last-child { border-bottom: none; }
            .user-table td::before { content: attr(data-label); position: absolute; left: 0; width: 45%; padding-left: 15px; font-weight: 600; color: #7f8c8d; text-align: left; }
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
            <li><a href="dashboard_admin.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a></li>
            <li><a href="kelola_pengguna.php" class="active">
                <i class="fas fa-users"></i>
                <span>Pengguna</span>
            </a></li>
            <li><a href="verifikasi.php"> 
                <i class="fas fa-check-double"></i>
                <span>Verifikasi Panen</span>
            </a></li>
            <li><a href="laporan.php">
                <i class="fas fa-chart-bar"></i>
                <span>Laporan Analisis</span>
            </a></li>
            <li><a href="geografis.php">
                <i class="fas fa-map"></i>
                <span>Geografis</span>
            </a></li>
            <li><a href="pengaturan.php">
                <i class="fas fa-cog"></i>
                <span>Pengaturan</span>
            </a></li>
            <li style="margin-top: 20px;"><a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a></li>
        </ul>
        </div>

    <div class="content">
        <div class="header">
            <h1>Kelola Akun Petani 🧑‍🌾</h1>
            <div class="user-badge">
                <i class="fas fa-user-shield"></i>Administrator
            </div>
        </div>

        <div class="page-content">
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><i class="fas fa-times-circle"></i><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message) || (isset($_GET['status']) && $_GET['status'] === 'deleted')): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i>
                    <?php 
                    echo $success_message;
                    if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
                        echo "Pengguna berhasil dihapus.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="action-bar">
                <button type="button" class="btn-primary" onclick="openModal('tambahModal')">
                    <i class="fas fa-plus"></i> Tambah Petani Baru
                </button>
                <div class="search-bar">
                    <input type="text" placeholder="Cari Petani..." style="padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
                </div>
            </div>

            <div class="table-responsive">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td data-label="ID"><?php echo htmlspecialchars($user['id']); ?></td>
                                <td data-label="Nama"><?php echo htmlspecialchars($user['name']); ?></td>
                                <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-Aktif">
                                        <?php echo htmlspecialchars($user['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Aksi">
                                    <button type="button" class="btn-action btn-edit" title="Edit" 
                                             onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="kelola_pengguna.php?action=hapus&id=<?php echo $user['id']; ?>" class="btn-action btn-delete" title="Hapus" onclick="return confirm('Yakin ingin menghapus petani <?php echo htmlspecialchars($user['name']); ?>?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #7f8c8d;">Tidak ada data petani ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="tambahModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="modalTitle">Tambah Petani Baru</h4>
                <button type="button" class="modal-close" onclick="closeModal('tambahModal')">&times;</button>
            </div>
            <form action="kelola_pengguna.php" method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="form-group">
                    <label for="nama">Nama Lengkap Petani</label>
                    <input type="text" id="nama" name="nama" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Kata Sandi Awal</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <small style="color: #7f8c8d; display: block; margin-top: 15px;">* Pengguna ini akan otomatis ditetapkan sebagai Petani.</small>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn-modal btn-modal-primary"><i class="fas fa-save"></i> Simpan Petani</button>
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('tambahModal')">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="editModalTitle">Edit Petani</h4>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form action="kelola_pengguna.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="form-group">
                    <label for="edit_nama">Nama Lengkap</label>
                    <input type="text" id="edit_nama" name="edit_nama" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Alamat Email</label>
                    <input type="email" id="edit_email" name="edit_email" required>
                </div>
                
                <small style="color: #c0392b; display: block; margin-top: 15px;">* Hanya Nama dan Email yang dapat diubah. Untuk sandi, melalui halaman profil.</small>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn-modal btn-modal-primary"><i class="fas fa-sync"></i> Update Petani</button>
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('editModal')">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function openEditModal(id, nama, email) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_email').value = email;
            
            document.getElementById('editModalTitle').textContent = `Edit Petani ID: ${id}`;
            
            openModal('editModal');
        }

        <?php if (!empty($error_message) && isset($_POST['action']) && $_POST['action'] === 'tambah'): ?>
            openModal('tambahModal');
        <?php endif; ?>

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeModal(e.target.id);
            }
        });
    </script>

</body>
</html>