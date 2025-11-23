<?php
// ไฟล์ทดสอบการสร้างโพล
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 ทดสอบการสร้างโพล</h1><hr>";

// 1. โหลดไฟล์
echo "<h2>1. โหลดไฟล์</h2>";
try {
    require_once 'config.php';
    echo "✅ config.php OK<br>";
    require_once 'functions.php';
    echo "✅ functions.php OK<br>";
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
echo "<hr>";

// 2. เชื่อมต่อ Database
echo "<h2>2. เชื่อมต่อ Database</h2>";
try {
    $db = getDB();
    echo "✅ Database connected<br>";
} catch (Exception $e) {
    die("❌ Database Error: " . $e->getMessage());
}
echo "<hr>";

// 3. เช็คตาราง polls
echo "<h2>3. เช็คตาราง polls</h2>";
try {
    $result = $db->query("DESCRIBE polls");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "ตารางมี columns:<br>";
    foreach ($columns as $col) {
        echo "- $col<br>";
    }
    
    $requiredColumns = ['id', 'token', 'title', 'week_start', 'week_end', 
                        'allow_maybe', 'time_mode', 'created_at', 'created_by', 
                        'creator_name', 'expire_date'];
    
    echo "<br>เช็ค columns ที่ต้องการ:<br>";
    foreach ($requiredColumns as $req) {
        if (in_array($req, $columns)) {
            echo "✅ $req<br>";
        } else {
            echo "❌ <strong>$req ไม่พบ!</strong><br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<strong>ตาราง polls ไม่มี! ต้องรัน install.php</strong><br>";
}
echo "<hr>";

// 4. เช็คตาราง poll_slots
echo "<h2>4. เช็คตาราง poll_slots</h2>";
try {
    $result = $db->query("DESCRIBE poll_slots");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "ตารางมี columns:<br>";
    foreach ($columns as $col) {
        echo "- $col<br>";
    }
    
    $requiredColumns = ['id', 'poll_id', 'slot_date', 'period', 'start_time', 'end_time'];
    
    echo "<br>เช็ค columns ที่ต้องการ:<br>";
    foreach ($requiredColumns as $req) {
        if (in_array($req, $columns)) {
            echo "✅ $req<br>";
        } else {
            echo "❌ <strong>$req ไม่พบ!</strong><br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<strong>ตาราง poll_slots ไม่มี! ต้องรัน install.php</strong><br>";
}
echo "<hr>";

// 5. เช็ค User
echo "<h2>5. เช็ค User Login</h2>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "✅ User logged in: ID = " . $_SESSION['user_id'] . "<br>";
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "✅ User found: " . $user['display_name'] . "<br>";
        } else {
            echo "❌ User ID ไม่พบในฐานข้อมูล<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ ไม่ได้ login - <a href='login.php'>Login ที่นี่</a><br>";
}
echo "<hr>";

// 6. ทดสอบสร้างโพล (จำลอง)
echo "<h2>6. ทดสอบสร้างโพล (Dry Run)</h2>";
try {
    $pollId = time() . rand(1000, 9999);
    $token = substr(md5(uniqid(rand(), true)), 0, 9);
    
    echo "Poll ID: $pollId<br>";
    echo "Token: $token<br>";
    
    // ลอง prepare SQL
    $stmt = $db->prepare("
        INSERT INTO polls (id, token, title, week_start, week_end, allow_maybe, time_mode, 
                           created_at, created_by, creator_name, expire_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
    ");
    echo "✅ SQL prepare สำเร็จ<br>";
    
    // ไม่ execute จริง แค่เช็ค syntax
    echo "<br><strong>ถ้าถึงตรงนี้แสดงว่า SQL ถูกต้อง</strong><br>";
    
} catch (Exception $e) {
    echo "❌ SQL Error: " . $e->getMessage() . "<br>";
}
echo "<hr>";

echo "<h2>✅ สรุป</h2>";
echo "<ul>";
echo "<li>ถ้าเห็น ❌ ที่ตาราง polls หรือ poll_slots → <strong>รัน install.php</strong></li>";
echo "<li>ถ้าเห็น ❌ ที่ columns → <strong>โครงสร้างตารางไม่ถูกต้อง ต้องรัน install.php ใหม่</strong></li>";
echo "<li>ถ้าเห็น ⚠️ ไม่ได้ login → <strong>Login ก่อน</strong></li>";
echo "<li>ถ้าทุกอย่าง ✅ แต่ยังสร้างไม่ได้ → <strong>ส่ง screenshot มา</strong></li>";
echo "</ul>";
?>