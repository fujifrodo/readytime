<?php
require_once 'functions.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $weekStart = $_POST['week_start'] ?? '';
    $weekEnd = $_POST['week_end'] ?? '';
    $allowMaybe = isset($_POST['allow_maybe']) ? 1 : 0;
    $timeMode = $_POST['time_mode'] ?? 'fullday';
    $expireDate = $_POST['expire_date'] ?? null;
    
    if (empty($title) || empty($weekStart) || empty($weekEnd)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif (strtotime($weekEnd) < strtotime($weekStart)) {
        $error = 'วันที่สิ้นสุดต้องมากกว่าวันที่เริ่มต้น';
    } else {
        try {
            $user = getCurrentUser();
            $db = getDB();
            
            $pollId = time() . rand(1000, 9999);
            $token = substr(md5(uniqid(rand(), true)), 0, 9);
            
            // เริ่ม transaction
            $db->beginTransaction();
            
            // บันทึกข้อมูล poll
            $stmt = $db->prepare("
                INSERT INTO polls (id, token, title, week_start, week_end, allow_maybe, time_mode, 
                                   created_at, created_by, creator_name, expire_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
            ");
            
            $expireDateValue = !empty($expireDate) ? $expireDate : null;
            
            $stmt->execute([
                (int)$pollId,
                $token,
                $title,
                $weekStart,
                $weekEnd,
                $allowMaybe,
                $timeMode,
                $user['id'],
                $user['display_name'],
                $expireDateValue
            ]);
            
            // สร้าง slots ตามช่วงเวลา
            $start = new DateTime($weekStart);
            $end = new DateTime($weekEnd);
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
            
            $timePeriods = [
                'fullday' => [['name' => 'ทั้งวัน', 'start' => '00:00', 'end' => '23:59']],
                'morning' => [['name' => 'เช้า', 'start' => '08:00', 'end' => '12:00']],
                'afternoon' => [['name' => 'บ่าย', 'start' => '13:00', 'end' => '17:00']],
                'evening' => [['name' => 'เย็น', 'start' => '18:00', 'end' => '22:00']],
                'default' => [
                    ['name' => 'เช้า', 'start' => '08:00', 'end' => '12:00'],
                    ['name' => 'บ่าย', 'start' => '13:00', 'end' => '17:00'],
                    ['name' => 'เย็น', 'start' => '18:00', 'end' => '22:00']
                ]
            ];
            
            $periods = $timePeriods[$timeMode] ?? $timePeriods['default'];
            $idx = 0;
            
            $stmtSlot = $db->prepare("
                INSERT INTO poll_slots (id, poll_id, slot_date, period, start_time, end_time)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($period as $date) {
                foreach ($periods as $timePeriod) {
                    $slotId = time() . rand(100, 999) . str_pad($idx, 3, '0', STR_PAD_LEFT);
                    
                    $stmtSlot->execute([
                        $slotId,
                        (int)$pollId,
                        $date->format('Y-m-d'),
                        $timePeriod['name'],
                        $timePeriod['start'],
                        $timePeriod['end']
                    ]);
                    
                    $idx++;
                    usleep(1000);
                }
            }
            
            // Commit transaction
            $db->commit();
            
            // 🔧 FIX: รอให้ database commit เสร็จสมบูรณ์
            usleep(50000); // รอ 0.05 วินาที
            
            // ตรวจสอบว่า poll ถูกสร้างจริง
            $stmt = $db->prepare("SELECT id FROM polls WHERE id = ? LIMIT 1");
            $stmt->execute([$pollId]);
            $pollExists = $stmt->fetch();
            
            if ($pollExists) {
                setToast('✅ สร้างโพลสำเร็จ! "' . htmlspecialchars($title) . '"', 'success');
                redirect("poll.php?id=$pollId");
            } else {
                // ถ้ายังไม่เจอ redirect ไป index แทน
                setToast('✅ สร้างโพลสำเร็จ! "' . htmlspecialchars($title) . '" (กำลังโหลด...)', 'success');
                redirect("index.php");
            }
            
        } catch (PDOException $e) {
            // Rollback ถ้ามีข้อผิดพลาด
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Create poll error: " . $e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการสร้างโพล กรุณาลองใหม่อีกครั้ง';
        }
    }
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างโพลใหม่ - นัดเพื่อน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="p-4">
    <?php include 'toast.php'; ?>
    
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                        ➕ สร้างโพลใหม่
                    </h1>
                    <p class="text-gray-600 mt-1">สร้างโพลหาเวลาว่างร่วมกัน</p>
                </div>
                <a href="index.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-xl hover:bg-gray-300 transition font-semibold">
                    ← กลับ
                </a>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <span class="text-2xl mr-3">❌</span>
                    <div>
                        <p class="font-bold">ข้อผิดพลาด</p>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">📊 ชื่อโพล *</label>
                    <input type="text" name="title" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition"
                           placeholder="เช้นว้าเจอกันวันไหนดี">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">📅 วันที่เริ่มต้น *</label>
                        <input type="date" name="week_start" required
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">📅 วันที่สิ้นสุด *</label>
                        <input type="date" name="week_end" required
                               value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 outline-none">
                    </div>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">⏰ โหมดเวลา</label>
                    <select name="time_mode" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 outline-none">
                        <option value="default">ทั้งวัน (เช้า/บ่าย/เย็น)</option>
                        <option value="fullday">ทั้งวัน (เช้า-ค่ำ)</option>
                        <option value="morning">เช้า (8:00-12:00)</option>
                        <option value="afternoon">บ่าย (13:00-17:00)</option>
                        <option value="evening">เย็น (18:00-22:00)</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">⏳ วันหมดอายุ (ถ้ามี)</label>
                    <input type="date" name="expire_date"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 outline-none">
                    <p class="text-sm text-gray-500 mt-1">โพลจะไม่สามารถใช้งานได้หลังวันนี้และจะไม่ถูกแสดง</p>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="allow_maybe" id="allow_maybe" class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                    <label for="allow_maybe" class="ml-3 text-gray-700 font-medium">
                        ⚠️ อนุญาตให้ตอบ "อาจจะ" นอกจาก "ว่าง" และ "ไม่ว่าง"
                    </label>
                </div>
                
                <div class="flex gap-4">
                    <button type="submit" 
                            class="flex-1 bg-gradient-to-r from-purple-600 to-blue-600 text-white py-4 rounded-xl font-bold text-lg hover:shadow-2xl transition transform hover:scale-105">
                        ✅ สร้างโพล
                    </button>
                    <a href="index.php" 
                       class="flex-1 bg-gray-300 text-gray-700 py-4 rounded-xl font-bold text-lg hover:bg-gray-400 transition text-center">
                        ❌ ยกเลิก
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>