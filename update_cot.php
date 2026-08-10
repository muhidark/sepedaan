<?php
require_once 'config.php';
$conn->query("INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES ('batas_cot', '180')"); // default 180 minutes (3 hours)
echo "COT setting added.";
?>
