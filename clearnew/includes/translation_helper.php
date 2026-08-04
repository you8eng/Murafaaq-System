<?php
// translation_helper.php - دالة مساعدة لترجمة البيانات من قاعدة البيانات

/**
 * ترجمة نص من قاعدة البيانات حسب اللغة المختارة
 * @param string $ar_text النص العربي
 * @param string|null $en_text النص الإنجليزي (اختياري)
 * @param string $lang اللغة الحالية
 * @return string النص المترجم
 */
function translate_db_text($ar_text, $en_text = null, $lang = 'ar') {
    if ($lang == 'en' && !empty($en_text)) {
        return $en_text;
    }
    return $ar_text ?: '';
}

/**
 * ترجمة بيانات المهمة حسب اللغة المختارة
 * @param array $task بيانات المهمة من قاعدة البيانات
 * @param string $lang اللغة الحالية
 * @return array بيانات المهمة المترجمة
 */
function translate_task($task, $lang = 'ar') {
    if (!$task || !is_array($task)) {
        return $task;
    }
    
    $translated = $task;
    
    // إذا كانت اللغة عربية، لا حاجة للترجمة
    if ($lang != 'en') {
        return $translated;
    }
    
    // ترجمة العنوان
    if (isset($task['title_en']) && !empty(trim($task['title_en']))) {
        $translated['title'] = $task['title_en'];
    }
    
    // ترجمة الوصف
    if (isset($task['description_en']) && !empty(trim($task['description_en']))) {
        $translated['description'] = $task['description_en'];
    }
    
    // ترجمة الموقع
    if (isset($task['location_en']) && !empty(trim($task['location_en']))) {
        $translated['location'] = $task['location_en'];
    }
    
    // ترجمة المنطقة
    if (isset($task['area_en']) && !empty(trim($task['area_en']))) {
        $translated['area'] = $task['area_en'];
    }
    
    // ترجمة المبنى
    if (isset($task['building_en']) && !empty(trim($task['building_en']))) {
        $translated['building'] = $task['building_en'];
    }
    
    // ترجمة الدور
    if (isset($task['floor_en']) && !empty(trim($task['floor_en']))) {
        $translated['floor'] = $task['floor_en'];
    }
    
    return $translated;
}

/**
 * ترجمة قائمة المهام
 * @param array $tasks قائمة المهام
 * @param string $lang اللغة الحالية
 * @return array قائمة المهام المترجمة
 */
function translate_tasks($tasks, $lang = 'ar') {
    if (empty($tasks)) {
        return $tasks;
    }
    
    $translated_tasks = [];
    foreach ($tasks as $task) {
        $translated_tasks[] = translate_task($task, $lang);
    }
    
    return $translated_tasks;
}

