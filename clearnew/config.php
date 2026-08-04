<?php
// config.php - إعدادات الاتصال بقاعدة البيانات

// تفعيل عرض الأخطاء للتطوير (يتم إيقافها عند النشر)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'murafaaq_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// بدء الجلسة
session_start();

// دعم اللغتين
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ar'; // اللغة الافتراضية العربية
}

// معالجة تغيير اللغة
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// الاتصال بقاعدة البيانات
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// وظيفة الحماية من هجمات SQL Injection وXSS
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>

