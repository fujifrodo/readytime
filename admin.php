<?php
require_once 'functions.php';
requireAdmin();

$user = getCurrentUser();
$toast = getToast();

try {
    $db = getDB();
    
    // Check ban columns
    $hasBanColumns = false;
    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'ban_reason'");
        $hasBanColumns = ($stmt->rowCount() > 0);
    } catch (Exception $e) {
        $hasBanColumns = false;
    }
    
    // Handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        if (isset($_POST['delete_user'])) {
            $userId = $_POST['user_id'] ?? null;
            if ($userId && $userId != $user['id']) {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                setToast('ลบผู้ใช้สำเร็จ', 'success');
                redirect('admin.php');
            }
        }
        
        if (isset($_POST['ban_user']) && $hasBanColumns) {
            $userId = $_POST['user_id'] ?? null;
            $banReason = trim($_POST['ban_reason'] ?? 'ไม่ระบุเหตุผล');
            $customReason = trim($_POST['custom_reason'] ?? '');
            $banDuration = $_POST['ban_duration'] ?? 'permanent';
            
            if ($userId && $userId != $user['id']) {
                if ($banReason === 'other' && !empty($customReason)) {
                    $banReason = $customReason;
                }
                
                $banUntil = null;
                if ($banDuration !== 'permanent') {
                    $hours = ['1h'=>1,'24h'=>24,'3d'=>72,'7d'=>168,'30d'=>720,'90d'=>2160,'1y'=>8760][$banDuration] ?? 0;
                    if ($hours > 0) $banUntil = date('Y-m-d H:i:s', time() + ($hours * 3600));
                }
                
                $bannedAt = date('Y-m-d H:i:s');
                $db->prepare("UPDATE users SET banned=1, ban_reason=?, ban_until=?, banned_at=?, banned_by=? WHERE id=?")
                   ->execute([$banReason, $banUntil, $bannedAt, $user['id'], $userId]);
                
                try {
                    $db->prepare("INSERT INTO ban_history (user_id, banned_by, ban_reason, ban_until, banned_at) VALUES (?,?,?,?,?)")
                       ->execute([$userId, $user['id'], $banReason, $banUntil, $bannedAt]);
                } catch (Exception $e) {}
                
                setToast('แบนผู้ใช้สำเร็จ', 'success');
                redirect('admin.php');
            }
        }
        
        if (isset($_POST['toggle_ban'])) {
            $userId = $_POST['user_id'] ?? null;
            if ($userId && $userId != $user['id']) {
                $stmt = $db->prepare("SELECT banned FROM users WHERE id=? LIMIT 1");
                $stmt->execute([$userId]);
                $u = $stmt->fetch();
                if ($u) {
                    $newBan = $u['banned'] ? 0 : 1;
                    $db->prepare("UPDATE users SET banned=? WHERE id=?")->execute([$newBan, $userId]);
                    setToast($newBan ? 'แบนสำเร็จ' : 'ปลดแบนสำเร็จ', 'success');
                    redirect('admin.php');
                }
            }
        }
        
        if (isset($_POST['unban_user'])) {
            $userId = $_POST['user_id'] ?? null;
            if ($userId) {
                if ($hasBanColumns) {
                    try {
                        $db->prepare("UPDATE ban_history SET unbanned_at=NOW(), unbanned_by=? WHERE user_id=? AND unbanned_at IS NULL ORDER BY banned_at DESC LIMIT 1")
                           ->execute([$user['id'], $userId]);
                    } catch (Exception $e) {}
                    $db->prepare("UPDATE users SET banned=0, ban_reason=NULL, ban_until=NULL, banned_at=NULL, banned_by=NULL WHERE id=?")
                       ->execute([$userId]);
                } else {
                    $db->prepare("UPDATE users SET banned=0 WHERE id=?")->execute([$userId]);
                }
                setToast('ปลดแบนสำเร็จ', 'success');
                redirect('admin.php');
            }
        }
        
        if (isset($_POST['change_role'])) {
            $userId = $_POST['user_id'] ?? null;
            $newRole = $_POST['new_role'] ?? 'user';
            if ($userId && $userId != $user['id']) {
                $db->prepare("UPDATE users SET role=? WHERE id=?")->execute([$newRole, $userId]);
                setToast('เปลี่ยนสิทธิ์สำเร็จ', 'success');
                redirect('admin.php');
            }
        }
        
        if (isset($_POST['create_user'])) {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $displayName = trim($_POST['display_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'user';
            
            if (!empty($username) && !empty($password) && !empty($displayName)) {
                $stmt = $db->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    setToast('Username หรือ Email ถูกใช้แล้ว', 'error');
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $token = generateToken();
                    $db->prepare("INSERT INTO users (username, password, display_name, email, role, token, created_at) VALUES (?,?,?,?,?,?,NOW())")
                       ->execute([$username, $hashedPassword, $displayName, $email, $role, $token]);
                    setToast('สร้างผู้ใช้สำเร็จ', 'success');
                    redirect('admin.php');
                }
            }
        }
    }
    
    // Search
    $searchQuery = $_GET['search'] ?? '';
    $searchResults = null;
    if (!empty($searchQuery)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id=? OR username LIKE ? OR display_name LIKE ? ORDER BY created_at DESC LIMIT 10");
        $searchLike = '%' . $searchQuery . '%';
        $stmt->execute([$searchQuery, $searchLike, $searchLike]);
        $searchResults = $stmt->fetchAll();
    }
    
    // Pagination
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalPages = max(1, ceil($totalUsers / $perPage));
    
    $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$perPage, $offset]);
    $users = $stmt->fetchAll();
    
    // Stats
    $totalUsersCount = $totalUsers;
    $bannedUsersCount = $db->query("SELECT COUNT(*) FROM users WHERE banned=1")->fetchColumn();
    $totalPolls = 0;
    try { $totalPolls = $db->query("SELECT COUNT(*) FROM polls")->fetchColumn(); } catch (Exception $e) {}
    
} catch (Exception $e) {
    error_log("Admin error: " . $e->getMessage());
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}

function renderUserCard($u, $currentUser, $hasBanColumns) {
    ?>
    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-6 mb-4 transform hover:-translate-y-1">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <span class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($u['display_name']); ?></span>
                    <?php if ($u['role'] === 'admin'): ?>
                        <span class="px-3 py-1 bg-yellow-400 text-black text-xs font-bold rounded-full">👑 ADMIN</span>
                    <?php else: ?>
                        <span class="px-3 py-1 bg-blue-500 text-white text-xs font-bold rounded-full">USER</span>
                    <?php endif; ?>
                    <?php if ($u['banned']): ?>
                        <span class="px-3 py-1 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse">🚫 BANNED</span>
                    <?php endif; ?>
                </div>
                <div class="text-sm text-gray-600 mb-2">
                    <strong>ID:</strong> <?php echo $u['id']; ?> | 
                    <strong>Username:</strong> <?php echo htmlspecialchars($u['username']); ?> | 
                    <strong>Email:</strong> <?php echo htmlspecialchars($u['email'] ?? 'N/A'); ?>
                </div>
                <div class="text-sm text-gray-500">
                    📅 สมัครเมื่อ: <?php echo formatDate($u['created_at']); ?>
                </div>
                
                <?php if ($u['banned'] && $hasBanColumns && isset($u['ban_reason'])): ?>
                    <div class="mt-3 bg-yellow-50 border-l-4 border-yellow-400 p-3 rounded">
                        <div class="text-sm">
                            <strong>🚫 สถานะแบน:</strong><br>
                            <strong>เหตุผล:</strong> <?php echo htmlspecialchars($u['ban_reason'] ?? 'ไม่ระบุ'); ?><br>
                            <?php if (!empty($u['ban_until'])): ?>
                                <strong>แบนจนถึง:</strong> <?php echo formatDateTime($u['ban_until']); ?>
                            <?php else: ?>
                                <strong>ประเภท:</strong> แบนถาวร
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="flex flex-wrap gap-2">
                <?php if ($u['id'] != $currentUser['id']): ?>
                    <?php if ($u['banned']): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" name="unban_user" 
                                    class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg transition-colors">
                                ✅ ปลดแบน
                            </button>
                        </form>
                    <?php else: ?>
                        <?php if ($hasBanColumns): ?>
                            <button onclick="openBanModal(<?php echo $u['id']; ?>)" 
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition-colors">
                                🚫 แบน
                            </button>
                        <?php else: ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="toggle_ban" 
                                        class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition-colors">
                                    🚫 แบน
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <form method="POST" class="inline">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="new_role" value="<?php echo $u['role'] === 'admin' ? 'user' : 'admin'; ?>">
                        <button type="submit" name="change_role" 
                                class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">
                            <?php echo $u['role'] === 'admin' ? '⬇️ User' : '⬆️ Admin'; ?>
                        </button>
                    </form>
                    
                    <form method="POST" class="inline">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <button type="submit" name="delete_user" 
                                onclick="return confirm('ลบผู้ใช้นี้? ไม่สามารถย้อนกลับได้')"
                                class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-medium rounded-lg transition-colors">
                            🗑️ ลบ
                        </button>
                    </form>
                <?php else: ?>
                    <span class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg">👤 คุณ</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการระบบ - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8 mb-6 animate-slide-in">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h1 class="text-3xl md:text-4xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                    🔧 จัดการระบบ Admin
                </h1>
                <div class="flex flex-wrap gap-2">
                    <a href="index.php" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                        ← กลับ
                    </a>
                    <a href="account.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                        👤 บัญชี
                    </a>
                    <button onclick="openModal('createUserModal')" 
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                        ➕ สร้างผู้ใช้
                    </button>
                </div>
            </div>
        </div>
        
        <?php if (!$hasBanColumns): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-6 rounded-lg animate-slide-in">
                <p class="text-yellow-800">
                    <strong>⚠️ แนะนำ:</strong> รัน SQL จาก database_UPGRADE_FIXED.sql เพื่อใช้ระบบแบนแบบละเอียด
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-xl p-6 text-white animate-slide-in">
                <div class="text-5xl font-bold mb-2"><?php echo $totalUsersCount; ?></div>
                <div class="text-blue-100">👥 ผู้ใช้ทั้งหมด</div>
            </div>
            <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-2xl shadow-xl p-6 text-white animate-slide-in" style="animation-delay: 0.1s;">
                <div class="text-5xl font-bold mb-2"><?php echo $bannedUsersCount; ?></div>
                <div class="text-red-100">🚫 ผู้ใช้ที่ถูกแบน</div>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl shadow-xl p-6 text-white animate-slide-in" style="animation-delay: 0.2s;">
                <div class="text-5xl font-bold mb-2"><?php echo $totalPolls; ?></div>
                <div class="text-purple-100">📊 โพลทั้งหมด</div>
            </div>
        </div>
        
        <!-- Search -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 animate-slide-in">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">🔍 ค้นหาผู้ใช้</h2>
            <form method="GET" class="flex gap-2 mb-4 flex-wrap">
                <input type="text" name="search" 
                       class="flex-1 min-w-[200px] px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-purple-500 focus:ring focus:ring-purple-200 outline-none transition-all"
                       placeholder="ค้นหาด้วย User ID, Username หรือชื่อ..."
                       value="<?php echo htmlspecialchars($searchQuery); ?>">
                <button type="submit" 
                        class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors font-medium">
                    ค้นหา
                </button>
                <?php if (!empty($searchQuery)): ?>
                    <a href="admin.php" 
                       class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-medium">
                        ล้าง
                    </a>
                <?php endif; ?>
            </form>
            
            <?php if (!empty($searchQuery)): ?>
                <h3 class="text-lg font-bold text-gray-700 mb-4">
                    ผลการค้นหา "<?php echo htmlspecialchars($searchQuery); ?>" (<?php echo count($searchResults); ?> รายการ)
                </h3>
                <?php if (empty($searchResults)): ?>
                    <div class="text-center py-12 text-gray-500">
                        <div class="text-6xl mb-4">😔</div>
                        <div class="text-xl">ไม่พบผู้ใช้ที่ตรงกับการค้นหา</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($searchResults as $u): renderUserCard($u, $user, $hasBanColumns); endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Users List -->
        <div class="bg-white rounded-2xl shadow-xl p-6 animate-slide-in">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                👥 ผู้ใช้ทั้งหมด <span class="text-gray-500 text-lg">(หน้า <?php echo $page; ?>/<?php echo $totalPages; ?>)</span>
            </h2>
            
            <?php foreach ($users as $u): renderUserCard($u, $user, $hasBanColumns); endforeach; ?>
            
            <?php if ($totalPages > 1): ?>
                <div class="flex justify-center gap-2 mt-6 flex-wrap">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" 
                           class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            ← ก่อนหน้า
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="px-4 py-2 <?php echo $i == $page ? 'bg-purple-600 text-white' : 'bg-white border-2 border-purple-600 text-purple-600 hover:bg-purple-50'; ?> rounded-lg transition-colors">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" 
                           class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            ถัดไป →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Ban Modal -->
    <?php if ($hasBanColumns): ?>
    <div id="banUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-slide-in">
            <div class="p-6 border-b-2 border-gray-200 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-2xl font-bold text-gray-800">🚫 แบนผู้ใช้</h2>
                <button onclick="closeModal('banUserModal')" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="user_id" id="ban_user_id">
                
                <div class="mb-6">
                    <label class="block text-lg font-bold text-gray-800 mb-3">เลือกเหตุผล:</label>
                    <div class="space-y-2">
                        <?php 
                        $reasons = [
                            'เนื้อหาไม่เหมาะสม' => '🔞 เนื้อหาไม่เหมาะสม',
                            'สแปม/ส่งข้อความรบกวน' => '📧 สแปม/ส่งข้อความรบกวน',
                            'พฤติกรรมไม่เหมาะสม/ก้าวร้าว' => '😡 พฤติกรรมไม่เหมาะสม',
                            'Bot/บัญชีปลอม' => '🤖 Bot/บัญชีปลอม',
                            'ละเมิดกฎ/ข้อตกลง' => '📜 ละเมิดกฎ/ข้อตกลง',
                            'เหตุผลด้านความปลอดภัย' => '🔒 ด้านความปลอดภัย',
                            'other' => '✍️ อื่น ๆ (ระบุเอง)'
                        ];
                        foreach ($reasons as $value => $label): ?>
                            <label class="flex items-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all">
                                <input type="radio" name="ban_reason" value="<?php echo $value; ?>" class="mr-3" <?php echo $value === 'เนื้อหาไม่เหมาะสม' ? 'required' : ''; ?>>
                                <span><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-6 hidden" id="custom_reason_group">
                    <label class="block text-lg font-bold text-gray-800 mb-2">ระบุเหตุผล:</label>
                    <textarea name="custom_reason" rows="3" 
                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none"
                              placeholder="ระบุเหตุผลการแบน..."></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-lg font-bold text-gray-800 mb-3">กำหนดระยะเวลา:</label>
                    <div class="space-y-2">
                        <?php 
                        $durations = [
                            '1h' => '⏰ 1 ชั่วโมง',
                            '24h' => '⏰ 24 ชั่วโมง',
                            '3d' => '⏰ 3 วัน',
                            '7d' => '⏰ 7 วัน',
                            '30d' => '⏰ 30 วัน',
                            '90d' => '⏰ 90 วัน',
                            '1y' => '⏰ 1 ปี',
                            'permanent' => '♾️ ถาวร'
                        ];
                        foreach ($durations as $value => $label): ?>
                            <label class="flex items-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all">
                                <input type="radio" name="ban_duration" value="<?php echo $value; ?>" class="mr-3" <?php echo $value === 'permanent' ? 'checked' : ''; ?>>
                                <span><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" name="ban_user" 
                            class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors font-bold">
                        ✅ ยืนยันแบน
                    </button>
                    <button type="button" onclick="closeModal('banUserModal')" 
                            class="flex-1 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-bold">
                        ❌ ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Create User Modal -->
    <div id="createUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full animate-slide-in">
            <div class="p-6 border-b-2 border-gray-200 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-800">➕ สร้างผู้ใช้ใหม่</h2>
                <button onclick="closeModal('createUserModal')" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <div>
                    <label class="block font-bold text-gray-700 mb-2">Username:</label>
                    <input type="text" name="username" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">รหัสผ่าน:</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">ชื่อที่แสดง:</label>
                    <input type="text" name="display_name" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">อีเมล:</label>
                    <input type="email" name="email" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">สิทธิ์:</label>
                    <select name="role"
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit" name="create_user" 
                            class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors font-bold">
                        สร้างผู้ใช้
                    </button>
                    <button type="button" onclick="closeModal('createUserModal')" 
                            class="flex-1 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-bold">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($toast): include 'toast.php'; endif; ?>
    
    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }
        function openBanModal(userId) {
            document.getElementById('ban_user_id').value = userId;
            openModal('banUserModal');
        }
        
        document.querySelectorAll('input[name="ban_reason"]').forEach(r => {
            r.addEventListener('change', function() {
                document.getElementById('custom_reason_group').classList.toggle('hidden', this.value !== 'other');
            });
        });
    </script>
</body>
</html>