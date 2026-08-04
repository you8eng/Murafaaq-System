CREATE DATABASE IF NOT EXISTS murafaaq_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE murafaaq_db;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_number VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'supervisor', 'worker') NOT NULL,
    location VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    location VARCHAR(100) NOT NULL,
    area VARCHAR(50) NOT NULL,
    building VARCHAR(50) NOT NULL,
    floor VARCHAR(50) NOT NULL,
    supervisor_id INT,
    worker_id INT,
    status ENUM('pending', 'in_progress', 'completed', 'approved') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    photo_url VARCHAR(255),
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    check_in TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    check_out TIMESTAMP NULL,
    date DATE NOT NULL,
    total_hours DECIMAL(5,2),
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    type ENUM('daily', 'weekly', 'monthly', 'performance') NOT NULL,
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (job_number, full_name, password, role, location, status) VALUES
('EMP001', 'يوسف فرحان مفضي العنزي', '$2y$10$Fl1orb44vXKYI2wQ371YTOljs.HXdCm3V0cRos1HWL3JZodyOSBSm', 'admin', 'المنطقة A', 'active');

INSERT INTO users (job_number, full_name, password, role, location, status) VALUES
('EMP002', 'عبدالله', '$2y$10$zyURT8IdSizfoociVJxIROYquYBNZ7s85IyhPTnP6PaZYZvckv7FW', 'supervisor', 'المنطقة A - المبنى الرئيسي', 'active');

INSERT INTO users (job_number, full_name, password, role, location, status) VALUES
('EMP003', 'فيصل', '$2y$10$ona448Ua1lwMYO9eFVN92ulHbvQ4GK1bw6oEOJ.tRuvCFmzLALr6u', 'worker', 'المنطقة A - المبنى الرئيسي - الدور الأول', 'active');

INSERT INTO tasks (
title,
description,
location,
area,
building,
floor,
supervisor_id,
worker_id,
status,
priority
) VALUES
('فحص نظام التكييف', 'إجراء فحص دوري لجميع وحدات التكييف.', 'المنطقة A', 'A', 'المبنى الرئيسي', 'الأول', 2, 3, 'pending', 'high'),

('صيانة لوحة الكهرباء', 'التحقق من سلامة لوحة التوزيع الكهربائية.', 'المنطقة A', 'A', 'المبنى الرئيسي', 'الأرضي', 2, 3, 'in_progress', 'high'),

('فحص أنظمة السلامة', 'التأكد من جاهزية أجهزة الإنذار ومخارج الطوارئ.', 'المنطقة A', 'A', 'المبنى الرئيسي', 'الثاني', 2, 3, 'completed', 'medium');

INSERT INTO attendance (
user_id,
date,
check_in,
check_out,
total_hours
) VALUES
(3, CURDATE(), '08:00:00', '16:00:00', 8.00);

INSERT INTO reports (
title,
type,
content,
created_by
) VALUES
(
'التقرير اليومي',
'daily',
'تم تنفيذ جميع المهام المجدولة بنجاح دون تسجيل أي ملاحظات.',
1
);

INSERT INTO notifications (
user_id,
title,
message
) VALUES
(
3,
'مهمة جديدة',
'تم إسناد مهمة جديدة إليك، يرجى مراجعة لوحة التحكم.'
);