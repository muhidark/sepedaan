<?php
require_once 'config.php';

// Validasi Admin Login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login_admin.php');
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// --- LOGIKA AKSI (DELETE, UPDATE) ---

// 1. Aksi Hapus Peserta
if (isset($_GET['action']) && $_GET['action'] == 'delete_peserta' && isset($_GET['id'])) {
    $id_hapus = (int)$_GET['id'];
    $conn->query("DELETE FROM peserta WHERE id = $id_hapus");
    header("Location: admin.php?page=peserta&msg=deleted");
    exit;
}

// 2. Aksi Edit Peserta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_peserta'])) {
    $id = (int)$_POST['id'];
    $nama = $conn->real_escape_string($_POST['nama']);
    $no_wa = $conn->real_escape_string($_POST['no_wa']);
    $akun_ig = $conn->real_escape_string($_POST['akun_ig']);
    $conn->query("UPDATE peserta SET nama='$nama', no_wa='$no_wa', akun_ig='$akun_ig' WHERE id=$id");
    header("Location: admin.php?page=peserta&msg=updated");
    exit;
}

// 2b. Aksi Reset PIN
if (isset($_GET['action']) && $_GET['action'] == 'reset_pin' && isset($_GET['id'])) {
    $id_reset = (int)$_GET['id'];
    $pin_default = password_hash('123456', PASSWORD_DEFAULT);
    $conn->query("UPDATE peserta SET pin = '$pin_default' WHERE id = $id_reset");
    header("Location: admin.php?page=peserta&msg=pin_reset");
    exit;
}

// 3. Aksi Update Pengaturan (Waktu & COT)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pengaturan'])) {
    $waktu_min = $conn->real_escape_string($_POST['waktu_start_min']);
    $waktu_max = $conn->real_escape_string($_POST['waktu_start_max']);
    $batas_cot = $conn->real_escape_string($_POST['batas_cot']);
    $tanggal_acara = $conn->real_escape_string($_POST['tanggal_acara']);
    
    $conn->query("UPDATE pengaturan SET nilai = '$waktu_min' WHERE kunci = 'waktu_start_min'");
    $conn->query("UPDATE pengaturan SET nilai = '$waktu_max' WHERE kunci = 'waktu_start_max'");
    $conn->query("UPDATE pengaturan SET nilai = '$batas_cot' WHERE kunci = 'batas_cot'");
    
    $cek_tgl = $conn->query("SELECT * FROM pengaturan WHERE kunci = 'tanggal_acara'");
    if ($cek_tgl->num_rows > 0) {
        $conn->query("UPDATE pengaturan SET nilai = '$tanggal_acara' WHERE kunci = 'tanggal_acara'");
    } else {
        $conn->query("INSERT INTO pengaturan (kunci, nilai) VALUES ('tanggal_acara', '$tanggal_acara')");
    }
    
    $msg_pengaturan = "Pengaturan waktu berhasil disimpan!";
}

// 4. Aksi Update Lokasi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_lokasi'])) {
    $kode_qr = $conn->real_escape_string($_POST['kode_qr']);
    $nama_titik = $conn->real_escape_string($_POST['nama_titik']);
    $lat = $conn->real_escape_string($_POST['latitude']);
    $lng = $conn->real_escape_string($_POST['longitude']);
    
    $conn->query("UPDATE titik_checkin SET nama_titik='$nama_titik', latitude='$lat', longitude='$lng' WHERE kode_qr='$kode_qr'");
    $msg_lokasi = "Titik lokasi $nama_titik berhasil diperbarui!";
}

// 5. Aksi Reset Riwayat Scan
if (isset($_GET['action']) && $_GET['action'] == 'reset_scans') {
    $conn->query("TRUNCATE TABLE riwayat_checkin"); // Hapus semua data riwayat scan
    header("Location: admin.php?page=pengaturan&msg=reset_success");
    exit;
}

// --- AMBIL DATA UMUM ---
$pengaturan = [];
$res_pengaturan = $conn->query("SELECT * FROM pengaturan");
if ($res_pengaturan) {
    while ($row = $res_pengaturan->fetch_assoc()) {
        $pengaturan[$row['kunci']] = $row['nilai'];
    }
}
$batas_cot = isset($pengaturan['batas_cot']) ? (int)$pengaturan['batas_cot'] : 180;
$batas_cot_detik = $batas_cot * 60;

// Fungsi helper status
function getStatusBadge($status) {
    if ($status == 'FINISHER') return 'background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'Over COT') return 'background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'ON PROGRESS') return 'background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'DNF') return 'background: #ef4444; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'DNS') return 'background: #6b7280; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    return '';
}

// Ambil semua data peserta dan statusnya
$sql_all = "
SELECT 
    p.id, p.nama, p.no_wa, p.akun_ig,
    MAX(CASE WHEN t.urutan = 1 THEN r.waktu_tercatat END) as waktu_start,
    MAX(CASE WHEN t.urutan = 2 THEN r.waktu_tercatat END) as waktu_cp1,
    MAX(CASE WHEN t.urutan = 3 THEN r.waktu_tercatat END) as waktu_finish
FROM peserta p
LEFT JOIN riwayat_checkin r ON p.id = r.peserta_id
LEFT JOIN titik_checkin t ON r.kode_qr = t.kode_qr
GROUP BY p.id
";
$result_all = $conn->query($sql_all);

$all_participants = [];
$stats = [
    'total' => 0,
    'start' => 0,
    'cp1' => 0,
    'finish' => 0
];

if ($result_all && $result_all->num_rows > 0) {
    while ($row = $result_all->fetch_assoc()) {
        $stats['total']++;
        if ($row['waktu_start']) $stats['start']++;
        if ($row['waktu_cp1']) $stats['cp1']++;
        if ($row['waktu_finish']) $stats['finish']++;

        $status = '';
        $durasi_detik = null;
        $durasi_format = '-';
        
        if (empty($row['waktu_start'])) {
            $status = 'DNS';
        } else if (empty($row['waktu_finish'])) {
            $elapsed = time() - strtotime($row['waktu_start']);
            if ($elapsed > $batas_cot_detik) {
                $status = 'DNF';
            } else {
                $status = 'ON PROGRESS';
            }
        } else {
            $durasi_detik = strtotime($row['waktu_finish']) - strtotime($row['waktu_start']);
            $jam = floor($durasi_detik / 3600);
            $menit = floor(($durasi_detik % 3600) / 60);
            $detik = $durasi_detik % 60;
            $durasi_format = sprintf("%02d:%02d:%02d", $jam, $menit, $detik);
            
            if ($durasi_detik > $batas_cot_detik) {
                $status = 'Over COT';
            } else {
                $status = 'FINISHER';
            }
        }
        
        $row['status'] = $status;
        $row['durasi_detik'] = $durasi_detik;
        $row['durasi_format'] = $durasi_format;
        $all_participants[] = $row;
    }
}

// Sort untuk leaderboard (Finisher dulu, lalu Over COT, DNF, DNS)
$finishers = [];
$others = [];
foreach ($all_participants as $p) {
    if ($p['status'] == 'FINISHER') $finishers[] = $p;
    else $others[] = $p;
}
usort($finishers, function($a, $b) { return $a['durasi_detik'] <=> $b['durasi_detik']; });
usort($others, function($a, $b) {
    $order = ['Over COT' => 1, 'ON PROGRESS' => 2, 'DNF' => 3, 'DNS' => 4];
    $rankA = isset($order[$a['status']]) ? $order[$a['status']] : 99;
    $rankB = isset($order[$b['status']]) ? $order[$b['status']] : 99;
    
    if ($rankA != $rankB) {
        return $rankA <=> $rankB;
    }
    if ($a['status'] == 'Over COT') {
        if ($a['durasi_detik'] == $b['durasi_detik']) {
             return strcasecmp($a['nama'], $b['nama']);
        }
        return $a['durasi_detik'] <=> $b['durasi_detik'];
    }
    return strcasecmp($a['nama'], $b['nama']);
});
$leaderboard = [];
$rank = 1;
foreach ($finishers as $f) { $f['rank'] = $rank++; $leaderboard[] = $f; }
foreach ($others as $o) { $o['rank'] = '-'; $leaderboard[] = $o; }

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Race Control</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        
        <!-- MOBILE HEADER -->
        <div class="admin-mobile-header">
            <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--primary); margin: 0;">RACE CONTROL</h2>
            <div style="display: flex; gap: 0.8rem;">
                <a href="index.php" target="_blank" style="color: var(--primary-light);"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></a>
                <a href="logout_admin.php" style="color: var(--error);"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></a>
            </div>
        </div>
        
        <!-- MOBILE BOTTOM NAV -->
        <nav class="admin-bottom-nav">
            <a href="?page=dashboard" class="bottom-nav-link <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                Dashboard
            </a>
            <a href="?page=peserta" class="bottom-nav-link <?php echo $page == 'peserta' ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Peserta
            </a>
            <a href="?page=pengaturan" class="bottom-nav-link <?php echo $page == 'pengaturan' ? 'active' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Pengaturan
            </a>
        </nav>

        <!-- SIDEBAR -->
        <aside class="admin-sidebar">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem; background: linear-gradient(45deg, var(--primary), var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">RACE CONTROL</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 2rem; font-weight: 600;">Admin Panel v2.0</p>
            </div>
            
            <a href="?page=dashboard" class="sidebar-link <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                📊 Dashboard
            </a>
            <a href="?page=pengaturan" class="sidebar-link <?php echo $page == 'pengaturan' ? 'active' : ''; ?>">
                ⚙️ Pengaturan
            </a>
            <a href="?page=peserta" class="sidebar-link <?php echo $page == 'peserta' ? 'active' : ''; ?>">
                👥 Peserta
            </a>
            
            <div style="margin-top: auto;">
                <a href="index.php" target="_blank" class="sidebar-link" style="font-size: 0.85rem; color: var(--primary-light);">
                    ↗ Buka Web Peserta
                </a>
                <a href="logout_admin.php" class="sidebar-link" style="font-size: 0.85rem; color: var(--error); margin-top: 0.5rem; background: rgba(239, 68, 68, 0.1);">
                    Keluar (Logout)
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="admin-content">
            
            <?php if ($page == 'dashboard'): ?>
                <h1 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 1.5rem;">Dashboard Ringkasan</h1>
                
                <div class="summary-grid">
                    <div class="summary-card">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Peserta</p>
                    </div>
                    <div class="summary-card">
                        <h3><?php echo $stats['start']; ?></h3>
                        <p>Check-in Start</p>
                    </div>
                    <div class="summary-card">
                        <h3><?php echo $stats['cp1']; ?></h3>
                        <p>Lolos CP 1</p>
                    </div>
                    <div class="summary-card">
                        <h3><?php echo $stats['finish']; ?></h3>
                        <p>Total Finisher</p>
                    </div>
                </div>

                <div class="glass-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <h3 style="margin: 0; font-size: 1.1rem;">Leaderboard & Rekap</h3>
                        <a href="export_excel.php" target="_blank" class="btn btn-primary" style="width: auto; padding: 0.5rem 1rem; font-size: 0.85rem;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Export Excel</a>
                    </div>
                    
                    <div class="table-container">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Peserta</th>
                                    <th>Waktu Start</th>
                                    <th>Waktu CP1</th>
                                    <th>Waktu Finish</th>
                                    <th>Durasi</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leaderboard)): ?>
                                    <tr><td colspan="6" class="text-center">Belum ada data.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($leaderboard as $lb): ?>
                                        <tr>
                                            <td>
                                                <?php if ($lb['rank'] !== '-'): ?>
                                                    <span class="rank-badge" style="background: var(--primary);"><?php echo $lb['rank']; ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-weight: bold;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($lb['nama']); ?></div>
                                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($lb['no_wa']); ?></div>
                                            </td>
                                            <td><?php echo $lb['waktu_start'] ? date('H:i:s', strtotime($lb['waktu_start'])) : '-'; ?></td>
                                            <td><?php echo $lb['waktu_cp1'] ? date('H:i:s', strtotime($lb['waktu_cp1'])) : '-'; ?></td>
                                            <td><?php echo $lb['waktu_finish'] ? date('H:i:s', strtotime($lb['waktu_finish'])) : '-'; ?></td>
                                            <td style="font-weight: 600; color: var(--primary-light);"><?php echo $lb['durasi_format']; ?></td>
                                            <td><span style="<?php echo getStatusBadge($lb['status']); ?>"><?php echo $lb['status']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- MOBILE LIST VIEW -->
                    <div class="leaderboard-list-mobile">
                        <?php if (empty($leaderboard)): ?>
                            <div class="text-center">Belum ada data.</div>
                        <?php else: ?>
                            <?php foreach ($leaderboard as $lb): ?>
                                <div class="lb-card">
                                    <div class="lb-card-header">
                                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                                            <?php if ($lb['rank'] !== '-'): ?>
                                                <span class="rank-badge" style="background: var(--primary);"><?php echo $lb['rank']; ?></span>
                                            <?php else: ?>
                                                <span class="rank-badge" style="background: var(--border-light); color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight: 800; color: var(--text-main); font-size: 1rem;"><?php echo htmlspecialchars($lb['nama']); ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($lb['no_wa']); ?></div>
                                            </div>
                                        </div>
                                        <div><span style="<?php echo getStatusBadge($lb['status']); ?>"><?php echo $lb['status']; ?></span></div>
                                    </div>
                                    <div class="lb-card-body">
                                        <div><span>Start</span> <strong><?php echo $lb['waktu_start'] ? date('H:i', strtotime($lb['waktu_start'])) : '-'; ?></strong></div>
                                        <div><span>CP 1</span> <strong><?php echo $lb['waktu_cp1'] ? date('H:i', strtotime($lb['waktu_cp1'])) : '-'; ?></strong></div>
                                        <div><span>Finish</span> <strong><?php echo $lb['waktu_finish'] ? date('H:i', strtotime($lb['waktu_finish'])) : '-'; ?></strong></div>
                                        <div><span>Durasi</span> <strong style="color: var(--primary-light);"><?php echo $lb['durasi_format']; ?></strong></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($page == 'pengaturan'): ?>
                <h1 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 1.5rem;">Pengaturan Sistem</h1>
                
                <?php if(isset($msg_pengaturan)): ?>
                    <div class="scan-status success mb-4"><?php echo $msg_pengaturan; ?></div>
                <?php endif; ?>
                <?php if(isset($msg_lokasi)): ?>
                    <div class="scan-status success mb-4"><?php echo $msg_lokasi; ?></div>
                <?php endif; ?>
                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'reset_success'): ?>
                    <div class="scan-status success mb-4">Seluruh data riwayat scan berhasil dihapus/direset!</div>
                <?php endif; ?>

                <div class="glass-card mb-4">
                    <h3 style="margin-bottom: 1rem;">Aturan Waktu (Pelaksanaan & COT)</h3>
                    <form method="POST" action="">
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                            <div class="form-group" style="flex: 1; min-width: 180px;">
                                <label>Tanggal Mulai Acara</label>
                                <input type="date" name="tanggal_acara" class="form-control" value="<?php echo isset($pengaturan['tanggal_acara']) ? $pengaturan['tanggal_acara'] : date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 150px;">
                                <label>Batas Awal Start</label>
                                <input type="time" step="1" name="waktu_start_min" class="form-control" value="<?php echo isset($pengaturan['waktu_start_min']) ? $pengaturan['waktu_start_min'] : '15:30:00'; ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 150px;">
                                <label>Batas Akhir Start</label>
                                <input type="time" step="1" name="waktu_start_max" class="form-control" value="<?php echo isset($pengaturan['waktu_start_max']) ? $pengaturan['waktu_start_max'] : '16:00:00'; ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 120px;">
                                <label>COT (Menit)</label>
                                <input type="number" name="batas_cot" class="form-control" value="<?php echo htmlspecialchars($batas_cot); ?>" required>
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 150px;">
                                <button type="submit" name="update_pengaturan" class="btn btn-primary" style="padding: 0.8rem 1rem;">Simpan Aturan</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="glass-card mb-4">
                    <h3 style="margin-bottom: 1rem;">Koordinat Pos Checkpoint</h3>
                    <div class="table-container">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>Kode QR</th>
                                    <th>Nama Titik</th>
                                    <th>Latitude</th>
                                    <th>Longitude</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $res_titik = $conn->query("SELECT * FROM titik_checkin ORDER BY urutan ASC");
                                while($titik = $res_titik->fetch_assoc()):
                                ?>
                                <tr>
                                    <form method="POST" action="">
                                        <input type="hidden" name="kode_qr" value="<?php echo htmlspecialchars($titik['kode_qr']); ?>">
                                        <td><span style="font-family: monospace; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px;"><?php echo htmlspecialchars($titik['kode_qr']); ?></span></td>
                                        <td><input type="text" name="nama_titik" class="form-control" value="<?php echo htmlspecialchars($titik['nama_titik']); ?>" style="padding: 0.4rem; font-size: 0.85rem;" required></td>
                                        <td><input type="text" name="latitude" class="form-control" value="<?php echo htmlspecialchars($titik['latitude']); ?>" style="padding: 0.4rem; font-size: 0.85rem;" required></td>
                                        <td><input type="text" name="longitude" class="form-control" value="<?php echo htmlspecialchars($titik['longitude']); ?>" style="padding: 0.4rem; font-size: 0.85rem;" required></td>
                                        <td><button type="submit" name="update_lokasi" class="btn btn-secondary" style="padding: 0.4rem 1rem; width: auto; font-size: 0.8rem;">Simpan</button></td>
                                    </form>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="glass-card mb-4">
                    <h3 style="margin-bottom: 1rem;">Generate QR Code Banner</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">Klik pada kode QR untuk mengunduhnya.</p>
                    <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                        <?php 
                        $res_titik->data_seek(0);
                        while($titik = $res_titik->fetch_assoc()):
                            $color_border = '#cbd5e1';
                            $color_bg = '#334155';
                            if ($titik['urutan'] == 1) { $color_border = '#16a34a'; $color_bg = '#16a34a'; } // Green for Start
                            else if ($titik['urutan'] == 2) { $color_border = '#f59e0b'; $color_bg = '#f59e0b'; } // Amber for CP1
                            else if ($titik['urutan'] == 3) { $color_border = '#dc2626'; $color_bg = '#dc2626'; } // Red for Finish
                        ?>
                        <div style="background: white; padding: 1.5rem; border-radius: 16px; border: 4px solid <?php echo $color_border; ?>; text-align: center; color: black; box-shadow: 0 4px 15px rgba(0,0,0,0.2); cursor: pointer; transition: transform 0.2s;" onclick="downloadQR('qr-<?php echo $titik['urutan']; ?>', '<?php echo $titik['nama_titik']; ?>')" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            <div style="display: inline-block; background: <?php echo $color_bg; ?>; color: white; padding: 4px 16px; border-radius: 999px; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 1rem;">POS CHECKPOINT</div>
                            <h4 style="font-size: 2rem; font-weight: 900; margin-bottom: 1rem; text-transform: uppercase; color: #1e293b;"><?php echo htmlspecialchars($titik['nama_titik']); ?></h4>
                            <div id="qr-<?php echo $titik['urutan']; ?>" data-kode="<?php echo htmlspecialchars($titik['kode_qr']); ?>" style="display: inline-block; padding: 10px; border: 2px solid #e2e8f0; border-radius: 12px; background: white;"></div>
                            <p style="font-size: 0.8rem; color: #64748b; margin-top: 1rem; font-family: monospace; border-top: 1px solid #e2e8f0; padding-top: 0.5rem;">KODE: <?php echo htmlspecialchars($titik['kode_qr']); ?></p>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="glass-card">
                    <h3 style="margin-bottom: 1rem; color: var(--error);">Zona Bahaya (Reset Data)</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                        Fitur ini digunakan khusus untuk proses Testing (Uji Coba). Jika diklik, seluruh riwayat jam dan lokasi <i>check-in</i> seluruh peserta akan dihapus, namun data diri/akun peserta akan tetap aman.
                    </p>
                    <a href="?action=reset_scans" onclick="return confirm('Apakah Anda super yakin ingin menghapus SEMUA data scan peserta saat ini? (Tindakan ini tidak bisa dibatalkan)');" class="btn" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; width: auto; display: inline-block;">
                        ⚠️ Reset Riwayat Scan Peserta
                    </a>
                </div>

                <script>
                    window.addEventListener('DOMContentLoaded', () => {
                        document.querySelectorAll('[id^="qr-"]').forEach(el => {
                            new QRCode(el, {
                                text: el.getAttribute('data-kode'),
                                width: 180,
                                height: 180,
                                colorDark: "#000000",
                                colorLight: "#ffffff",
                                correctLevel: QRCode.CorrectLevel.H
                            });
                        });
                    });
                    
                    function downloadQR(elementId, name) {
                        const img = document.querySelector(`#${elementId} img`);
                        if(img && img.src) {
                            const a = document.createElement('a');
                            a.href = img.src;
                            a.download = `QR_${name}.png`;
                            a.click();
                        } else {
                            // Fallback for canvas if img not ready
                            const canvas = document.querySelector(`#${elementId} canvas`);
                            if(canvas) {
                                const a = document.createElement('a');
                                a.href = canvas.toDataURL("image/png");
                                a.download = `QR_${name}.png`;
                                a.click();
                            }
                        }
                    }
                </script>

            <?php elseif ($page == 'peserta'): ?>
                <h1 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 1.5rem;">Manajemen Peserta</h1>
                
                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                    <div class="scan-status success mb-4">Peserta berhasil dihapus.</div>
                <?php endif; ?>
                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
                    <div class="scan-status success mb-4">Data peserta berhasil diperbarui.</div>
                <?php endif; ?>
                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'pin_reset'): ?>
                    <div class="scan-status success mb-4">PIN peserta berhasil direset menjadi: 123456</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])): 
                    $id_edit = (int)$_GET['id'];
                    $res_edit = $conn->query("SELECT * FROM peserta WHERE id = $id_edit");
                    if ($res_edit && $res_edit->num_rows > 0):
                        $p_edit = $res_edit->fetch_assoc();
                ?>
                    <div class="glass-card mb-4">
                        <h3 style="margin-bottom: 1rem;">Edit Peserta: <?php echo htmlspecialchars($p_edit['nama']); ?></h3>
                        <form method="POST" action="admin.php?page=peserta">
                            <input type="hidden" name="id" value="<?php echo $p_edit['id']; ?>">
                            <div class="form-group">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control" value="<?php echo htmlspecialchars($p_edit['nama']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Nomor WhatsApp</label>
                                <input type="text" name="no_wa" class="form-control" value="<?php echo htmlspecialchars($p_edit['no_wa']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Akun IG</label>
                                <input type="text" name="akun_ig" class="form-control" value="<?php echo htmlspecialchars($p_edit['akun_ig']); ?>" required>
                            </div>
                            <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                                <button type="submit" name="edit_peserta" class="btn btn-primary" style="width: auto; padding: 0.8rem 2rem;">Simpan Perubahan</button>
                                <a href="?page=peserta&action=reset_pin&id=<?php echo $p_edit['id']; ?>" class="btn btn-secondary" onclick="return confirm('Apakah Anda yakin ingin mereset PIN peserta ini menjadi 123456?');" style="width: auto; padding: 0.8rem 2rem; text-decoration: none; background: #eab308; color: white;">🔑 Reset PIN ke 123456</a>
                                <a href="?page=peserta" class="btn btn-secondary" style="width: auto; padding: 0.8rem 2rem; text-decoration: none;">Batal</a>
                            </div>
                        </form>
                    </div>
                <?php endif; endif; ?>

                <div class="glass-card">
                    <div class="table-container">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Peserta</th>
                                    <th>WhatsApp</th>
                                    <th>Instagram</th>
                                    <th>Status Terkini</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_participants)): ?>
                                    <tr><td colspan="6" class="text-center">Belum ada peserta terdaftar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($all_participants as $p): ?>
                                        <tr>
                                            <td><?php echo $p['id']; ?></td>
                                            <td style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($p['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($p['no_wa']); ?></td>
                                            <td><?php echo htmlspecialchars($p['akun_ig']); ?></td>
                                            <td><span style="<?php echo getStatusBadge($p['status']); ?>"><?php echo $p['status']; ?></span></td>
                                            <td style="display: flex; gap: 0.5rem;">
                                                <a href="?page=peserta&action=edit&id=<?php echo $p['id']; ?>" style="background: var(--primary-light); color: white; padding: 4px 10px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: bold;">Edit</a>
                                                <a href="?page=peserta&action=delete_peserta&id=<?php echo $p['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus peserta ini? Semua riwayat check-in-nya juga akan terhapus.');" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 0.8rem;">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>