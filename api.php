<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['peserta_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$pengaturan = [];
$res_pengaturan = $conn->query("SELECT * FROM pengaturan");
if ($res_pengaturan) {
    while ($row = $res_pengaturan->fetch_assoc()) {
        $pengaturan[$row['kunci']] = $row['nilai'];
    }
}
$waktu_min = isset($pengaturan['waktu_start_min']) ? $pengaturan['waktu_start_min'] : '15:30:00';
$waktu_max = isset($pengaturan['waktu_start_max']) ? $pengaturan['waktu_start_max'] : '16:00:00';
$tanggal_acara = isset($pengaturan['tanggal_acara']) ? $pengaturan['tanggal_acara'] : date('Y-m-d');

$event_datetime = $tanggal_acara . ' ' . $waktu_min;
$scan_active_timestamp = strtotime($event_datetime) - 1800; // 30 menit sebelum acara
$current_timestamp = time();

// Validasi Waktu Acara (hanya bisa scan jika sudah masuk waktu aktif)
if ($current_timestamp < $scan_active_timestamp) {
    echo json_encode(['success' => false, 'message' => 'Mohon maaf, check-in belum dibuka. Tombol check-in akan aktif 30 menit sebelum acara.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] == 'scan') {
        $peserta_id = $_SESSION['peserta_id'];
        $kode_qr = $conn->real_escape_string($data['kode_qr']);
        $user_lat = $data['latitude'];
        $user_lng = $data['longitude'];
        
        // Cek apakah QR Code valid
        $cek_qr = $conn->query("SELECT * FROM titik_checkin WHERE kode_qr = '$kode_qr'");
        if ($cek_qr->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'QR Code tidak valid!']);
            exit;
        }
        $titik = $cek_qr->fetch_assoc();
        
        // Validasi Jarak (Maks 200 Meter)
        $jarak = calculateDistance($user_lat, $user_lng, $titik['latitude'], $titik['longitude']);
        
        if ($jarak > 200) {
            echo json_encode(['success' => false, 'message' => 'Anda berada di luar jangkauan titik check-in. Jarak Anda: ' . round($jarak) . ' meter.']);
            exit;
        }
        
        // Validasi urutan (Harus berurutan: Start -> CP1 -> Finish)
        if ($titik['urutan'] > 1) {
            $urutan_sebelumnya = $titik['urutan'] - 1;
            $cek_sebelumnya = $conn->query("
                SELECT r.id 
                FROM riwayat_checkin r 
                JOIN titik_checkin t ON r.kode_qr = t.kode_qr 
                WHERE r.peserta_id = $peserta_id AND t.urutan = $urutan_sebelumnya
            ");
            if ($cek_sebelumnya->num_rows == 0) {
                echo json_encode(['success' => false, 'message' => 'Anda harus melakukan check-in secara berurutan! Silakan scan di titik sebelumnya terlebih dahulu.']);
                exit;
            }
        }
        
        // Cek apakah peserta sudah pernah scan di titik ini
        $cek_riwayat = $conn->query("SELECT id FROM riwayat_checkin WHERE peserta_id = $peserta_id AND kode_qr = '$kode_qr'");
        if ($cek_riwayat->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah melakukan check-in di titik ini!']);
            exit;
        }
        
        // Logika Waktu
        $waktu_sekarang = time();
        $waktu_tercatat = date('Y-m-d H:i:s', $waktu_sekarang);
        
        // Khusus untuk Start (asumsikan urutan = 1 adalah Start)
        if ($titik['urutan'] == 1) {
            $hari_ini = date('Y-m-d');
            
            // Ambil pengaturan batas waktu dari database
            $pengaturan = [];
            $res_pengaturan = $conn->query("SELECT * FROM pengaturan");
            if ($res_pengaturan) {
                while ($row = $res_pengaturan->fetch_assoc()) {
                    $pengaturan[$row['kunci']] = $row['nilai'];
                }
            }
            
            $waktu_start_min_str = isset($pengaturan['waktu_start_min']) ? $pengaturan['waktu_start_min'] : '15:30:00';
            $waktu_start_max_str = isset($pengaturan['waktu_start_max']) ? $pengaturan['waktu_start_max'] : '16:00:00';
            
            $waktu_min = strtotime($hari_ini . ' ' . $waktu_start_min_str);
            $waktu_max = strtotime($hari_ini . ' ' . $waktu_start_max_str);
            
            if ($waktu_sekarang < $waktu_min) {
                // Sebelum batas minimal -> catat sebagai batas minimal
                $waktu_tercatat = date('Y-m-d H:i:s', $waktu_min);
            } else if ($waktu_sekarang > $waktu_max) {
                // Setelah batas maksimal -> catat sebagai batas maksimal
                $waktu_tercatat = date('Y-m-d H:i:s', $waktu_max);
            }
        }
        
        $waktu_scan_asli = date('Y-m-d H:i:s', $waktu_sekarang);
        
        // Simpan ke riwayat
        $sql = "INSERT INTO riwayat_checkin (peserta_id, kode_qr, waktu_scan, waktu_tercatat, latitude, longitude, status) 
                VALUES ($peserta_id, '$kode_qr', '$waktu_scan_asli', '$waktu_tercatat', $user_lat, $user_lng, 'BERHASIL')";
                
        if ($conn->query($sql)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Check-in ' . $titik['nama_titik'] . ' berhasil!',
                'waktu_tercatat' => date('H:i:s', strtotime($waktu_tercatat))
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan check-in.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
?>