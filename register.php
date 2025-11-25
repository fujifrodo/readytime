<?php
require_once 'functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username) || empty($password) || empty($displayName)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } elseif ($password !== $confirmPassword) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        try {
            $db = getDB();
            
            // ตรวจสอบว่า username ซ้ำหรือไม่
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = 'Username นี้ถูกใช้งานแล้ว';
            } else {
                // ตรวจสอบว่าเป็น user คนแรกหรือไม่ (จะได้เป็น admin)
                $stmt = $db->query("SELECT COUNT(*) as count FROM users");
                $isFirstUser = ($stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0);
                
                // สร้าง user ID และ token (ใช้ safe method)
                $userId = generateSafeUserId();
                $token = generateToken();
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $role = $isFirstUser ? 'admin' : 'user';
                
                // บันทึกข้อมูลลง database
                $stmt = $db->prepare("
                    INSERT INTO users (id, username, password, display_name, email, role, token, created_at, last_login)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $emailValue = !empty($email) ? $email : null;
                
                $stmt->execute([
                    (int)$userId,
                    $username,
                    $hashedPassword,
                    $displayName,
                    $emailValue,
                    $role,
                    $token
                ]);
                
                // ตั้งค่า session
                $_SESSION['user_id'] = (int)$userId;
                $_SESSION['token'] = $token;
                $_SESSION['username'] = $username;
                $_SESSION['display_name'] = $displayName;
                $_SESSION['role'] = $role;
                
                $welcomeMsg = $isFirstUser 
                    ? '🎉 สมัครสมาชิกสำเร็จ! คุณได้รับสิทธิ์ Admin' 
                    : '🎉 สมัครสมาชิกสำเร็จ! ยินดีต้อนรับ ' . htmlspecialchars($displayName);
                    
                setToast($welcomeMsg, 'success');
                redirect('index.php');
            }
        } catch (PDOException $e) {
            error_log("Register error: " . $e->getMessage());
            // แสดง error จริงๆ เพื่อ debug (ใช้ในระหว่างพัฒนา)
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            // เมื่อใช้งานจริง ใช้ $error = 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - นัดเพื่อน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <?php include 'toast.php'; ?>
    
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md animate-fade-in">
        <h1 class="text-3xl font-bold text-center mb-2 text-purple-600">📅 นัดเพื่อน</h1>
        <p class="text-center text-gray-600 mb-6">สมัครสมาชิก</p>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">
                    👤 Username <span class="text-red-500">*</span>
                </label>
                <input type="text" name="username" required autofocus
                       class="w-full px-5 py-3 border-2 rounded-xl outline-none focus:ring-4 focus:ring-purple-300 transition"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       placeholder="username">
            </div>
            
            <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">
                    ✨ ชื่อที่แสดง <span class="text-red-500">*</span>
                </label>
                <input type="text" name="display_name" required 
                       class="w-full px-5 py-3 border-2 rounded-xl outline-none focus:ring-4 focus:ring-purple-300 transition"
                       value="<?php echo htmlspecialchars($_POST['display_name'] ?? ''); ?>"
                       placeholder="ชื่อที่ต้องการให้แสดง">
            </div>
            
            <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">📧 Email (ไม่บังคับ)</label>
                <input type="email" name="email" 
                       class="w-full px-5 py-3 border-2 rounded-xl outline-none focus:ring-4 focus:ring-purple-300 transition"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="email@example.com">
            </div>
            
            <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">
                    🔑 Password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" required minlength="6"
                       class="w-full px-5 py-3 border-2 rounded-xl outline-none focus:ring-4 focus:ring-purple-300 transition"
                       placeholder="password">
                <p class="text-xs text-gray-500 mt-1">อย่างน้อย 6 ตัวอักษร</p>
            </div>
            
            <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">
                    🔑 ยืนยัน Password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="confirm_password" required minlength="6"
                       class="w-full px-5 py-3 border-2 rounded-xl outline-none focus:ring-4 focus:ring-purple-300 transition"
                       placeholder="ยืนยัน password">
            </div>
            
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white py-4 rounded-xl font-semibold shadow-lg hover:shadow-xl transition transform hover:scale-[1.02]">
                📝 สมัครสมาชิก
            </button>
        </form>
        
        <p class="text-center mt-6 text-gray-600">
            มีบัญชีอยู่แล้ว? <a href="login.php" class="text-purple-600 hover:underline font-semibold">เข้าสู่ระบบ</a>
        </p>
    </div>

    <?php if ($error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showError('<?php echo addslashes($error); ?>');
        });
    </script>
    <?php endif; ?>
</body>
</html>