<?php
// dashboard/supervisor/tasks.php - إدارة المهام للمشرف
require_once '../../config.php';
require_once '../../includes/lang.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'supervisor'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// معالجة إنشاء/تحديث المهمة
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $area = trim($_POST['area']);
    $building = trim($_POST['building']);
    $floor = trim($_POST['floor']);
    $worker_id = $_POST['worker_id'] ?: null;
    $status = $_POST['status'];
    $priority = $_POST['priority'];
    $due_date = $_POST['due_date'] ?: null;
    
    try {
        if ($action == 'create') {
            $stmt = $pdo->prepare("INSERT INTO tasks (title, description, location, area, building, floor, supervisor_id, worker_id, status, priority, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description ?: null, $location, $area, $building, $floor, $user_id, $worker_id, $status, $priority, $due_date]);
            $success = $lang['task_created'];
        } elseif ($action == 'update' && $id) {
            // التحقق من أن المهمة تخص هذا المشرف
            $stmt = $pdo->prepare("SELECT supervisor_id FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();
            
            if ($task && $task['supervisor_id'] == $user_id) {
                $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, location = ?, area = ?, building = ?, floor = ?, worker_id = ?, status = ?, priority = ?, due_date = ? WHERE id = ? AND supervisor_id = ?");
                $stmt->execute([$title, $description ?: null, $location, $area, $building, $floor, $worker_id, $status, $priority, $due_date, $id, $user_id]);
                $success = $lang['task_updated'];
            } else {
                $error = 'ليس لديك صلاحية لتعديل هذه المهمة';
            }
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'] . (ini_get('display_errors') ? ': ' . $e->getMessage() : '');
    }
}

// معالجة تغيير حالة المهمة
if (isset($_GET['change_status']) && isset($_GET['id']) && isset($_GET['status'])) {
    try {
        $id = $_GET['id'];
        $new_status = $_GET['status'];
        
        // التحقق من أن المهمة تخص هذا المشرف
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND supervisor_id = ?");
        $stmt->execute([$new_status, $id, $user_id]);
        $success = $lang['task_updated'];
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// جلب المهمة للتعديل أو العرض
$edit_task = null;
$view_task = null;
if (isset($_GET['edit']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND supervisor_id = ?");
        $stmt->execute([$_GET['id'], $user_id]);
        $edit_task = $stmt->fetch();
        if (!$edit_task) {
            $error = 'المهمة غير موجودة أو ليس لديك صلاحية للوصول إليها';
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
} elseif (isset($_GET['view']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT t.*, w.full_name as worker_name, w.phone as worker_phone FROM tasks t LEFT JOIN users w ON t.worker_id = w.id WHERE t.id = ? AND t.supervisor_id = ?");
        $stmt->execute([$_GET['id'], $user_id]);
        $view_task = $stmt->fetch();
        if (!$view_task) {
            $error = 'المهمة غير موجودة أو ليس لديك صلاحية للوصول إليها';
        } else {
            // جلب المرفقات (الصور والأصوات)
            $stmt = $pdo->prepare("SELECT * FROM task_attachments WHERE task_id = ? ORDER BY created_at DESC");
            $stmt->execute([$_GET['id']]);
            $attachments = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
        $attachments = [];
    }
} else {
    $attachments = [];
}

// جلب العمال النشطين
try {
    $stmt = $pdo->query("SELECT id, full_name, location FROM users WHERE role = 'worker' AND status = 'active' ORDER BY full_name");
    $workers = $stmt->fetchAll();
} catch (PDOException $e) {
    $workers = [];
}

// جلب المهام الخاصة بهذا المشرف
try {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $worker_filter = $_GET['worker'] ?? '';
    
    $query = "SELECT t.*, w.full_name as worker_name 
              FROM tasks t 
              LEFT JOIN users w ON t.worker_id = w.id 
              WHERE t.supervisor_id = ?";
    $params = [$user_id];
    
    if (!empty($search)) {
        $query .= " AND (t.title LIKE ? OR t.description LIKE ? OR t.location LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($status_filter)) {
        $query .= " AND t.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($worker_filter)) {
        $query .= " AND t.worker_id = ?";
        $params[] = $worker_filter;
    }
    
    $query .= " ORDER BY t.assigned_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    $tasks = [];
    $error = $lang['system_error'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['assigned_tasks']; ?> - <?php echo $lang['site_title']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- الشريط الجانبي -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="d-flex flex-column">
                    <div class="text-center mb-4">
                        <h3 class="text-white mb-0">
                            <i class="fas fa-sparkles me-2"></i>
                            <?php echo $lang['site_title']; ?>
                        </h3>
                        <small class="text-light"><?php echo $lang['role_supervisor']; ?></small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt"></i>
                                <?php echo $lang['dashboard']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="tasks.php">
                                <i class="fas fa-tasks"></i>
                                <?php echo $lang['assigned_tasks']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="workers.php">
                                <i class="fas fa-users"></i>
                                <?php echo $lang['role_worker']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                <?php echo $lang['reports']; ?>
                            </a>
                        </li>
                    </ul>
                    <div class="mt-auto p-3 text-center">
                        <h6 class="text-white mb-1"><?php echo $_SESSION['user_name']; ?></h6>
                        <small class="text-light"><?php echo $lang['role_supervisor']; ?></small>
                        <hr class="bg-light">
                        <a href="../../logout.php" class="btn btn-sm btn-outline-light w-100">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            <?php echo $lang['logout']; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- المحتوى الرئيسي -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-tasks me-2 text-primary"></i>
                        <?php echo $lang['assigned_tasks']; ?>
                    </h1>
                    <div class="d-flex align-items-center gap-2">
                        <a href="?lang=<?php echo $_SESSION['lang'] == 'ar' ? 'en' : 'ar'; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-language"></i>
                            <?php echo $_SESSION['lang'] == 'ar' ? 'English' : 'العربية'; ?>
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            <?php echo $lang['back_to_home']; ?>
                        </a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- عرض تفاصيل المهمة -->
                <?php if ($view_task): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo $lang['task_details']; ?>
                            </h5>
                            <a href="tasks.php" class="btn btn-sm btn-light">
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
                                </div>
                                <div class="col-md-6">
                                    <h6><?php echo $lang['assigned_to']; ?></h6>
                                    <p><?php echo htmlspecialchars($view_task['worker_name'] ?: '-'); ?></p>
                                    
                                    <h6><?php echo $lang['status']; ?></h6>
                                    <p>
                                        <?php if ($view_task['completion_status'] == 'not_completed'): ?>
                                            <span class="badge bg-danger fs-6">
                                                <i class="fas fa-times-circle me-1"></i>
                                                مرفوضة - عدم إكمال
                                            </span>
                                        <?php elseif ($view_task['completion_status'] == 'completed' && $view_task['status'] == 'completed'): ?>
                                            <span class="badge bg-success fs-6">
                                                <i class="fas fa-check-circle me-1"></i>
                                                مقبولة - مكتملة
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-<?php 
                                                echo $view_task['status'] == 'completed' ? 'success' : 
                                                     ($view_task['status'] == 'in_progress' ? 'warning' : 
                                                     ($view_task['status'] == 'approved' ? 'info' : 'secondary'));
                                            ?>">
                                                <?php echo $lang[$view_task['status']]; ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php if ($view_task['completion_status'] == 'not_completed'): ?>
                                        <div class="alert alert-danger mt-3">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                حالة المهمة: مرفوضة من قبل العامل
                                            </h6>
                                        </div>
                                    <?php elseif ($view_task['completion_status'] == 'completed' && $view_task['status'] == 'completed'): ?>
                                        <div class="alert alert-success mt-3">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-check-circle me-2"></i>
                                                حالة المهمة: مقبولة ومكتملة من قبل العامل
                                            </h6>
                                        </div>
                                    <?php endif; ?>
                                    
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
                                    
                                    <h6><?php echo $lang['date']; ?></h6>
                                    <p><?php echo $view_task['assigned_at'] ? date('Y-m-d H:i', strtotime($view_task['assigned_at'])) : '-'; ?></p>
                                    
                                    <?php if ($view_task['completion_notes']): ?>
                                        <div class="alert alert-success mt-3">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-check-circle me-2"></i>
                                                ملاحظات الإكمال من العامل
                                            </h6>
                                            <hr>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($view_task['completion_notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($view_task['non_completion_notes']): ?>
                                        <div class="alert alert-danger mt-3">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-exclamation-circle me-2"></i>
                                                ملاحظات عدم الإكمال من العامل
                                            </h6>
                                            <hr>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($view_task['non_completion_notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- عرض المرفقات (الصور والأصوات) -->
                            <?php if (isset($attachments) && count($attachments) > 0): ?>
                                <hr>
                                <div class="mt-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-paperclip me-2"></i>
                                        المرفقات المرفوعة من العامل
                                    </h5>
                                    
                                    <?php 
                                    $images = array_filter($attachments, function($att) { return $att['file_type'] == 'image'; });
                                    $audios = array_filter($attachments, function($att) { return $att['file_type'] == 'audio'; });
                                    ?>
                                    
                                    <!-- عرض الصور -->
                                    <?php if (count($images) > 0): ?>
                                        <div class="mb-4">
                                            <h6 class="mb-3">
                                                <i class="fas fa-images me-2 text-primary"></i>
                                                الصور (<?php echo count($images); ?>)
                                            </h6>
                                            <div class="row g-3">
                                                <?php foreach ($images as $attachment): ?>
                                                    <div class="col-md-4 col-lg-3">
                                                        <div class="card h-100">
                                                            <div class="position-relative">
                                                                <img src="../../<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                                     class="card-img-top" 
                                                                     style="height: 200px; object-fit: cover; cursor: pointer;"
                                                                     onclick="showFullImage('../../<?php echo htmlspecialchars($attachment['file_path']); ?>')"
                                                                     alt="<?php echo htmlspecialchars($attachment['file_name']); ?>">
                                                            </div>
                                                            <div class="card-body p-2">
                                                                <small class="text-muted d-block">
                                                                    <i class="fas fa-file-image me-1"></i>
                                                                    <?php echo htmlspecialchars($attachment['file_name']); ?>
                                                                </small>
                                                                <small class="text-muted">
                                                                    <?php echo (isset($attachment['file_size']) && $attachment['file_size']) ? number_format($attachment['file_size'] / 1024, 2) . ' KB' : ''; ?>
                                                                </small>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?php echo $attachment['attachment_type'] == 'completion' ? '<span class="badge bg-success">إكمال</span>' : '<span class="badge bg-danger">عدم إكمال</span>'; ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- عرض الأصوات -->
                                    <?php if (count($audios) > 0): ?>
                                        <div class="mb-4">
                                            <h6 class="mb-3">
                                                <i class="fas fa-volume-up me-2 text-success"></i>
                                                التسجيلات الصوتية (<?php echo count($audios); ?>)
                                            </h6>
                                            <div class="row g-3">
                                                <?php foreach ($audios as $attachment): ?>
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h6 class="card-title">
                                                                    <i class="fas fa-file-audio me-2 text-success"></i>
                                                                    <?php echo htmlspecialchars($attachment['file_name']); ?>
                                                                </h6>
                                                                <audio controls class="w-100 mb-2">
                                                                    <source src="../../<?php echo htmlspecialchars($attachment['file_path']); ?>" type="audio/mpeg">
                                                                    <source src="../../<?php echo htmlspecialchars($attachment['file_path']); ?>" type="audio/wav">
                                                                    <source src="../../<?php echo htmlspecialchars($attachment['file_path']); ?>" type="audio/ogg">
                                                                    <source src="../../<?php echo htmlspecialchars($attachment['file_path']); ?>" type="audio/webm">
                                                                    متصفحك لا يدعم تشغيل الصوت.
                                                                </audio>
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <small class="text-muted">
                                                                        <?php echo (isset($attachment['file_size']) && $attachment['file_size']) ? number_format($attachment['file_size'] / 1024, 2) . ' KB' : ''; ?>
                                                                    </small>
                                                                    <small>
                                                                        <?php echo $attachment['attachment_type'] == 'completion' ? '<span class="badge bg-success">إكمال</span>' : '<span class="badge bg-danger">عدم إكمال</span>'; ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (count($images) == 0 && count($audios) == 0): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            لا توجد مرفقات لهذه المهمة
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="tasks.php?edit=1&id=<?php echo $view_task['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i>
                                    <?php echo $lang['edit']; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- نموذج إنشاء/تعديل المهمة -->
                <?php if (isset($_GET['action']) && $_GET['action'] == 'create' || $edit_task): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $edit_task ? 'edit' : 'plus'; ?> me-2"></i>
                                <?php echo $edit_task ? $lang['edit'] : $lang['assign_task']; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="<?php echo $edit_task ? 'update' : 'create'; ?>">
                                <?php if ($edit_task): ?>
                                    <input type="hidden" name="id" value="<?php echo $edit_task['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label"><?php echo $lang['task_name']; ?> *</label>
                                        <input type="text" class="form-control" name="title" 
                                               value="<?php echo htmlspecialchars($edit_task['title'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><?php echo $lang['priority']; ?> *</label>
                                        <select class="form-select" name="priority" required>
                                            <option value="low" <?php echo ($edit_task['priority'] ?? 'medium') == 'low' ? 'selected' : ''; ?>>
                                                <?php echo $lang['low']; ?>
                                            </option>
                                            <option value="medium" <?php echo ($edit_task['priority'] ?? 'medium') == 'medium' ? 'selected' : ''; ?>>
                                                <?php echo $lang['medium']; ?>
                                            </option>
                                            <option value="high" <?php echo ($edit_task['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>
                                                <?php echo $lang['high']; ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><?php echo $lang['description']; ?></label>
                                    <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($edit_task['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label"><?php echo $lang['location']; ?> *</label>
                                        <input type="text" class="form-control" name="location" 
                                               value="<?php echo htmlspecialchars($edit_task['location'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label"><?php echo $lang['area']; ?> *</label>
                                        <input type="text" class="form-control" name="area" 
                                               value="<?php echo htmlspecialchars($edit_task['area'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label"><?php echo $lang['building']; ?> *</label>
                                        <input type="text" class="form-control" name="building" 
                                               value="<?php echo htmlspecialchars($edit_task['building'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label"><?php echo $lang['floor']; ?> *</label>
                                        <input type="text" class="form-control" name="floor" 
                                               value="<?php echo htmlspecialchars($edit_task['floor'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><?php echo $lang['worker']; ?> *</label>
                                        <select class="form-select" name="worker_id" required>
                                            <option value="">-- <?php echo $lang['select_role']; ?> --</option>
                                            <?php foreach ($workers as $worker): ?>
                                                <option value="<?php echo $worker['id']; ?>" 
                                                    <?php echo ($edit_task['worker_id'] ?? '') == $worker['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($worker['full_name']); ?>
                                                    <?php if ($worker['location']): ?>
                                                        (<?php echo htmlspecialchars($worker['location']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><?php echo $lang['status']; ?> *</label>
                                        <select class="form-select" name="status" required>
                                            <option value="pending" <?php echo ($edit_task['status'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>
                                                <?php echo $lang['pending']; ?>
                                            </option>
                                            <option value="in_progress" <?php echo ($edit_task['status'] ?? '') == 'in_progress' ? 'selected' : ''; ?>>
                                                <?php echo $lang['in_progress']; ?>
                                            </option>
                                            <option value="completed" <?php echo ($edit_task['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>
                                                <?php echo $lang['completed']; ?>
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><?php echo $lang['due_date']; ?></label>
                                        <input type="date" class="form-control" name="due_date" 
                                               value="<?php echo $edit_task['due_date'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>
                                        <?php echo $lang['save']; ?>
                                    </button>
                                    <a href="tasks.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        <?php echo $lang['cancel']; ?>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- البحث والتصفية -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="<?php echo $lang['search']; ?>" 
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value=""><?php echo $lang['all_tasks']; ?></option>
                                    <option value="pending" <?php echo ($_GET['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>
                                        <?php echo $lang['pending']; ?>
                                    </option>
                                    <option value="in_progress" <?php echo ($_GET['status'] ?? '') == 'in_progress' ? 'selected' : ''; ?>>
                                        <?php echo $lang['in_progress']; ?>
                                    </option>
                                    <option value="completed" <?php echo ($_GET['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>
                                        <?php echo $lang['completed']; ?>
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="worker">
                                    <option value="">جميع العمال</option>
                                    <?php foreach ($workers as $worker): ?>
                                        <option value="<?php echo $worker['id']; ?>" 
                                            <?php echo ($_GET['worker'] ?? '') == $worker['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($worker['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i>
                                    <?php echo $lang['filter']; ?>
                                </button>
                            </div>
                        </form>
                        <div class="mt-2">
                            <a href="tasks.php?action=create" class="btn btn-success">
                                <i class="fas fa-plus me-1"></i>
                                <?php echo $lang['assign_task']; ?>
                            </a>
                            <a href="tasks.php" class="btn btn-secondary">
                                <i class="fas fa-redo me-1"></i>
                                <?php echo $lang['reset']; ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- جدول المهام -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            <?php echo $lang['assigned_tasks']; ?> (<?php echo count($tasks); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo $lang['task_name']; ?></th>
                                        <th><?php echo $lang['location']; ?></th>
                                        <th><?php echo $lang['assigned_to']; ?></th>
                                        <th><?php echo $lang['priority']; ?></th>
                                        <th><?php echo $lang['status']; ?></th>
                                        <th><?php echo $lang['due_date']; ?></th>
                                        <th><?php echo $lang['actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($tasks) > 0): ?>
                                        <?php foreach ($tasks as $index => $task): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                                    <?php if ($task['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($task['location']); ?><br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($task['area']); ?> - 
                                                        <?php echo htmlspecialchars($task['building']); ?> - 
                                                        <?php echo htmlspecialchars($task['floor']); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($task['worker_name'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $task['priority'] == 'high' ? 'danger' : 
                                                             ($task['priority'] == 'medium' ? 'warning' : 'info');
                                                    ?>">
                                                        <?php echo $lang[$task['priority']]; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($task['completion_status'] == 'not_completed'): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-times-circle me-1"></i>
                                                            مرفوضة
                                                        </span>
                                                    <?php elseif ($task['completion_status'] == 'completed' && $task['status'] == 'completed'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i>
                                                            مقبولة - مكتملة
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-<?php 
                                                            echo $task['status'] == 'completed' ? 'success' : 
                                                                 ($task['status'] == 'in_progress' ? 'warning' : 'secondary');
                                                        ?>">
                                                            <?php echo $lang[$task['status']]; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : '-'; ?></td>
                                                <td>
                                                    <a href="tasks.php?view=1&id=<?php echo $task['id']; ?>" 
                                                       class="btn btn-sm btn-info" title="<?php echo $lang['view']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="tasks.php?edit=1&id=<?php echo $task['id']; ?>" 
                                                       class="btn btn-sm btn-warning" title="<?php echo $lang['edit']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($task['status'] != 'completed'): ?>
                                                        <a href="tasks.php?change_status=1&id=<?php echo $task['id']; ?>&status=completed" 
                                                           class="btn btn-sm btn-success" 
                                                           title="تأكيد الإكمال"
                                                           onclick="return confirm('هل تريد تأكيد إكمال هذه المهمة؟')">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php echo $lang['no_data']; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // عرض الصورة بالحجم الكامل
        function showFullImage(imageSrc) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">معاينة الصورة</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center p-0">
                            <img src="${imageSrc}" class="img-fluid" style="max-height: 80vh;">
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
    </script>
</body>
</html>

