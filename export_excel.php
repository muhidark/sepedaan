<?php
require_once 'config.php';

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

// Ambil Data Rekapitulasi
$sql = "
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

$result = $conn->query($sql);
$all_participants = [];

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

// Sorting mirip leaderboard
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
            return 0;
        }
        return $a['durasi_detik'] <=> $b['durasi_detik'];
    }
    return 0;
});
$leaderboard = [];
$rank = 1;
foreach ($finishers as $f) { $f['rank'] = $rank++; $leaderboard[] = $f; }
foreach ($others as $o) { $o['rank'] = '-'; $leaderboard[] = $o; }

// Output as Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"Rekap_Peserta_Sepeda_" . date('Ymd_His') . ".xls\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr>
        <th>Rank</th>
        <th>Nama Peserta</th>
        <th>No WhatsApp</th>
        <th>Akun IG</th>
        <th>Waktu Start</th>
        <th>Waktu CP1</th>
        <th>Waktu Finish</th>
        <th>Durasi Total</th>
        <th>Keterangan</th>
      </tr>";

foreach ($leaderboard as $lb) {
    echo "<tr>";
    echo "<td>" . $lb['rank'] . "</td>";
    echo "<td>" . $lb['nama'] . "</td>";
    echo "<td>" . $lb['no_wa'] . "</td>";
    echo "<td>" . $lb['akun_ig'] . "</td>";
    echo "<td>" . ($lb['waktu_start'] ? $lb['waktu_start'] : '-') . "</td>";
    echo "<td>" . ($lb['waktu_cp1'] ? $lb['waktu_cp1'] : '-') . "</td>";
    echo "<td>" . ($lb['waktu_finish'] ? $lb['waktu_finish'] : '-') . "</td>";
    echo "<td>" . $lb['durasi_format'] . "</td>";
    echo "<td>" . $lb['status'] . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
