<?php
// auth.php - وظائف المصادقة

// التحقق من تسجيل الدخول
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }
}

// التحقق من الدور
function requireRole($allowed_roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        header('Location: ../index.php');
        exit();
    }
}

// التحقق من أن المستخدم هو المالك
function requireAdmin() {
    requireRole(['admin']);
}

// التحقق من أن المستخدم هو مشرف
function requireSupervisor() {
    requireRole(['admin', 'supervisor']);
}

// التحقق من أن المستخدم هو عامل
function requireWorker() {
    requireRole(['admin', 'supervisor', 'worker']);
}

// الحصول على معلومات المستخدم الحالي
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}
?>

