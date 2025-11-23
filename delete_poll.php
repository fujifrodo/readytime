<?php
require_once 'functions.php';
requireLogin();

$pollId = $_GET['id'] ?? null;

if (!$pollId) {
    setToast('❌ ไม่พบโพลที่ต้องการลบ', 'error');
    redirect('index.php');
}

$user = getCurrentUser();

try {
    $db = getDB();
    
    // ดึงข้อมูล poll
    $stmt = $db->prepare("SELECT * FROM polls WHERE id = ? LIMIT 1");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$poll) {
        setToast('❌ ไม่พบโพลนี้', 'error');
        redirect('index.php');
    }
    
    // ตรวจสอบสิทธิ์
    $isOwner = isset($poll['created_by']) && $poll['created_by'] == $user['id'];
    
    if (!isAdmin() && !$isOwner) {
        setToast('🚫 คุณไม่มีสิทธิ์ลบโพลนี้', 'error');
        redirect('index.php');
    }
    
    // ลบโพล (CASCADE จะลบข้อมูลที่เกี่ยวข้องทั้งหมด)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("DELETE FROM polls WHERE id = ?");
        $stmt->execute([$pollId]);
        
        setToast('🗑️ ลบโพล "' . htmlspecialchars($poll['title']) . '" สำเร็จ', 'success');
        redirect('index.php');
    }
    
} catch (PDOException $e) {
    error_log("Delete poll error: " . $e->getMessage());
    setToast('❌ เกิดข้อผิดพลาดในการลบโพล', 'error');
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลบโพล - นัดเพื่อน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-10px); } 75% { transform: translateX(10px); } }
        .shake { animation: shake 0.5s; }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <?php include 'toast.php'; ?>
    
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full">
        <div class="text-center mb-6">
            <div class="text-6xl mb-4">⚠️</div>
            <h1 class="text-2xl font-bold text-red-600 mb-2">ยืนยันการลบโพล</h1>
            <p class="text-gray-600">การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
        </div>
        
        <div class="mb-6 bg-red-50 border-2 border-red-200 p-4 rounded-xl">
            <p class="text-gray-700 mb-2 font-medium">คุณต้องการลบโพลนี้หรือไม่?</p>
            <div class="bg-white p-4 rounded-lg border border-red-200">
                <p class="font-bold text-gray-800 text-lg">📊 <?php echo htmlspecialchars($poll['title']); ?></p>
                <p class="text-sm text-gray-600 mt-2">👤 สร้างโดย: <?php echo htmlspecialchars($poll['creator_name']); ?></p>
                <p class="text-sm text-gray-600">📅 <?php echo date('d/m/Y', strtotime($poll['created_at'])); ?></p>
            </div>
        </div>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded">
            <p class="text-yellow-800 text-sm font-medium">
                ⚠️ การลบจะลบข้อมูลทั้งหมดที่เกี่ยวข้อง รวมถึงคำตอบและโหวตของผู้ใช้ทั้งหมด
            </p>
        </div>
        
        <form method="POST" class="flex gap-4">
            <button type="submit" 
                    class="flex-1 bg-gradient-to-r from-red-600 to-red-700 text-white py-4 rounded-xl font-bold shadow-lg hover:shadow-xl transition transform hover:scale-[1.02]">
                🗑️ ยืนยันการลบ
            </button>
            <a href="index.php" 
               class="flex-1 bg-gradient-to-r from-gray-400 to-gray-500 text-white py-4 rounded-xl font-bold hover:from-gray-500 hover:to-gray-600 transition text-center">
                ❌ ยกเลิก
            </a>
        </form>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showWarning('⚠️ คุณกำลังจะลบโพล กรุณาตรวจสอบให้แน่ใจ!');
        });
    </script>
</body>
</html>
