<?php
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS pengaturan (
    kunci VARCHAR(50) PRIMARY KEY,
    nilai VARCHAR(255) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table pengaturan created successfully\n";
    $conn->query("INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES ('waktu_start_min', '15:30:00')");
    $conn->query("INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES ('waktu_start_max', '16:00:00')");
    echo "Default settings inserted\n";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
