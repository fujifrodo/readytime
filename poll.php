<?php
require_once 'functions.php';
requireLogin();

$pollId = $_GET['id'] ?? null;

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
        setToast('❌ ไม่พบโพลนี้', 'error');
        redirect('index.php');
    }
    
    // ดึงข้อมูล slots
    $stmt = $db->prepare("SELECT * FROM poll_slots WHERE poll_id = ? ORDER BY slot_date, start_time");
    $stmt->execute([$pollId]);
    $poll['slots'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // จัดการการโหวต
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
        $votes = $_POST['votes'] ?? [];
        
        $stmt = $db->prepare("SELECT id FROM responses WHERE poll_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$pollId, $user['id']]);
        $existingResponse = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $db->beginTransaction();
        
        try {
            if ($existingResponse) {
                $responseId = $existingResponse['id'];
                
                $stmt = $db->prepare("DELETE FROM votes WHERE response_id = ?");
                $stmt->execute([$responseId]);
                
                $stmt = $db->prepare("UPDATE responses SET submitted_at = NOW() WHERE id = ?");
                $stmt->execute([$responseId]);
                
                $stmtVote = $db->prepare("INSERT INTO votes (id, response_id, slot_id, value) VALUES (?, ?, ?, ?)");
                
                foreach ($votes as $slotId => $value) {
                    if (in_array($value, ['yes', 'maybe', 'no'])) {
                        $voteId = generateSafeVoteId();
                        $stmtVote->execute([$voteId, $responseId, $slotId, $value]);
                    }
                }
                
                setToast('✏️ อัปเดตคำตอบสำเร็จ!', 'success');
            } else {
                $responseId = generateSafeResponseId();
                
                $stmt = $db->prepare("
                    INSERT INTO responses (id, poll_id, user_id, user_name, submitted_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$responseId, $pollId, $user['id'], $user['display_name']]);
                
                $stmtVote = $db->prepare("INSERT INTO votes (id, response_id, slot_id, value) VALUES (?, ?, ?, ?)");
                
                foreach ($votes as $slotId => $value) {
                    if (in_array($value, ['yes', 'maybe', 'no'])) {
                        $voteId = generateSafeVoteId();
                        $stmtVote->execute([$voteId, $responseId, $slotId, $value]);
                    }
                }
                
                setToast('✅ ส่งคำตอบสำเร็จ!', 'success');
            }
            
            $db->commit();
            redirect("poll.php?id=$pollId");
            
        } catch (PDOException $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    // ดึงข้อมูล responses และ votes
    $stmt = $db->prepare("SELECT * FROM responses WHERE poll_id = ? ORDER BY submitted_at");
    $stmt->execute([$pollId]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึง votes ทั้งหมด
    $stmt = $db->prepare("
        SELECT v.*, r.user_name 
        FROM votes v
        INNER JOIN responses r ON v.response_id = r.id
        WHERE r.poll_id = ?
    ");
    $stmt->execute([$pollId]);
    $allVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // จัดระเบียบ votes ตาม slot
    $votesBySlot = [];
    foreach ($allVotes as $vote) {
        $slotId = $vote['slot_id'];
        if (!isset($votesBySlot[$slotId])) {
            $votesBySlot[$slotId] = ['yes' => [], 'maybe' => [], 'no' => []];
        }
        $votesBySlot[$slotId][$vote['value']][] = $vote['user_name'];
    }
    
    // คำนวณผลโหวต
    $results = [];
    foreach ($poll['slots'] as $slot) {
        $yesCount = 0;
        $maybeCount = 0;
        $noCount = 0;
        
        foreach ($responses as $resp) {
            $stmt = $db->prepare("SELECT value FROM votes WHERE response_id = ? AND slot_id = ? LIMIT 1");
            $stmt->execute([$resp['id'], $slot['id']]);
            $vote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($vote) {
                if ($vote['value'] === 'yes') $yesCount++;
                elseif ($vote['value'] === 'maybe') $maybeCount++;
                elseif ($vote['value'] === 'no') $noCount++;
            }
        }
        
        $score = ($yesCount * 2) + $maybeCount;
        $totalResponses = count($responses);
        $coverage = $totalResponses > 0 ? (($yesCount + $maybeCount) / $totalResponses * 100) : 0;
        
        $results[] = [
            'slot' => $slot,
            'yes' => $yesCount,
            'maybe' => $maybeCount,
            'no' => $noCount,
            'score' => $score,
            'coverage' => $coverage
        ];
    }
    
    usort($results, function($a, $b) {
        if ($b['score'] != $a['score']) return $b['score'] - $a['score'];
        if ($a['no'] != $b['no']) return $a['no'] - $b['no'];
        return $b['coverage'] <=> $a['coverage'];
    });
    
    // ดึงคำตอบของ user ปัจจุบัน
    $stmt = $db->prepare("SELECT id FROM responses WHERE poll_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pollId, $user['id']]);
    $userResponse = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $userVotes = [];
    if ($userResponse) {
        $stmt = $db->prepare("SELECT slot_id, value FROM votes WHERE response_id = ?");
        $stmt->execute([$userResponse['id']]);
        $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($votes as $vote) {
            $userVotes[$vote['slot_id']] = $vote['value'];
        }
    }
    
} catch (PDOException $e) {
    error_log("Poll error: " . $e->getMessage());
    setToast('❌ เกิดข้อผิดพลาดในการโหลดโพล', 'error');
    redirect('index.php');
}

$isExpired = !empty($poll['expire_date']) && strtotime($poll['expire_date']) < time();
$isCreator = $poll['creator_id'] == $user['id'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($poll['title']); ?> - นัดเพื่อน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Kanit', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .vote-btn { cursor: pointer; transition: all 0.2s; }
        .vote-btn:hover { transform: scale(1.1); }
        .vote-btn.selected { transform: scale(1.15); box-shadow: 0 0 20px rgba(0,0,0,0.3); }
        
        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; 
                 background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 1rem; max-width: 600px; width: 90%; 
                        max-height: 80vh; overflow-y: auto; }
    </style>
</head>
<body class="p-4">
    <?php include 'toast.php'; ?>
    
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-800">📊 <?php echo htmlspecialchars($poll['title']); ?></h1>
                    <div class="mt-3 space-y-1 text-sm text-gray-600">
                        <p>📅 <?php echo date('d/m/Y', strtotime($poll['week_start'])); ?> - <?php echo date('d/m/Y', strtotime($poll['week_end'])); ?></p>
                        <p>👤 สร้างโดย: <strong><?php echo htmlspecialchars($poll['creator_name']); ?></strong></p>
                        <p>💬 จำนวนผู้ตอบ: <strong class="text-purple-600"><?php echo count($responses); ?> คน</strong></p>
                        <?php if (!empty($poll['expire_date'])): ?>
                        <p>⏰ หมดอายุ: <?php echo date('d/m/Y', strtotime($poll['expire_date'])); ?>
                            <?php if ($isExpired): ?><span class="text-red-600 font-bold">(หมดอายุแล้ว)</span><?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <a href="index.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-xl hover:bg-gray-300 transition font-semibold">
                        ← กลับ
                    </a>
                    <?php if ($isCreator): ?>
                    <a href="export_poll.php?id=<?php echo $pollId; ?>" 
                       class="bg-green-500 text-white px-6 py-3 rounded-xl hover:bg-green-600 transition font-semibold">
                        📥 ส่งออก
                    </a>
                    <a href="delete_poll.php?id=<?php echo $pollId; ?>" 
                       class="bg-red-500 text-white px-6 py-3 rounded-xl hover:bg-red-600 transition font-semibold"
                       onclick="return confirm('⚠️ ต้องการลบโพลนี้?')">
                        🗑️ ลบ
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Results Summary with Details -->
        <?php if (count($responses) > 0): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">🏆 ผลโหวต (Top 3)</h2>
            
            <div class="space-y-3">
                <?php foreach (array_slice($results, 0, 3) as $idx => $result): ?>
                <div class="border-2 border-purple-200 rounded-xl p-4 hover:shadow-lg transition cursor-pointer"
                     onclick="showVoteDetails(<?php echo $result['slot']['id']; ?>)">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">
                                    <?php if ($idx === 0): ?>🥇
                                    <?php elseif ($idx === 1): ?>🥈
                                    <?php elseif ($idx === 2): ?>🥉
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <h3 class="font-bold text-lg"><?php echo date('d/m/Y', strtotime($result['slot']['slot_date'])); ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo $result['slot']['period']; ?> (<?php echo substr($result['slot']['start_time'], 0, 5); ?>-<?php echo substr($result['slot']['end_time'], 0, 5); ?>)</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <div class="bg-green-100 px-3 py-1 rounded-full">
                                <span class="text-green-700 font-bold">✅ <?php echo $result['yes']; ?></span>
                            </div>
                            <?php if ($poll['allow_maybe']): ?>
                            <div class="bg-yellow-100 px-3 py-1 rounded-full">
                                <span class="text-yellow-700 font-bold">⚠️ <?php echo $result['maybe']; ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="bg-red-100 px-3 py-1 rounded-full">
                                <span class="text-red-700 font-bold">❌ <?php echo $result['no']; ?></span>
                            </div>
                        </div>
                        
                        <div class="ml-4 text-right">
                            <div class="text-2xl font-bold text-purple-600"><?php echo $result['score']; ?></div>
                            <div class="text-sm text-gray-500"><?php echo round($result['coverage'], 1); ?>%</div>
                        </div>
                    </div>
                    
                    <div class="mt-2 text-xs text-gray-500 text-center">
                        💡 คลิกเพื่อดูรายละเอียดว่าใครโหวตอะไร
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Voting Form -->
        <?php if (!$isExpired): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <?php echo $userResponse ? '✏️ แก้ไขคำตอบของคุณ' : '✅ โหวตเลือกช่วงเวลา'; ?>
            </h2>
            
            <form method="POST" class="space-y-4">
                <?php foreach ($poll['slots'] as $slot): ?>
                <div class="border-2 border-gray-200 rounded-xl p-4 hover:border-purple-300 transition">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <div class="font-bold text-lg"><?php echo date('d/m/Y', strtotime($slot['slot_date'])); ?></div>
                            <div class="text-gray-600"><?php echo $slot['period']; ?> (<?php echo substr($slot['start_time'], 0, 5); ?>-<?php echo substr($slot['end_time'], 0, 5); ?>)</div>
                            <div class="text-xs text-gray-500 mt-1">
                                👥 <?php echo (isset($votesBySlot[$slot['id']]) ? array_sum(array_map('count', $votesBySlot[$slot['id']])) : 0); ?> คนโหวตแล้ว
                            </div>
                        </div>
                        
                        <div class="flex gap-3">
                            <?php 
                            $currentVote = $userVotes[$slot['id']] ?? null;
                            ?>
                            
                            <button type="button" 
                                    class="vote-btn w-16 h-16 rounded-full text-2xl <?php echo $currentVote === 'yes' ? 'bg-green-500 text-white selected' : 'bg-green-100 text-green-600'; ?>"
                                    onclick="selectVote(this, <?php echo $slot['id']; ?>, 'yes')"
                                    title="ว่าง">
                                ✅
                            </button>
                            
                            <?php if ($poll['allow_maybe']): ?>
                            <button type="button"
                                    class="vote-btn w-16 h-16 rounded-full text-2xl <?php echo $currentVote === 'maybe' ? 'bg-yellow-500 text-white selected' : 'bg-yellow-100 text-yellow-600'; ?>"
                                    onclick="selectVote(this, <?php echo $slot['id']; ?>, 'maybe')"
                                    title="อาจจะ">
                                ⚠️
                            </button>
                            <?php endif; ?>
                            
                            <button type="button"
                                    class="vote-btn w-16 h-16 rounded-full text-2xl <?php echo $currentVote === 'no' ? 'bg-red-500 text-white selected' : 'bg-red-100 text-red-600'; ?>"
                                    onclick="selectVote(this, <?php echo $slot['id']; ?>, 'no')"
                                    title="ไม่ว่าง">
                                ❌
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="votes[<?php echo $slot['id']; ?>]" id="vote_<?php echo $slot['id']; ?>" value="<?php echo $currentVote ?? 'no'; ?>">
                </div>
                <?php endforeach; ?>
                
                <button type="submit" name="vote" 
                        class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white py-4 rounded-xl font-bold text-lg hover:shadow-2xl transition">
                    <?php echo $userResponse ? '✏️ อัปเดตคำตอบ' : '✅ ส่งคำตอบ'; ?>
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6">
            <p class="font-bold">⚠️ โพลนี้หมดอายุแล้ว</p>
            <p>ไม่สามารถโหวตหรือแก้ไขคำตอบได้อีกต่อไป</p>
        </div>
        <?php endif; ?>
        
        <!-- Who Voted -->
        <?php if (count($responses) > 0): ?>
        <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">👥 ผู้ตอบแบบสอบถาม (<?php echo count($responses); ?> คน)</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php foreach ($responses as $resp): ?>
                <div class="bg-purple-50 p-3 rounded-lg text-center border-2 border-purple-200">
                    <div class="text-2xl mb-1">👤</div>
                    <div class="font-semibold"><?php echo htmlspecialchars($resp['user_name']); ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?php echo date('d/m H:i', strtotime($resp['submitted_at'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Vote Details Modal -->
    <div id="voteDetailsModal" class="modal">
        <div class="modal-content p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800" id="modalTitle">รายละเอียดการโหวต</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-3xl">&times;</button>
            </div>
            
            <div id="modalContent" class="space-y-4">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        function selectVote(btn, slotId, value) {
            // Remove selected from siblings
            const siblings = btn.parentElement.querySelectorAll('.vote-btn');
            siblings.forEach(s => {
                s.classList.remove('selected', 'bg-green-500', 'bg-yellow-500', 'bg-red-500', 'text-white');
                if (s.classList.contains('bg-green-100')) {
                    s.classList.add('text-green-600');
                } else if (s.classList.contains('bg-yellow-100')) {
                    s.classList.add('text-yellow-600');
                } else if (s.classList.contains('bg-red-100')) {
                    s.classList.add('text-red-600');
                }
            });
            
            // Add selected to clicked button
            btn.classList.add('selected', 'text-white');
            btn.classList.remove('text-green-600', 'text-yellow-600', 'text-red-600');
            
            if (value === 'yes') {
                btn.classList.add('bg-green-500');
            } else if (value === 'maybe') {
                btn.classList.add('bg-yellow-500');
            } else {
                btn.classList.add('bg-red-500');
            }
            
            // Update hidden input
            document.getElementById('vote_' + slotId).value = value;
        }
        
        const votesBySlot = <?php echo json_encode($votesBySlot); ?>;
        const slots = <?php echo json_encode($poll['slots']); ?>;
        
        function showVoteDetails(slotId) {
            const slot = slots.find(s => s.id == slotId);
            const votes = votesBySlot[slotId] || {yes: [], maybe: [], no: []};
            
            if (!slot) return;
            
            document.getElementById('modalTitle').textContent = 
                date('d/m/Y', slot.slot_date) + ' - ' + slot.period;
            
            let html = '';
            
            // Yes votes
            if (votes.yes.length > 0) {
                html += '<div class="bg-green-50 p-4 rounded-lg border-2 border-green-200">';
                html += '<h3 class="font-bold text-green-700 mb-2">✅ ว่าง (' + votes.yes.length + ' คน)</h3>';
                html += '<div class="space-y-1">';
                votes.yes.forEach(name => {
                    html += '<div class="bg-white px-3 py-2 rounded">👤 ' + escapeHtml(name) + '</div>';
                });
                html += '</div></div>';
            }
            
            // Maybe votes
            if (votes.maybe.length > 0) {
                html += '<div class="bg-yellow-50 p-4 rounded-lg border-2 border-yellow-200">';
                html += '<h3 class="font-bold text-yellow-700 mb-2">⚠️ อาจจะ (' + votes.maybe.length + ' คน)</h3>';
                html += '<div class="space-y-1">';
                votes.maybe.forEach(name => {
                    html += '<div class="bg-white px-3 py-2 rounded">👤 ' + escapeHtml(name) + '</div>';
                });
                html += '</div></div>';
            }
            
            // No votes
            if (votes.no.length > 0) {
                html += '<div class="bg-red-50 p-4 rounded-lg border-2 border-red-200">';
                html += '<h3 class="font-bold text-red-700 mb-2">❌ ไม่ว่าง (' + votes.no.length + ' คน)</h3>';
                html += '<div class="space-y-1">';
                votes.no.forEach(name => {
                    html += '<div class="bg-white px-3 py-2 rounded">👤 ' + escapeHtml(name) + '</div>';
                });
                html += '</div></div>';
            }
            
            if (!html) {
                html = '<div class="text-center text-gray-500 py-8">ยังไม่มีใครโหวตช่วงเวลานี้</div>';
            }
            
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('voteDetailsModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('voteDetailsModal').classList.remove('active');
        }
        
        function date(format, dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('th-TH');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>