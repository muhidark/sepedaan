<?php
require_once 'config.php';

$tables = array();
$result = $conn->query("SHOW TABLES");
while($row = $result->fetch_row()){
    $tables[] = $row[0];
}

$return = "";
foreach($tables as $table){
    $result = $conn->query("SELECT * FROM ".$table);
    $num_fields = $result->field_count;
    
    $return .= "DROP TABLE IF EXISTS ".$table.";";
    $row2 = $conn->query("SHOW CREATE TABLE ".$table)->fetch_row();
    $return .= "\n\n".$row2[1].";\n\n";
    
    for($i=0; $i<$num_fields; $i++){
        while($row = $result->fetch_row()){
            $return .= "INSERT INTO ".$table." VALUES(";
            for($j=0; $j<$num_fields; $j++){
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n","\\n",$row[$j]);
                if(isset($row[$j])){ $return .= '"'.$row[$j].'"'; }else{ $return .= '""'; }
                if($j<($num_fields-1)){ $return .= ','; }
            }
            $return .= ");\n";
        }
    }
    $return .= "\n\n\n";
}

$handle = fopen('sepedaan_backup.sql','w+');
fwrite($handle, $return);
fclose($handle);
echo "Database Exported Successfully";
?>
