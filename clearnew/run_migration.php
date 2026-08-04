<?php
// run_migration.php - تشغيل تحديثات قاعدة البيانات
require_once 'config.php';

try {
    // إنشاء جدول task_attachments
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_attachments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        task_id INT NOT NULL,
        file_type ENUM('image', 'audio') NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_size INT,
        attachment_type ENUM('completion', 'non_completion') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        INDEX idx_task_id (task_id),
        INDEX idx_attachment_type (attachment_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "✓ تم إنشاء جدول task_attachments بنجاح\n";
    
    // التحقق من وجود الأعمدة قبل إضافتها
    $columns = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                            WHERE TABLE_SCHEMA = 'murafaaq_db' 
                            AND TABLE_NAME = 'tasks' 
                            AND COLUMN_NAME IN ('completion_notes', 'non_completion_notes', 'completion_status')")
                   ->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('completion_notes', $columns)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN completion_notes TEXT");
        echo "✓ تم إضافة عمود completion_notes\n";
    } else {
        echo "○ عمود completion_notes موجود بالفعل\n";
    }
    
    if (!in_array('non_completion_notes', $columns)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN non_completion_notes TEXT");
        echo "✓ تم إضافة عمود non_completion_notes\n";
    } else {
        echo "○ عمود non_completion_notes موجود بالفعل\n";
    }
    
    if (!in_array('completion_status', $columns)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN completion_status ENUM('completed', 'not_completed') NULL");
        echo "✓ تم إضافة عمود completion_status\n";
    } else {
        echo "○ عمود completion_status موجود بالفعل\n";
    }
    
    echo "\n✓ تم تنفيذ جميع التحديثات بنجاح!\n";
    
} catch (PDOException $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
}
?>

