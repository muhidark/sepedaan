<?php
require_once 'config.php';

// Hapus sesi admin secara spesifik
if (isset($_SESSION['admin_logged_in'])) {
    unset($_SESSION['admin_logged_in']);
}

header('Location: login_admin.php');
exit;
?>
