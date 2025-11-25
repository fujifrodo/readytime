<?php
require_once 'functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$banInfo = null;

// เช็คว่ามีข้อมูล ban จาก session หรือไม่ (จาก getCurrentUser)
if (isset($_SESSION['ban_info'])) {
    $banInfo = $_SESSION['ban_info'];
    unset($_SESSION['ban_info']); // ลบออกหลังแสดงผล
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        try {
            $db = getDB();
            
            // ดึงข้อมูล user รวมข้อมูล ban
            $stmt = $db->prepare("
                SELECT id, username, password, display_name, role, banned, 
                       ban_reason, ban_until, banned_at, banned_by 
                FROM users 
                WHERE username = ? 
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = 'ไม่พบ Username นี้';
            } elseif (!password_verify($password, $user['password'])) {
                $error = 'รหัสผ่านไม่ถูกต้อง';
            } elseif ($user['banned']) {
                // เช็คสถานะ ban ละเอียด
                $banStatus = checkBanStatus($user);
                
                if ($banStatus['banned']) {
                    $banInfo = $banStatus;
                } else {
                    // ถ้าหมดเวลาแบนแล้ว ให้ login ได้
                    goto allow_login;
                }
            } else {
                allow_login:
                // Login สำเร็จ - สร้าง token และอัปเดต last_login
                $token = generateToken();
                
                $stmt = $db->prepare("UPDATE users SET token = ?, last_login = NOW() WHERE id = ?");
                $stmt->execute([$token, $user['id']]);
                
                // ตั้งค่า session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['token'] = $token;
                $_SESSION['username'] = $user['username'];
                $_SESSION['display_name'] = $user['display_name'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                
                setToast('🎉 เข้าสู่ระบบสำเร็จ! ยินดีต้อนรับ ' . htmlspecialchars($user['display_name']), 'success');
                redirect('index.php');
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - นัดเพื่อน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        
        .ban-alert {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(255, 65, 108, 0.3);
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .ban-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .ban-title {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .ban-details {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            backdrop-filter: blur(10px);
        }
        
        .ban-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .ban-row:last-child {
            margin-bottom: 0;
        }
        
        .ban-label {
            font-weight: 600;
            opacity: 0.9;
        }
        
        .ban-value {
            font-weight: 500;
            text-align: right;
        }
        
        .ban-countdown {
            background: rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .ban-note {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            opacity: 0.85;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <?php include 'toast.php'; ?>
    
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md animate-fade-in">
        <?php if ($banInfo): ?>
            <!-- Ban Alert Message -->
            <div class="ban-alert">
                <div class="ban-icon">🚫</div>
                <div class="ban-title">บัญชีของคุณถูกระงับ</div>
                
                <div class="ban-details">
                    <div class="ban-row">
                        <span class="ban-label">📝 เหตุผล:</span>
                        <span class="ban-value"><?php echo htmlspecialchars($banInfo['reason']); ?></span>
                    </div>
                    
                    <?php if ($banInfo['banned_at']): ?>
                        <div class="ban-row">
                            <span class="ban-label">🕐 ระงับเมื่อ:</span>
                            <span class="ban-value"><?php echo formatDateTime($banInfo['banned_at']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($banInfo['permanent']): ?>
                        <div class="ban-row">
                            <span class="ban-label">⏱️ ประเภท:</span>
                            <span class="ban-value">♾️ แบนถาวร</span>
                        </div>
                    <?php else: ?>
                        <div class="ban-row">
                            <span class="ban-label">⏰ ระงับจนถึง:</span>
                            <span class="ban-value"><?php echo formatDateTime($banInfo['ban_until']); ?></span>
                        </div>
                        
                        <?php if (isset($banInfo['remaining'])): ?>
                            <div class="ban-countdown">
                                ⏳ เหลืออีก <?php echo formatBanDuration($banInfo['remaining']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="ban-note">
                    💬 หากคุณคิดว่านี่เป็นข้อผิดพลาด<br>
                    โปรดติดต่อผู้ดูแลระบบ
                </div>
            </div>
        <?php endif; ?>
        
        <h1 class="text-3xl font-bold text-center mb-2 text-purple-600">📅 นัดเพื่อน</h1>
        <p class="text-center text-gray-600 mb-6">เข้าสู่ระบบ</p>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">👤 Username</label>
                <input type="text" name="username" required autofocus
                       class="w-full px-5 py-3 border-2 rounded-xl outline-none focus:ring-4 focus:ring-purple-300 transition"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       placeholder="username">
            </div>
            
            <div>
                <label class="block text-sm font-semibold mb-2 text-gray-700">🔑 Password</label>
                <input type="password" name="password" required 
                       class="w-full px-5 py-3 border-2 rounded-xl outline-none focus:ring-4 focus:ring-purple-300 transition"
                       placeholder="password">
            </div>
            
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white py-4 rounded-xl font-semibold shadow-lg hover:shadow-xl transition transform hover:scale-[1.02]">
                🔐 เข้าสู่ระบบ
            </button>
        </form>
        
        <p class="text-center mt-6 text-gray-600">
            ยังไม่มีบัญชี? <a href="register.php" class="text-purple-600 hover:underline font-semibold">สมัครสมาชิก</a>
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