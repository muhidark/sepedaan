<?php
require_once 'config.php';

// Jika sudah login sebagai admin, langsung ke admin.php
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Kredensial Admin Default (Bisa diubah di kode ini)
    $valid_username = 'admin';
    $valid_password = 'password123';

    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Username atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Race Control</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 400px; margin-top: 10vh;">
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <h1 style="font-size: 1.5rem; font-weight: 800; background: linear-gradient(45deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Yuk.Sepedaan Keliling Pulau Kalimantan</h1>
        </div>

        <div class="glass-card" style="border-top: 4px solid var(--accent);">
            <h1 class="header-title" style="font-size: 1.4rem; color: var(--primary); -webkit-text-fill-color: var(--primary);">Admin Panel</h1>
            <p class="header-subtitle">Silakan masuk untuk mengelola perlombaan.</p>
            
            <?php if($error): ?>
                <div class="scan-status error mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username admin" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 1rem; background: linear-gradient(45deg, #f72585, #7209b7);">🔒 Masuk Admin</button>
            </form>
            
            <a href="index.php" class="auth-link" style="margin-top: 2rem;">Kembali ke Aplikasi Peserta</a>
        </div>
        
    </div>
</body>
</html>
