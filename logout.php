<?php
// =====================================================================
// logout.php - Script untuk mengakhiri sesi dan mengalihkan pengguna
// =====================================================================

// 1. Mulai sesi
session_start();

// 2. Hancurkan semua data sesi
$_SESSION = array();

// 3. Jika sesi menggunakan cookie, paksa kedaluwarsa cookie sesi
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Hancurkan sesi secara keseluruhan
session_destroy();

// 5. Alihkan ke halaman beranda (index.php)
header("Location: index.php");
exit;
?>