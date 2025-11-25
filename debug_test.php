<?php
// ไฟล์ debug สำหรับเช็คปัญหา
// อัปโหลดไฟล์นี้แล้วเข้า debug_test.php ในเบราว์เซอร์

echo "<h1>🔧 ตรวจสอบระบบ</h1>";
echo "<hr>";

// 1. เช็ค PHP Version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo "✅ PHP Version OK<br>";
} else {
    echo "❌ PHP Version ต่ำเกินไป (ต้อง >= 7.4)<br>";
}
echo "<hr>";

// 2. เช็คไฟล์ที่จำเป็น
echo "<h2>2. ไฟล์ที่จำเป็น</h2>";
$required_files = [
    'config.php',
    'functions.php', 
    'install.php',
    'login.php',
    'register.php',
    'index.php',
    'poll.php',
    'account.php',
    'admin.php',
    'toast.php',
    'logout.php',
    'create_poll.php',
    'delete_poll.php',
    'export_poll.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file<br>";
    } else {
        echo "❌ <strong>$file ไม่พบ!</strong><br>";
    }
}
echo "<hr>";

// 3. เช็ค config.php
echo "<h2>3. ตรวจสอบ config.php</h2>";
if (file_exists('config.php')) {
    require_once 'config.php';
    echo "✅ config.php โหลดได้<br>";
    echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : '❌ ไม่ได้กำหนด') . "<br>";
    echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : '❌ ไม่ได้กำหนด') . "<br>";
    echo "DB_USER: " . (defined('DB_USER') ? DB_USER : '❌ ไม่ได้กำหนด') . "<br>";
    echo "DB_PASS: " . (defined('DB_PASS') ? '***' : '❌ ไม่ได้กำหนด') . "<br>";
} else {
    echo "❌ ไม่พบ config.php<br>";
}
echo "<hr>";

// 4. เช็คการเชื่อมต่อ Database
echo "<h2>4. ทดสอบเชื่อมต่อ MySQL</h2>";
if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        echo "✅ เชื่อมต่อ MySQL สำเร็จ!<br>";
        
        // เช็คตาราง
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<br><strong>ตารางที่มีในระบบ:</strong><br>";
        if (empty($tables)) {
            echo "❌ ไม่มีตารางใด ๆ - <strong>ต้องรัน install.php ก่อน!</strong><br>";
        } else {
            foreach ($tables as $table) {
                echo "✅ $table<br>";
            }
        }
        
    } catch (PDOException $e) {
        echo "❌ เชื่อมต่อ MySQL ไม่สำเร็จ!<br>";
        echo "Error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ config.php ไม่ครบถ้วน<br>";
}
echo "<hr>";

// 5. สรุป
echo "<h2>5. สรุป</h2>";
echo "<p>หากมี ❌ ให้แก้ไขตามคำแนะนำด้านล่าง:</p>";
echo "<ul>";
echo "<li>❌ PHP Version ต่ำ → ติดต่อ hosting เพื่ออัพเกรด PHP</li>";
echo "<li>❌ ไฟล์ไม่พบ → อัปโหลดไฟล์ที่ขาดใหม่</li>";
echo "<li>❌ config.php → แก้ไขข้อมูล database ให้ถูกต้อง</li>";
echo "<li>❌ MySQL ไม่ได้ → ตรวจสอบ username/password/database name</li>";
echo "<li>❌ ไม่มีตาราง → รัน install.php ก่อน</li>";
echo "</ul>";
echo "<br>";
echo "<strong>หลังแก้ไขแล้ว รันไฟล์นี้ใหม่อีกครั้ง</strong>";
?>
