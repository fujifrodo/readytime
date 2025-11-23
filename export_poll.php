<?php
require_once 'functions.php';
requireLogin();

$pollId = $_GET['id'] ?? null;
$format = $_GET['format'] ?? 'view';

if (!$pollId) {
    setToast('ไม่พบโพล', 'error');
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
        setToast('ไม่พบโพลนี้', 'error');
        redirect('index.php');
    }
    
    // ดึง slots
    $stmt = $db->prepare("SELECT * FROM poll_slots WHERE poll_id = ? ORDER BY slot_date, start_time");
    $stmt->execute([$pollId]);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึง responses
    $stmt = $db->prepare("SELECT * FROM responses WHERE poll_id = ?");
    $stmt->execute([$pollId]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // คำนวณ rankings
    $rankings = [];
    foreach ($slots as $slot) {
        $stmt = $db->prepare("
            SELECT v.value, COUNT(*) as count
            FROM votes v
            INNER JOIN responses r ON v.response_id = r.id
            WHERE r.poll_id = ? AND v.slot_id = ?
            GROUP BY v.value
        ");
        $stmt->execute([$pollId, $slot['id']]);
        $voteCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $yesCount = 0;
        $maybeCount = 0;
        $noCount = 0;
        
        foreach ($voteCounts as $vc) {
            if ($vc['value'] === 'yes') $yesCount = $vc['count'];
            elseif ($vc['value'] === 'maybe') $maybeCount = $vc['count'];
            elseif ($vc['value'] === 'no') $noCount = $vc['count'];
        }
        
        $score = ($yesCount * 2) + $maybeCount;
        $coverage = count($responses) > 0 ? (($yesCount + $maybeCount) / count($responses) * 100) : 0;
        
        $rankings[] = [
            'slot' => $slot,
            'yes' => $yesCount,
            'maybe' => $maybeCount,
            'no' => $noCount,
            'score' => $score,
            'coverage' => $coverage
        ];
    }
    
    usort($rankings, function($a, $b) {
        if ($b['score'] != $a['score']) return $b['score'] - $a['score'];
        if ($a['no'] != $b['no']) return $a['no'] - $b['no'];
        return $b['coverage'] - $a['coverage'];
    });
    
} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    setToast('❌ เกิดข้อผิดพลาดในการส่งออกข้อมูล', 'error');
    redirect('index.php');
}

// Export CSV
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="poll_' . $pollId . '_' . date('Y-m-d') . '.csv"');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    echo "ผลการโหวต: " . $poll['title'] . "\n";
    echo "ช่วงเวลา: " . date('d/m/Y', strtotime($poll['week_start'])) . " - " . date('d/m/Y', strtotime($poll['week_end'])) . "\n";
    echo "จำนวนผู้ตอบ: " . count($responses) . " คน\n";
    echo "ส่งออกเมื่อ: " . date('d/m/Y H:i:s') . "\n\n";
    
    echo "อันดับ,วันที่,ช่วงเวลา,เวลา,ว่าง,อาจจะ,ไม่ว่าง,คะแนน,% ครอบคลุม\n";
    
    foreach ($rankings as $idx => $rank) {
        echo ($idx + 1) . ",";
        echo date('d/m/Y', strtotime($rank['slot']['slot_date'])) . ",";
        echo $rank['slot']['period'] . ",";
        echo substr($rank['slot']['start_time'], 0, 5) . "-" . substr($rank['slot']['end_time'], 0, 5) . ",";
        echo $rank['yes'] . ",";
        echo $rank['maybe'] . ",";
        echo $rank['no'] . ",";
        echo $rank['score'] . ",";
        echo round($rank['coverage'], 1) . "%\n";
    }
    
    echo "\nรายชื่อผู้ตอบ\n";
    foreach ($responses as $resp) {
        echo $resp['user_name'] . "\n";
    }
    
    exit;
}

// Export Excel-style HTML
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="poll_' . $pollId . '_' . date('Y-m-d') . '.xls"');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr><th colspan="9" style="font-size:16px;font-weight:bold;">ผลการโหวต: ' . htmlspecialchars($poll['title']) . '</th></tr>';
    echo '<tr><th colspan="9">ช่วงเวลา: ' . date('d/m/Y', strtotime($poll['week_start'])) . ' - ' . date('d/m/Y', strtotime($poll['week_end'])) . '</th></tr>';
    echo '<tr><th colspan="9">จำนวนผู้ตอบ: ' . count($responses) . ' คน</th></tr>';
    echo '<tr><th colspan="9">ส่งออกเมื่อ: ' . date('d/m/Y H:i:s') . '</th></tr>';
    echo '<tr><td colspan="9"></td></tr>';
    
    echo '<tr style="background-color:#667eea;color:white;font-weight:bold;">';
    echo '<th>อันดับ</th><th>วันที่</th><th>ช่วงเวลา</th><th>เวลา</th><th>ว่าง</th><th>อาจจะ</th><th>ไม่ว่าง</th><th>คะแนน</th><th>% ครอบคลุม</th>';
    echo '</tr>';
    
    foreach ($rankings as $idx => $rank) {
        $bgColor = $rank['coverage'] > 70 ? '#d1fae5' : ($rank['coverage'] > 40 ? '#fef3c7' : '#fee2e2');
        echo '<tr style="background-color:' . $bgColor . ';">';
        echo '<td>' . ($idx + 1) . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($rank['slot']['slot_date'])) . '</td>';
        echo '<td>' . htmlspecialchars($rank['slot']['period']) . '</td>';
        echo '<td>' . substr($rank['slot']['start_time'], 0, 5) . '-' . substr($rank['slot']['end_time'], 0, 5) . '</td>';
        echo '<td style="color:#059669;font-weight:bold;">' . $rank['yes'] . '</td>';
        echo '<td style="color:#d97706;font-weight:bold;">' . $rank['maybe'] . '</td>';
        echo '<td style="color:#dc2626;font-weight:bold;">' . $rank['no'] . '</td>';
        echo '<td style="font-weight:bold;">' . $rank['score'] . '</td>';
        echo '<td>' . round($rank['coverage'], 1) . '%</td>';
        echo '</tr>';
    }
    
    echo '<tr><td colspan="9"></td></tr>';
    echo '<tr style="background-color:#f3f4f6;"><th colspan="9">รายชื่อผู้ตอบ</th></tr>';
    foreach ($responses as $resp) {
        echo '<tr><td colspan="9">' . htmlspecialchars($resp['user_name']) . '</td></tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit;
}

// View format (HTML)
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งออกผลโหวต - <?php echo htmlspecialchars($poll['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        @media print {
            body { background: white; }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="p-4">
    <?php include 'toast.php'; ?>
    
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6 no-print">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-purple-600">📥 ส่งออกผลโหวต</h1>
                <div class="flex gap-3">
                    <a href="export_poll.php?id=<?php echo $pollId; ?>&format=csv" 
                       class="bg-green-500 text-white px-6 py-3 rounded-xl hover:bg-green-600 transition font-semibold">
                        📊 ดาวน์โหลด CSV
                    </a>
                    <a href="export_poll.php?id=<?php echo $pollId; ?>&format=excel" 
                       class="bg-blue-500 text-white px-6 py-3 rounded-xl hover:bg-blue-600 transition font-semibold">
                        📈 ดาวน์โหลด Excel
                    </a>
                    <button onclick="window.print()" 
                            class="bg-purple-500 text-white px-6 py-3 rounded-xl hover:bg-purple-600 transition font-semibold">
                        🖨️ พิมพ์
                    </button>
                    <a href="index.php" class="bg-gray-300 text-gray-700 px-6 py-3 rounded-xl hover:bg-gray-400 transition font-semibold">
                        ← กลับ
                    </a>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-800 mb-2">📊 ผลการโหวต</h2>
                <h3 class="text-2xl text-purple-600 font-semibold"><?php echo htmlspecialchars($poll['title']); ?></h3>
                <div class="mt-4 text-gray-600">
                    <p>📅 ช่วงเวลา: <?php echo date('d/m/Y', strtotime($poll['week_start'])); ?> - <?php echo date('d/m/Y', strtotime($poll['week_end'])); ?></p>
                    <p>💬 จำนวนผู้ตอบ: <strong class="text-purple-600"><?php echo count($responses); ?> คน</strong></p>
                    <p>👤 สร้างโดย: <?php echo htmlspecialchars($poll['creator_name']); ?></p>
                    <p class="text-sm mt-2">📥 ส่งออกเมื่อ: <?php echo date('d/m/Y H:i:s'); ?></p>
                </div>
            </div>
            
            <div class="mb-8">
                <h4 class="text-xl font-bold text-gray-800 mb-4">🏆 อันดับช่วงเวลาที่เหมาะสมที่สุด</h4>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gradient-to-r from-purple-600 to-blue-600 text-white">
                                <th class="border border-purple-300 p-3 text-center">อันดับ</th>
                                <th class="border border-purple-300 p-3 text-left">วันที่</th>
                                <th class="border border-purple-300 p-3 text-left">ช่วงเวลา</th>
                                <th class="border border-purple-300 p-3 text-left">เวลา</th>
                                <th class="border border-purple-300 p-3 text-center">✅ ว่าง</th>
                                <th class="border border-purple-300 p-3 text-center">⚠️ อาจจะ</th>
                                <th class="border border-purple-300 p-3 text-center">❌ ไม่ว่าง</th>
                                <th class="border border-purple-300 p-3 text-center">คะแนน</th>
                                <th class="border border-purple-300 p-3 text-center">% ครอบคลุม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rankings as $idx => $rank): 
                                $bgClass = $rank['coverage'] > 70 ? 'bg-green-50' : ($rank['coverage'] > 40 ? 'bg-yellow-50' : 'bg-red-50');
                            ?>
                            <tr class="<?php echo $bgClass; ?>">
                                <td class="border border-gray-300 p-3 text-center font-bold text-lg">
                                    <?php if ($idx === 0): ?>
                                        🥇 <?php echo $idx + 1; ?>
                                    <?php elseif ($idx === 1): ?>
                                        🥈 <?php echo $idx + 1; ?>
                                    <?php elseif ($idx === 2): ?>
                                        🥉 <?php echo $idx + 1; ?>
                                    <?php else: ?>
                                        <?php echo $idx + 1; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="border border-gray-300 p-3 font-semibold">
                                    <?php echo date('d/m/Y', strtotime($rank['slot']['slot_date'])); ?>
                                    <br><span class="text-xs text-gray-500"><?php echo date('l', strtotime($rank['slot']['slot_date'])); ?></span>
                                </td>
                                <td class="border border-gray-300 p-3 font-semibold"><?php echo htmlspecialchars($rank['slot']['period']); ?></td>
                                <td class="border border-gray-300 p-3"><?php echo substr($rank['slot']['start_time'], 0, 5); ?> - <?php echo substr($rank['slot']['end_time'], 0, 5); ?></td>
                                <td class="border border-gray-300 p-3 text-center">
                                    <span class="bg-green-500 text-white px-3 py-1 rounded-full font-bold"><?php echo $rank['yes']; ?></span>
                                </td>
                                <td class="border border-gray-300 p-3 text-center">
                                    <span class="bg-yellow-500 text-white px-3 py-1 rounded-full font-bold"><?php echo $rank['maybe']; ?></span>
                                </td>
                                <td class="border border-gray-300 p-3 text-center">
                                    <span class="bg-red-500 text-white px-3 py-1 rounded-full font-bold"><?php echo $rank['no']; ?></span>
                                </td>
                                <td class="border border-gray-300 p-3 text-center font-bold text-lg text-purple-600"><?php echo $rank['score']; ?></td>
                                <td class="border border-gray-300 p-3 text-center">
                                    <div class="font-bold text-lg"><?php echo round($rank['coverage'], 1); ?>%</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <div class="<?php echo $rank['coverage'] > 70 ? 'bg-green-500' : ($rank['coverage'] > 40 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo $rank['coverage']; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="bg-purple-50 rounded-xl p-6">
                <h4 class="text-xl font-bold text-gray-800 mb-4">👥 รายชื่อผู้ตอบ (<?php echo count($responses); ?> คน)</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <?php foreach ($responses as $idx => $resp): ?>
                    <div class="bg-white p-3 rounded-lg border-2 border-purple-200 text-center">
                        <div class="text-2xl mb-1">👤</div>
                        <div class="font-semibold text-sm"><?php echo htmlspecialchars($resp['user_name']); ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?php echo date('d/m/Y H:i', strtotime($resp['submitted_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mt-8 text-center text-sm text-gray-500">
                <p>🔒 ข้อมูลนี้เป็นความลับและสำหรับการใช้งานภายในเท่านั้น</p>
                <p class="mt-1">พัฒนาโดย ระบบนัดเพื่อน | ส่งออกเมื่อ <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
