<?php
// functions.php - Updated with Advanced Ban System

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// ===== Database Connection =====

function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("เชื่อมต่อ database ไม่สำเร็จ กรุณาตรวจสอบ config.php");
        }
    }
    
    return $pdo;
}

// ===== Ban Management =====

function checkBanStatus($user) {
    // ถ้าไม่ถูกแบน
    if (!$user['banned']) {
        return ['banned' => false];
    }
    
    // ถ้าแบนถาวร (ban_until = NULL)
    if ($user['ban_until'] === null) {
        return [
            'banned' => true,
            'permanent' => true,
            'reason' => $user['ban_reason'] ?? 'ไม่ระบุเหตุผล',
            'banned_at' => $user['banned_at']
        ];
    }
    
    // เช็คว่าหมดเวลาแบนหรือยัง
    $banUntil = strtotime($user['ban_until']);
    $now = time();
    
    if ($now >= $banUntil) {
        // หมดเวลาแบนแล้ว - ปลดแบนอัตโนมัติ
        try {
            $db = getDB();
            $stmt = $db->prepare("
                UPDATE users 
                SET banned = 0, ban_reason = NULL, ban_until = NULL, banned_at = NULL, banned_by = NULL
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);
            
            return ['banned' => false, 'auto_unbanned' => true];
        } catch (PDOException $e) {
            error_log("Auto unban error: " . $e->getMessage());
        }
    }
    
    // ยังโดนแบนอยู่
    return [
        'banned' => true,
        'permanent' => false,
        'reason' => $user['ban_reason'] ?? 'ไม่ระบุเหตุผล',
        'banned_at' => $user['banned_at'],
        'ban_until' => $user['ban_until'],
        'remaining' => $banUntil - $now
    ];
}

function formatBanDuration($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = "$days วัน";
    if ($hours > 0) $parts[] = "$hours ชั่วโมง";
    if ($minutes > 0) $parts[] = "$minutes นาที";
    
    return empty($parts) ? "น้อยกว่า 1 นาที" : implode(" ", $parts);
}

// ===== User Functions =====

function generateToken() {
    return bin2hex(random_bytes(32));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['token']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND token = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // เช็คสถานะแบน
            $banStatus = checkBanStatus($user);
            
            if ($banStatus['banned']) {
                // ถูกแบน - ออกจากระบบ
                $_SESSION['ban_info'] = $banStatus;
                session_destroy();
                return null;
            }
            
            return $user;
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("getCurrentUser error: " . $e->getMessage());
        return null;
    }
}

function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        redirect('index.php');
    }
}

function login($userId, $token) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['token'] = $token;
    $_SESSION['login_time'] = time();
}

function logout() {
    session_destroy();
    redirect('login.php');
}

// ===== Toast Messages =====

function setToast($message, $type = 'info') {
    $_SESSION['toast'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getToast() {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        return $toast;
    }
    return null;
}

// ===== Helper Functions =====

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    return date($format, strtotime($datetime));
}

function getThaiDayName($date) {
    $days = [
        'Monday' => 'จันทร์',
        'Tuesday' => 'อังคาร',
        'Wednesday' => 'พุธ',
        'Thursday' => 'พฤหัสบดี',
        'Friday' => 'ศุกร์',
        'Saturday' => 'เสาร์',
        'Sunday' => 'อาทิตย์'
    ];
    $englishDay = date('l', strtotime($date));
    return $days[$englishDay] ?? $englishDay;
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return "เมื่อสักครู่";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " นาทีที่แล้ว";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " ชั่วโมงที่แล้ว";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " วันที่แล้ว";
    } else {
        return formatDateTime($datetime);
    }
}

// ===== Poll Functions =====

function getPollById($pollId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM polls WHERE id = ? LIMIT 1");
        $stmt->execute([$pollId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("getPollById error: " . $e->getMessage());
        return null;
    }
}

function getUserPolls($userId, $limit = null) {
    try {
        $db = getDB();
        $sql = "SELECT * FROM polls WHERE creator_id = ? ORDER BY created_at DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getUserPolls error: " . $e->getMessage());
        return [];
    }
}

function getPollSlots($pollId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM poll_slots WHERE poll_id = ? ORDER BY slot_date, start_time");
        $stmt->execute([$pollId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getPollSlots error: " . $e->getMessage());
        return [];
    }
}

function getPollResponses($pollId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM responses WHERE poll_id = ? ORDER BY submitted_at");
        $stmt->execute([$pollId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getPollResponses error: " . $e->getMessage());
        return [];
    }
}

function getUserResponse($pollId, $userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM responses WHERE poll_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$pollId, $userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("getUserResponse error: " . $e->getMessage());
        return null;
    }
}

function getResponseVotes($responseId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM votes WHERE response_id = ?");
        $stmt->execute([$responseId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getResponseVotes error: " . $e->getMessage());
        return [];
    }
}

function hasUserResponded($pollId, $userId) {
    $response = getUserResponse($pollId, $userId);
    return $response !== null && $response !== false;
}

function canUserAccessPoll($poll, $user) {
    if (!$poll) return false;
    
    if ($user && $poll['creator_id'] == $user['id']) {
        return true;
    }
    
    if (!empty($poll['expire_date']) && strtotime($poll['expire_date']) < time()) {
        return false;
    }
    
    return true;
}

function isPollExpired($poll) {
    if (!$poll) return true;
    return !empty($poll['expire_date']) && strtotime($poll['expire_date']) < time();
}

// ===== Vote Calculation =====

function calculatePollResults($pollId) {
    try {
        $db = getDB();
        
        $slots = getPollSlots($pollId);
        $responses = getPollResponses($pollId);
        
        $results = [];
        foreach ($slots as $slot) {
            $stmt = $db->prepare("
                SELECT v.value, COUNT(*) as count
                FROM votes v
                INNER JOIN responses r ON v.response_id = r.id
                WHERE r.poll_id = ? AND v.slot_id = ?
                GROUP BY v.value
            ");
            $stmt->execute([$pollId, $slot['id']]);
            $voteCounts = $stmt->fetchAll();
            
            $yesCount = 0;
            $maybeCount = 0;
            $noCount = 0;
            
            foreach ($voteCounts as $vc) {
                if ($vc['value'] === 'yes') $yesCount = $vc['count'];
                elseif ($vc['value'] === 'maybe') $maybeCount = $vc['count'];
                elseif ($vc['value'] === 'no') $noCount = $vc['count'];
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
        
        return $results;
    } catch (PDOException $e) {
        error_log("calculatePollResults error: " . $e->getMessage());
        return [];
    }
}

// ===== Validation Functions =====

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ===== Security Functions =====

function preventCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function getCSRFToken() {
    preventCSRF();
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===== Pagination =====

function paginate($totalItems, $itemsPerPage, $currentPage) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

// ===== Legacy Compatibility =====

function loadData($file) {
    return ['users' => [], 'polls' => [], 'responses' => [], 'votes' => []];
}

function saveData($file, $data) {
    return true;
}
?>