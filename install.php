<?php
// install.php - Database Installation Script

// เปิดแสดง errors เพื่อ debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include config
require_once 'config.php';

$message = '';
$error = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Check if already installed
$installed = false;
try {
    $conn = getDbConnection();
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    if ($stmt) {
        $installed = true;
    }
} catch (Exception $e) {
    $installed = false;
}

// Handle installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $conn = getDbConnection();
        
        // Read SQL file
        $sql = file_get_contents('database.sql');
        
        // Remove comments and split by semicolon
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $successCount = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            if (empty($statement)) continue;
            
            try {
                // Skip DELIMITER statements (not supported in PDO)
                if (stripos($statement, 'DELIMITER') !== false) {
                    continue;
                }
                
                // Skip CREATE PROCEDURE/TRIGGER (handle separately)
                if (stripos($statement, 'CREATE PROCEDURE') !== false || 
                    stripos($statement, 'CREATE TRIGGER') !== false) {
                    continue;
                }
                
                $conn->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                $errors[] = "Error in statement: " . substr($statement, 0, 50) . "... - " . $e->getMessage();
            }
        }
        
        if (empty($errors)) {
            $message = "✅ ติดตั้งฐานข้อมูลสำเร็จ! จำนวน {$successCount} คำสั่ง";
            $step = 2;
        } else {
            $error = "⚠️ ติดตั้งบางส่วนสำเร็จ ({$successCount} คำสั่ง) แต่มีข้อผิดพลาด:<br>" . implode('<br>', $errors);
            $step = 2; // ให้ไปต่อได้
        }
        
    } catch (Exception $e) {
        $error = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// Create admin user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $displayName = trim($_POST['display_name']);
    
    if (empty($username) || empty($password) || empty($displayName)) {
        $error = "❌ กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        try {
            $conn = getDbConnection();
            
            $userId = 1; // First user always ID 1
            $token = bin2hex(random_bytes(32));
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO users (id, username, password, display_name, email, role, token, created_at, banned)
                VALUES (?, ?, ?, ?, '', 'admin', ?, NOW(), 0)
            ");
            
            $stmt->execute([$userId, $username, $hashedPassword, $displayName, $token]);
            
            $message = "✅ สร้างบัญชี Admin สำเร็จ! คุณสามารถเข้าสู่ระบบได้แล้ว";
            $step = 3;
            
        } catch (PDOException $e) {
            $error = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตั้งระบบ - นัดเพื่อน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="max-w-3xl w-full">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-purple-600 mb-2">📅 นัดเพื่อน</h1>
                <p class="text-gray-600">ติดตั้งระบบฐานข้อมูล MySQL</p>
            </div>
            
            <?php if ($installed && $step === 1): ?>
            <!-- Already Installed -->
            <div class="bg-green-100 border-2 border-green-400 rounded-xl p-6 text-center">
                <div class="text-6xl mb-4">✅</div>
                <h2 class="text-2xl font-bold text-green-800 mb-4">ระบบติดตั้งเรียบร้อยแล้ว!</h2>
                <p class="text-green-700 mb-6">ฐานข้อมูลพร้อมใช้งาน</p>
                <a href="login.php" class="inline-block bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition font-semibold">
                    เข้าสู่ระบบ
                </a>
            </div>
            
            <?php elseif ($step === 1): ?>
            <!-- Step 1: Install Database -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">ขั้นตอนที่ 1: สร้างตารางฐานข้อมูล</h2>
                
                <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                    <h3 class="font-bold text-blue-800 mb-2">ข้อมูลการเชื่อมต่อฐานข้อมูล:</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>🔹 Host: <?php echo DB_HOST; ?></li>
                        <li>🔹 Port: <?php echo DB_PORT; ?></li>
                        <li>🔹 Database: <?php echo DB_NAME; ?></li>
                        <li>🔹 Username: <?php echo DB_USER; ?></li>
                    </ul>
                </div>
                
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6">
                    <h3 class="font-bold text-yellow-800 mb-2">⚠️ คำเตือน:</h3>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• ตรวจสอบให้แน่ใจว่าข้อมูลในไฟล์ config.php ถูกต้อง</li>
                        <li>• การติดตั้งจะสร้างตารางใหม่ทั้งหมด</li>
                        <li>• ถ้ามีข้อมูลเก่าอยู่แล้ว จะถูกเก็บไว้</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <button type="submit" name="install" value="1"
                            class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white py-4 rounded-xl font-bold text-lg hover:shadow-lg transition">
                        🚀 เริ่มติดตั้งฐานข้อมูล
                    </button>
                </form>
            </div>
            
            <?php elseif ($step === 2): ?>
            <!-- Step 2: Create Admin User -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">ขั้นตอนที่ 2: สร้างบัญชี Admin</h2>
                
                <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                    <p class="text-blue-700">สร้างบัญชีผู้ดูแลระบบคนแรก (Admin)</p>
                </div>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">👤 Username *</label>
                        <input type="text" name="username" required
                               class="w-full px-4 py-3 border-2 rounded-xl focus:ring-4 focus:ring-purple-300 outline-none"
                               placeholder="admin">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">✨ ชื่อที่แสดง *</label>
                        <input type="text" name="display_name" required
                               class="w-full px-4 py-3 border-2 rounded-xl focus:ring-4 focus:ring-purple-300 outline-none"
                               placeholder="ผู้ดูแลระบบ">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">🔑 รหัสผ่าน *</label>
                        <input type="password" name="password" required minlength="6"
                               class="w-full px-4 py-3 border-2 rounded-xl focus:ring-4 focus:ring-purple-300 outline-none"
                               placeholder="อย่างน้อย 6 ตัวอักษร">
                    </div>
                    
                    <button type="submit" name="create_admin" value="1"
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-4 rounded-xl font-bold text-lg hover:shadow-lg transition">
                        ✅ สร้างบัญชี Admin
                    </button>
                </form>
            </div>
            
            <?php elseif ($step === 3): ?>
            <!-- Step 3: Complete -->
            <div class="bg-green-100 border-2 border-green-400 rounded-xl p-6 text-center">
                <div class="text-6xl mb-4">🎉</div>
                <h2 class="text-2xl font-bold text-green-800 mb-4">ติดตั้งเสร็จสมบูรณ์!</h2>
                
                <?php if ($message): ?>
                <div class="bg-white border border-green-300 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <div class="bg-white border border-green-300 rounded-lg p-4 mb-6 text-left">
                    <h3 class="font-bold text-green-800 mb-3">✅ สิ่งที่ติดตั้งแล้ว:</h3>
                    <ul class="text-sm text-green-700 space-y-2">
                        <li>✓ ตารางฐานข้อมูล (users, polls, poll_slots, responses, votes)</li>
                        <li>✓ บัญชี Admin คนแรก</li>
                        <li>✓ Views และ Indexes สำหรับ Performance</li>
                    </ul>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-6 text-left">
                    <h3 class="font-bold text-yellow-800 mb-3">🔒 ความปลอดภัย:</h3>
                    <ul class="text-sm text-yellow-700 space-y-2">
                        <li>⚠️ ลบหรือเปลี่ยนชื่อไฟล์ install.php ทันที</li>
                        <li>⚠️ อย่าเปิดเผยข้อมูลในไฟล์ config.php</li>
                        <li>⚠️ เปลี่ยนรหัสผ่าน Admin หลังเข้าสู่ระบบ</li>
                    </ul>
                </div>
                
                <div class="flex gap-3">
                    <a href="login.php" class="flex-1 bg-gradient-to-r from-purple-600 to-blue-600 text-white px-8 py-4 rounded-xl hover:shadow-lg transition font-bold">
                        🔐 เข้าสู่ระบบ
                    </a>
                    <a href="index.php" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white px-8 py-4 rounded-xl hover:shadow-lg transition font-bold">
                        🏠 หน้าหลัก
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Info -->
            <div class="mt-8 text-center text-sm text-gray-500">
                <p>🔧 ระบบนัดเพื่อน - MySQL Database Version</p>
                <p class="mt-1">พัฒนาโดย Claude AI</p>
            </div>
        </div>
    </div>
</body>
</html>
