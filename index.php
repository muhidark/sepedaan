<?php
require_once 'config.php';

if (!isset($_SESSION['peserta_id'])) {
    header('Location: login.php');
    exit;
}

$peserta_id = $_SESSION['peserta_id'];
$nama = $_SESSION['nama'];

// Get user's IG
$peserta_query = $conn->query("SELECT akun_ig FROM peserta WHERE id = $peserta_id");
$peserta_data = $peserta_query->fetch_assoc();
$akun_ig = $peserta_data ? $peserta_data['akun_ig'] : '';

// Get user's scan history
$riwayat_query = $conn->query("
    SELECT t.nama_titik, r.waktu_tercatat 
    FROM riwayat_checkin r 
    JOIN titik_checkin t ON r.kode_qr = t.kode_qr 
    WHERE r.peserta_id = $peserta_id 
    ORDER BY t.urutan ASC
");

$riwayat = [];
while ($row = $riwayat_query->fetch_assoc()) {
    $riwayat[$row['nama_titik']] = date('H:i:s', strtotime($row['waktu_tercatat']));
}

// Get Event Settings for Countdown
$pengaturan = [];
$res_pengaturan = $conn->query("SELECT * FROM pengaturan");
if ($res_pengaturan) {
    while ($row = $res_pengaturan->fetch_assoc()) {
        $pengaturan[$row['kunci']] = $row['nilai'];
    }
}
$tanggal_acara = isset($pengaturan['tanggal_acara']) ? $pengaturan['tanggal_acara'] : date('Y-m-d');
$waktu_start_min = isset($pengaturan['waktu_start_min']) ? $pengaturan['waktu_start_min'] : '15:30:00';
$event_datetime = $tanggal_acara . ' ' . $waktu_start_min;
$scan_active_timestamp = strtotime($event_datetime) - 1800; // 30 menit sebelum acara
$current_timestamp = time();
$is_scan_active = $current_timestamp >= $scan_active_timestamp;

// Fetch Titik Check-in
$titik_query = $conn->query("SELECT * FROM titik_checkin ORDER BY urutan ASC");
$titik_checkin = [];
while ($row = $titik_query->fetch_assoc()) {
    $titik_checkin[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Scanner - Self Check-in Sepeda</title>
    <link rel="stylesheet" href="style.css">
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
            <a href="index.php" class="nav-tab active">Check-in</a>
            <a href="leaderboard.php" class="nav-tab">Leaderboard</a>
        </div>

        <div class="glass-card">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-size: 1.5rem; color: var(--text-main); font-weight: 800; line-height: 1.2;"><?php echo htmlspecialchars($nama); ?></h3>
                    <?php if(!empty($akun_ig)): ?>
                        <p style="font-size: 0.9rem; margin-top: 2px;">
                            <?php $ig_clean = ltrim($akun_ig, '@'); ?>
                            <a href="https://www.instagram.com/<?php echo htmlspecialchars($ig_clean); ?>" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                                @<?php echo htmlspecialchars($ig_clean); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
                <a href="logout.php" style="background: var(--error); color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.9rem; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);">Keluar</a>
            </div>

            <h2 style="font-size: 1.1rem; margin-bottom: 0.2rem; text-align: center;">Lokasi Check-in</h2>
            <p style="text-align: center; font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
                Start dari: <strong style="color: var(--text-main);"><?php echo htmlspecialchars(isset($titik_checkin[0]) ? $titik_checkin[0]['nama_titik'] : '-'); ?></strong>
            </p>
            
            <div id="countdown-container" style="display: <?php echo $is_scan_active ? 'none' : 'block'; ?>; text-align: center; padding: 2rem; background: var(--background); border: 1px solid var(--border-light); border-radius: 12px; margin-bottom: 1rem;">
                <p style="color: var(--text-muted); margin-bottom: 1rem;">Check-in akan aktif dalam:</p>
                <div id="countdown-timer" style="font-size: 1.5rem; font-weight: 800; color: var(--accent);">
                    Menghitung...
                </div>
            </div>

            <div id="scanner-container" style="display: <?php echo $is_scan_active ? 'block' : 'none'; ?>;">
                <div id="statusBox" class="scan-status idle" style="margin-bottom: 1rem; display: none;">
                    Memproses...
                </div>
                
                <div class="timeline">
                <?php 
                $checkin_sebelumnya_done = true; // Start is always available first
                foreach ($titik_checkin as $titik) {
                    $nama_titik = $titik['nama_titik'];
                    // Tambahkan keterangan khusus untuk CP 1
                    if ($titik['urutan'] == 2 && stripos($nama_titik, 'km 10') === false) {
                        $nama_titik .= ' (km 10)';
                    }
                    
                    $is_done = isset($riwayat[$titik['nama_titik']]);
                    $waktu_done = $is_done ? $riwayat[$titik['nama_titik']] : null;
                    
                    $dot_class = $is_done ? 'done' : ($checkin_sebelumnya_done ? 'active' : '');
                    $dot_icon = $is_done ? '✓' : '';
                    
                    $btn_class = 'btn btn-primary';
                    $btn_text = 'Check-in ' . htmlspecialchars($nama_titik);
                    $disabled_attr = (!$checkin_sebelumnya_done) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '';
                    
                    echo '<div class="timeline-item">';
                    echo '<div class="timeline-dot '.$dot_class.'">'.$dot_icon.'</div>';
                    echo '<div class="timeline-content">';
                    echo '<h4>'.htmlspecialchars($nama_titik).'</h4>';
                    
                    if ($is_done) {
                        echo '<div class="timeline-time"><span style="color: var(--success); font-weight: 700;">✓ Berhasil pada '.$waktu_done.'</span></div>';
                    } else {
                        echo '<button class="'.$btn_class.'" '.$disabled_attr.' onclick="processCheckin(\''.htmlspecialchars($titik['kode_qr']).'\')" style="margin-top: 0.8rem; padding: 0.8rem; font-size: 0.95rem;">'.$btn_text.'</button>';
                    }
                    
                    echo '</div>'; // end timeline-content
                    echo '</div>'; // end timeline-item
                    
                    // Update condition for next button
                    if (!$is_done) {
                        $checkin_sebelumnya_done = false;
                    }
                }
                ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isProcessing = false;
        const scanActiveTimestamp = <?php echo $scan_active_timestamp; ?> * 1000;
        let countdownInterval;

        document.addEventListener('DOMContentLoaded', () => {
            const now = new Date().getTime();
            if (now >= scanActiveTimestamp) {
                // Already active
            } else {
                startCountdown();
            }
        });

        function startCountdown() {
            const countdownEl = document.getElementById('countdown-timer');
            
            function updateTimer() {
                const now = new Date().getTime();
                const distance = scanActiveTimestamp - now;

                if (distance <= 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('countdown-container').style.display = 'none';
                    document.getElementById('scanner-container').style.display = 'block';
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

                let timeString = "";
                if (days > 0) {
                    timeString += days + " Hari, ";
                }
                timeString += hours + " Jam, " + minutes + " Menit";

                countdownEl.innerHTML = timeString;
            }
            
            updateTimer();
            countdownInterval = setInterval(updateTimer, 1000);
        }

        function showStatus(text, type) {
            const el = document.getElementById('statusBox');
            el.style.display = 'block';
            el.className = `scan-status ${type}`;
            el.innerText = text;
        }

        function processCheckin(kodeQr) {
            if (isProcessing) return;
            isProcessing = true;
            
            showStatus('Memeriksa lokasi GPS...', 'idle');

            if (!navigator.geolocation) {
                showStatus('Perangkat tidak mendukung GPS', 'error');
                setTimeout(() => { isProcessing = false; document.getElementById('statusBox').style.display = 'none'; }, 3000);
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    sendScanData(kodeQr, lat, lng);
                },
                (error) => {
                    showStatus('Izin lokasi ditolak. Aktifkan GPS Anda!', 'error');
                    setTimeout(() => { isProcessing = false; document.getElementById('statusBox').style.display = 'none'; }, 3000);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        async function sendScanData(kodeQr, lat, lng) {
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'scan',
                        kode_qr: kodeQr.trim(),
                        latitude: lat,
                        longitude: lng
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showStatus(result.message, 'success');
                    setTimeout(() => {
                        window.location.reload(); // Reload to update logs
                    }, 2000);
                } else {
                    showStatus(result.message, 'error');
                    setTimeout(() => { isProcessing = false; document.getElementById('statusBox').style.display = 'none'; }, 3000);
                }
            } catch (err) {
                showStatus('Gagal terhubung ke server', 'error');
                setTimeout(() => { isProcessing = false; document.getElementById('statusBox').style.display = 'none'; }, 3000);
            }
        }
    </script>
</body>
</html>