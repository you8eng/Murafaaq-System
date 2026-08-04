<?php
// functions.php - وظائف مساعدة

// تنسيق التاريخ
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return '-';
    }
    return date($format, strtotime($date));
}

// تنسيق الوقت
function formatTime($time) {
    if (empty($time)) {
        return '-';
    }
    return date('H:i', strtotime($time));
}

// الحصول على حالة المهمة مع التنسيق
function getTaskStatusBadge($status, $lang) {
    $badges = [
        'pending' => ['class' => 'secondary', 'text' => $lang['pending'] ?? 'معلقة'],
        'in_progress' => ['class' => 'warning', 'text' => $lang['in_progress'] ?? 'قيد التنفيذ'],
        'completed' => ['class' => 'success', 'text' => $lang['completed'] ?? 'مكتملة'],
        'approved' => ['class' => 'info', 'text' => $lang['approved'] ?? 'معتمدة']
    ];
    
    $status_key = str_replace(' ', '_', $status);
    $badge = $badges[$status_key] ?? ['class' => 'secondary', 'text' => $status];
    
    return '<span class="badge bg-' . $badge['class'] . '">' . $badge['text'] . '</span>';
}

// الحصول على أولوية المهمة مع التنسيق
function getPriorityBadge($priority, $lang) {
    $badges = [
        'low' => ['class' => 'info', 'text' => $lang['low'] ?? 'منخفضة'],
        'medium' => ['class' => 'warning', 'text' => $lang['medium'] ?? 'متوسطة'],
        'high' => ['class' => 'danger', 'text' => $lang['high'] ?? 'عالية']
    ];
    
    $badge = $badges[$priority] ?? ['class' => 'secondary', 'text' => $priority];
    
    return '<span class="badge bg-' . $badge['class'] . '">' . $badge['text'] . '</span>';
}

// حساب الفرق بين وقتين
function calculateHours($start, $end) {
    if (empty($start) || empty($end)) {
        return 0;
    }
    
    $start_time = strtotime($start);
    $end_time = strtotime($end);
    $diff = $end_time - $start_time;
    
    return round($diff / 3600, 2);
}

// إرسال تنبيه
function sendNotification($pdo, $user_id, $title, $message) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $title, $message]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// الحصول على عدد التنبيهات غير المقروءة
function getUnreadNotificationsCount($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}
?>

