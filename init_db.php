<?php
$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password is empty

// Create connection without database
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS sepedaan";
if ($conn->query($sql) === TRUE) {
    echo "Database 'sepedaan' berhasil dibuat atau sudah ada.<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db('sepedaan');

// Create tables
$table_peserta = "CREATE TABLE IF NOT EXISTS peserta (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    no_wa VARCHAR(20) NOT NULL UNIQUE,
    akun_ig VARCHAR(50) NOT NULL,
    pin VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($table_peserta) === TRUE) {
    echo "Tabel 'peserta' berhasil dibuat.<br>";
} else {
    echo "Error creating table peserta: " . $conn->error . "<br>";
}

$table_titik = "CREATE TABLE IF NOT EXISTS titik_checkin (
    kode_qr VARCHAR(50) PRIMARY KEY,
    nama_titik VARCHAR(50) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    urutan INT(2) NOT NULL
)";

if ($conn->query($table_titik) === TRUE) {
    echo "Tabel 'titik_checkin' berhasil dibuat.<br>";
    
    // Insert default check-in points (You will need to update these coordinates later)
    // For testing, let's insert some dummy points. 
    // Start, CP1, Finish
    $insert_points = "INSERT IGNORE INTO titik_checkin (kode_qr, nama_titik, latitude, longitude, urutan) VALUES 
        ('QR-START-123', 'Start', -6.200000, 106.816666, 1),
        ('QR-CP1-123', 'Check Point 1', -6.210000, 106.820000, 2),
        ('QR-FINISH-123', 'Finish', -6.220000, 106.823333, 3)";
    $conn->query($insert_points);
} else {
    echo "Error creating table titik_checkin: " . $conn->error . "<br>";
}

$table_riwayat = "CREATE TABLE IF NOT EXISTS riwayat_checkin (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    peserta_id INT(6) UNSIGNED NOT NULL,
    kode_qr VARCHAR(50) NOT NULL,
    waktu_scan DATETIME NOT NULL,
    waktu_tercatat DATETIME NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (peserta_id) REFERENCES peserta(id) ON DELETE CASCADE,
    FOREIGN KEY (kode_qr) REFERENCES titik_checkin(kode_qr) ON DELETE CASCADE
)";

if ($conn->query($table_riwayat) === TRUE) {
    echo "Tabel 'riwayat_checkin' berhasil dibuat.<br>";
} else {
    echo "Error creating table riwayat_checkin: " . $conn->error . "<br>";
}

echo "<h3>Setup Database Selesai!</h3>";
echo "<a href='index.php'>Kembali ke Beranda</a>";
$conn->close();
?>
