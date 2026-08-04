<?php
// dashboard/worker/index.php - لوحة تحكم العامل
require_once '../../config.php';
require_once '../../includes/lang.php';
require_once '../../includes/translation_helper.php';

// معالجة تغيير اللغة
if (isset($_GET['lang'])) {
    $lang_code = $_GET['lang'];
    if (in_array($lang_code, ['ar', 'en'])) {
        $_SESSION['lang'] = $lang_code;
        header('Location: index.php' . (isset($_GET['view']) && isset($_GET['id']) ? '?view=1&id=' . $_GET['id'] : ''));
        exit();
    }
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'supervisor', 'worker'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// معالجة تبديل الحضور/الانصراف (Toggle)
if (isset($_GET['toggle_attendance'])) {
    try {
        $today = date('Y-m-d');
        // التحقق من وجود تسجيل حضور نشط اليوم (بدون انصراف)
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1");
        $stmt->execute([$user_id, $today]);
        $active_attendance = $stmt->fetch();
        
        if ($active_attendance) {
            // يوجد حضور نشط → إنهاء الدوام
            $check_in = strtotime($active_attendance['check_in']);
            $check_out = time();
            $total_hours = round(($check_out - $check_in) / 3600, 2);
            
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = NOW(), total_hours = ? WHERE id = ?");
            $stmt->execute([$total_hours, $active_attendance['id']]);
            $success = $lang['checkout_success'] . ': ' . number_format($total_hours, 2) . ' ' . $lang['hours'];
        } else {
            // لا يوجد حضور نشط → بدء الدوام
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $today]);
            $success = $lang['attendance_registered_success'];
        }
        
        // إعادة تحميل الصفحة
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// معالجة تسجيل الحضور (القبول) - للتوافق مع الروابط القديمة
if (isset($_GET['check_in']) && $_GET['check_in'] == '1') {
    try {
        $today = date('Y-m-d');
        // التحقق من عدم وجود تسجيل حضور نشط اليوم (بدون انصراف)
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ? AND check_out IS NULL");
        $stmt->execute([$user_id, $today]);
        
        if ($stmt->fetch()) {
            // يوجد حضور نشط بدون انصراف
            $error = $lang['attendance_already_registered'];
        } else {
            // لا يوجد حضور نشط، يمكن الحضور
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $today]);
            $success = $lang['attendance_registered_success'];
            // إعادة تحميل الصفحة لإزالة المعامل من الرابط
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// معالجة رفض بدء الدوام
if (isset($_GET['check_in']) && $_GET['check_in'] == '0') {
    try {
        $today = date('Y-m-d');
        // التحقق من عدم وجود تسجيل حضور اليوم
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$user_id, $today]);
        if ($stmt->fetch()) {
            $error = $lang['attendance_already_registered'];
        } else {
            // تسجيل الرفض في قاعدة البيانات (اختياري - يمكنك إضافة جدول منفصل للرفض)
            $success = $lang['attendance_rejected_success'];
            // إعادة تحميل الصفحة
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// معالجة تسجيل الانصراف
if (isset($_GET['check_out'])) {
    try {
        $today = date('Y-m-d');
        // جلب تسجيل الحضور اليوم
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$user_id, $today]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            $error = $lang['attendance_not_registered_today'];
        } elseif ($attendance['check_out']) {
            $error = $lang['checkout_already_registered'];
        } else {
            // حساب ساعات العمل
            $check_in = strtotime($attendance['check_in']);
            $check_out = time();
            $total_hours = round(($check_out - $check_in) / 3600, 2);
            
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = NOW(), total_hours = ? WHERE id = ?");
            $stmt->execute([$total_hours, $attendance['id']]);
            $success = $lang['checkout_success'] . ': ' . $total_hours . ' ' . $lang['hours'];
            // إعادة تحميل الصفحة لإزالة المعامل من الرابط
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// معالجة قبول المهمة مع المرفقات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_task'])) {
    try {
        $task_id = $_POST['task_id'];
        $notes = trim($_POST['accept_notes'] ?? '');
        
        // التحقق من أن المهمة تخص هذا العامل
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND worker_id = ? AND status = 'pending'");
        $stmt->execute([$task_id, $user_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            $error = $lang['task_not_found_or_cannot_start'];
        } else {
            // تحديث حالة المهمة
            $stmt = $pdo->prepare("UPDATE tasks SET status = 'in_progress', started_at = NOW(), completion_notes = ? WHERE id = ?");
            $stmt->execute([$notes ?: null, $task_id]);
            
            // رفع الصور
            if (isset($_FILES['accept_images']) && !empty($_FILES['accept_images']['name'][0])) {
                $upload_dir = '../../uploads/tasks/' . $task_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['accept_images']['name'] as $key => $name) {
                    if ($_FILES['accept_images']['error'][$key] == UPLOAD_ERR_OK) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                        if (in_array(strtolower($ext), $allowed)) {
                            $new_name = uniqid() . '_' . time() . '.' . $ext;
                            $file_path = $upload_dir . $new_name;
                            if (move_uploaded_file($_FILES['accept_images']['tmp_name'][$key], $file_path)) {
                                $relative_path = 'uploads/tasks/' . $task_id . '/' . $new_name;
                                $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_type, file_path, file_name, file_size, attachment_type) VALUES (?, 'image', ?, ?, ?, 'completion')");
                                $stmt->execute([$task_id, $relative_path, $name, $_FILES['accept_images']['size'][$key]]);
                            }
                        }
                    }
                }
            }
            
            // رفع الصوت
            if (isset($_FILES['accept_audio']) && $_FILES['accept_audio']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/tasks/' . $task_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($_FILES['accept_audio']['name'], PATHINFO_EXTENSION);
                // إذا لم يكن هناك امتداد، افترض أنه webm (التسجيل من المتصفح)
                if (empty($ext)) {
                    $ext = 'webm';
                }
                $allowed = ['mp3', 'wav', 'ogg', 'm4a', 'webm'];
                if (in_array(strtolower($ext), $allowed)) {
                    $new_name = 'audio_' . uniqid() . '_' . time() . '.' . $ext;
                    $file_path = $upload_dir . $new_name;
                    if (move_uploaded_file($_FILES['accept_audio']['tmp_name'], $file_path)) {
                        $relative_path = 'uploads/tasks/' . $task_id . '/' . $new_name;
                        $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_type, file_path, file_name, file_size, attachment_type) VALUES (?, 'audio', ?, ?, ?, 'completion')");
                        $stmt->execute([$task_id, $relative_path, $_FILES['accept_audio']['name'], $_FILES['accept_audio']['size']]);
                    }
                }
            }
            
            $success = $lang['task_started_success'];
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// معالجة رفض المهمة مع المرفقات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_task'])) {
    try {
        $task_id = $_POST['task_id'];
        $notes = trim($_POST['reject_notes'] ?? '');
        
        // التحقق من أن المهمة تخص هذا العامل
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND worker_id = ? AND status = 'pending'");
        $stmt->execute([$task_id, $user_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            $error = $lang['task_not_found_or_cannot_update'];
        } else {
            // تحديث حالة المهمة إلى مرفوضة
            $reject_note = $notes ?: 'تم رفض المهمة من قبل العامل';
            $stmt = $pdo->prepare("UPDATE tasks SET status = 'completed', completed_at = NOW(), completion_status = 'not_completed', non_completion_notes = ? WHERE id = ?");
            $stmt->execute([$reject_note, $task_id]);
            
            // رفع الصور
            if (isset($_FILES['reject_images']) && !empty($_FILES['reject_images']['name'][0])) {
                $upload_dir = '../../uploads/tasks/' . $task_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['reject_images']['name'] as $key => $name) {
                    if ($_FILES['reject_images']['error'][$key] == UPLOAD_ERR_OK) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                        if (in_array(strtolower($ext), $allowed)) {
                            $new_name = uniqid() . '_' . time() . '.' . $ext;
                            $file_path = $upload_dir . $new_name;
                            if (move_uploaded_file($_FILES['reject_images']['tmp_name'][$key], $file_path)) {
                                $relative_path = 'uploads/tasks/' . $task_id . '/' . $new_name;
                                $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_type, file_path, file_name, file_size, attachment_type) VALUES (?, 'image', ?, ?, ?, 'non_completion')");
                                $stmt->execute([$task_id, $relative_path, $name, $_FILES['reject_images']['size'][$key]]);
                            }
                        }
                    }
                }
            }
            
            // رفع الصوت
            if (isset($_FILES['reject_audio']) && $_FILES['reject_audio']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/tasks/' . $task_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($_FILES['reject_audio']['name'], PATHINFO_EXTENSION);
                // إذا لم يكن هناك امتداد، افترض أنه webm (التسجيل من المتصفح)
                if (empty($ext)) {
                    $ext = 'webm';
                }
                $allowed = ['mp3', 'wav', 'ogg', 'm4a', 'webm'];
                if (in_array(strtolower($ext), $allowed)) {
                    $new_name = 'audio_' . uniqid() . '_' . time() . '.' . $ext;
                    $file_path = $upload_dir . $new_name;
                    if (move_uploaded_file($_FILES['reject_audio']['tmp_name'], $file_path)) {
                        $relative_path = 'uploads/tasks/' . $task_id . '/' . $new_name;
                        $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_type, file_path, file_name, file_size, attachment_type) VALUES (?, 'audio', ?, ?, ?, 'non_completion')");
                        $stmt->execute([$task_id, $relative_path, $_FILES['reject_audio']['name'], $_FILES['reject_audio']['size']]);
                    }
                }
            }
            
            $success = $lang['task_rejected_success'] ?? 'تم رفض المهمة بنجاح';
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// معالجة إكمال المهمة مع المرفقات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_task'])) {
    try {
        $task_id = $_POST['task_id'];
        $notes = trim($_POST['completion_notes'] ?? '');
        
        // التحقق من أن المهمة تخص هذا العامل
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND worker_id = ? AND status = 'in_progress'");
        $stmt->execute([$task_id, $user_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            $error = $lang['task_not_found_or_cannot_complete'];
        } else {
            // تحديث حالة المهمة
            $stmt = $pdo->prepare("UPDATE tasks SET status = 'completed', completed_at = NOW(), completion_notes = ?, completion_status = 'completed' WHERE id = ?");
            $stmt->execute([$notes ?: null, $task_id]);
            
            // رفع الصور
            if (isset($_FILES['completion_images']) && !empty($_FILES['completion_images']['name'][0])) {
                $upload_dir = '../../uploads/tasks/' . $task_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['completion_images']['name'] as $key => $name) {
                    if ($_FILES['completion_images']['error'][$key] == UPLOAD_ERR_OK) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                        if (in_array(strtolower($ext), $allowed)) {
                            $new_name = uniqid() . '_' . time() . '.' . $ext;
                            $file_path = $upload_dir . $new_name;
                            if (move_uploaded_file($_FILES['completion_images']['tmp_name'][$key], $file_path)) {
                                $relative_path = 'uploads/tasks/' . $task_id . '/' . $new_name;
                                $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_type, file_path, file_name, file_size, attachment_type) VALUES (?, 'image', ?, ?, ?, 'completion')");
                                $stmt->execute([$task_id, $relative_path, $name, $_FILES['completion_images']['size'][$key]]);
                            }
                        }
                    }
                }
            }
            
            // رفع الصوت
            if (isset($_FILES['completion_audio']) && $_FILES['completion_audio']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/tasks/' . $task_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($_FILES['completion_audio']['name'], PATHINFO_EXTENSION);
                $allowed = ['mp3', 'wav', 'ogg', 'm4a', 'webm'];
                if (in_array(strtolower($ext), $allowed)) {
                    $new_name = 'audio_' . uniqid() . '_' . time() . '.' . $ext;
                    $file_path = $upload_dir . $new_name;
                    if (move_uploaded_file($_FILES['completion_audio']['tmp_name'], $file_path)) {
                        $relative_path = 'uploads/tasks/' . $task_id . '/' . $new_name;
                        $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_type, file_path, file_name, file_size, attachment_type) VALUES (?, 'audio', ?, ?, ?, 'completion')");
                        $stmt->execute([$task_id, $relative_path, $_FILES['completion_audio']['name'], $_FILES['completion_audio']['size']]);
                    }
                }
            }
            
            $success = $lang['task_completed_success'];
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'] . (ini_get('display_errors') ? ': ' . $e->getMessage() : '');
    }
}

// معالجة عدم إكمال المهمة مع المرفقات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['not_complete_task'])) {
    try {
        $task_id = $_POST['task_id'];
        $notes = trim($_POST['non_completion_notes'] ?? '');
        
        // التحقق من أن المهمة تخص هذا العامل
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND worker_id = ? AND status = 'in_progress'");
        $stmt->execute([$task_id, $user_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            $error = $lang['task_not_found_or_cannot_update'];
        } else {
            // تحديث حالة المهمة - تغيير status إلى completed مع completion_status = 'not_completed'
            $stmt = $pdo->prepare("UPDATE tasks SET status = 'completed', completed_at = NOW(), non_completion_notes = ?, completion_status = 'not_completed' WHERE id = ?");
            $stmt->execute([$notes ?: null, $task_id]);
            
            // رفع الصور
            if (isset($_FILES['non_completion_images']) && !empty($_FILES['non_completion_images']['name'][0])) {
                $upload_dir = '../../uploads/tasks/' . $task_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['non_completion_images']['name'] as $key => $name) {
                    if ($_FILES['non_completion_images']['error'][$key] == UPLOAD_ERR_OK) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                        if (in_array(strtolower($ext), $allowed)) {
                            $new_name = uniqid() . '_' . time() . '.' . $ext;
                            $file_path = $upload_dir . $new_name;
                            if (move_uploaded_file($_FILES['non_completion_images']['tmp_name'][$key], $file_path)) {
                                $relative_path = 'uploads/tasks/' . $task_id . '/' . $new_name;
                                $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_type, file_path, file_name, file_size, attachment_type) VALUES (?, 'image', ?, ?, ?, 'non_completion')");
                                $stmt->execute([$task_id, $relative_path, $name, $_FILES['non_completion_images']['size'][$key]]);
                            }
                        }
                    }
                }
            }
            
            // رفع الصوت
            if (isset($_FILES['non_completion_audio']) && $_FILES['non_completion_audio']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/tasks/' . $task_id . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($_FILES['non_completion_audio']['name'], PATHINFO_EXTENSION);
                $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
                if (in_array(strtolower($ext), $allowed)) {
                    $new_name = 'audio_' . uniqid() . '_' . time() . '.' . $ext;
                    $file_path = $upload_dir . $new_name;
                    if (move_uploaded_file($_FILES['non_completion_audio']['tmp_name'], $file_path)) {
                        $relative_path = 'uploads/tasks/' . $task_id . '/' . $new_name;
                        $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_type, file_path, file_name, file_size, attachment_type) VALUES (?, 'audio', ?, ?, ?, 'non_completion')");
                        $stmt->execute([$task_id, $relative_path, $_FILES['non_completion_audio']['name'], $_FILES['non_completion_audio']['size']]);
                    }
                }
            }
            
            $success = $lang['task_non_completion_registered'];
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'] . (ini_get('display_errors') ? ': ' . $e->getMessage() : '');
    }
}

// جلب المهمة للعرض
$view_task = null;
if (isset($_GET['view']) && isset($_GET['id'])) {
    try {
        // جلب المهمة مع محاولة جلب الحقول المترجمة
        $query = "SELECT t.*, s.full_name as supervisor_name, s.phone as supervisor_phone";
        
        // محاولة إضافة الحقول المترجمة (إذا كانت موجودة في قاعدة البيانات)
        try {
            $test_stmt = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'title_en'");
            if ($test_stmt->rowCount() > 0) {
                $query = "SELECT t.*, 
                         COALESCE(t.title_en, '') as title_en,
                         COALESCE(t.description_en, '') as description_en,
                         COALESCE(t.location_en, '') as location_en, 
                         COALESCE(t.area_en, '') as area_en,
                         COALESCE(t.building_en, '') as building_en,
                         COALESCE(t.floor_en, '') as floor_en,
                         s.full_name as supervisor_name, s.phone as supervisor_phone";
            }
        } catch (PDOException $e) {
            // الحقول غير موجودة، استخدم الاستعلام البسيط
        }
        
        $query .= " FROM tasks t 
                   LEFT JOIN users s ON t.supervisor_id = s.id 
                   WHERE t.id = ? AND t.worker_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET['id'], $user_id]);
        $view_task_raw = $stmt->fetch();
        
        // ترجمة المهمة حسب اللغة المختارة
        $view_task = translate_task($view_task_raw, $_SESSION['lang'] ?? 'ar');
        if (!$view_task) {
            $error = $lang['task_not_found_or_no_permission'];
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// جلب الإحصائيات
try {
    // إجمالي المهام المعينة
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE worker_id = ?");
    $stmt->execute([$user_id]);
    $total_tasks = $stmt->fetch()['total'];
    
    // المهام المكتملة
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE worker_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completed_tasks = $stmt->fetch()['total'];
    
    // المهام المعلقة
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE worker_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_tasks = $stmt->fetch()['total'];
    
    // المهام قيد التنفيذ
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE worker_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_id]);
    $in_progress_tasks = $stmt->fetch()['total'];
    
    // التحقق من الحضور اليوم (آخر سجل حضور في اليوم)
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? ORDER BY check_in DESC LIMIT 1");
    $stmt->execute([$user_id, $today]);
    $today_attendance = $stmt->fetch();
    
    // حساب ساعات العمل الحالية إذا كان العامل لا يزال في الدوام
    $current_work_hours = 0;
    if ($today_attendance && $today_attendance['check_in'] && !$today_attendance['check_out']) {
        $check_in_time = strtotime($today_attendance['check_in']);
        $current_time = time();
        $current_work_hours = round(($current_time - $check_in_time) / 3600, 2);
    }
    
    // إجمالي ساعات العمل هذا الشهر
    $stmt = $pdo->prepare("
        SELECT SUM(total_hours) as total 
        FROM attendance 
        WHERE user_id = ? 
        AND MONTH(date) = MONTH(CURRENT_DATE()) 
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$user_id]);
    $monthly_hours = $stmt->fetch()['total'] ?: 0;
    
} catch (PDOException $e) {
    $error = $lang['system_error'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
<?php 
// تحديد حالة الدوام
$is_in_work = false;
if ($today_attendance && $today_attendance['check_in'] && empty($today_attendance['check_out'])) {
    $is_in_work = true;
}
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['dashboard']; ?> - <?php echo $lang['site_title']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Translate -->
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: '<?php echo $_SESSION['lang'] == 'ar' ? 'ar' : 'en'; ?>',
                includedLanguages: 'ar,en,fr,es,de,it,pt,ru,zh-CN,ja,ko,hi',
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                autoDisplay: false,
                multilanguagePage: true
            }, 'google_translate_element');
            
            // التأكد من ترجمة جميع العناصر بعد تحميل Google Translate
            setTimeout(function() {
                if (typeof google !== 'undefined' && google.translate) {
                    // إعادة تطبيق الترجمة على جميع العناصر
                    var translateInstance = google.translate.TranslateElement.getInstance();
                    if (translateInstance) {
                        // ترجمة جميع العناصر الديناميكية
                        translateAllElements();
                    }
                }
            }, 500);
        }
        
        // دالة لترجمة جميع العناصر في الصفحة
        function translateAllElements() {
            // Google Translate يترجم تلقائياً جميع العناصر
            // لكننا نتأكد من تطبيق الترجمة على العناصر الديناميكية
            var cards = document.querySelectorAll('.card, .card-body, .card-title, .task-card, .task-title, .task-description, .task-location, p, h1, h2, h3, h4, h5, h6, span, small, label, .badge');
            cards.forEach(function(element) {
                // إزالة أي class يمنع الترجمة
                element.classList.remove('notranslate');
                element.classList.remove('skip-translation');
                // التأكد من أن العنصر قابل للترجمة
                element.setAttribute('translate', 'yes');
            });
            
            // التأكد من ترجمة المهام بشكل خاص
            var taskTitles = document.querySelectorAll('.task-title, .task-description, .task-location');
            taskTitles.forEach(function(element) {
                element.classList.remove('notranslate');
                element.setAttribute('translate', 'yes');
            });
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    
    <style>
        /* تعطيل الصفحة عند عدم تسجيل الدخول */
        <?php if (!$is_in_work): ?>
        .disabled-content {
            pointer-events: none;
            opacity: 0.5;
            position: relative;
        }
        .disabled-content::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.3);
            z-index: 1;
            pointer-events: auto;
        }
        .check-in-buttons,
        .alert,
        .modal {
            pointer-events: auto !important;
            opacity: 1 !important;
            position: relative;
            z-index: 10;
        }
        /* منع الضغط على الأزرار والروابط */
        .disabled-content a:not(.check-in-buttons a),
        .disabled-content button:not(.check-in-buttons button):not(.btn-primary):not(.btn-danger):not(.btn-success),
        .disabled-content .btn:not(.check-in-buttons .btn):not(.btn-primary):not(.btn-danger):not(.btn-success),
        .btn.disabled,
        a.disabled {
            pointer-events: none;
            cursor: not-allowed;
            opacity: 0.5;
        }
        /* السماح للأزرار بالعمل حتى داخل disabled-content */
        .disabled-content button.btn-primary:not(.disabled),
        .disabled-content button.btn-danger:not(.disabled),
        .disabled-content button.btn-success:not(.disabled),
        button.btn-primary:not(.disabled),
        button.btn-danger:not(.disabled),
        button.btn-success:not(.disabled) {
            pointer-events: auto !important;
            opacity: 1 !important;
            position: relative;
            z-index: 20;
            cursor: pointer !important;
        }
        
        /* تحسين مظهر الأزرار المعطلة */
        .btn.disabled,
        a.disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        <?php endif; ?>
        
        /* تحسين مظهر النقطة */
        .status-dot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }
        .status-dot[style*="#dc3545"] {
            animation: pulse-red 2s infinite;
        }
        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }
        
        /* تنسيق Google Translate */
        #google_translate_element {
            display: inline-block;
            vertical-align: middle;
        }
        .goog-te-banner-frame {
            display: none !important;
        }
        .goog-te-menu-value {
            color: #fff !important;
        }
        .goog-te-menu-value span {
            color: #fff !important;
        }
        .goog-te-menu-frame {
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            border-radius: 4px;
            z-index: 9999;
        }
        .skiptranslate {
            display: none !important;
        }
        body {
            top: 0 !important;
            position: static !important;
        }
        .goog-te-banner-frame.skiptranslate {
            display: none !important;
        }
        /* تحسين مظهر قائمة الترجمة */
        .goog-te-combo {
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.3);
            background-color: rgba(255,255,255,0.1);
            color: #fff;
            font-size: 14px;
        }
        .goog-te-combo option {
            background-color: #fff;
            color: #000;
        }
        /* إخفاء iframe Google Translate */
        iframe.goog-te-banner-frame {
            display: none !important;
        }
        iframe.goog-te-menu-frame {
            z-index: 9999 !important;
        }
        /* التأكد من أن جميع النصوص قابلة للترجمة */
        /* Google Translate سيرجم تلقائياً جميع النصوص في الصفحة */
        .card, .card-body, .card-title, .card-text, p, span, h1, h2, h3, h4, h5, h6, small, label, .badge, button, a {
            /* جميع هذه العناصر قابلة للترجمة تلقائياً */
        }
        
        /* إزالة أي قيود على الترجمة */
        * {
            -webkit-translation: auto;
            translation: auto;
        }
        
        /* التأكد من أن المهام قابلة للترجمة */
        .card .card-title,
        .card .card-text,
        .card p,
        .card small,
        .card span,
        .task-card,
        .task-title,
        .task-description,
        .task-location {
            /* جميع النصوص في الكاردات قابلة للترجمة */
            translate: yes;
        }
        
        /* إزالة أي قيود على ترجمة المهام */
        .task-card * {
            translate: yes !important;
        }
        
        /* التأكد من أن Google Translate يترجم كل شيء */
        #tasks-container,
        #tasks-container * {
            translate: yes;
        }
    </style>
</head>
<body class="<?php echo (!$is_in_work) ? 'page-disabled' : ''; ?>">
    <div class="container-fluid">
        <!-- المحتوى الرئيسي المبسط -->
        <div class="main-content py-4">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo $lang['close_btn']; ?>"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo $lang['close_btn']; ?>"></button>
                    </div>
                <?php endif; ?>

                <!-- شريط الأدوات العلوي -->
                <div class="card mb-3 bg-primary text-white">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0">
                                <i class="fas fa-tasks me-2"></i>
                                <?php echo $lang['my_tasks']; ?>
                            </h5>
                            <div class="d-flex gap-2 flex-wrap align-items-center check-in-buttons">
                                <!-- مؤشر حالة الدوام -->
                                <div class="d-flex align-items-center gap-2 me-2">
                                    <span class="badge bg-<?php echo $is_in_work ? 'success' : 'danger'; ?> d-flex align-items-center gap-1 px-2 py-1">
                                        <span class="status-dot" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; background-color: <?php echo $is_in_work ? '#28a745' : '#dc3545'; ?>; box-shadow: 0 0 5px <?php echo $is_in_work ? '#28a745' : '#dc3545'; ?>;"></span>
                                        <strong><?php echo $is_in_work ? $lang['in_work'] : $lang['not_in_work']; ?></strong>
                                    </span>
                                </div>
                                
                                <?php 
                                // التحقق من وجود حضور نشط (بدون انصراف)
                                $has_active_attendance = $today_attendance && $today_attendance['check_in'] && empty($today_attendance['check_out']);
                                ?>
                                <!-- زر تبديل الحضور/الانصراف -->
                                <a href="index.php?toggle_attendance=1" 
                                   class="btn btn-<?php echo $has_active_attendance ? 'danger' : 'success'; ?> btn-sm">
                                    <?php if ($has_active_attendance): ?>
                                        <i class="fas fa-sign-out-alt me-1"></i>
                                        <?php echo $lang['end_work']; ?>
                                    <?php else: ?>
                                        <i class="fas fa-check-circle me-1"></i>
                                        <?php echo $lang['attend']; ?>
                                    <?php endif; ?>
                                </a>
                                <!-- Google Translate Widget -->
                                <div id="google_translate_element" class="d-inline-block me-2" style="vertical-align: middle;"></div>
                                
                                <button onclick="triggerGoogleTranslate()" class="btn btn-light btn-sm" title="<?php echo $lang['language']; ?>">
                                    <i class="fas fa-language"></i>
                                    <span class="d-none d-md-inline"><?php echo $lang['language']; ?></span>
                                </button>
                                
                                <a href="?lang=<?php echo $_SESSION['lang'] == 'ar' ? 'en' : 'ar'; ?>" 
                                   class="btn btn-light btn-sm">
                                    <i class="fas fa-exchange-alt"></i>
                                    <?php echo $_SESSION['lang'] == 'ar' ? $lang['english'] : $lang['arabic']; ?>
                                </a>
                                <a href="../../logout.php" class="btn btn-danger btn-sm">
                                    <i class="fas fa-sign-out-alt me-1"></i>
                                    <?php echo $lang['logout']; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- عرض تفاصيل المهمة -->
                <?php if ($view_task): ?>
                    <div class="card mb-4 <?php echo (!$is_in_work) ? 'disabled-content' : ''; ?>">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo $lang['task_details']; ?>
                            </h5>
                            <a href="index.php" class="btn btn-sm btn-light" title="<?php echo $lang['close_btn']; ?>">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><?php echo $lang['task_name']; ?></h6>
                                    <p><?php echo htmlspecialchars($view_task['title']); ?></p>
                                    
                                    <h6><?php echo $lang['description']; ?></h6>
                                    <p><?php echo htmlspecialchars($view_task['description'] ?: '-'); ?></p>
                                    
                                    <h6><?php echo $lang['location']; ?></h6>
                                    <p>
                                        <?php echo htmlspecialchars($view_task['location']); ?><br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($view_task['area']); ?> - 
                                            <?php echo htmlspecialchars($view_task['building']); ?> - 
                                            <?php echo htmlspecialchars($view_task['floor']); ?>
                                        </small>
                                    </p>
                                    
                                    <h6><?php echo $lang['supervisor']; ?></h6>
                                    <p><?php echo htmlspecialchars($view_task['supervisor_name'] ?: '-'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6><?php echo $lang['status']; ?></h6>
                                    <p>
                                        <span class="badge bg-<?php 
                                            echo $view_task['status'] == 'completed' ? 'success' : 
                                                 ($view_task['status'] == 'in_progress' ? 'warning' : 'secondary');
                                        ?>">
                                            <?php echo $lang[$view_task['status']]; ?>
                                        </span>
                                    </p>
                                    
                                    <h6><?php echo $lang['priority']; ?></h6>
                                    <p>
                                        <span class="badge bg-<?php 
                                            echo $view_task['priority'] == 'high' ? 'danger' : 
                                                 ($view_task['priority'] == 'medium' ? 'warning' : 'info');
                                        ?>">
                                            <?php echo $lang[$view_task['priority']]; ?>
                                        </span>
                                    </p>
                                    
                                    <h6><?php echo $lang['due_date']; ?></h6>
                                    <p><?php echo $view_task['due_date'] ? date('Y-m-d', strtotime($view_task['due_date'])) : '-'; ?></p>
                                    
                                    <?php if ($view_task['started_at']): ?>
                                        <h6><?php echo $lang['start_time']; ?></h6>
                                        <p><?php echo date('Y-m-d H:i', strtotime($view_task['started_at'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($view_task['completed_at']): ?>
                                        <h6><?php echo $lang['completion_time']; ?></h6>
                                        <p><?php echo date('Y-m-d H:i', strtotime($view_task['completed_at'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- أزرار الإجراءات -->
                            <div class="mt-3">
                                <?php if ($view_task['status'] == 'pending'): ?>
                                    <?php if (!$is_in_work): ?>
                                        <button type="button" class="btn btn-success disabled" onclick="alert('<?php echo $lang['please_check_in']; ?>'); return false;">
                                            <i class="fas fa-check me-1"></i>
                                            <?php echo $lang['accept']; ?>
                                        </button>
                                        <button type="button" class="btn btn-danger disabled" onclick="alert('<?php echo $lang['please_check_in']; ?>'); return false;">
                                            <i class="fas fa-times me-1"></i>
                                            <?php echo $lang['reject']; ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#acceptTaskModal<?php echo $view_task['id']; ?>">
                                            <i class="fas fa-check me-1"></i>
                                            <?php echo $lang['accept']; ?>
                                        </button>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectTaskModal<?php echo $view_task['id']; ?>">
                                            <i class="fas fa-times me-1"></i>
                                            <?php echo $lang['reject']; ?>
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($view_task['status'] == 'in_progress'): ?>
                                    <?php if (!$is_in_work): ?>
                                        <button type="button" class="btn btn-primary disabled" onclick="alert('<?php echo $lang['please_check_in']; ?>'); return false;">
                                            <i class="fas fa-check me-1"></i>
                                            <?php echo $lang['complete_task_btn']; ?>
                                        </button>
                                        <button type="button" class="btn btn-danger disabled" onclick="alert('<?php echo $lang['please_check_in']; ?>'); return false;">
                                            <i class="fas fa-times me-1"></i>
                                            <?php echo $lang['not_complete_task']; ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#completeTaskModal<?php echo $view_task['id']; ?>">
                                            <i class="fas fa-check me-1"></i>
                                            <?php echo $lang['complete_task_btn']; ?>
                                        </button>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#notCompleteTaskModal<?php echo $view_task['id']; ?>">
                                            <i class="fas fa-times me-1"></i>
                                            <?php echo $lang['not_complete_task']; ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- المهام -->
                <div class="card shadow-sm mb-4 <?php echo (!$is_in_work) ? 'disabled-content' : ''; ?>">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>
                            <?php echo $lang['my_tasks']; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!$is_in_work): ?>
                            <div class="alert alert-warning text-center mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?php echo $lang['please_check_in']; ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php
                        try {
                            // جلب المهام مع محاولة جلب الحقول المترجمة
                            $query = "SELECT t.*, s.full_name as supervisor_name";
                            
                            // محاولة إضافة الحقول المترجمة (إذا كانت موجودة في قاعدة البيانات)
                            try {
                                $test_stmt = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'title_en'");
                                if ($test_stmt->rowCount() > 0) {
                                    $query = "SELECT t.*, 
                                             COALESCE(t.title_en, '') as title_en,
                                             COALESCE(t.description_en, '') as description_en,
                                             COALESCE(t.location_en, '') as location_en, 
                                             COALESCE(t.area_en, '') as area_en,
                                             COALESCE(t.building_en, '') as building_en,
                                             COALESCE(t.floor_en, '') as floor_en,
                                             s.full_name as supervisor_name";
                                }
                            } catch (PDOException $e) {
                                // الحقول غير موجودة، استخدم الاستعلام البسيط
                            }
                            
                            $query .= " FROM tasks t 
                                       LEFT JOIN users s ON t.supervisor_id = s.id 
                                       WHERE t.worker_id = ? 
                                       AND (t.completion_status IS NULL OR t.completion_status != 'not_completed')
                                       ORDER BY 
                                       CASE t.status 
                                           WHEN 'pending' THEN 1 
                                           WHEN 'in_progress' THEN 2 
                                           WHEN 'completed' THEN 3 
                                           ELSE 4 
                                       END,
                                       t.priority DESC,
                                       t.assigned_at DESC";
                            
                            $stmt = $pdo->prepare($query);
                            $stmt->execute([$user_id]);
                            $tasks_raw = $stmt->fetchAll();
                            
                            // ترجمة المهام حسب اللغة المختارة
                            $tasks = translate_tasks($tasks_raw, $_SESSION['lang'] ?? 'ar');
                            
                            if ($tasks && count($tasks) > 0):
                        ?>
                        <div class="row g-3" id="tasks-container">
                            <?php foreach ($tasks as $task): ?>
                            <div class="col-md-6 col-lg-4 task-card">
                                <div class="card border h-100 shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0 fw-bold task-title"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <span class="badge bg-<?php 
                                                echo $task['status'] == 'completed' ? 'success' : 
                                                     ($task['status'] == 'in_progress' ? 'warning' : 'secondary');
                                            ?>">
                                                <?php echo $lang[$task['status']]; ?>
                                            </span>
                                        </div>
                                        <?php if ($task['description']): ?>
                                            <p class="text-muted small mb-2 task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 60)); ?><?php echo strlen($task['description']) > 60 ? '...' : ''; ?></p>
                                        <?php endif; ?>
                                        <small class="text-muted d-block mb-2 task-location">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($task['location']); ?>
                                        </small>
                                        <div class="d-grid gap-2">
                                            <?php if ($task['status'] == 'pending'): ?>
                                                <div class="btn-group w-100" role="group">
                                                    <?php if (!$is_in_work): ?>
                                                        <button type="button" class="btn btn-success btn-sm disabled" onclick="alert('<?php echo $lang['please_check_in']; ?>'); return false;">
                                                            <i class="fas fa-check me-1"></i>
                                                            <?php echo $lang['accept']; ?>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm disabled" onclick="alert('<?php echo $lang['please_check_in']; ?>'); return false;">
                                                            <i class="fas fa-times me-1"></i>
                                                            <?php echo $lang['reject']; ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#acceptTaskModal<?php echo $task['id']; ?>">
                                                            <i class="fas fa-check me-1"></i>
                                                            <?php echo $lang['accept']; ?>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectTaskModal<?php echo $task['id']; ?>">
                                                            <i class="fas fa-times me-1"></i>
                                                            <?php echo $lang['reject']; ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="index.php?view=1&id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-outline-info btn-sm w-100 mt-2 <?php echo (!$is_in_work) ? 'disabled' : ''; ?>"
                                                   onclick="<?php echo (!$is_in_work) ? 'alert(\'' . $lang['please_check_in'] . '\'); return false;' : ''; ?>">
                                                    <i class="fas fa-eye me-1"></i>
                                                    <?php echo $lang['view_details']; ?>
                                                </a>
                                            <?php elseif ($task['status'] == 'in_progress'): ?>
                                                <div class="btn-group" role="group">
                                                    <?php if (!$is_in_work): ?>
                                                        <button type="button" class="btn btn-success btn-sm disabled" onclick="alert('<?php echo $lang['please_check_in']; ?>'); return false;">
                                                            <i class="fas fa-check me-1"></i>
                                                            <?php echo $lang['complete_task']; ?>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm disabled" onclick="alert('<?php echo $lang['please_check_in']; ?>'); return false;">
                                                            <i class="fas fa-times me-1"></i>
                                                            <?php echo $lang['reject']; ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#completeTaskModal<?php echo $task['id']; ?>">
                                                            <i class="fas fa-check me-1"></i>
                                                            <?php echo $lang['complete_task']; ?>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#notCompleteTaskModal<?php echo $task['id']; ?>">
                                                            <i class="fas fa-times me-1"></i>
                                                            <?php echo $lang['reject']; ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="index.php?view=1&id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-outline-info btn-sm <?php echo (!$is_in_work) ? 'disabled' : ''; ?>"
                                                   onclick="<?php echo (!$is_in_work) ? 'alert(\'' . $lang['please_check_in'] . '\'); return false;' : ''; ?>">
                                                    <i class="fas fa-eye me-1"></i>
                                                    <?php echo $lang['view_details']; ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="index.php?view=1&id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-outline-info btn-sm <?php echo (!$is_in_work) ? 'disabled' : ''; ?>"
                                                   onclick="<?php echo (!$is_in_work) ? 'alert(\'' . $lang['please_check_in'] . '\'); return false;' : ''; ?>">
                                                    <i class="fas fa-eye me-1"></i>
                                                    <?php echo $lang['view_details']; ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo $lang['no_data']; ?>
                        </div>
                        <?php
                            endif;
                        } catch (PDOException $e) {
                            echo '<div class="alert alert-danger">' . $lang['system_error'] . '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نماذج قبول/رفض المهام -->
    <?php if (isset($tasks) && count($tasks) > 0): ?>
        <?php foreach ($tasks as $task): ?>
            <?php if ($task['status'] == 'pending'): ?>
                <!-- نموذج قبول المهمة -->
                <div class="modal fade" id="acceptTaskModal<?php echo $task['id']; ?>" tabindex="-1" aria-labelledby="acceptTaskModalLabel<?php echo $task['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form method="POST" action="index.php" enctype="multipart/form-data">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title" id="acceptTaskModalLabel<?php echo $task['id']; ?>">
                                        <i class="fas fa-check-circle me-2"></i>
                                        قبول المهمة: <?php echo htmlspecialchars($task['title']); ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">ملاحظات (اختياري)</label>
                                        <textarea class="form-control" name="accept_notes" rows="3" placeholder="أضف ملاحظات حول قبول المهمة..."></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">رفع صور (اختياري - يمكن رفع أكثر من صورة)</label>
                                        <input type="file" class="form-control" name="accept_images[]" accept="image/*" multiple>
                                        <small class="text-muted">يمكنك اختيار أكثر من صورة</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">تسجيل صوتي (اختياري)</label>
                                        <div class="d-flex gap-2 mb-2">
                                            <button type="button" class="btn btn-outline-primary" id="startRecordingAccept<?php echo $task['id']; ?>">
                                                <i class="fas fa-microphone me-1"></i>
                                                <?php echo $lang['start_recording']; ?>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger d-none" id="stopRecordingAccept<?php echo $task['id']; ?>">
                                                <i class="fas fa-stop me-1"></i>
                                                <?php echo $lang['stop_recording']; ?>
                                            </button>
                                        </div>
                                        <audio id="audioPlaybackAccept<?php echo $task['id']; ?>" controls class="d-none w-100 mb-2"></audio>
                                        <input type="file" class="form-control" name="accept_audio" accept="audio/*,.webm" id="audioFileAccept<?php echo $task['id']; ?>">
                                        <small class="text-muted">أو قم برفع ملف صوتي</small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang['cancel']; ?></button>
                                    <button type="submit" name="accept_task" class="btn btn-success">
                                        <i class="fas fa-check me-1"></i>
                                        قبول المهمة
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- نموذج رفض المهمة -->
                <div class="modal fade" id="rejectTaskModal<?php echo $task['id']; ?>" tabindex="-1" aria-labelledby="rejectTaskModalLabel<?php echo $task['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form method="POST" action="index.php" enctype="multipart/form-data">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="rejectTaskModalLabel<?php echo $task['id']; ?>">
                                        <i class="fas fa-times-circle me-2"></i>
                                        رفض المهمة: <?php echo htmlspecialchars($task['title']); ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">ملاحظات الرفض (اختياري)</label>
                                        <textarea class="form-control" name="reject_notes" rows="3" placeholder="أضف ملاحظات حول سبب رفض المهمة..."></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">رفع صور (اختياري - يمكن رفع أكثر من صورة)</label>
                                        <input type="file" class="form-control" name="reject_images[]" accept="image/*" multiple>
                                        <small class="text-muted">يمكنك اختيار أكثر من صورة</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">تسجيل صوتي (اختياري)</label>
                                        <div class="d-flex gap-2 mb-2">
                                            <button type="button" class="btn btn-outline-primary" id="startRecordingReject<?php echo $task['id']; ?>">
                                                <i class="fas fa-microphone me-1"></i>
                                                <?php echo $lang['start_recording']; ?>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger d-none" id="stopRecordingReject<?php echo $task['id']; ?>">
                                                <i class="fas fa-stop me-1"></i>
                                                <?php echo $lang['stop_recording']; ?>
                                            </button>
                                        </div>
                                        <audio id="audioPlaybackReject<?php echo $task['id']; ?>" controls class="d-none w-100 mb-2"></audio>
                                        <input type="file" class="form-control" name="reject_audio" accept="audio/*,.webm" id="audioFileReject<?php echo $task['id']; ?>">
                                        <small class="text-muted">أو قم برفع ملف صوتي</small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang['cancel']; ?></button>
                                    <button type="submit" name="reject_task" class="btn btn-danger">
                                        <i class="fas fa-times me-1"></i>
                                        رفض المهمة
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if ($view_task && $view_task['status'] == 'pending'): ?>
        <!-- نماذج قبول/رفض المهمة (عند عرض التفاصيل) -->
        <!-- نموذج قبول المهمة -->
        <div class="modal fade" id="acceptTaskModal<?php echo $view_task['id']; ?>" tabindex="-1" aria-labelledby="acceptTaskModalLabel<?php echo $view_task['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="task_id" value="<?php echo $view_task['id']; ?>">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="acceptTaskModalLabel<?php echo $view_task['id']; ?>">
                                <i class="fas fa-check-circle me-2"></i>
                                قبول المهمة: <?php echo htmlspecialchars($view_task['title']); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">ملاحظات (اختياري)</label>
                                <textarea class="form-control" name="accept_notes" rows="3" placeholder="أضف ملاحظات حول قبول المهمة..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">رفع صور (اختياري - يمكن رفع أكثر من صورة)</label>
                                <input type="file" class="form-control" name="accept_images[]" accept="image/*" multiple>
                                <small class="text-muted">يمكنك اختيار أكثر من صورة</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">تسجيل صوتي (اختياري)</label>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-primary" id="startRecordingAccept<?php echo $view_task['id']; ?>">
                                        <i class="fas fa-microphone me-1"></i>
                                        <?php echo $lang['start_recording']; ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger d-none" id="stopRecordingAccept<?php echo $view_task['id']; ?>">
                                        <i class="fas fa-stop me-1"></i>
                                        <?php echo $lang['stop_recording']; ?>
                                    </button>
                                </div>
                                <audio id="audioPlaybackAccept<?php echo $view_task['id']; ?>" controls class="d-none w-100 mb-2"></audio>
                                <input type="file" class="form-control" name="accept_audio" accept="audio/*,.webm" id="audioFileAccept<?php echo $view_task['id']; ?>">
                                <small class="text-muted">أو قم برفع ملف صوتي</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang['cancel']; ?></button>
                            <button type="submit" name="accept_task" class="btn btn-success">
                                <i class="fas fa-check me-1"></i>
                                قبول المهمة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- نموذج رفض المهمة -->
        <div class="modal fade" id="rejectTaskModal<?php echo $view_task['id']; ?>" tabindex="-1" aria-labelledby="rejectTaskModalLabel<?php echo $view_task['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="task_id" value="<?php echo $view_task['id']; ?>">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="rejectTaskModalLabel<?php echo $view_task['id']; ?>">
                                <i class="fas fa-times-circle me-2"></i>
                                رفض المهمة: <?php echo htmlspecialchars($view_task['title']); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">ملاحظات الرفض (اختياري)</label>
                                <textarea class="form-control" name="reject_notes" rows="3" placeholder="أضف ملاحظات حول سبب رفض المهمة..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">رفع صور (اختياري - يمكن رفع أكثر من صورة)</label>
                                <input type="file" class="form-control" name="reject_images[]" accept="image/*" multiple>
                                <small class="text-muted">يمكنك اختيار أكثر من صورة</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">تسجيل صوتي (اختياري)</label>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-primary" id="startRecordingReject<?php echo $view_task['id']; ?>">
                                        <i class="fas fa-microphone me-1"></i>
                                        <?php echo $lang['start_recording']; ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger d-none" id="stopRecordingReject<?php echo $view_task['id']; ?>">
                                        <i class="fas fa-stop me-1"></i>
                                        <?php echo $lang['stop_recording']; ?>
                                    </button>
                                </div>
                                <audio id="audioPlaybackReject<?php echo $view_task['id']; ?>" controls class="d-none w-100 mb-2"></audio>
                                <input type="file" class="form-control" name="reject_audio" accept="audio/*,.webm" id="audioFileReject<?php echo $view_task['id']; ?>">
                                <small class="text-muted">أو قم برفع ملف صوتي</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang['cancel']; ?></button>
                            <button type="submit" name="reject_task" class="btn btn-danger">
                                <i class="fas fa-times me-1"></i>
                                رفض المهمة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- نماذج إكمال/عدم إكمال المهام -->
    <?php if (isset($tasks) && count($tasks) > 0): ?>
        <?php foreach ($tasks as $task): ?>
            <?php if ($task['status'] == 'in_progress'): ?>
                <!-- نموذج إكمال المهمة -->
                <div class="modal fade" id="completeTaskModal<?php echo $task['id']; ?>" tabindex="-1" aria-labelledby="completeTaskModalLabel<?php echo $task['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form method="POST" action="index.php" enctype="multipart/form-data">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title" id="completeTaskModalLabel<?php echo $task['id']; ?>">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?php echo $lang['complete_task_btn']; ?>: <?php echo htmlspecialchars($task['title']); ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?php echo $lang['close_btn']; ?>"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $lang['completion_notes_optional']; ?></label>
                                        <textarea class="form-control" name="completion_notes" rows="3" placeholder="<?php echo $lang['completion_notes_placeholder']; ?>"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $lang['upload_images_optional']; ?></label>
                                        <input type="file" class="form-control" name="completion_images[]" accept="image/*" multiple>
                                        <small class="text-muted"><?php echo $lang['select_multiple_images']; ?></small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $lang['audio_recording_optional']; ?></label>
                                        <div class="d-flex gap-2 mb-2">
                                            <button type="button" class="btn btn-outline-primary" id="startRecording<?php echo $task['id']; ?>">
                                                <i class="fas fa-microphone me-1"></i>
                                                <?php echo $lang['start_recording']; ?>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger d-none" id="stopRecording<?php echo $task['id']; ?>">
                                                <i class="fas fa-stop me-1"></i>
                                                <?php echo $lang['stop_recording']; ?>
                                            </button>
                                        </div>
                                        <audio id="audioPlayback<?php echo $task['id']; ?>" controls class="d-none w-100 mb-2"></audio>
                                        <input type="file" class="form-control" name="completion_audio" accept="audio/*" id="audioFile<?php echo $task['id']; ?>">
                                        <small class="text-muted"><?php echo $lang['upload_audio_file']; ?></small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang['cancel']; ?></button>
                                    <button type="submit" name="complete_task" class="btn btn-primary">
                                        <i class="fas fa-check me-1"></i>
                                        <?php echo $lang['complete_task_btn']; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- نموذج عدم إكمال المهمة -->
                <div class="modal fade" id="notCompleteTaskModal<?php echo $task['id']; ?>" tabindex="-1" aria-labelledby="notCompleteTaskModalLabel<?php echo $task['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form method="POST" action="index.php" enctype="multipart/form-data">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="notCompleteTaskModalLabel<?php echo $task['id']; ?>">
                                        <i class="fas fa-times-circle me-2"></i>
                                        <?php echo $lang['not_complete_task']; ?>: <?php echo htmlspecialchars($task['title']); ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?php echo $lang['close_btn']; ?>"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $lang['completion_notes_optional']; ?></label>
                                        <textarea class="form-control" name="non_completion_notes" rows="3" placeholder="<?php echo $lang['not_completion_notes_placeholder']; ?>"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $lang['upload_images_optional']; ?></label>
                                        <input type="file" class="form-control" name="non_completion_images[]" accept="image/*" multiple>
                                        <small class="text-muted"><?php echo $lang['select_multiple_images']; ?></small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $lang['audio_recording_optional']; ?></label>
                                        <div class="d-flex gap-2 mb-2">
                                            <button type="button" class="btn btn-outline-primary" id="startRecordingNC<?php echo $task['id']; ?>">
                                                <i class="fas fa-microphone me-1"></i>
                                                <?php echo $lang['start_recording']; ?>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger d-none" id="stopRecordingNC<?php echo $task['id']; ?>">
                                                <i class="fas fa-stop me-1"></i>
                                                <?php echo $lang['stop_recording']; ?>
                                            </button>
                                        </div>
                                        <audio id="audioPlaybackNC<?php echo $task['id']; ?>" controls class="d-none w-100 mb-2"></audio>
                                        <input type="file" class="form-control" name="non_completion_audio" accept="audio/*" id="audioFileNC<?php echo $task['id']; ?>">
                                        <small class="text-muted"><?php echo $lang['upload_audio_file']; ?></small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang['cancel']; ?></button>
                                    <button type="submit" name="not_complete_task" class="btn btn-danger">
                                        <i class="fas fa-times me-1"></i>
                                        <?php echo $lang['register_not_completion']; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if ($view_task && $view_task['status'] == 'in_progress'): ?>
        <!-- نماذج إكمال/عدم إكمال المهمة (عند عرض التفاصيل) -->
        <!-- نموذج إكمال المهمة -->
        <div class="modal fade" id="completeTaskModal<?php echo $view_task['id']; ?>" tabindex="-1" aria-labelledby="completeTaskModalLabel<?php echo $view_task['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="task_id" value="<?php echo $view_task['id']; ?>">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="completeTaskModalLabel<?php echo $view_task['id']; ?>">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $lang['complete_task_btn']; ?>: <span class="notranslate"><?php echo htmlspecialchars($view_task['title']); ?></span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $lang['completion_notes_optional']; ?></label>
                                <textarea class="form-control" name="completion_notes" rows="3" placeholder="<?php echo $lang['completion_notes_placeholder']; ?>"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php echo $lang['upload_images_optional']; ?></label>
                                <input type="file" class="form-control" name="completion_images[]" accept="image/*" multiple>
                                <small class="text-muted"><?php echo $lang['select_multiple_images']; ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php echo $lang['audio_recording_optional']; ?></label>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-primary" id="startRecording<?php echo $view_task['id']; ?>">
                                        <i class="fas fa-microphone me-1"></i>
                                        <?php echo $lang['start_recording']; ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger d-none" id="stopRecording<?php echo $view_task['id']; ?>">
                                        <i class="fas fa-stop me-1"></i>
                                        <?php echo $lang['stop_recording']; ?>
                                    </button>
                                </div>
                                <audio id="audioPlayback<?php echo $view_task['id']; ?>" controls class="d-none w-100 mb-2"></audio>
                                <input type="file" class="form-control" name="completion_audio" accept="audio/*" id="audioFile<?php echo $view_task['id']; ?>">
                                <small class="text-muted"><?php echo $lang['upload_audio_file']; ?></small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang['cancel']; ?></button>
                            <button type="submit" name="complete_task" class="btn btn-primary">
                                <i class="fas fa-check me-1"></i>
                                <?php echo $lang['complete_task_btn']; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- نموذج عدم إكمال المهمة -->
        <div class="modal fade" id="notCompleteTaskModal<?php echo $view_task['id']; ?>" tabindex="-1" aria-labelledby="notCompleteTaskModalLabel<?php echo $view_task['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="task_id" value="<?php echo $view_task['id']; ?>">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="notCompleteTaskModalLabel<?php echo $view_task['id']; ?>">
                                <i class="fas fa-times-circle me-2"></i>
                                <?php echo $lang['not_complete_task']; ?>: <span class="notranslate"><?php echo htmlspecialchars($view_task['title']); ?></span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $lang['completion_notes_optional']; ?></label>
                                <textarea class="form-control" name="non_completion_notes" rows="3" placeholder="<?php echo $lang['not_completion_notes_placeholder']; ?>"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php echo $lang['upload_images_optional']; ?></label>
                                <input type="file" class="form-control" name="non_completion_images[]" accept="image/*" multiple>
                                <small class="text-muted"><?php echo $lang['select_multiple_images']; ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php echo $lang['audio_recording_optional']; ?></label>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-primary" id="startRecordingNC<?php echo $view_task['id']; ?>">
                                        <i class="fas fa-microphone me-1"></i>
                                        <?php echo $lang['start_recording']; ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger d-none" id="stopRecordingNC<?php echo $view_task['id']; ?>">
                                        <i class="fas fa-stop me-1"></i>
                                        <?php echo $lang['stop_recording']; ?>
                                    </button>
                                </div>
                                <audio id="audioPlaybackNC<?php echo $view_task['id']; ?>" controls class="d-none w-100 mb-2"></audio>
                                <input type="file" class="form-control" name="non_completion_audio" accept="audio/*" id="audioFileNC<?php echo $view_task['id']; ?>">
                                <small class="text-muted"><?php echo $lang['upload_audio_file']; ?></small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang['cancel']; ?></button>
                            <button type="submit" name="not_complete_task" class="btn btn-danger">
                                <i class="fas fa-times me-1"></i>
                                <?php echo $lang['register_not_completion']; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script>
        // Google Translate Enhancement
        function triggerGoogleTranslate() {
            if (typeof google !== 'undefined' && google.translate) {
                var select = document.querySelector('.goog-te-combo');
                if (select) {
                    var currentLang = '<?php echo $_SESSION['lang'] == 'ar' ? 'ar' : 'en'; ?>';
                    var targetLang = currentLang === 'ar' ? 'en' : 'ar';
                    select.value = targetLang;
                    select.dispatchEvent(new Event('change'));
                } else {
                    // إذا لم يكن الـ widget جاهزاً، انتظر قليلاً
                    setTimeout(triggerGoogleTranslate, 500);
                }
            } else {
                // إذا لم يكن Google Translate محملاً، انتظر
                setTimeout(triggerGoogleTranslate, 500);
            }
        }
        
        // إخفاء شريط Google Translate العلوي وتحسين المظهر
        window.addEventListener('load', function() {
            setTimeout(function() {
                // إخفاء البانر العلوي
                var banner = document.querySelector('.goog-te-banner-frame');
                if (banner) {
                    banner.style.display = 'none';
                }
                
                // إزالة تأثير top من body
                var body = document.querySelector('body');
                if (body) {
                    body.style.top = '0';
                    body.style.position = 'static';
                }
                
                // تحسين مظهر قائمة الترجمة
                var select = document.querySelector('.goog-te-combo');
                if (select) {
                    select.style.cssText = 'padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.3); background-color: rgba(255,255,255,0.1); color: #fff; font-size: 14px;';
                }
                
                // إخفاء iframe البانر
                var iframes = document.querySelectorAll('iframe.goog-te-banner-frame');
                iframes.forEach(function(iframe) {
                    iframe.style.display = 'none';
                });
            }, 500);
        });
        
        // إعادة تطبيق الإخفاء عند تغيير اللغة
        var observer = new MutationObserver(function(mutations) {
            var banner = document.querySelector('.goog-te-banner-frame');
            if (banner && banner.style.display !== 'none') {
                banner.style.display = 'none';
            }
            
            // التأكد من ترجمة العناصر الجديدة
            if (typeof google !== 'undefined' && google.translate) {
                // إعادة ترجمة الصفحة للعناصر الجديدة
                var translateInstance = google.translate.TranslateElement.getInstance();
                if (translateInstance) {
                    // إعادة تطبيق الترجمة على العناصر الجديدة
                    setTimeout(function() {
                        var select = document.querySelector('.goog-te-combo');
                        if (select && select.value) {
                            // الصفحة ستترجم تلقائياً
                        }
                    }, 100);
                }
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // التأكد من ترجمة جميع العناصر بعد تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                // التأكد من أن Google Translate جاهز
                if (typeof google !== 'undefined' && google.translate) {
                    // جميع العناصر ستترجم تلقائياً
                    translateAllElements();
                    
                    // إعادة تطبيق الترجمة عند إضافة عناصر جديدة (مثل المهام)
                    var taskObserver = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.addedNodes.length > 0) {
                                setTimeout(function() {
                                    translateAllElements();
                                }, 100);
                            }
                        });
                    });
                    
                    // مراقبة إضافة عناصر جديدة في منطقة المهام
                    var tasksContainer = document.querySelector('#tasks-container');
                    if (!tasksContainer) {
                        tasksContainer = document.querySelector('.card-body .row');
                    }
                    if (tasksContainer) {
                        taskObserver.observe(tasksContainer, {
                            childList: true,
                            subtree: true
                        });
                    }
                    
                    // مراقبة جميع التغييرات في الصفحة
                    var bodyObserver = new MutationObserver(function(mutations) {
                        setTimeout(function() {
                            translateAllElements();
                        }, 200);
                    });
                    
                    bodyObserver.observe(document.body, {
                        childList: true,
                        subtree: true,
                        attributes: false
                    });
                }
            }, 1000);
        });
        
        // إعادة تطبيق الترجمة عند تغيير اللغة من Google Translate
        window.addEventListener('load', function() {
            setTimeout(function() {
                var select = document.querySelector('.goog-te-combo');
                if (select) {
                    select.addEventListener('change', function() {
                        setTimeout(function() {
                            translateAllElements();
                        }, 500);
                    });
                }
            }, 1500);
        });
        
        // تسجيل الصوت
        let mediaRecorder;
        let audioChunks = [];
        
        function setupAudioRecording(taskId, isNonCompletion = false) {
            const prefix = isNonCompletion ? 'NC' : '';
            const startBtn = document.getElementById('startRecording' + prefix + taskId);
            const stopBtn = document.getElementById('stopRecording' + prefix + taskId);
            const audioPlayback = document.getElementById('audioPlayback' + prefix + taskId);
            const audioFile = document.getElementById('audioFile' + prefix + taskId);
            
            if (!startBtn) return;
            
            startBtn.addEventListener('click', async () => {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorder = new MediaRecorder(stream);
                    audioChunks = [];
                    
                    mediaRecorder.ondataavailable = (event) => {
                        audioChunks.push(event.data);
                    };
                    
                    mediaRecorder.onstop = () => {
                        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                        const audioUrl = URL.createObjectURL(audioBlob);
                        audioPlayback.src = audioUrl;
                        audioPlayback.classList.remove('d-none');
                        
                        // إنشاء ملف صوتي مع امتداد صحيح واسم فريد
                        const timestamp = new Date().getTime();
                        const audioFileObj = new File([audioBlob], 'recording_' + timestamp + '.webm', { 
                            type: 'audio/webm',
                            lastModified: Date.now()
                        });
                        
                        // إضافة الملف إلى input باستخدام DataTransfer
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(audioFileObj);
                        audioFile.files = dataTransfer.files;
                        
                        // التأكد من أن الملف تم إضافته
                        console.log('Audio file added:', audioFile.files.length, 'files');
                        if (audioFile.files.length > 0) {
                            console.log('File name:', audioFile.files[0].name);
                            console.log('File size:', audioFile.files[0].size, 'bytes');
                        }
                        
                        stream.getTracks().forEach(track => track.stop());
                    };
                    
                    mediaRecorder.start();
                    startBtn.classList.add('d-none');
                    stopBtn.classList.remove('d-none');
                } catch (error) {
                    console.error('Microphone access error:', error);
                    alert('<?php echo $lang['microphone_access_error']; ?>');
                }
            });
            
            if (stopBtn) {
                stopBtn.addEventListener('click', () => {
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        mediaRecorder.stop();
                        startBtn.classList.remove('d-none');
                        stopBtn.classList.add('d-none');
                    }
                });
            }
        }
        
        // دالة لتسجيل الصوت في modals القبول والرفض
        function setupAcceptRejectAudioRecording(taskId, type) {
            const prefix = type === 'accept' ? 'Accept' : 'Reject';
            const startBtn = document.getElementById('startRecording' + prefix + taskId);
            const stopBtn = document.getElementById('stopRecording' + prefix + taskId);
            const audioPlayback = document.getElementById('audioPlayback' + prefix + taskId);
            const audioFile = document.getElementById('audioFile' + prefix + taskId);
            
            if (!startBtn) return;
            
            startBtn.addEventListener('click', async () => {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorder = new MediaRecorder(stream);
                    audioChunks = [];
                    
                    mediaRecorder.ondataavailable = (event) => {
                        audioChunks.push(event.data);
                    };
                    
                    mediaRecorder.onstop = () => {
                        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                        const audioUrl = URL.createObjectURL(audioBlob);
                        audioPlayback.src = audioUrl;
                        audioPlayback.classList.remove('d-none');
                        
                        // إنشاء ملف صوتي مع امتداد صحيح واسم فريد
                        const timestamp = new Date().getTime();
                        const audioFileObj = new File([audioBlob], 'recording_' + timestamp + '.webm', { 
                            type: 'audio/webm',
                            lastModified: Date.now()
                        });
                        
                        // إضافة الملف إلى input باستخدام DataTransfer
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(audioFileObj);
                        audioFile.files = dataTransfer.files;
                        
                        // التأكد من أن الملف تم إضافته
                        console.log('Audio file added:', audioFile.files.length, 'files');
                        if (audioFile.files.length > 0) {
                            console.log('File name:', audioFile.files[0].name);
                            console.log('File size:', audioFile.files[0].size, 'bytes');
                        }
                        
                        stream.getTracks().forEach(track => track.stop());
                    };
                    
                    mediaRecorder.start();
                    startBtn.classList.add('d-none');
                    stopBtn.classList.remove('d-none');
                } catch (error) {
                    console.error('Microphone access error:', error);
                    alert('<?php echo $lang['microphone_access_error'] ?? 'خطأ في الوصول إلى الميكروفون'; ?>');
                }
            });
            
            if (stopBtn) {
                stopBtn.addEventListener('click', () => {
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        mediaRecorder.stop();
                        startBtn.classList.remove('d-none');
                        stopBtn.classList.add('d-none');
                    }
                });
            }
        }
        
        // إعداد التسجيل الصوتي لجميع المهام
        <?php if (isset($tasks) && count($tasks) > 0): ?>
            <?php foreach ($tasks as $task): ?>
                <?php if ($task['status'] == 'pending'): ?>
                    setupAcceptRejectAudioRecording(<?php echo $task['id']; ?>, 'accept');
                    setupAcceptRejectAudioRecording(<?php echo $task['id']; ?>, 'reject');
                <?php elseif ($task['status'] == 'in_progress'): ?>
                    setupAudioRecording(<?php echo $task['id']; ?>, false);
                    setupAudioRecording(<?php echo $task['id']; ?>, true);
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if ($view_task): ?>
            <?php if ($view_task['status'] == 'pending'): ?>
                setupAcceptRejectAudioRecording(<?php echo $view_task['id']; ?>, 'accept');
                setupAcceptRejectAudioRecording(<?php echo $view_task['id']; ?>, 'reject');
            <?php elseif ($view_task['status'] == 'in_progress'): ?>
                setupAudioRecording(<?php echo $view_task['id']; ?>, false);
                setupAudioRecording(<?php echo $view_task['id']; ?>, true);
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>

