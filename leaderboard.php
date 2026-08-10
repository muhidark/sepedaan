<?php
require_once 'config.php';

if (!isset($_SESSION['peserta_id'])) {
    header('Location: login.php');
    exit;
}

$peserta_id = $_SESSION['peserta_id'];
$nama_saya = $_SESSION['nama'];

// Ambil batas COT
$pengaturan = [];
$res_pengaturan = $conn->query("SELECT * FROM pengaturan");
if ($res_pengaturan) {
    while ($row = $res_pengaturan->fetch_assoc()) {
        $pengaturan[$row['kunci']] = $row['nilai'];
    }
}
$batas_cot = isset($pengaturan['batas_cot']) ? (int)$pengaturan['batas_cot'] : 180;
$batas_cot_detik = $batas_cot * 60;

// Get all participants
$sql = "
SELECT 
    p.id, 
    p.nama, 
    p.akun_ig,
    MAX(CASE WHEN t.urutan = 1 THEN r.waktu_tercatat END) as waktu_start,
    MAX(CASE WHEN t.urutan = 3 THEN r.waktu_tercatat END) as waktu_finish
FROM peserta p
LEFT JOIN riwayat_checkin r ON p.id = r.peserta_id
LEFT JOIN titik_checkin t ON r.kode_qr = t.kode_qr
GROUP BY p.id
";

$result = $conn->query($sql);

$finishers = [];
$others = [];

$peringkat_saya = '-';
$durasi_saya = '-';
$keterangan_saya = '-';

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
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
            
            // Format durasi
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
        
        if ($status == 'FINISHER') {
            $finishers[] = $row;
        } else {
            $others[] = $row;
        }
    }
}

// Sort finishers by duration
usort($finishers, function($a, $b) {
    return $a['durasi_detik'] <=> $b['durasi_detik'];
});

// Sort others: Over COT first (by duration), then DNF, then DNS
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

// Combine and assign ranks
$leaderboard = [];
$rank = 1;

foreach ($finishers as $f) {
    $f['rank'] = $rank;
    if ($f['id'] == $peserta_id) {
        $peringkat_saya = $rank;
        $durasi_saya = $f['durasi_format'];
        $keterangan_saya = $f['status'];
    }
    $leaderboard[] = $f;
    $rank++;
}

foreach ($others as $o) {
    $o['rank'] = '-';
    if ($o['id'] == $peserta_id) {
        $peringkat_saya = '-';
        $durasi_saya = $o['durasi_format'];
        $keterangan_saya = $o['status'];
    }
    $leaderboard[] = $o;
}

// Pagination logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$total_items = count($leaderboard);
$total_pages = ceil($total_items / $limit) ?: 1;
$offset = ($page - 1) * $limit;
$paged_leaderboard = array_slice($leaderboard, $offset, $limit);

// Helper badge color
function getStatusBadge($status) {
    if ($status == 'FINISHER') return 'background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'Over COT') return 'background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'ON PROGRESS') return 'background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'DNF') return 'background: #ef4444; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'DNS') return 'background: #6b7280; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    return '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Self Check-in Sepeda</title>
    <link rel="stylesheet" href="style.css">
    <style>
        @media (max-width: 768px) {
            .leaderboard-table {
                display: table !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <p style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.2rem;">Yuk.Sepedaan</p>
            <h1 style="font-size: 1.5rem; font-weight: 800; background: linear-gradient(45deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem;">Keliling Pulau Kalimantan</h1>
            <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.4; padding: 0 10px;">
                Challenge gowes 20 km rute Pulau Kalimantan di Sampit. Catat waktumu dan pantau leaderboard secara realtime!
            </p>
        </div>
        
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">Check-in</a>
            <a href="leaderboard.php" class="nav-tab active">Leaderboard</a>
        </div>

        <div class="glass-card">
            
            <div class="leaderboard-top">
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($nama_saya); ?></h3>
                    <p>Waktu: <?php echo $durasi_saya; ?></p>
                    <div style="margin-top: 4px; display: flex; gap: 0.5rem; align-items: center;">
                        <span style="<?php echo getStatusBadge($keterangan_saya); ?>"><?php echo $keterangan_saya; ?></span>
                        <?php if ($keterangan_saya == 'FINISHER'): ?>
                            <a href="sertifikat.php?id=<?php echo $peserta_id; ?>" target="_blank" style="background: #FBBF24; color: #1E293B; padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; text-decoration: none; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">🏅 Unduh Sertifikat</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="user-rank">
                    <?php echo $peringkat_saya !== '-' ? '#' . $peringkat_saya : '-'; ?>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;">Tabel Peringkat</h3>
                <a href="leaderboard_detail.php" class="btn btn-secondary" style="width: auto; padding: 0.4rem 0.8rem; font-size: 0.8rem; border-radius: 8px;">Lihat Detail</a>
            </div>
            
            <div class="table-container">
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Peserta</th>
                            <th>Waktu</th>
                            <th>Ket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaderboard)): ?>
                            <tr>
                                <td colspan="4" class="text-center" style="padding: 2rem; color: var(--text-muted);">Belum ada data peserta.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paged_leaderboard as $lb): ?>
                                <tr class="<?php echo $lb['rank'] !== '-' ? 'rank-' . $lb['rank'] : ''; ?>">
                                    <td>
                                        <?php if ($lb['rank'] !== '-'): ?>
                                            <span class="rank-badge"><?php echo $lb['rank']; ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-weight: bold;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($lb['nama']); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                                            <?php $ig_clean = ltrim($lb['akun_ig'], '@'); ?>
                                            <a href="https://www.instagram.com/<?php echo htmlspecialchars($ig_clean); ?>" target="_blank" style="color: var(--primary-light); text-decoration: none;">
                                                @<?php echo htmlspecialchars($ig_clean); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td style="font-weight: 600; color: var(--primary-light);">
                                        <?php echo $lb['durasi_format']; ?>
                                    </td>
                                    <td>
                                        <span style="<?php echo getStatusBadge($lb['status']); ?>"><?php echo $lb['status']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1rem; margin-bottom: 0.5rem; flex-wrap: nowrap;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.9rem;">&laquo; Prev</a>
                <?php else: ?>
                    <span style="color: var(--text-muted); font-weight: 600; font-size: 0.9rem; opacity: 0.5;">&laquo; Prev</span>
                <?php endif; ?>
                
                <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-main); white-space: nowrap;">
                    Hal <?php echo $page; ?> dari <?php echo $total_pages; ?>
                </span>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.9rem;">Next &raquo;</a>
                <?php else: ?>
                    <span style="color: var(--text-muted); font-weight: 600; font-size: 0.9rem; opacity: 0.5;">Next &raquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-light); font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
            Sistem Informasi Race Control V.2.0<br/>
            <?php echo date('Y'); ?> by Politeknik Sampit X <a href="https://www.instagram.com/yuk.sepedaan/" target="_blank" style="color: var(--primary-light); text-decoration: none; font-weight: 600;">@yuk.sepedaan</a>
        </div>
        
    </div>
</body>
</html>
