<?php
require_once 'functions.php';
requireLogin();

$user = getCurrentUser();
$toast = getToast();

$search = $_GET['search'] ?? '';
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

try {
    $db = getDB();
    
    // นับจำนวนโพลทั้งหมด (ตามเงื่อนไขการค้นหา)
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM polls 
            WHERE title LIKE ? OR creator_name LIKE ?
        ");
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
        $stmt = $db->query("SELECT COUNT(*) as total FROM polls");
    }
    $totalPolls = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalPolls / $perPage);
    
    // ดึงข้อมูลโพลพร้อม pagination
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT p.*, 
                   (SELECT COUNT(*) FROM responses WHERE poll_id = p.id) as response_count
            FROM polls p
            WHERE p.title LIKE ? OR p.creator_name LIKE ?
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$searchTerm, $searchTerm, $perPage, $offset]);
    } else {
        $stmt = $db->prepare("
            SELECT p.*, 
                   (SELECT COUNT(*) FROM responses WHERE poll_id = p.id) as response_count
            FROM polls p
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
    }
    
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Index error: " . $e->getMessage());
    $polls = [];
    $totalPolls = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นัดเพื่อน - หน้าหลัก</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease-in; }
    </style>
</head>
<body class="p-4">
    <?php include 'toast.php'; ?>
    
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6 animate-fade-in">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-purple-600">📅 นัดเพื่อน</h1>
                    <p class="text-gray-600">ระบบหาเวลาว่างร่วมกัน</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-700">สวัสดี, <strong><?php echo htmlspecialchars($user['display_name']); ?></strong></span>
                    <a href="account.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition text-sm font-medium shadow-md hover:shadow-lg">
                        👤 บัญชี
                    </a>
                    <?php if (isAdmin()): ?>
                    <a href="admin.php" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition text-sm font-medium shadow-md hover:shadow-lg">
                        ⚙️ จัดการระบบ
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition text-sm font-medium shadow-md hover:shadow-lg">
                        ออกจากระบบ
                    </a>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6 animate-fade-in">
            <div class="flex flex-col md:flex-row gap-4">
                <a href="create_poll.php" class="flex-1 bg-gradient-to-r from-purple-600 to-blue-600 text-white px-6 py-4 rounded-xl font-semibold text-center hover:shadow-lg transition transform hover:scale-[1.02]">
                    ➕ สร้างโพลใหม่
                </a>
                
                <form method="GET" class="flex-1 flex gap-2">
                    <input type="text" name="search" placeholder="🔍 ค้นหาโพล..." 
                           class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none transition"
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition font-medium">
                        ค้นหา
                    </button>
                    <?php if (!empty($search)): ?>
                    <a href="index.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition font-medium">
                        ล้าง
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-2xl p-6 animate-fade-in">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">📊 โพลทั้งหมด (<?php echo $totalPolls; ?>)</h2>
            
            <?php if (empty($polls)): ?>
            <div class="text-center py-12 text-gray-500">
                <div class="text-6xl mb-4">📭</div>
                <p class="text-lg font-semibold mb-2">ไม่พบโพล</p>
                <?php if (!empty($search)): ?>
                <p class="mt-2">ลองค้นหาด้วยคำอื่น</p>
                <?php else: ?>
                <p class="mt-2">เริ่มสร้างโพลแรกของคุณเลย!</p>
                <a href="create_poll.php" class="inline-block mt-4 bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition font-medium">
                    ➕ สร้างโพลใหม่
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($polls as $poll): 
                    $isExpired = !empty($poll['expire_date']) && strtotime($poll['expire_date']) < time();
                ?>
                <div class="border-2 border-gray-200 rounded-xl p-4 hover:shadow-lg transition hover:border-purple-300 <?php echo $isExpired ? 'opacity-60 bg-gray-50' : ''; ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">
                                <?php echo htmlspecialchars($poll['title']); ?>
                                <?php if ($isExpired): ?>
                                <span class="text-sm text-red-600 font-normal bg-red-100 px-2 py-1 rounded">(หมดอายุ)</span>
                                <?php endif; ?>
                            </h3>
                            <div class="text-sm text-gray-600 space-y-1">
                                <p>📅 <?php echo date('d/m/Y', strtotime($poll['week_start'])); ?> - <?php echo date('d/m/Y', strtotime($poll['week_end'])); ?></p>
                                <p>👤 สร้างโดย: <span class="font-medium"><?php echo htmlspecialchars($poll['creator_name']); ?></span></p>
                                <p>💬 จำนวนผู้ตอบ: <span class="font-semibold text-purple-600"><?php echo $poll['response_count']; ?> คน</span></p>
                                <p>🕐 สร้างเมื่อ: <?php echo date('d/m/Y H:i', strtotime($poll['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <a href="poll.php?id=<?php echo $poll['id']; ?>" 
                               class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition text-sm font-medium shadow-md hover:shadow-lg">
                                👁️ ดูโพล
                            </a>
                            <?php if ($poll['created_by'] == $user['id'] || isAdmin()): ?>
                            <a href="delete_poll.php?id=<?php echo $poll['id']; ?>" 
                               class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition text-sm font-medium shadow-md hover:shadow-lg"
                               onclick="return confirm('⚠️ คุณต้องการลบโพลนี้หรือไม่?')">
                                🗑️ ลบ
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="flex justify-center items-center gap-2 mt-6">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition font-medium">
                    ← ก่อนหน้า
                </a>
                <?php endif; ?>
                
                <span class="px-4 py-2 text-gray-700 font-medium">
                    หน้า <?php echo $page; ?> / <?php echo $totalPages; ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition font-medium">
                    ถัดไป →
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($toast): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($toast['message']); ?>', '<?php echo $toast['type']; ?>');
        });
    </script>
    <?php endif; ?>
</body>
</html>
