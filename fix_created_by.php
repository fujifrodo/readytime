<?php
// ปิด error reporting ชั่วคราว
error_reporting(E_ALL);
ini_set('display_errors', 1);

// เชื่อมต่อฐานข้อมูล
$host = 'localhost';
$dbname = 'u601857655_readytime';
$username = 'u601857655_fuji';
$password = 'dWUPd6SMj*E9';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<!DOCTYPE html>";
    echo "<html lang='th'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>แก้ไขปัญหา created_by</title>";
    echo "<style>";
    echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }";
    echo "h2 { color: #333; }";
    echo "table { border-collapse: collapse; width: 100%; margin: 20px 0; }";
    echo "th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }";
    echo "th { background-color: #4CAF50; color: white; }";
    echo ".success { color: green; font-weight: bold; }";
    echo ".error { color: red; font-weight: bold; }";
    echo ".info { color: blue; }";
    echo "a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; }";
    echo "a:hover { background: #45a049; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    echo "<h2>🔧 แก้ไขปัญหา created_by</h2>";
    
    // 1. เช็คว่ามี column created_by หรือยัง
    $stmt = $conn->query("SHOW COLUMNS FROM polls LIKE 'created_by'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "<p class='info'>⏳ กำลังเพิ่ม column created_by...</p>";
        $conn->exec("ALTER TABLE polls ADD COLUMN created_by INT(11) AFTER created_at");
        echo "<p class='success'>✅ เพิ่ม created_by สำเร็จ!</p>";
    } else {
        echo "<p class='success'>✅ มี column created_by อยู่แล้ว</p>";
    }
    
    // 2. เช็คโครงสร้างตาราง polls
    echo "<h3>📋 โครงสร้างตาราง polls</h3>";
    $stmt = $conn->query("SHOW COLUMNS FROM polls");
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $highlight = ($row['Field'] == 'created_by' || $row['Field'] == 'creator_id') ? "style='background-color: #ffeb3b;'" : "";
        echo "<tr $highlight>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p class='success'>✅ สำเร็จทั้งหมด! ตอนนี้สามารถสร้างโพลได้แล้ว</p>";
    echo "<a href='create_poll.php'>← กลับไปสร้างโพล</a>";
    
    echo "</body>";
    echo "</html>";
    
} catch(PDOException $e) {
    echo "<!DOCTYPE html>";
    echo "<html lang='th'><head><meta charset='UTF-8'><title>Error</title></head><body>";
    echo "<h2 class='error'>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>กรุณาตรวจสอบ:</p>";
    echo "<ul>";
    echo "<li>ข้อมูลการเชื่อมต่อฐานข้อมูลถูกต้อง</li>";
    echo "<li>มีสิทธิ์ในการแก้ไขโครงสร้างตาราง (ALTER TABLE)</li>";
    echo "</ul>";
    echo "</body></html>";
}
?>