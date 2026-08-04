-- Migration: إضافة حقول الترجمة لجدول المهام
-- Run this SQL to add translation fields to tasks table

USE murafaaq_db;

-- إضافة حقول الترجمة الإنجليزية
ALTER TABLE tasks 
ADD COLUMN title_en VARCHAR(200) NULL AFTER title,
ADD COLUMN description_en TEXT NULL AFTER description,
ADD COLUMN location_en VARCHAR(100) NULL AFTER location,
ADD COLUMN area_en VARCHAR(50) NULL AFTER area,
ADD COLUMN building_en VARCHAR(50) NULL AFTER building,
ADD COLUMN floor_en VARCHAR(50) NULL AFTER floor;

-- تحديث البيانات الموجودة (مثال)
-- يمكنك تحديث البيانات يدوياً أو من خلال الواجهة
UPDATE tasks SET 
    title_en = CASE 
        WHEN title = 'تنظيف الدور الأرضي' THEN 'Clean Ground Floor'
        WHEN title = 'تنظيف المدخل الرئيسي' THEN 'Clean Main Entrance'
        WHEN title = 'تعقيم الحمامات' THEN 'Disinfect Bathrooms'
        ELSE title
    END,
    description_en = CASE 
        WHEN description = 'تنظيف كامل للدور الأرضي' THEN 'Complete cleaning of ground floor'
        WHEN description = 'تنظيف المدخل والسلالم' THEN 'Clean entrance and stairs'
        WHEN description = 'تعقيم جميع حمامات الدور الأرضي' THEN 'Disinfect all ground floor bathrooms'
        ELSE description
    END,
    location_en = CASE 
        WHEN location = 'المنطقة A' THEN 'Area A'
        ELSE location
    END,
    area_en = CASE 
        WHEN area = 'A' THEN 'A'
        ELSE area
    END,
    building_en = CASE 
        WHEN building = 'F' THEN 'F'
        ELSE building
    END,
    floor_en = CASE 
        WHEN floor = 'الأرضي' THEN 'Ground Floor'
        ELSE floor
    END;

