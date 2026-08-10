<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    die("ID Peserta tidak valid.");
}
$peserta_id = (int)$_GET['id'];

// Ambil data peserta ini
$sql_user = "
SELECT 
    p.id, p.nama,
    MAX(CASE WHEN t.urutan = 1 THEN r.waktu_tercatat END) as waktu_start,
    MAX(CASE WHEN t.urutan = 3 THEN r.waktu_tercatat END) as waktu_finish
FROM peserta p
LEFT JOIN riwayat_checkin r ON p.id = r.peserta_id
LEFT JOIN titik_checkin t ON r.kode_qr = t.kode_qr
WHERE p.id = $peserta_id
GROUP BY p.id
";
$res_user = $conn->query($sql_user);
if (!$res_user || $res_user->num_rows == 0) {
    die("Peserta tidak ditemukan.");
}
$user = $res_user->fetch_assoc();

if (empty($user['waktu_start']) || empty($user['waktu_finish'])) {
    die("Sertifikat belum tersedia. Peserta belum menyelesaikan rute.");
}

// Cek COT
$pengaturan = [];
$res_pengaturan = $conn->query("SELECT * FROM pengaturan");
if ($res_pengaturan) {
    while ($row = $res_pengaturan->fetch_assoc()) {
        $pengaturan[$row['kunci']] = $row['nilai'];
    }
}
$batas_cot = isset($pengaturan['batas_cot']) ? (int)$pengaturan['batas_cot'] : 180;
$batas_cot_detik = $batas_cot * 60;

$user_durasi_detik = strtotime($user['waktu_finish']) - strtotime($user['waktu_start']);
if ($user_durasi_detik > $batas_cot_detik) {
    die("Mohon maaf, Anda melebihi batas waktu COT, sehingga tidak mendapatkan E-Sertifikat Finisher.");
}

// Format durasi user
$jam = floor($user_durasi_detik / 3600);
$menit = floor(($user_durasi_detik % 3600) / 60);
$detik = $user_durasi_detik % 60;
$user_durasi_format = sprintf("%02d:%02d:%02d", $jam, $menit, $detik);

// Hitung Peringkat (Rank)
$sql_all = "
SELECT 
    p.id,
    MAX(CASE WHEN t.urutan = 1 THEN r.waktu_tercatat END) as waktu_start,
    MAX(CASE WHEN t.urutan = 3 THEN r.waktu_tercatat END) as waktu_finish
FROM peserta p
LEFT JOIN riwayat_checkin r ON p.id = r.peserta_id
LEFT JOIN titik_checkin t ON r.kode_qr = t.kode_qr
GROUP BY p.id
";
$res_all = $conn->query($sql_all);
$finishers = [];
while ($row = $res_all->fetch_assoc()) {
    if (!empty($row['waktu_start']) && !empty($row['waktu_finish'])) {
        $durasi = strtotime($row['waktu_finish']) - strtotime($row['waktu_start']);
        if ($durasi <= $batas_cot_detik) {
            $finishers[] = ['id' => $row['id'], 'durasi' => $durasi];
        }
    }
}
usort($finishers, function($a, $b) {
    return $a['durasi'] <=> $b['durasi'];
});

$user_rank = 0;
foreach ($finishers as $index => $f) {
    if ($f['id'] == $peserta_id) {
        $user_rank = $index + 1;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Sertifikat - <?php echo htmlspecialchars($user['nama']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @page {
            size: A4 landscape;
            margin: 0mm;
        }
        body {
            background: #e2e8f0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .cert-container {
            width: 297mm;
            height: 210mm;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="%23ffffff" /><stop offset="100%" stop-color="%23f8fafc" /></linearGradient></defs><rect width="100%" height="100%" fill="url(%23g)"/></svg>');
            background-size: cover;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 15mm;
            display: flex;
            flex-direction: column;
        }
        /* Ornamen Latar Merah Putih */
        .cert-bg-shape1 {
            position: absolute;
            top: -50mm;
            left: -50mm;
            width: 150mm;
            height: 150mm;
            background: linear-gradient(135deg, rgba(230,32,32,0.15), rgba(255,77,77,0.1));
            border-radius: 50%;
            z-index: 1;
        }
        .cert-bg-shape2 {
            position: absolute;
            bottom: -50mm;
            right: -50mm;
            width: 200mm;
            height: 200mm;
            background: linear-gradient(135deg, rgba(255,77,77,0.1), rgba(230,32,32,0.15));
            border-radius: 50%;
            z-index: 1;
        }
        /* Border Dalam */
        .cert-border {
            position: relative;
            z-index: 2;
            flex: 1;
            border: 4mm solid #E62020;
            padding: 10mm 15mm;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .kalimantan-bg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 75%;
            height: 75%;
            background-image: url('kalimantan.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.2;
            z-index: 0;
            pointer-events: none;
        }
        .cert-header {
            position: relative;
            z-index: 1;
            font-family: 'Oswald', sans-serif;
            color: #E62020;
            font-size: 3.5rem;
            letter-spacing: 2px;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
        }
        .cert-subheader {
            position: relative;
            z-index: 1;
            color: #1e293b;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }
        .cert-body {
            position: relative;
            z-index: 1;
            margin-bottom: 1rem;
        }
        .cert-body p {
            font-size: 1.2rem;
            color: #475569;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .participant-name {
            font-family: 'Oswald', sans-serif;
            font-size: 4.5rem;
            color: #1E293B;
            margin: 0.5rem 0;
            border-bottom: 4px solid #E62020;
            display: inline-block;
            padding: 0 2rem;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .achievement {
            font-size: 1.4rem;
            font-weight: 800;
            color: #E62020;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        .stats-box {
            display: inline-flex;
            gap: 3rem;
            background: #fff;
            padding: 1.5rem 3rem;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(230,32,32,0.05);
        }
        .stat-item {
            text-align: center;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #E62020;
        }
        
        /* Event Info & Signature */
        .cert-footer {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
        }
        .event-info {
            text-align: left;
            color: #64748b;
            font-size: 1rem;
            line-height: 1.6;
            font-weight: 600;
        }
        .event-info strong {
            color: #1e293b;
            font-weight: 800;
        }
        .signature-area {
            text-align: center;
        }
        .signature-line {
            width: 200px;
            margin-top: 20px;
            padding-top: 10px;
            font-weight: 800;
            color: #1e293b;
            font-size: 1.2rem;
            text-transform: uppercase;
        }
        .signature-logo {
            width: 150px;
            height: auto;
            object-fit: contain;
        }
        .badge {
            position: absolute;
            top: 20mm;
            right: 20mm;
            width: 100px;
            height: 100px;
            background: #E62020;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(230,32,32, 0.4);
            border: 4px solid white;
            z-index: 10;
        }

        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .controls button {
            background: #E62020;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
        }
        .controls button:hover {
            background: #B31212;
        }
        
        @page {
            size: A4 landscape;
            margin: 0;
        }
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            body {
                background: none;
                min-height: auto;
                margin: 0;
                padding: 0;
            }
            .cert-container {
                box-shadow: none;
                width: 297mm;
                height: 210mm;
                margin: 0;
                padding: 15mm;
                page-break-after: avoid;
            }
            .controls {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="controls">
        <p style="font-size: 0.8rem; color: #666; margin-bottom: 10px; text-align: center;">Format A4 Landscape</p>
        <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    </div>

    <div class="cert-container">
        <div class="cert-bg-shape1"></div>
        <div class="cert-bg-shape2"></div>
        
        <div class="cert-border">
            <div class="kalimantan-bg"></div>
            <div class="badge">FINISHER</div>
            
            <div>
                <h1 class="cert-header">Sertifikat Penghargaan</h1>
            </div>

            <div class="cert-body">
                <p>Diberikan dengan penuh apresiasi kepada:</p>
                <div class="participant-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                <div class="achievement">Telah Berhasil Menyelesaikan Rute</div>
                <h3 class="cert-subheader">Yuk.Sepedaan Keliling Pulau Kalimantan</h3>
                
                <div class="stats-box">
                    <div class="stat-item">
                        <div class="stat-label">Waktu Tempuh</div>
                        <div class="stat-value"><?php echo $user_durasi_format; ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Peringkat Ke-</div>
                        <div class="stat-value">#<?php echo $user_rank; ?></div>
                    </div>
                </div>
            </div>

            <div class="cert-footer">
                <div class="event-info">
                    <strong>Detail Pelaksanaan:</strong><br>
                    Jarak Tempuh: 19 km<br>
                    Tanggal: 15 Agustus 2026<br>
                    Lokasi: Sampit - Kalimantan Tengah
                </div>
                <div class="signature-area">
                    <img src="logo_kotim.png" alt="Kotim Cycles" class="signature-logo">
                </div>
            </div>
            
        </div>
    </div>

</body>
</html>
