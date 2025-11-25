<?php
require_once 'functions.php';
requireAdmin();

$user = getCurrentUser();
$toast = getToast();
$db = getDB();

// Check columns exist
$hasBanColumns = false;
$hasAnnouncementTable = false;
$hasActivityLogTable = false;
$hasSettingsTable = false;

try {
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'ban_reason'");
    $hasBanColumns = ($stmt->rowCount() > 0);
} catch (Exception $e) {}

try {
    $db->query("SELECT 1 FROM announcements LIMIT 1");
    $hasAnnouncementTable = true;
} catch (Exception $e) {}

try {
    $db->query("SELECT 1 FROM activity_log LIMIT 1");
    $hasActivityLogTable = true;
} catch (Exception $e) {}

try {
    $db->query("SELECT 1 FROM system_settings LIMIT 1");
    $hasSettingsTable = true;
} catch (Exception $e) {}

// Activity Log Function
function logActivity($db, $adminId, $action, $targetType = null, $targetId = null, $details = null) {
    try {
        $db->query("SELECT 1 FROM activity_log LIMIT 1");
        $stmt = $db->prepare("INSERT INTO activity_log (admin_id, action, target_type, target_id, details, created_at) VALUES (?,?,?,?,?,NOW())");
        $stmt->execute([$adminId, $action, $targetType, $targetId, $details]);
    } catch (Exception $e) {}
}

// Current Tab
$tab = $_GET['tab'] ?? 'dashboard';

// ==================== HANDLE POST REQUESTS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Delete User
    if (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'] ?? null;
        if ($userId && $userId != $user['id']) {
            $stmt = $db->prepare("SELECT display_name FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $targetUser = $stmt->fetch();
            
            $db->prepare("DELETE FROM votes WHERE response_id IN (SELECT id FROM responses WHERE user_id=?)")->execute([$userId]);
            $db->prepare("DELETE FROM responses WHERE user_id=?")->execute([$userId]);
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);
            
            logActivity($db, $user['id'], 'delete_user', 'user', $userId, $targetUser['display_name'] ?? 'Unknown');
            setToast('ลบผู้ใช้สำเร็จ', 'success');
            redirect('admin.php?tab=users');
        }
    }
    
    // Bulk Delete Users
    if (isset($_POST['bulk_delete'])) {
        $userIds = $_POST['selected_users'] ?? [];
        $count = 0;
        foreach ($userIds as $userId) {
            if ($userId != $user['id']) {
                $db->prepare("DELETE FROM votes WHERE response_id IN (SELECT id FROM responses WHERE user_id=?)")->execute([$userId]);
                $db->prepare("DELETE FROM responses WHERE user_id=?")->execute([$userId]);
                $db->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);
                $count++;
            }
        }
        logActivity($db, $user['id'], 'bulk_delete_users', 'users', null, "Deleted $count users");
        setToast("ลบผู้ใช้ $count คนสำเร็จ", 'success');
        redirect('admin.php?tab=users');
    }
    
    // Bulk Ban Users
    if (isset($_POST['bulk_ban'])) {
        $userIds = $_POST['selected_users'] ?? [];
        $count = 0;
        foreach ($userIds as $userId) {
            if ($userId != $user['id']) {
                $db->prepare("UPDATE users SET banned=1 WHERE id=?")->execute([$userId]);
                $count++;
            }
        }
        logActivity($db, $user['id'], 'bulk_ban_users', 'users', null, "Banned $count users");
        setToast("แบนผู้ใช้ $count คนสำเร็จ", 'success');
        redirect('admin.php?tab=users');
    }
    
    // Bulk Unban Users
    if (isset($_POST['bulk_unban'])) {
        $userIds = $_POST['selected_users'] ?? [];
        $count = 0;
        foreach ($userIds as $userId) {
            $db->prepare("UPDATE users SET banned=0, ban_reason=NULL, ban_until=NULL, banned_at=NULL, banned_by=NULL WHERE id=?")->execute([$userId]);
            $count++;
        }
        logActivity($db, $user['id'], 'bulk_unban_users', 'users', null, "Unbanned $count users");
        setToast("ปลดแบนผู้ใช้ $count คนสำเร็จ", 'success');
        redirect('admin.php?tab=users');
    }
    
    // Ban User (Advanced)
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
            
            logActivity($db, $user['id'], 'ban_user', 'user', $userId, $banReason);
            setToast('แบนผู้ใช้สำเร็จ', 'success');
            redirect('admin.php?tab=users');
        }
    }
    
    // Simple Ban Toggle
    if (isset($_POST['toggle_ban'])) {
        $userId = $_POST['user_id'] ?? null;
        if ($userId && $userId != $user['id']) {
            $stmt = $db->prepare("SELECT banned FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch();
            if ($u) {
                $newBan = $u['banned'] ? 0 : 1;
                $db->prepare("UPDATE users SET banned=? WHERE id=?")->execute([$newBan, $userId]);
                logActivity($db, $user['id'], $newBan ? 'ban_user' : 'unban_user', 'user', $userId);
                setToast($newBan ? 'แบนสำเร็จ' : 'ปลดแบนสำเร็จ', 'success');
                redirect('admin.php?tab=users');
            }
        }
    }
    
    // Unban User
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
            logActivity($db, $user['id'], 'unban_user', 'user', $userId);
            setToast('ปลดแบนสำเร็จ', 'success');
            redirect('admin.php?tab=users');
        }
    }
    
    // Change Role
    if (isset($_POST['change_role'])) {
        $userId = $_POST['user_id'] ?? null;
        $newRole = $_POST['new_role'] ?? 'user';
        if ($userId && $userId != $user['id']) {
            $db->prepare("UPDATE users SET role=? WHERE id=?")->execute([$newRole, $userId]);
            logActivity($db, $user['id'], 'change_role', 'user', $userId, "Changed to $newRole");
            setToast('เปลี่ยนสิทธิ์สำเร็จ', 'success');
            redirect('admin.php?tab=users');
        }
    }
    
    // Create User
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
                $newId = generateSafeUserId();
                $db->prepare("INSERT INTO users (id, username, password, display_name, email, role, token, created_at) VALUES (?,?,?,?,?,?,?,NOW())")
                   ->execute([$newId, $username, $hashedPassword, $displayName, $email, $role, $token]);
                logActivity($db, $user['id'], 'create_user', 'user', $newId, $displayName);
                setToast('สร้างผู้ใช้สำเร็จ', 'success');
                redirect('admin.php?tab=users');
            }
        }
    }
    
    // Edit User
    if (isset($_POST['edit_user'])) {
        $userId = $_POST['user_id'] ?? null;
        $displayName = trim($_POST['display_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        
        if ($userId && !empty($displayName)) {
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET display_name=?, email=?, password=? WHERE id=?")
                   ->execute([$displayName, $email, $hashedPassword, $userId]);
            } else {
                $db->prepare("UPDATE users SET display_name=?, email=? WHERE id=?")
                   ->execute([$displayName, $email, $userId]);
            }
            logActivity($db, $user['id'], 'edit_user', 'user', $userId, $displayName);
            setToast('แก้ไขข้อมูลผู้ใช้สำเร็จ', 'success');
            redirect('admin.php?tab=users');
        }
    }
    
    // Delete Poll
    if (isset($_POST['delete_poll'])) {
        $pollId = $_POST['poll_id'] ?? null;
        if ($pollId) {
            $db->prepare("DELETE FROM votes WHERE response_id IN (SELECT id FROM responses WHERE poll_id=?)")->execute([$pollId]);
            $db->prepare("DELETE FROM responses WHERE poll_id=?")->execute([$pollId]);
            $db->prepare("DELETE FROM poll_slots WHERE poll_id=?")->execute([$pollId]);
            $db->prepare("DELETE FROM polls WHERE id=?")->execute([$pollId]);
            logActivity($db, $user['id'], 'delete_poll', 'poll', $pollId);
            setToast('ลบโพลสำเร็จ', 'success');
            redirect('admin.php?tab=polls');
        }
    }
    
    // Close Poll
    if (isset($_POST['close_poll'])) {
        $pollId = $_POST['poll_id'] ?? null;
        if ($pollId) {
            $db->prepare("UPDATE polls SET expire_date=NOW() WHERE id=?")->execute([$pollId]);
            logActivity($db, $user['id'], 'close_poll', 'poll', $pollId);
            setToast('ปิดโพลสำเร็จ', 'success');
            redirect('admin.php?tab=polls');
        }
    }
    
    // Create Announcement
    if (isset($_POST['create_announcement']) && $hasAnnouncementTable) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $expireAt = !empty($_POST['expire_at']) ? $_POST['expire_at'] : null;
        
        if (!empty($title) && !empty($content)) {
            $db->prepare("INSERT INTO announcements (title, content, type, expire_at, created_by, created_at) VALUES (?,?,?,?,?,NOW())")
               ->execute([$title, $content, $type, $expireAt, $user['id']]);
            logActivity($db, $user['id'], 'create_announcement', 'announcement', null, $title);
            setToast('สร้างประกาศสำเร็จ', 'success');
            redirect('admin.php?tab=announcements');
        }
    }
    
    // Delete Announcement
    if (isset($_POST['delete_announcement']) && $hasAnnouncementTable) {
        $annId = $_POST['announcement_id'] ?? null;
        if ($annId) {
            $db->prepare("DELETE FROM announcements WHERE id=?")->execute([$annId]);
            logActivity($db, $user['id'], 'delete_announcement', 'announcement', $annId);
            setToast('ลบประกาศสำเร็จ', 'success');
            redirect('admin.php?tab=announcements');
        }
    }
    
    // Save Settings
    if (isset($_POST['save_settings']) && $hasSettingsTable) {
        $settings = [
            'site_name' => trim($_POST['site_name'] ?? 'ReadyTime'),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
            'max_polls_per_user' => (int)($_POST['max_polls_per_user'] ?? 50),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("SELECT id FROM system_settings WHERE setting_key=?");
            $stmt->execute([$key]);
            if ($stmt->fetch()) {
                $db->prepare("UPDATE system_settings SET setting_value=?, updated_at=NOW() WHERE setting_key=?")->execute([$value, $key]);
            } else {
                $db->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())")->execute([$key, $value]);
            }
        }
        logActivity($db, $user['id'], 'update_settings', 'system', null);
        setToast('บันทึกการตั้งค่าสำเร็จ', 'success');
        redirect('admin.php?tab=settings');
    }
}

// ==================== FETCH DATA ====================

// Stats
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$bannedUsers = $db->query("SELECT COUNT(*) FROM users WHERE banned=1")->fetchColumn();
$adminUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$totalPolls = 0;
$totalResponses = 0;
$activePolls = 0;
try {
    $totalPolls = $db->query("SELECT COUNT(*) FROM polls")->fetchColumn();
    $totalResponses = $db->query("SELECT COUNT(*) FROM responses")->fetchColumn();
    $activePolls = $db->query("SELECT COUNT(*) FROM polls WHERE expire_date IS NULL OR expire_date > NOW()")->fetchColumn();
} catch (Exception $e) {}

// New users stats (last 7 days)
$newUsersData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $newUsersData[] = [
        'date' => date('d/m', strtotime($date)),
        'count' => (int)$stmt->fetchColumn()
    ];
}

// New polls stats (last 7 days)
$newPollsData = [];
try {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $db->prepare("SELECT COUNT(*) FROM polls WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $newPollsData[] = [
            'date' => date('d/m', strtotime($date)),
            'count' => (int)$stmt->fetchColumn()
        ];
    }
} catch (Exception $e) {
    $newPollsData = array_fill(0, 7, ['date' => '', 'count' => 0]);
}

// Users Tab
$userSearch = $_GET['user_search'] ?? '';
$userFilter = $_GET['user_filter'] ?? 'all';
$userSort = $_GET['user_sort'] ?? 'newest';
$userPage = max(1, (int)($_GET['user_page'] ?? 1));
$perPage = 15;

$userWhere = "WHERE 1=1";
$userParams = [];

if (!empty($userSearch)) {
    $userWhere .= " AND (id = ? OR username LIKE ? OR display_name LIKE ? OR email LIKE ?)";
    $userParams[] = $userSearch;
    $userParams[] = "%$userSearch%";
    $userParams[] = "%$userSearch%";
    $userParams[] = "%$userSearch%";
}

if ($userFilter === 'banned') {
    $userWhere .= " AND banned = 1";
} elseif ($userFilter === 'active') {
    $userWhere .= " AND banned = 0";
} elseif ($userFilter === 'admin') {
    $userWhere .= " AND role = 'admin'";
}

$orderBy = match($userSort) {
    'oldest' => 'created_at ASC',
    'name' => 'display_name ASC',
    'username' => 'username ASC',
    default => 'created_at DESC'
};

$totalFilteredUsers = $db->prepare("SELECT COUNT(*) FROM users $userWhere");
$totalFilteredUsers->execute($userParams);
$totalFilteredUsers = $totalFilteredUsers->fetchColumn();
$totalUserPages = max(1, ceil($totalFilteredUsers / $perPage));
$userOffset = ($userPage - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM users $userWhere ORDER BY $orderBy LIMIT $perPage OFFSET $userOffset");
$stmt->execute($userParams);
$users = $stmt->fetchAll();

// Polls Tab
$pollSearch = $_GET['poll_search'] ?? '';
$pollFilter = $_GET['poll_filter'] ?? 'all';
$pollPage = max(1, (int)($_GET['poll_page'] ?? 1));

$pollWhere = "WHERE 1=1";
$pollParams = [];

if (!empty($pollSearch)) {
    $pollWhere .= " AND (p.id = ? OR p.title LIKE ? OR u.display_name LIKE ?)";
    $pollParams[] = $pollSearch;
    $pollParams[] = "%$pollSearch%";
    $pollParams[] = "%$pollSearch%";
}

if ($pollFilter === 'active') {
    $pollWhere .= " AND (p.expire_date IS NULL OR p.expire_date > NOW())";
} elseif ($pollFilter === 'expired') {
    $pollWhere .= " AND p.expire_date IS NOT NULL AND p.expire_date <= NOW()";
}

$polls = [];
$totalFilteredPolls = 0;
$totalPollPages = 1;
try {
    $totalFilteredPolls = $db->prepare("SELECT COUNT(*) FROM polls p LEFT JOIN users u ON p.creator_id = u.id $pollWhere");
    $totalFilteredPolls->execute($pollParams);
    $totalFilteredPolls = $totalFilteredPolls->fetchColumn();
    $totalPollPages = max(1, ceil($totalFilteredPolls / $perPage));
    $pollOffset = ($pollPage - 1) * $perPage;
    
    $stmt = $db->prepare("
        SELECT p.*, u.display_name as creator_name, u.username as creator_username,
               (SELECT COUNT(*) FROM responses WHERE poll_id = p.id) as response_count
        FROM polls p 
        LEFT JOIN users u ON p.creator_id = u.id 
        $pollWhere 
        ORDER BY p.created_at DESC 
        LIMIT $perPage OFFSET $pollOffset
    ");
    $stmt->execute($pollParams);
    $polls = $stmt->fetchAll();
} catch (Exception $e) {}

// Ban History
$banHistory = [];
try {
    $stmt = $db->query("
        SELECT bh.*, 
               u.display_name as user_name, u.username,
               admin.display_name as admin_name,
               unbanner.display_name as unbanner_name
        FROM ban_history bh
        LEFT JOIN users u ON bh.user_id = u.id
        LEFT JOIN users admin ON bh.banned_by = admin.id
        LEFT JOIN users unbanner ON bh.unbanned_by = unbanner.id
        ORDER BY bh.banned_at DESC
        LIMIT 50
    ");
    $banHistory = $stmt->fetchAll();
} catch (Exception $e) {}

// Activity Log
$activityLog = [];
if ($hasActivityLogTable) {
    try {
        $stmt = $db->query("
            SELECT al.*, u.display_name as admin_name
            FROM activity_log al
            LEFT JOIN users u ON al.admin_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 100
        ");
        $activityLog = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// Announcements
$announcements = [];
if ($hasAnnouncementTable) {
    try {
        $stmt = $db->query("
            SELECT a.*, u.display_name as creator_name
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC
        ");
        $announcements = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// System Settings
$settings = [
    'site_name' => 'ReadyTime',
    'site_description' => 'ระบบนัดหมายออนไลน์',
    'allow_registration' => '1',
    'max_polls_per_user' => '50',
    'maintenance_mode' => '0'
];
if ($hasSettingsTable) {
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}
}

// Recent Activity (last 10)
$recentActivity = [];
try {
    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    while ($row = $stmt->fetch()) {
        $recentActivity[] = [
            'type' => 'user_register',
            'message' => $row['display_name'] . ' สมัครสมาชิก',
            'time' => $row['created_at']
        ];
    }
} catch (Exception $e) {}

usort($recentActivity, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
$recentActivity = array_slice($recentActivity, 0, 10);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Admin Panel - ReadyTime</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        :root {
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        body { background: var(--bg-gradient); }
        body.dark { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); }
        body.dark .bg-white { background: #1e293b !important; color: #e2e8f0; }
        body.dark .text-gray-800, body.dark .text-gray-700, body.dark .text-gray-600 { color: #e2e8f0 !important; }
        body.dark .text-gray-500 { color: #94a3b8 !important; }
        body.dark .border-gray-200, body.dark .border-gray-300 { border-color: #334155 !important; }
        body.dark input, body.dark select, body.dark textarea { background: #334155 !important; color: #e2e8f0 !important; border-color: #475569 !important; }
        body.dark .hover\:bg-gray-50:hover { background: #334155 !important; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        .animate-pulse-slow { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        .tab-btn { transition: all 0.3s; }
        .tab-btn.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card { transition: transform 0.3s, box-shadow 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .user-card { transition: all 0.3s; }
        .user-card:hover { transform: translateX(5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .checkbox-fancy { appearance: none; width: 20px; height: 20px; border: 2px solid #d1d5db; border-radius: 4px; cursor: pointer; transition: all 0.2s; }
        .checkbox-fancy:checked { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-color: #667eea; }
        .checkbox-fancy:checked::after { content: '✓'; color: white; display: flex; justify-content: center; align-items: center; font-size: 14px; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
    </style>
</head>
<body class="min-h-screen p-2 md:p-6" id="body">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-2xl p-4 md:p-6 mb-4 animate-slide-in">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl md:text-4xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                        🔧 Admin Panel
                    </h1>
                    <p class="text-gray-500 mt-1">จัดการระบบ ReadyTime</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="toggleDarkMode()" class="p-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors" title="Dark Mode">
                        🌙
                    </button>
                    <a href="index.php" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors text-sm">
                        ← กลับ
                    </a>
                    <a href="account.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors text-sm">
                        👤 บัญชี
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tab Navigation -->
        <div class="bg-white rounded-2xl shadow-xl p-2 mb-4 animate-slide-in overflow-x-auto">
            <div class="flex gap-1 min-w-max">
                <?php
                $tabs = [
                    'dashboard' => ['📊', 'Dashboard'],
                    'users' => ['👥', 'ผู้ใช้'],
                    'polls' => ['📋', 'โพล'],
                    'bans' => ['🚫', 'ประวัติแบน'],
                    'logs' => ['📜', 'Activity Log'],
                    'announcements' => ['📢', 'ประกาศ'],
                    'settings' => ['⚙️', 'ตั้งค่า']
                ];
                foreach ($tabs as $key => $info): ?>
                    <a href="?tab=<?php echo $key; ?>" 
                       class="tab-btn px-3 md:px-5 py-2 md:py-3 rounded-xl font-medium text-sm md:text-base whitespace-nowrap <?php echo $tab === $key ? 'active' : 'text-gray-600 hover:bg-gray-100'; ?>">
                        <?php echo $info[0] . ' ' . $info[1]; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- ==================== DASHBOARD TAB ==================== -->
        <?php if ($tab === 'dashboard'): ?>
        <div class="space-y-4 animate-fade-in">
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-xl p-4 text-white">
                    <div class="text-3xl md:text-4xl font-bold"><?php echo number_format($totalUsers); ?></div>
                    <div class="text-blue-100 text-sm">👥 ผู้ใช้ทั้งหมด</div>
                </div>
                <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-2xl shadow-xl p-4 text-white">
                    <div class="text-3xl md:text-4xl font-bold"><?php echo number_format($totalUsers - $bannedUsers); ?></div>
                    <div class="text-green-100 text-sm">✅ ผู้ใช้ปกติ</div>
                </div>
                <div class="stat-card bg-gradient-to-br from-red-500 to-red-600 rounded-2xl shadow-xl p-4 text-white">
                    <div class="text-3xl md:text-4xl font-bold"><?php echo number_format($bannedUsers); ?></div>
                    <div class="text-red-100 text-sm">🚫 ถูกแบน</div>
                </div>
                <div class="stat-card bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-2xl shadow-xl p-4 text-white">
                    <div class="text-3xl md:text-4xl font-bold"><?php echo number_format($adminUsers); ?></div>
                    <div class="text-yellow-100 text-sm">👑 Admin</div>
                </div>
                <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl shadow-xl p-4 text-white">
                    <div class="text-3xl md:text-4xl font-bold"><?php echo number_format($totalPolls); ?></div>
                    <div class="text-purple-100 text-sm">📊 โพลทั้งหมด</div>
                </div>
                <div class="stat-card bg-gradient-to-br from-pink-500 to-pink-600 rounded-2xl shadow-xl p-4 text-white">
                    <div class="text-3xl md:text-4xl font-bold"><?php echo number_format($totalResponses); ?></div>
                    <div class="text-pink-100 text-sm">📝 การตอบกลับ</div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">📈 ผู้ใช้ใหม่ (7 วัน)</h3>
                    <canvas id="usersChart" height="200"></canvas>
                </div>
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">📊 โพลใหม่ (7 วัน)</h3>
                    <canvas id="pollsChart" height="200"></canvas>
                </div>
            </div>
            
            <!-- Quick Actions & Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">⚡ Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <button onclick="openModal('createUserModal')" class="p-4 bg-green-100 hover:bg-green-200 rounded-xl transition-colors text-center">
                            <div class="text-2xl mb-1">➕</div>
                            <div class="text-sm font-medium text-green-800">สร้างผู้ใช้</div>
                        </button>
                        <a href="?tab=users&user_filter=banned" class="p-4 bg-red-100 hover:bg-red-200 rounded-xl transition-colors text-center block">
                            <div class="text-2xl mb-1">🚫</div>
                            <div class="text-sm font-medium text-red-800">ดูผู้ถูกแบน</div>
                        </a>
                        <a href="?tab=polls" class="p-4 bg-purple-100 hover:bg-purple-200 rounded-xl transition-colors text-center block">
                            <div class="text-2xl mb-1">📋</div>
                            <div class="text-sm font-medium text-purple-800">จัดการโพล</div>
                        </a>
                        <a href="?tab=settings" class="p-4 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors text-center block">
                            <div class="text-2xl mb-1">⚙️</div>
                            <div class="text-sm font-medium text-gray-800">ตั้งค่าระบบ</div>
                        </a>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl shadow-xl p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">🕐 กิจกรรมล่าสุด</h3>
                    <div class="space-y-3 max-h-64 overflow-y-auto scrollbar-thin">
                        <?php if (empty($recentActivity)): ?>
                            <p class="text-gray-500 text-center py-4">ไม่มีกิจกรรม</p>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="text-xl">
                                        <?php echo $activity['type'] === 'user_register' ? '👤' : '📊'; ?>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($activity['message']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo timeAgo($activity['time']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">💻 System Status</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-green-50 rounded-xl text-center">
                        <div class="text-2xl mb-1">✅</div>
                        <div class="text-sm font-medium text-green-800">Database</div>
                        <div class="text-xs text-green-600">Connected</div>
                    </div>
                    <div class="p-4 <?php echo $hasBanColumns ? 'bg-green-50' : 'bg-yellow-50'; ?> rounded-xl text-center">
                        <div class="text-2xl mb-1"><?php echo $hasBanColumns ? '✅' : '⚠️'; ?></div>
                        <div class="text-sm font-medium <?php echo $hasBanColumns ? 'text-green-800' : 'text-yellow-800'; ?>">Ban System</div>
                        <div class="text-xs <?php echo $hasBanColumns ? 'text-green-600' : 'text-yellow-600'; ?>"><?php echo $hasBanColumns ? 'Advanced' : 'Basic'; ?></div>
                    </div>
                    <div class="p-4 <?php echo $hasActivityLogTable ? 'bg-green-50' : 'bg-gray-50'; ?> rounded-xl text-center">
                        <div class="text-2xl mb-1"><?php echo $hasActivityLogTable ? '✅' : '❌'; ?></div>
                        <div class="text-sm font-medium <?php echo $hasActivityLogTable ? 'text-green-800' : 'text-gray-800'; ?>">Activity Log</div>
                        <div class="text-xs <?php echo $hasActivityLogTable ? 'text-green-600' : 'text-gray-600'; ?>"><?php echo $hasActivityLogTable ? 'Active' : 'Disabled'; ?></div>
                    </div>
                    <div class="p-4 <?php echo $hasAnnouncementTable ? 'bg-green-50' : 'bg-gray-50'; ?> rounded-xl text-center">
                        <div class="text-2xl mb-1"><?php echo $hasAnnouncementTable ? '✅' : '❌'; ?></div>
                        <div class="text-sm font-medium <?php echo $hasAnnouncementTable ? 'text-green-800' : 'text-gray-800'; ?>">Announcements</div>
                        <div class="text-xs <?php echo $hasAnnouncementTable ? 'text-green-600' : 'text-gray-600'; ?>"><?php echo $hasAnnouncementTable ? 'Active' : 'Disabled'; ?></div>
                    </div>
                </div>
                
                <?php if (!$hasBanColumns || !$hasActivityLogTable || !$hasAnnouncementTable || !$hasSettingsTable): ?>
                    <div class="mt-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-lg">
                        <p class="text-yellow-800 text-sm">
                            <strong>💡 แนะนำ:</strong> รัน SQL ด้านล่างเพื่อเปิดใช้งานฟีเจอร์เพิ่มเติม
                        </p>
                        <button onclick="openModal('sqlModal')" class="mt-2 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-sm transition-colors">
                            ดู SQL Script
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ==================== USERS TAB ==================== -->
        <?php elseif ($tab === 'users'): ?>
        <div class="space-y-4 animate-fade-in">
            
            <!-- Search & Filters -->
            <div class="bg-white rounded-2xl shadow-xl p-4 md:p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-4">
                    <h2 class="text-xl font-bold text-gray-800">👥 จัดการผู้ใช้</h2>
                    <button onclick="openModal('createUserModal')" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                        ➕ สร้างผู้ใช้ใหม่
                    </button>
                </div>
                
                <form method="GET" class="space-y-4">
                    <input type="hidden" name="tab" value="users">
                    <div class="flex flex-col md:flex-row gap-3">
                        <div class="flex-1">
                            <input type="text" name="user_search" value="<?php echo htmlspecialchars($userSearch); ?>"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 outline-none transition-all"
                                   placeholder="🔍 ค้นหา ID, Username, ชื่อ หรือ Email...">
                        </div>
                        <select name="user_filter" class="px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 outline-none">
                            <option value="all" <?php echo $userFilter === 'all' ? 'selected' : ''; ?>>📋 ทั้งหมด</option>
                            <option value="active" <?php echo $userFilter === 'active' ? 'selected' : ''; ?>>✅ ปกติ</option>
                            <option value="banned" <?php echo $userFilter === 'banned' ? 'selected' : ''; ?>>🚫 ถูกแบน</option>
                            <option value="admin" <?php echo $userFilter === 'admin' ? 'selected' : ''; ?>>👑 Admin</option>
                        </select>
                        <select name="user_sort" class="px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 outline-none">
                            <option value="newest" <?php echo $userSort === 'newest' ? 'selected' : ''; ?>>🕐 ใหม่สุด</option>
                            <option value="oldest" <?php echo $userSort === 'oldest' ? 'selected' : ''; ?>>🕐 เก่าสุด</option>
                            <option value="name" <?php echo $userSort === 'name' ? 'selected' : ''; ?>>🔤 ตามชื่อ</option>
                            <option value="username" <?php echo $userSort === 'username' ? 'selected' : ''; ?>>🔤 ตาม Username</option>
                        </select>
                        <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl transition-colors">
                            ค้นหา
                        </button>
                    </div>
                </form>
                
                <div class="mt-4 text-sm text-gray-600">
                    พบ <?php echo number_format($totalFilteredUsers); ?> ผู้ใช้ (หน้า <?php echo $userPage; ?>/<?php echo $totalUserPages; ?>)
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <form method="POST" id="bulkForm">
                <div class="bg-white rounded-2xl shadow-xl p-4 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-gray-700">Bulk Actions:</span>
                    <button type="submit" name="bulk_ban" onclick="return confirmBulk('แบน')" 
                            class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-sm rounded-lg transition-colors">
                        🚫 แบนที่เลือก
                    </button>
                    <button type="submit" name="bulk_unban" onclick="return confirmBulk('ปลดแบน')"
                            class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-sm rounded-lg transition-colors">
                        ✅ ปลดแบนที่เลือก
                    </button>
                    <button type="submit" name="bulk_delete" onclick="return confirmBulk('ลบ')"
                            class="px-3 py-1.5 bg-gray-700 hover:bg-gray-800 text-white text-sm rounded-lg transition-colors">
                        🗑️ ลบที่เลือก
                    </button>
                    <button type="button" onclick="selectAll()" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm rounded-lg transition-colors ml-auto">
                        ☑️ เลือกทั้งหมด
                    </button>
                    <button type="button" onclick="deselectAll()" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm rounded-lg transition-colors">
                        ⬜ ยกเลิกทั้งหมด
                    </button>
                </div>
                
                <!-- Users List -->
                <div class="mt-4 space-y-3">
                    <?php if (empty($users)): ?>
                        <div class="bg-white rounded-2xl shadow-xl p-12 text-center">
                            <div class="text-6xl mb-4">😔</div>
                            <div class="text-xl text-gray-500">ไม่พบผู้ใช้</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <div class="user-card bg-white rounded-xl shadow-md p-4 flex flex-col md:flex-row md:items-center gap-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($u['id'] != $user['id']): ?>
                                        <input type="checkbox" name="selected_users[]" value="<?php echo $u['id']; ?>" class="checkbox-fancy">
                                    <?php else: ?>
                                        <div class="w-5"></div>
                                    <?php endif; ?>
                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                        <?php echo mb_substr($u['display_name'], 0, 1); ?>
                                    </div>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-gray-800 truncate"><?php echo htmlspecialchars($u['display_name']); ?></span>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="px-2 py-0.5 bg-yellow-400 text-black text-xs font-bold rounded-full">👑 ADMIN</span>
                                        <?php endif; ?>
                                        <?php if ($u['banned']): ?>
                                            <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse-slow">🚫 BANNED</span>
                                        <?php endif; ?>
                                        <?php if ($u['id'] == $user['id']): ?>
                                            <span class="px-2 py-0.5 bg-blue-500 text-white text-xs font-bold rounded-full">👤 YOU</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">
                                        <span class="mr-3">ID: <?php echo $u['id']; ?></span>
                                        <span class="mr-3">@<?php echo htmlspecialchars($u['username']); ?></span>
                                        <span><?php echo htmlspecialchars($u['email'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        📅 สมัครเมื่อ <?php echo formatDateTime($u['created_at']); ?>
                                    </div>
                                    
                                    <?php if ($u['banned'] && $hasBanColumns && !empty($u['ban_reason'])): ?>
                                        <div class="mt-2 p-2 bg-red-50 border-l-4 border-red-400 rounded text-sm">
                                            <strong>เหตุผล:</strong> <?php echo htmlspecialchars($u['ban_reason']); ?>
                                            <?php if (!empty($u['ban_until'])): ?>
                                                <br><strong>ถึง:</strong> <?php echo formatDateTime($u['ban_until']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex flex-wrap gap-2">
                                    <?php if ($u['id'] != $user['id']): ?>
                                        <button type="button" onclick='openViewUser(<?php echo json_encode($u); ?>)' 
                                                class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded-lg transition-colors">
                                            👁️ ดู
                                        </button>
                                        <button type="button" onclick='openEditUser(<?php echo json_encode($u); ?>)'
                                                class="px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white text-sm rounded-lg transition-colors">
                                            ✏️ แก้ไข
                                        </button>
                                        <?php if ($u['banned']): ?>
                                            <button type="button" onclick="unbanUser(<?php echo $u['id']; ?>)"
                                                    class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-sm rounded-lg transition-colors">
                                                ✅ ปลดแบน
                                            </button>
                                        <?php else: ?>
                                            <button type="button" onclick="openBanModal(<?php echo $u['id']; ?>)"
                                                    class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-sm rounded-lg transition-colors">
                                                🚫 แบน
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" onclick="changeRole(<?php echo $u['id']; ?>, '<?php echo $u['role'] === 'admin' ? 'user' : 'admin'; ?>')"
                                                class="px-3 py-1.5 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded-lg transition-colors">
                                            <?php echo $u['role'] === 'admin' ? '⬇️' : '⬆️'; ?>
                                        </button>
                                        <button type="button" onclick="deleteUser(<?php echo $u['id']; ?>)"
                                                class="px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white text-sm rounded-lg transition-colors">
                                            🗑️
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Pagination -->
            <?php if ($totalUserPages > 1): ?>
                <div class="flex justify-center gap-2 flex-wrap">
                    <?php if ($userPage > 1): ?>
                        <a href="?tab=users&user_page=<?php echo $userPage - 1; ?>&user_search=<?php echo urlencode($userSearch); ?>&user_filter=<?php echo $userFilter; ?>&user_sort=<?php echo $userSort; ?>" 
                           class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">← ก่อนหน้า</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $userPage - 2); $i <= min($totalUserPages, $userPage + 2); $i++): ?>
                        <a href="?tab=users&user_page=<?php echo $i; ?>&user_search=<?php echo urlencode($userSearch); ?>&user_filter=<?php echo $userFilter; ?>&user_sort=<?php echo $userSort; ?>" 
                           class="px-4 py-2 <?php echo $i == $userPage ? 'bg-purple-600 text-white' : 'bg-white text-purple-600 border-2 border-purple-600 hover:bg-purple-50'; ?> rounded-lg transition-colors">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($userPage < $totalUserPages): ?>
                        <a href="?tab=users&user_page=<?php echo $userPage + 1; ?>&user_search=<?php echo urlencode($userSearch); ?>&user_filter=<?php echo $userFilter; ?>&user_sort=<?php echo $userSort; ?>" 
                           class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">ถัดไป →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ==================== POLLS TAB ==================== -->
        <?php elseif ($tab === 'polls'): ?>
        <div class="space-y-4 animate-fade-in">
            
            <!-- Search & Filters -->
            <div class="bg-white rounded-2xl shadow-xl p-4 md:p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📋 จัดการโพล</h2>
                
                <form method="GET" class="flex flex-col md:flex-row gap-3">
                    <input type="hidden" name="tab" value="polls">
                    <div class="flex-1">
                        <input type="text" name="poll_search" value="<?php echo htmlspecialchars($pollSearch); ?>"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 outline-none transition-all"
                               placeholder="🔍 ค้นหา ID, ชื่อโพล หรือผู้สร้าง...">
                    </div>
                    <select name="poll_filter" class="px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 outline-none">
                        <option value="all" <?php echo $pollFilter === 'all' ? 'selected' : ''; ?>>📋 ทั้งหมด</option>
                        <option value="active" <?php echo $pollFilter === 'active' ? 'selected' : ''; ?>>✅ กำลังเปิด</option>
                        <option value="expired" <?php echo $pollFilter === 'expired' ? 'selected' : ''; ?>>⏰ หมดอายุ</option>
                    </select>
                    <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl transition-colors">
                        ค้นหา
                    </button>
                </form>
                
                <div class="mt-4 text-sm text-gray-600">
                    พบ <?php echo number_format($totalFilteredPolls); ?> โพล (หน้า <?php echo $pollPage; ?>/<?php echo $totalPollPages; ?>)
                </div>
            </div>
            
            <!-- Polls List -->
            <div class="space-y-3">
                <?php if (empty($polls)): ?>
                    <div class="bg-white rounded-2xl shadow-xl p-12 text-center">
                        <div class="text-6xl mb-4">📭</div>
                        <div class="text-xl text-gray-500">ไม่พบโพล</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($polls as $p): 
                        $isExpired = !empty($p['expire_date']) && strtotime($p['expire_date']) < time();
                    ?>
                        <div class="bg-white rounded-xl shadow-md p-4 flex flex-col md:flex-row md:items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($p['title']); ?></span>
                                    <?php if ($isExpired): ?>
                                        <span class="px-2 py-0.5 bg-gray-500 text-white text-xs font-bold rounded-full">หมดอายุ</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">กำลังเปิด</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-500 mt-1">
                                    <span class="mr-3">ID: <?php echo $p['id']; ?></span>
                                    <span class="mr-3">👤 <?php echo htmlspecialchars($p['creator_name'] ?? 'Unknown'); ?></span>
                                    <span>📝 <?php echo $p['response_count']; ?> ตอบกลับ</span>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    📅 สร้างเมื่อ <?php echo formatDateTime($p['created_at']); ?>
                                    <?php if (!empty($p['expire_date'])): ?>
                                        | หมดอายุ <?php echo formatDateTime($p['expire_date']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-2">
                                <a href="poll.php?id=<?php echo $p['id']; ?>" target="_blank"
                                   class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded-lg transition-colors">
                                    👁️ ดู
                                </a>
                                <?php if (!$isExpired): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="poll_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="close_poll" onclick="return confirm('ปิดโพลนี้?')"
                                                class="px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white text-sm rounded-lg transition-colors">
                                            🔒 ปิด
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="poll_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="delete_poll" onclick="return confirm('ลบโพลนี้? จะลบข้อมูลทั้งหมดที่เกี่ยวข้อง')"
                                            class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors">
                                        🗑️ ลบ
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPollPages > 1): ?>
                <div class="flex justify-center gap-2 flex-wrap">
                    <?php if ($pollPage > 1): ?>
                        <a href="?tab=polls&poll_page=<?php echo $pollPage - 1; ?>&poll_search=<?php echo urlencode($pollSearch); ?>&poll_filter=<?php echo $pollFilter; ?>" 
                           class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">← ก่อนหน้า</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $pollPage - 2); $i <= min($totalPollPages, $pollPage + 2); $i++): ?>
                        <a href="?tab=polls&poll_page=<?php echo $i; ?>&poll_search=<?php echo urlencode($pollSearch); ?>&poll_filter=<?php echo $pollFilter; ?>" 
                           class="px-4 py-2 <?php echo $i == $pollPage ? 'bg-purple-600 text-white' : 'bg-white text-purple-600 border-2 border-purple-600 hover:bg-purple-50'; ?> rounded-lg transition-colors">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($pollPage < $totalPollPages): ?>
                        <a href="?tab=polls&poll_page=<?php echo $pollPage + 1; ?>&poll_search=<?php echo urlencode($pollSearch); ?>&poll_filter=<?php echo $pollFilter; ?>" 
                           class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">ถัดไป →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- ==================== BANS TAB ==================== -->
        <?php elseif ($tab === 'bans'): ?>
        <div class="space-y-4 animate-fade-in">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">🚫 ประวัติการแบน</h2>
                
                <?php if (empty($banHistory)): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">✅</div>
                        <div class="text-xl text-gray-500">ไม่มีประวัติการแบน</div>
                        <p class="text-gray-400 mt-2">หรือยังไม่ได้ติดตั้งตาราง ban_history</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">ผู้ใช้</th>
                                    <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">แบนโดย</th>
                                    <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">เหตุผล</th>
                                    <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">ระยะเวลา</th>
                                    <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">วันที่แบน</th>
                                    <th class="px-4 py-3 text-left text-sm font-bold text-gray-700">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($banHistory as $bh): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium"><?php echo htmlspecialchars($bh['user_name'] ?? 'Deleted'); ?></div>
                                            <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($bh['username'] ?? '?'); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($bh['admin_name'] ?? 'System'); ?></td>
                                        <td class="px-4 py-3 text-sm max-w-xs truncate"><?php echo htmlspecialchars($bh['ban_reason'] ?? 'N/A'); ?></td>
                                        <td class="px-4 py-3 text-sm">
                                            <?php echo $bh['ban_until'] ? formatDateTime($bh['ban_until']) : 'ถาวร'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm"><?php echo formatDateTime($bh['banned_at']); ?></td>
                                        <td class="px-4 py-3">
                                            <?php if ($bh['unbanned_at']): ?>
                                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                    ปลดแบนแล้ว
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">
                                                    ยังโดนแบน
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ==================== ACTIVITY LOG TAB ==================== -->
        <?php elseif ($tab === 'logs'): ?>
        <div class="space-y-4 animate-fade-in">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📜 Activity Log</h2>
                
                <?php if (!$hasActivityLogTable): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">⚠️</div>
                        <div class="text-xl text-gray-500">Activity Log ยังไม่ถูกเปิดใช้งาน</div>
                        <p class="text-gray-400 mt-2">กรุณารัน SQL Script เพื่อสร้างตาราง activity_log</p>
                        <button onclick="openModal('sqlModal')" class="mt-4 px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            ดู SQL Script
                        </button>
                    </div>
                <?php elseif (empty($activityLog)): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📭</div>
                        <div class="text-xl text-gray-500">ยังไม่มีกิจกรรม</div>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-[600px] overflow-y-auto scrollbar-thin">
                        <?php foreach ($activityLog as $log): 
                            $actionIcon = match($log['action']) {
                                'delete_user', 'bulk_delete_users' => '🗑️',
                                'ban_user', 'bulk_ban_users' => '🚫',
                                'unban_user', 'bulk_unban_users' => '✅',
                                'create_user' => '➕',
                                'edit_user' => '✏️',
                                'change_role' => '🔄',
                                'delete_poll', 'close_poll' => '📋',
                                'create_announcement', 'delete_announcement' => '📢',
                                'update_settings' => '⚙️',
                                default => '📝'
                            };
                            $actionColor = match($log['action']) {
                                'delete_user', 'bulk_delete_users', 'ban_user', 'bulk_ban_users', 'delete_poll' => 'bg-red-50 border-red-200',
                                'unban_user', 'bulk_unban_users', 'create_user' => 'bg-green-50 border-green-200',
                                default => 'bg-gray-50 border-gray-200'
                            };
                        ?>
                            <div class="flex items-start gap-3 p-4 <?php echo $actionColor; ?> border rounded-xl">
                                <div class="text-2xl"><?php echo $actionIcon; ?></div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-800">
                                        <?php echo htmlspecialchars($log['admin_name'] ?? 'System'); ?> 
                                        <span class="text-gray-500 font-normal">» <?php echo htmlspecialchars($log['action']); ?></span>
                                    </div>
                                    <?php if ($log['details']): ?>
                                        <div class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($log['details']); ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-400 mt-1"><?php echo formatDateTime($log['created_at']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ==================== ANNOUNCEMENTS TAB ==================== -->
        <?php elseif ($tab === 'announcements'): ?>
        <div class="space-y-4 animate-fade-in">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">📢 จัดการประกาศ</h2>
                    <?php if ($hasAnnouncementTable): ?>
                        <button onclick="openModal('createAnnouncementModal')" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                            ➕ สร้างประกาศ
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (!$hasAnnouncementTable): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">⚠️</div>
                        <div class="text-xl text-gray-500">ระบบประกาศยังไม่ถูกเปิดใช้งาน</div>
                        <p class="text-gray-400 mt-2">กรุณารัน SQL Script เพื่อสร้างตาราง announcements</p>
                        <button onclick="openModal('sqlModal')" class="mt-4 px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            ดู SQL Script
                        </button>
                    </div>
                <?php elseif (empty($announcements)): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📭</div>
                        <div class="text-xl text-gray-500">ยังไม่มีประกาศ</div>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($announcements as $ann): 
                            $typeColor = match($ann['type']) {
                                'warning' => 'border-yellow-500 bg-yellow-50',
                                'danger' => 'border-red-500 bg-red-50',
                                'success' => 'border-green-500 bg-green-50',
                                default => 'border-blue-500 bg-blue-50'
                            };
                        ?>
                            <div class="border-l-4 <?php echo $typeColor; ?> p-4 rounded-lg">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($ann['title']); ?></h3>
                                        <p class="text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                                        <div class="text-xs text-gray-400 mt-2">
                                            โดย <?php echo htmlspecialchars($ann['creator_name'] ?? 'System'); ?> 
                                            | <?php echo formatDateTime($ann['created_at']); ?>
                                            <?php if ($ann['expire_at']): ?>
                                                | หมดอายุ <?php echo formatDateTime($ann['expire_at']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                        <button type="submit" name="delete_announcement" onclick="return confirm('ลบประกาศนี้?')"
                                                class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white text-sm rounded-lg transition-colors">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ==================== SETTINGS TAB ==================== -->
        <?php elseif ($tab === 'settings'): ?>
        <div class="space-y-4 animate-fade-in">
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">⚙️ ตั้งค่าระบบ</h2>
                
                <?php if (!$hasSettingsTable): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">⚠️</div>
                        <div class="text-xl text-gray-500">ระบบตั้งค่ายังไม่ถูกเปิดใช้งาน</div>
                        <p class="text-gray-400 mt-2">กรุณารัน SQL Script เพื่อสร้างตาราง system_settings</p>
                        <button onclick="openModal('sqlModal')" class="mt-4 px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                            ดู SQL Script
                        </button>
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block font-bold text-gray-700 mb-2">ชื่อเว็บไซต์</label>
                                <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 outline-none">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-2">จำนวนโพลสูงสุด/ผู้ใช้</label>
                                <input type="number" name="max_polls_per_user" value="<?php echo htmlspecialchars($settings['max_polls_per_user']); ?>"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 outline-none">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block font-bold text-gray-700 mb-2">คำอธิบายเว็บไซต์</label>
                            <textarea name="site_description" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 outline-none"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                        </div>
                        
                        <div class="space-y-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="allow_registration" <?php echo $settings['allow_registration'] === '1' ? 'checked' : ''; ?>
                                       class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                <span class="font-medium text-gray-700">อนุญาตให้สมัครสมาชิกใหม่</span>
                            </label>
                            
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="maintenance_mode" <?php echo $settings['maintenance_mode'] === '1' ? 'checked' : ''; ?>
                                       class="w-5 h-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <span class="font-medium text-gray-700">โหมดปิดปรับปรุง (Maintenance Mode)</span>
                            </label>
                        </div>
                        
                        <div class="pt-4 border-t">
                            <button type="submit" name="save_settings" class="px-8 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl transition-colors font-bold">
                                💾 บันทึกการตั้งค่า
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Database Info -->
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">💾 ข้อมูล Database</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <div class="text-2xl font-bold text-gray-800"><?php echo number_format($totalUsers); ?></div>
                        <div class="text-sm text-gray-500">Users</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <div class="text-2xl font-bold text-gray-800"><?php echo number_format($totalPolls); ?></div>
                        <div class="text-sm text-gray-500">Polls</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <div class="text-2xl font-bold text-gray-800"><?php echo number_format($totalResponses); ?></div>
                        <div class="text-sm text-gray-500">Responses</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <div class="text-2xl font-bold text-gray-800"><?php echo DB_NAME; ?></div>
                        <div class="text-sm text-gray-500">Database</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- ==================== MODALS ==================== -->
    
    <!-- Create User Modal -->
    <div id="createUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full animate-slide-in max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b-2 border-gray-200 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-xl font-bold text-gray-800">➕ สร้างผู้ใช้ใหม่</h2>
                <button onclick="closeModal('createUserModal')" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <div>
                    <label class="block font-bold text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">รหัสผ่าน</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">ชื่อที่แสดง</label>
                    <input type="text" name="display_name" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">อีเมล</label>
                    <input type="email" name="email" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">สิทธิ์</label>
                    <select name="role" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" name="create_user" class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors font-bold">
                        สร้างผู้ใช้
                    </button>
                    <button type="button" onclick="closeModal('createUserModal')" class="flex-1 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-bold">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Ban User Modal -->
    <div id="banUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full animate-slide-in max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b-2 border-gray-200 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-xl font-bold text-gray-800">🚫 แบนผู้ใช้</h2>
                <button onclick="closeModal('banUserModal')" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="user_id" id="ban_user_id">
                
                <div class="mb-4">
                    <label class="block font-bold text-gray-700 mb-2">เลือกเหตุผล:</label>
                    <div class="space-y-2">
                        <?php 
                        $reasons = [
                            'เนื้อหาไม่เหมาะสม' => '🔞 เนื้อหาไม่เหมาะสม',
                            'สแปม/ส่งข้อความรบกวน' => '📧 สแปม/รบกวน',
                            'พฤติกรรมไม่เหมาะสม' => '😡 พฤติกรรมไม่เหมาะสม',
                            'Bot/บัญชีปลอม' => '🤖 Bot/บัญชีปลอม',
                            'ละเมิดกฎ' => '📜 ละเมิดกฎ',
                            'other' => '✍️ อื่น ๆ'
                        ];
                        foreach ($reasons as $value => $label): ?>
                            <label class="flex items-center p-2 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all">
                                <input type="radio" name="ban_reason" value="<?php echo $value; ?>" class="mr-2" required>
                                <span class="text-sm"><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-4 hidden" id="custom_reason_group">
                    <label class="block font-bold text-gray-700 mb-2">ระบุเหตุผล:</label>
                    <textarea name="custom_reason" rows="2" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold text-gray-700 mb-2">ระยะเวลา:</label>
                    <div class="grid grid-cols-4 gap-2">
                        <?php 
                        $durations = ['1h'=>'1ชม.','24h'=>'24ชม.','3d'=>'3วัน','7d'=>'7วัน','30d'=>'30วัน','90d'=>'90วัน','1y'=>'1ปี','permanent'=>'ถาวร'];
                        foreach ($durations as $value => $label): ?>
                            <label class="flex items-center justify-center p-2 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all text-sm">
                                <input type="radio" name="ban_duration" value="<?php echo $value; ?>" class="mr-1" <?php echo $value === 'permanent' ? 'checked' : ''; ?>>
                                <?php echo $label; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" name="ban_user" class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors font-bold">
                        ✅ ยืนยันแบน
                    </button>
                    <button type="button" onclick="closeModal('banUserModal')" class="flex-1 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-bold">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View User Modal -->
    <div id="viewUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full animate-slide-in max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b-2 border-gray-200 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-xl font-bold text-gray-800">👤 ข้อมูลผู้ใช้</h2>
                <button onclick="closeModal('viewUserModal')" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <div class="p-6" id="viewUserContent">
                <!-- Content filled by JS -->
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full animate-slide-in max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b-2 border-gray-200 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-xl font-bold text-gray-800">✏️ แก้ไขผู้ใช้</h2>
                <button onclick="closeModal('editUserModal')" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div>
                    <label class="block font-bold text-gray-700 mb-2">ชื่อที่แสดง</label>
                    <input type="text" name="display_name" id="edit_display_name" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">อีเมล</label>
                    <input type="email" name="email" id="edit_email"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">รหัสผ่านใหม่ <span class="font-normal text-gray-500">(เว้นว่างถ้าไม่เปลี่ยน)</span></label>
                    <input type="password" name="new_password"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" name="edit_user" class="flex-1 px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg transition-colors font-bold">
                        บันทึก
                    </button>
                    <button type="button" onclick="closeModal('editUserModal')" class="flex-1 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-bold">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Announcement Modal -->
    <div id="createAnnouncementModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full animate-slide-in max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b-2 border-gray-200 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-xl font-bold text-gray-800">📢 สร้างประกาศ</h2>
                <button onclick="closeModal('createAnnouncementModal')" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <div>
                    <label class="block font-bold text-gray-700 mb-2">หัวข้อ</label>
                    <input type="text" name="title" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">เนื้อหา</label>
                    <textarea name="content" rows="4" required
                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none"></textarea>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">ประเภท</label>
                    <select name="type" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                        <option value="info">ℹ️ ข้อมูล</option>
                        <option value="success">✅ สำเร็จ</option>
                        <option value="warning">⚠️ เตือน</option>
                        <option value="danger">🚨 สำคัญ</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-gray-700 mb-2">หมดอายุ <span class="font-normal text-gray-500">(ไม่บังคับ)</span></label>
                    <input type="datetime-local" name="expire_at"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-purple-500 outline-none">
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="submit" name="create_announcement" class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors font-bold">
                        สร้างประกาศ
                    </button>
                    <button type="button" onclick="closeModal('createAnnouncementModal')" class="flex-1 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors font-bold">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- SQL Script Modal -->
    <div id="sqlModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-3xl w-full animate-slide-in max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b-2 border-gray-200 flex justify-between items-center sticky top-0 bg-white">
                <h2 class="text-xl font-bold text-gray-800">💾 SQL Script สำหรับฟีเจอร์เพิ่มเติม</h2>
                <button onclick="closeModal('sqlModal')" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">คัดลอก SQL ด้านล่างและรันใน phpMyAdmin หรือ MySQL client:</p>
                <pre class="bg-gray-900 text-green-400 p-4 rounded-xl text-sm overflow-x-auto"><code>-- Ban System Columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_reason VARCHAR(500) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_until DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_by INT NULL;

-- Ban History Table
CREATE TABLE IF NOT EXISTS ban_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    banned_by INT,
    ban_reason VARCHAR(500),
    ban_until DATETIME,
    banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    unbanned_at DATETIME,
    unbanned_by INT,
    INDEX idx_user_id (user_id),
    INDEX idx_banned_at (banned_at)
);

-- Activity Log Table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at)
);

-- Announcements Table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    expire_at DATETIME,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
);

-- System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);</code></pre>
                <button onclick="copySQL()" class="mt-4 px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                    📋 คัดลอก SQL
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hidden Forms for Actions -->
    <form id="actionForm" method="POST" class="hidden">
        <input type="hidden" name="user_id" id="action_user_id">
        <input type="hidden" name="new_role" id="action_new_role">
    </form>
    
    <?php if ($toast): include 'toast.php'; endif; ?>
    
    <script>
        // Charts
        <?php if ($tab === 'dashboard'): ?>
        const usersCtx = document.getElementById('usersChart').getContext('2d');
        new Chart(usersCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($newUsersData, 'date')); ?>,
                datasets: [{
                    label: 'ผู้ใช้ใหม่',
                    data: <?php echo json_encode(array_column($newUsersData, 'count')); ?>,
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        
        const pollsCtx = document.getElementById('pollsChart').getContext('2d');
        new Chart(pollsCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($newPollsData, 'date')); ?>,
                datasets: [{
                    label: 'โพลใหม่',
                    data: <?php echo json_encode(array_column($newPollsData, 'count')); ?>,
                    backgroundColor: 'rgba(168, 85, 247, 0.8)',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
        <?php endif; ?>
        
        // Modal Functions
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        // Close modal on backdrop click
        document.querySelectorAll('[id$="Modal"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
        });
        
        // Ban Modal
        function openBanModal(userId) {
            document.getElementById('ban_user_id').value = userId;
            openModal('banUserModal');
        }
        
        document.querySelectorAll('input[name="ban_reason"]').forEach(r => {
            r.addEventListener('change', function() {
                document.getElementById('custom_reason_group').classList.toggle('hidden', this.value !== 'other');
            });
        });
        
        // View User
        function openViewUser(user) {
            const content = `
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center text-white font-bold text-3xl mx-auto mb-3">
                        ${user.display_name.charAt(0)}
                    </div>
                    <h3 class="text-xl font-bold">${escapeHtml(user.display_name)}</h3>
                    <p class="text-gray-500">@${escapeHtml(user.username)}</p>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">User ID</span>
                        <span class="font-medium">${user.id}</span>
                    </div>
                    <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Email</span>
                        <span class="font-medium">${escapeHtml(user.email || 'N/A')}</span>
                    </div>
                    <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">สิทธิ์</span>
                        <span class="font-medium">${user.role === 'admin' ? '👑 Admin' : 'User'}</span>
                    </div>
                    <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">สถานะ</span>
                        <span class="font-medium">${user.banned ? '🚫 ถูกแบน' : '✅ ปกติ'}</span>
                    </div>
                    <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">สมัครเมื่อ</span>
                        <span class="font-medium">${user.created_at}</span>
                    </div>
                </div>
            `;
            document.getElementById('viewUserContent').innerHTML = content;
            openModal('viewUserModal');
        }
        
        // Edit User
        function openEditUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_display_name').value = user.display_name;
            document.getElementById('edit_email').value = user.email || '';
            openModal('editUserModal');
        }
        
        // Action Forms
        function deleteUser(userId) {
            if (confirm('ลบผู้ใช้นี้? ข้อมูลทั้งหมดจะถูกลบ')) {
                const form = document.getElementById('actionForm');
                document.getElementById('action_user_id').value = userId;
                form.innerHTML += '<input type="hidden" name="delete_user" value="1">';
                form.submit();
            }
        }
        
        function unbanUser(userId) {
            if (confirm('ปลดแบนผู้ใช้นี้?')) {
                const form = document.getElementById('actionForm');
                document.getElementById('action_user_id').value = userId;
                form.innerHTML += '<input type="hidden" name="unban_user" value="1">';
                form.submit();
            }
        }
        
        function changeRole(userId, newRole) {
            if (confirm(`เปลี่ยนสิทธิ์เป็น ${newRole}?`)) {
                const form = document.getElementById('actionForm');
                document.getElementById('action_user_id').value = userId;
                document.getElementById('action_new_role').value = newRole;
                form.innerHTML += '<input type="hidden" name="change_role" value="1">';
                form.submit();
            }
        }
        
        // Bulk Actions
        function selectAll() {
            document.querySelectorAll('input[name="selected_users[]"]').forEach(cb => cb.checked = true);
        }
        
        function deselectAll() {
            document.querySelectorAll('input[name="selected_users[]"]').forEach(cb => cb.checked = false);
        }
        
        function confirmBulk(action) {
            const selected = document.querySelectorAll('input[name="selected_users[]"]:checked');
            if (selected.length === 0) {
                alert('กรุณาเลือกผู้ใช้อย่างน้อย 1 คน');
                return false;
            }
            return confirm(`${action} ${selected.length} ผู้ใช้ที่เลือก?`);
        }
        
        // Dark Mode
        function toggleDarkMode() {
            document.body.classList.toggle('dark');
            localStorage.setItem('darkMode', document.body.classList.contains('dark'));
        }
        
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark');
        }
        
        // Copy SQL
        function copySQL() {
            const sql = document.querySelector('#sqlModal code').textContent;
            navigator.clipboard.writeText(sql).then(() => {
                alert('คัดลอก SQL แล้ว!');
            });
        }
        
        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
