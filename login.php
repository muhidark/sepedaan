<?php
require_once 'config.php';

if (isset($_SESSION['peserta_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_wa = $conn->real_escape_string($_POST['no_wa']);
    $pin = $_POST['pin'];
    
    $result = $conn->query("SELECT id, nama, pin FROM peserta WHERE no_wa = '$no_wa'");
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($pin, $row['pin'])) {
            $_SESSION['peserta_id'] = $row['id'];
            $_SESSION['nama'] = $row['nama'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'PIN salah.';
        }
    } else {
        $error = 'Nomor WhatsApp tidak ditemukan.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Self Check-in Sepeda</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 400px; margin-top: 5vh;">
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <p style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.2rem;">Yuk.Sepedaan</p>
            <h1 style="font-size: 1.5rem; font-weight: 800; background: linear-gradient(45deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem;">Keliling Pulau Kalimantan</h1>
            <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.4; padding: 0 10px;">
                Challenge gowes 20 km rute Pulau Kalimantan di Sampit. Catat waktumu dan pantau leaderboard secara realtime!
            </p>
        </div>

        <div class="glass-card">
            <h1 class="header-title">Selamat Datang</h1>
            <p class="header-subtitle">Masuk untuk melakukan check-in dan melihat progres Anda.</p>
            
            <?php if($error): ?>
                <div class="scan-status error mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Nomor WhatsApp</label>
                    <input type="text" name="no_wa" class="form-control" placeholder="08123456789" required>
                </div>
                <div class="form-group">
                    <label>PIN (6 Angka)</label>
                    <input type="password" name="pin" class="form-control" placeholder="******" required>
                </div>
                
                <button type="submit" class="btn btn-primary mt-4">Masuk</button>
            </form>
            
            <a href="register.php" class="auth-link">Belum punya akun? Daftar di sini</a>
        </div>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-light); font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
            Sistem Informasi Race Control V.2.0<br/>
            <?php echo date('Y'); ?> by Politeknik Sampit X <a href="https://www.instagram.com/yuk.sepedaan/" target="_blank" style="color: var(--primary-light); text-decoration: none; font-weight: 600;">@yuk.sepedaan</a>
        </div>
        
    </div>
</body>
</html>
