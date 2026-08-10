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
    MAX(CASE WHEN t.urutan = 2 THEN r.waktu_tercatat END) as waktu_cp1,
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
                $status = 'COT';
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

// Sort others: COT first (by duration), then DNF, then DNS
usort($others, function($a, $b) {
    $order = ['COT' => 1, 'ON PROGRESS' => 2, 'DNF' => 3, 'DNS' => 4];
    $rankA = isset($order[$a['status']]) ? $order[$a['status']] : 99;
    $rankB = isset($order[$b['status']]) ? $order[$b['status']] : 99;
    
    if ($rankA != $rankB) {
        return $rankA <=> $rankB;
    }
    if ($a['status'] == 'COT') {
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

// Helper badge color
function getStatusBadge($status) {
    if ($status == 'FINISHER') return 'background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
    if ($status == 'COT') return 'background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;';
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
        .container { max-width: 900px !important; }
        @media (max-width: 768px) {
            .leaderboard-table { display: table !important; }
            .rotate-warning {
                display: block;
                background: #fff3cd;
                color: #856404;
                padding: 10px;
                border-radius: 8px;
                text-align: center;
                margin-bottom: 1rem;
                font-weight: bold;
                font-size: 0.85rem;
                border: 1px solid #ffeeba;
            }
        }
        @media (min-width: 769px) {
            .rotate-warning { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <h1 style="font-size: 1.5rem; font-weight: 800; background: linear-gradient(45deg, var(--primary-light), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Detail Leaderboard</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Yuk.Sepedaan Keliling Pulau Kalimantan</p>
        </div>
        
        <div class="rotate-warning">📱 Harap putar HP Anda (Landscape) agar tabel terlihat penuh.</div>

        <div style="margin-bottom: 1rem;">
            <a href="leaderboard.php" class="btn btn-secondary" style="width: auto; padding: 0.5rem 1rem; display: inline-flex; border-radius: 8px;">&larr; Kembali ke Leaderboard Umum</a>
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

            <h3 style="margin-bottom: 1rem;">Tabel Peringkat (Detail)</h3>
            
            <div class="table-container">
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Peserta</th>
                            <th>Start</th>
                            <th>CP 1</th>
                            <th>Finish</th>
                            <th>Durasi</th>
                            <th>Ket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaderboard)): ?>
                            <tr>
                                <td colspan="4" class="text-center" style="padding: 2rem; color: var(--text-muted);">Belum ada data peserta.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leaderboard as $lb): ?>
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
                                            <span><?php echo htmlspecialchars($lb['akun_ig']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $lb['waktu_start'] ? date('H:i:s', strtotime($lb['waktu_start'])) : '-'; ?></td>
                                    <td><?php echo isset($lb['waktu_cp1']) && $lb['waktu_cp1'] ? date('H:i:s', strtotime($lb['waktu_cp1'])) : '-'; ?></td>
                                    <td><?php echo $lb['waktu_finish'] ? date('H:i:s', strtotime($lb['waktu_finish'])) : '-'; ?></td>
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

        </div>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-light); font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
            Sistem Informasi Race Control V.2.0<br/>
            <?php echo date('Y'); ?> by Politeknik Sampit X <a href="https://www.instagram.com/yuk.sepedaan/" target="_blank" style="color: var(--primary-light); text-decoration: none; font-weight: 600;">@yuk.sepedaan</a>
        </div>
        
    </div>
</body>
</html>
