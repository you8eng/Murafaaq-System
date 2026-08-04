-- إضافة جدول مرفقات المهام
CREATE TABLE IF NOT EXISTS task_attachments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة حقول completion_notes للمهام (تنفيذ يدوي - تحقق من وجود الحقول أولاً)
-- ALTER TABLE tasks ADD COLUMN completion_notes TEXT;
-- ALTER TABLE tasks ADD COLUMN non_completion_notes TEXT;
-- ALTER TABLE tasks ADD COLUMN completion_status ENUM('completed', 'not_completed') NULL;

-- أو استخدم هذا الاستعلام للتحقق من وجود الحقول قبل الإضافة:
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'murafaaq_db' AND TABLE_NAME = 'tasks' AND COLUMN_NAME IN ('completion_notes', 'non_completion_notes', 'completion_status');

