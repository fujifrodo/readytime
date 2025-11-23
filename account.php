<?php
require_once 'functions.php';
requireLogin();

$user = getCurrentUser();
$toast = getToast();

try {
    $db = getDB();
    
    // Handle Profile Update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $displayName = trim($_POST['display_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (!empty($displayName)) {
            $db->beginTransaction();
            
            try {
                // อัปเดตข้อมูล user
                $stmt = $db->prepare("UPDATE users SET display_name = ?, email = ? WHERE id = ?");
                $emailValue = !empty($email) ? $email : null;
                $stmt->execute([$displayName, $emailValue, $user['id']]);
                
                // อัปเดต creator_name ในโพล
                $stmt = $db->prepare("UPDATE polls SET creator_name = ? WHERE created_by = ?");
                $stmt->execute([$displayName, $user['id']]);
                
                // อัปเดต user_name ใน responses
                $stmt = $db->prepare("UPDATE responses SET user_name = ? WHERE user_id = ?");
                $stmt->execute([$displayName, $user['id']]);
                
                $db->commit();
                $_SESSION['display_name'] = $displayName;
                
                setToast('✅ อัปเดตโปรไฟล์สำเร็จ!', 'success');
                redirect('account.php');
                
            } catch (PDOException $e) {
                $db->rollBack();
                throw $e;
            }
        }
    }
    
    // Handle Password Change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword)) {
            setToast('❌ กรุณากรอกข้อมูลให้ครบถ้วน', 'error');
        } elseif ($newPassword !== $confirmPassword) {
            setToast('❌ รหัสผ่านใหม่ไม่ตรงกัน', 'error');
        } elseif (strlen($newPassword) < 6) {
            setToast('❌ รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร', 'error');
        } else {
            // ดึงรหัสผ่านปัจจุบันจาก database
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData && password_verify($currentPassword, $userData['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $user['id']]);
                
                setToast('🔑 เปลี่ยนรหัสผ่านสำเร็จ!', 'success');
                redirect('account.php');
            } else {
                setToast('❌ รหัสผ่านปัจจุบันไม่ถูกต้อง', 'error');
            }
        }
    }
    
    // Get user statistics
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM polls WHERE created_by = ?");
    $stmt->execute([$user['id']]);
    $pollCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM responses WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $responseCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM votes v 
        INNER JOIN responses r ON v.response_id = r.id 
        WHERE r.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $voteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get user's polls with response counts
    $stmt = $db->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM responses WHERE poll_id = p.id) as response_count
        FROM polls p
        WHERE p.created_by = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $myPolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $user = getCurrentUser(); // Refresh user data
    
} catch (PDOException $e) {
    error_log("Account error: " . $e->getMessage());
    $pollCount = 0;
    $responseCount = 0;
    $voteCount = 0;
    $myPolls = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบัญชี - นัดเพื่อน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease-in; }
        .tab-button { cursor: pointer; transition: all 0.3s; }
        .tab-button.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body class="p-4">
    <?php include 'toast.php'; ?>
    
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6 animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">👤 จัดการบัญชี</h1>
                    <p class="text-gray-600 mt-1">ข้อมูลส่วนตัวและการตั้งค่า</p>
                </div>
                <a href="index.php" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-xl hover:shadow-lg transition font-semibold">
                    ← กลับหน้าหลัก
                </a>
            </div>
        </div>
        
        <!-- User Info Card -->
        <div class="bg-gradient-to-r from-purple-600 to-blue-600 rounded-2xl shadow-2xl p-8 mb-6 text-white animate-fade-in">
            <div class="flex items-center gap-6">
                <div class="bg-white rounded-full p-6 text-6xl">
                    👤
                </div>
                <div class="flex-1">
                    <h2 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($user['display_name']); ?></h2>
                    <div class="space-y-1 text-purple-100">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'ไม่ระบุ'); ?></p>
                        <p><strong>สิทธิ์:</strong> <?php echo ($user['role'] ?? 'user') === 'admin' ? '👑 Admin' : '👤 User'; ?></p>
                        <p><strong>สมาชิกเมื่อ:</strong> <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-lg p-6 animate-fade-in hover:scale-105 transition">
                <div class="text-center">
                    <div class="text-5xl mb-2">📊</div>
                    <div class="text-4xl font-bold text-purple-600"><?php echo $pollCount; ?></div>
                    <div class="text-gray-600 font-medium mt-2">โพลที่สร้าง</div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 animate-fade-in hover:scale-105 transition">
                <div class="text-center">
                    <div class="text-5xl mb-2">💬</div>
                    <div class="text-4xl font-bold text-green-600"><?php echo $responseCount; ?></div>
                    <div class="text-gray-600 font-medium mt-2">โพลที่ตอบ</div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 animate-fade-in hover:scale-105 transition">
                <div class="text-center">
                    <div class="text-5xl mb-2">✅</div>
                    <div class="text-4xl font-bold text-blue-600"><?php echo $voteCount; ?></div>
                    <div class="text-gray-600 font-medium mt-2">จำนวนโหวต</div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 animate-fade-in">
            <div class="flex gap-2 mb-6 overflow-x-auto">
                <button class="tab-button active px-6 py-3 rounded-xl font-semibold whitespace-nowrap" onclick="switchTab('profile')">
                    ✏️ แก้ไขโปรไฟล์
                </button>
                <button class="tab-button px-6 py-3 rounded-xl font-semibold whitespace-nowrap bg-gray-100" onclick="switchTab('password')">
                    🔑 เปลี่ยนรหัสผ่าน
                </button>
                <button class="tab-button px-6 py-3 rounded-xl font-semibold whitespace-nowrap bg-gray-100" onclick="switchTab('mypolls')">
                    📊 โพลของฉัน
                </button>
            </div>
            
            <!-- Profile Tab -->
            <div id="tab-profile" class="tab-content active">
                <h3 class="text-2xl font-bold text-gray-800 mb-4">✏️ แก้ไขข้อมูลส่วนตัว</h3>
                <form method="POST" class="space-y-4 max-w-2xl">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">👤 Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled
                               class="w-full px-4 py-3 border-2 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed">
                        <p class="text-xs text-gray-500 mt-1">Username ไม่สามารถเปลี่ยนแปลงได้</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">✨ ชื่อที่แสดง *</label>
                        <input type="text" name="display_name" required
                               value="<?php echo htmlspecialchars($user['display_name']); ?>"
                               class="w-full px-4 py-3 border-2 rounded-xl focus:ring-4 focus:ring-purple-300 outline-none transition">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">📧 อีเมล</label>
                        <input type="email" name="email"
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               class="w-full px-4 py-3 border-2 rounded-xl focus:ring-4 focus:ring-purple-300 outline-none transition">
                    </div>
                    
                    <button type="submit" name="update_profile"
                            class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white py-4 rounded-xl font-semibold shadow-lg hover:shadow-xl transition transform hover:scale-[1.02]">
                        ✅ บันทึกการเปลี่ยนแปลง
                    </button>
                </form>
            </div>
            
            <!-- Password Tab -->
            <div id="tab-password" class="tab-content">
                <h3 class="text-2xl font-bold text-gray-800 mb-4">🔑 เปลี่ยนรหัสผ่าน</h3>
                <form method="POST" class="space-y-4 max-w-2xl">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">🔐 รหัสผ่านปัจจุบัน *</label>
                        <input type="password" name="current_password" required
                               class="w-full px-4 py-3 border-2 rounded-xl focus:ring-4 focus:ring-purple-300 outline-none transition"
                               placeholder="กรอกรหัสผ่านปัจจุบัน">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">🔑 รหัสผ่านใหม่ *</label>
                        <input type="password" name="new_password" required minlength="6"
                               class="w-full px-4 py-3 border-2 rounded-xl focus:ring-4 focus:ring-purple-300 outline-none transition"
                               placeholder="อย่างน้อย 6 ตัวอักษร">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">🔑 ยืนยันรหัสผ่านใหม่ *</label>
                        <input type="password" name="confirm_password" required minlength="6"
                               class="w-full px-4 py-3 border-2 rounded-xl focus:ring-4 focus:ring-purple-300 outline-none transition"
                               placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
                    </div>
                    
                    <button type="submit" name="change_password"
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-4 rounded-xl font-semibold shadow-lg hover:shadow-xl transition transform hover:scale-[1.02]">
                        ✅ เปลี่ยนรหัสผ่าน
                    </button>
                </form>
            </div>
            
            <!-- My Polls Tab -->
            <div id="tab-mypolls" class="tab-content">
                <h3 class="text-2xl font-bold text-gray-800 mb-4">📊 โพลของฉัน (<?php echo count($myPolls); ?>)</h3>
                
                <?php if (empty($myPolls)): ?>
                <div class="text-center py-12 text-gray-500">
                    <div class="text-6xl mb-4">📭</div>
                    <p class="text-lg font-semibold mb-2">คุณยังไม่ได้สร้างโพล</p>
                    <a href="create_poll.php" class="inline-block mt-4 bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition font-medium">
                        ➕ สร้างโพลแรก
                    </a>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($myPolls as $poll): 
                        $isExpired = !empty($poll['expire_date']) && strtotime($poll['expire_date']) < time();
                    ?>
                    <div class="border-2 border-gray-200 rounded-xl p-4 hover:shadow-lg transition hover:border-purple-300 <?php echo $isExpired ? 'opacity-60 bg-gray-50' : ''; ?>">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="text-lg font-bold text-gray-800">
                                    📊 <?php echo htmlspecialchars($poll['title']); ?>
                                    <?php if ($isExpired): ?>
                                    <span class="text-sm text-red-600 font-normal bg-red-100 px-2 py-1 rounded">(หมดอายุ)</span>
                                    <?php endif; ?>
                                </h4>
                                <div class="text-sm text-gray-600 mt-2 space-y-1">
                                    <p>📅 <?php echo date('d/m/Y', strtotime($poll['week_start'])); ?> - <?php echo date('d/m/Y', strtotime($poll['week_end'])); ?></p>
                                    <p>💬 จำนวนผู้ตอบ: <span class="font-semibold text-purple-600"><?php echo $poll['response_count']; ?> คน</span></p>
                                    <p>🕐 สร้างเมื่อ: <?php echo date('d/m/Y H:i', strtotime($poll['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex gap-2">
                                <a href="poll.php?id=<?php echo $poll['id']; ?>" 
                                   class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition text-sm font-medium shadow-md">
                                    👁️ ดู
                                </a>
                                <a href="export_poll.php?id=<?php echo $poll['id']; ?>" 
                                   class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition text-sm font-medium shadow-md">
                                    📥 ส่งออก
                                </a>
                                <a href="delete_poll.php?id=<?php echo $poll['id']; ?>" 
                                   class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition text-sm font-medium shadow-md"
                                   onclick="return confirm('⚠️ ลบโพลนี้?')">
                                    🗑️ ลบ
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('bg-gray-100');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            event.target.classList.add('active');
            event.target.classList.remove('bg-gray-100');
            document.getElementById('tab-' + tab).classList.add('active');
        }
    </script>

    <?php if ($toast): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($toast['message']); ?>', '<?php echo $toast['type']; ?>');
        });
    </script>
    <?php endif; ?>
</body>
</html>
