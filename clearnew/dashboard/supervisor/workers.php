<?php
// dashboard/supervisor/workers.php - عرض وإدارة العمال
require_once '../../config.php';
require_once '../../includes/lang.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'supervisor'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$view_worker = null;
$worker_tasks_with_attachments = [];

// عرض تفاصيل عامل مع المرفقات
if (isset($_GET['view_worker']) && isset($_GET['id'])) {
    try {
        $worker_id = $_GET['id'];
        // التحقق من أن العامل موجود
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'worker' AND status = 'active'");
        $stmt->execute([$worker_id]);
        $view_worker = $stmt->fetch();
        
        if ($view_worker) {
            // جلب مهام العامل مع المرفقات
            $stmt = $pdo->prepare("
                SELECT t.*, 
                       COUNT(DISTINCT ta.id) as attachments_count,
                       GROUP_CONCAT(DISTINCT ta.file_type) as file_types
                FROM tasks t
                LEFT JOIN task_attachments ta ON t.id = ta.task_id
                WHERE t.worker_id = ? AND t.supervisor_id = ?
                GROUP BY t.id
                ORDER BY t.assigned_at DESC
            ");
            $stmt->execute([$worker_id, $user_id]);
            $worker_tasks_with_attachments = $stmt->fetchAll();
            
            // جلب المرفقات لكل مهمة
            foreach ($worker_tasks_with_attachments as $key => $task) {
                $stmt = $pdo->prepare("
                    SELECT * FROM task_attachments 
                    WHERE task_id = ? 
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$task['id']]);
                $worker_tasks_with_attachments[$key]['attachments'] = $stmt->fetchAll();
            }
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// جلب العمال
try {
    $search = $_GET['search'] ?? '';
    
    $query = "SELECT u.*, 
                     COUNT(t.id) as total_tasks,
                     COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks
              FROM users u 
              LEFT JOIN tasks t ON u.id = t.worker_id AND t.supervisor_id = ?
              WHERE u.role = 'worker' AND u.status = 'active'";
    $params = [$user_id];
    
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.job_number LIKE ? OR u.location LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " GROUP BY u.id ORDER BY u.full_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $workers = $stmt->fetchAll();
} catch (PDOException $e) {
    $workers = [];
    $error = $lang['system_error'];
}

// جلب مهام كل عامل
$workers_tasks = [];
foreach ($workers as $worker) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, status 
            FROM tasks 
            WHERE worker_id = ? AND supervisor_id = ?
            GROUP BY status
        ");
        $stmt->execute([$worker['id'], $user_id]);
        $workers_tasks[$worker['id']] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $workers_tasks[$worker['id']] = [];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['role_worker']; ?> - <?php echo $lang['site_title']; ?></title>
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
                            <a class="nav-link" href="tasks.php">
                                <i class="fas fa-tasks"></i>
                                <?php echo $lang['assigned_tasks']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="workers.php">
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
                        <i class="fas fa-users me-2 text-primary"></i>
                        <?php echo $lang['role_worker']; ?>
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

                <!-- البحث -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="<?php echo $lang['search']; ?>..." 
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i>
                                    <?php echo $lang['search']; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- بطاقات العمال -->
                <div class="row g-4">
                    <?php if (count($workers) > 0): ?>
                        <?php foreach ($workers as $worker): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <i class="fas fa-user me-2"></i>
                                                <?php echo htmlspecialchars($worker['full_name']); ?>
                                            </h6>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($worker['job_number']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <small class="text-muted d-block"><?php echo $lang['location']; ?></small>
                                            <strong><?php echo htmlspecialchars($worker['location'] ?: '-'); ?></strong>
                                        </div>
                                        
                                        <?php if ($worker['phone']): ?>
                                            <div class="mb-3">
                                                <small class="text-muted d-block"><?php echo $lang['phone']; ?></small>
                                                <strong><?php echo htmlspecialchars($worker['phone']); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <hr>
                                        
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="text-primary">
                                                    <i class="fas fa-tasks fa-2x mb-2"></i>
                                                    <div class="fw-bold"><?php echo $worker['total_tasks']; ?></div>
                                                    <small class="text-muted"><?php echo $lang['total_tasks']; ?></small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-success">
                                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                                    <div class="fw-bold"><?php echo $worker['completed_tasks']; ?></div>
                                                    <small class="text-muted"><?php echo $lang['completed_tasks']; ?></small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="text-warning">
                                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                                    <div class="fw-bold"><?php echo $worker['total_tasks'] - $worker['completed_tasks']; ?></div>
                                                    <small class="text-muted"><?php echo $lang['pending_tasks']; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (isset($workers_tasks[$worker['id']]) && count($workers_tasks[$worker['id']]) > 0): ?>
                                            <hr>
                                            <div class="mb-2">
                                                <small class="text-muted"><?php echo $lang['task_status']; ?>:</small>
                                            </div>
                                            <?php foreach ($workers_tasks[$worker['id']] as $task_stat): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge bg-<?php 
                                                        echo $task_stat['status'] == 'completed' ? 'success' : 
                                                             ($task_stat['status'] == 'in_progress' ? 'warning' : 'secondary');
                                                    ?>">
                                                        <?php echo $lang[$task_stat['status']]; ?>
                                                    </span>
                                                    <strong><?php echo $task_stat['count']; ?></strong>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-sm btn-info w-100" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#workerDetailsModal<?php echo $worker['id']; ?>">
                                                <i class="fas fa-eye me-1"></i>
                                                عرض المرفقات والملاحظات
                                            </button>
                                            <a href="tasks.php?worker=<?php echo $worker['id']; ?>" class="btn btn-sm btn-primary w-100">
                                                <i class="fas fa-tasks me-1"></i>
                                                <?php echo $lang['view']; ?> <?php echo $lang['tasks']; ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo $lang['no_data']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- نماذج عرض تفاصيل العامل مع المرفقات -->
    <?php if (count($workers) > 0): ?>
        <?php foreach ($workers as $worker): ?>
            <?php
            // جلب مهام هذا العامل مع المرفقات
            try {
                $stmt = $pdo->prepare("
                    SELECT t.*, 
                           COUNT(DISTINCT ta.id) as attachments_count
                    FROM tasks t
                    LEFT JOIN task_attachments ta ON t.id = ta.task_id
                    WHERE t.worker_id = ? AND t.supervisor_id = ?
                    GROUP BY t.id
                    ORDER BY t.assigned_at DESC
                ");
                $stmt->execute([$worker['id'], $user_id]);
                $worker_tasks = $stmt->fetchAll();
                
                // جلب المرفقات لكل مهمة
                foreach ($worker_tasks as $key => $task) {
                    $stmt = $pdo->prepare("
                        SELECT * FROM task_attachments 
                        WHERE task_id = ? 
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$task['id']]);
                    $worker_tasks[$key]['attachments'] = $stmt->fetchAll();
                    
                    // جلب الملاحظات
                    $stmt = $pdo->prepare("SELECT completion_notes, non_completion_notes, completion_status FROM tasks WHERE id = ?");
                    $stmt->execute([$task['id']]);
                    $task_notes = $stmt->fetch();
                    $worker_tasks[$key]['notes'] = $task_notes;
                }
            } catch (PDOException $e) {
                $worker_tasks = [];
            }
            ?>
            
            <div class="modal fade" id="workerDetailsModal<?php echo $worker['id']; ?>" tabindex="-1" aria-labelledby="workerDetailsModalLabel<?php echo $worker['id']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="workerDetailsModalLabel<?php echo $worker['id']; ?>">
                                <i class="fas fa-user me-2"></i>
                                مرفقات وملاحظات: <?php echo htmlspecialchars($worker['full_name']); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                            <?php if (count($worker_tasks) > 0): ?>
                                <?php foreach ($worker_tasks as $task): ?>
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-tasks me-2"></i>
                                                    <?php echo htmlspecialchars($task['title']); ?>
                                                </h6>
                                                <div>
                                                    <span class="badge bg-<?php 
                                                        echo $task['status'] == 'completed' ? 'success' : 
                                                             ($task['status'] == 'in_progress' ? 'warning' : 'secondary');
                                                    ?>">
                                                        <?php echo $lang[$task['status']] ?? $task['status']; ?>
                                                    </span>
                                                    <?php if ($task['attachments_count'] > 0): ?>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-paperclip me-1"></i>
                                                            <?php echo $task['attachments_count']; ?> مرفق
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- معلومات المهمة -->
                                            <div class="mb-3">
                                                <small class="text-muted">الموقع:</small>
                                                <strong><?php echo htmlspecialchars($task['location']); ?> - <?php echo htmlspecialchars($task['area']); ?> - <?php echo htmlspecialchars($task['building']); ?> - <?php echo htmlspecialchars($task['floor']); ?></strong>
                                            </div>
                                            
                                            <!-- الملاحظات -->
                                            <?php if (!empty($task['notes']['completion_notes']) || !empty($task['notes']['non_completion_notes'])): ?>
                                                <div class="mb-3">
                                                    <h6><i class="fas fa-sticky-note me-2 text-warning"></i>الملاحظات:</h6>
                                                    <?php if (!empty($task['notes']['completion_notes'])): ?>
                                                        <div class="alert alert-success">
                                                            <strong>ملاحظات الإكمال:</strong>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($task['notes']['completion_notes'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($task['notes']['non_completion_notes'])): ?>
                                                        <div class="alert alert-danger">
                                                            <strong>ملاحظات عدم الإكمال:</strong>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($task['notes']['non_completion_notes'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- المرفقات -->
                                            <?php if (count($task['attachments']) > 0): ?>
                                                <div class="mb-3">
                                                    <h6><i class="fas fa-paperclip me-2 text-primary"></i>المرفقات:</h6>
                                                    
                                                    <!-- الصور -->
                                                    <?php 
                                                    $images = array_filter($task['attachments'], function($att) { return $att['file_type'] == 'image'; });
                                                    if (count($images) > 0): 
                                                    ?>
                                                        <div class="mb-3">
                                                            <strong><i class="fas fa-image me-1"></i>الصور:</strong>
                                                            <div class="row g-2 mt-2">
                                                                <?php foreach ($images as $img): ?>
                                                                    <div class="col-md-3 col-sm-4 col-6">
                                                                        <div class="card">
                                                                            <img src="../../<?php echo htmlspecialchars($img['file_path']); ?>" 
                                                                                 class="card-img-top" 
                                                                                 style="height: 150px; object-fit: cover; cursor: pointer;"
                                                                                 onclick="openImageModal('<?php echo htmlspecialchars($img['file_path']); ?>', '<?php echo htmlspecialchars($img['file_name']); ?>')"
                                                                                 alt="<?php echo htmlspecialchars($img['file_name']); ?>">
                                                                            <div class="card-body p-2">
                                                                                <small class="text-muted d-block" style="font-size: 0.7rem;">
                                                                                    <?php echo htmlspecialchars($img['file_name']); ?>
                                                                                </small>
                                                                                <small class="text-muted">
                                                                                    <?php echo $img['attachment_type'] == 'completion' ? 'إكمال' : 'عدم إكمال'; ?>
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- الصوت -->
                                                    <?php 
                                                    $audios = array_filter($task['attachments'], function($att) { return $att['file_type'] == 'audio'; });
                                                    if (count($audios) > 0): 
                                                    ?>
                                                        <div class="mb-3">
                                                            <strong><i class="fas fa-volume-up me-1"></i>التسجيلات الصوتية:</strong>
                                                            <div class="mt-2">
                                                                <?php foreach ($audios as $audio): ?>
                                                                    <div class="card mb-2">
                                                                        <div class="card-body">
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <div>
                                                                                    <i class="fas fa-music me-2"></i>
                                                                                    <strong><?php echo htmlspecialchars($audio['file_name']); ?></strong>
                                                                                    <br>
                                                                                    <small class="text-muted">
                                                                                        <?php echo $audio['attachment_type'] == 'completion' ? 'إكمال' : 'عدم إكمال'; ?>
                                                                                        | <?php echo date('Y-m-d H:i', strtotime($audio['created_at'])); ?>
                                                                                    </small>
                                                                                </div>
                                                                                <audio controls class="ms-3">
                                                                                    <source src="../../<?php echo htmlspecialchars($audio['file_path']); ?>" type="audio/mpeg">
                                                                                    <source src="../../<?php echo htmlspecialchars($audio['file_path']); ?>" type="audio/webm">
                                                                                    <source src="../../<?php echo htmlspecialchars($audio['file_path']); ?>" type="audio/wav">
                                                                                    المتصفح لا يدعم تشغيل الصوت.
                                                                                </audio>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- الفيديو (إذا كان موجود في المستقبل) -->
                                                    <?php 
                                                    $videos = array_filter($task['attachments'], function($att) { 
                                                        $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                                                        return in_array($ext, ['mp4', 'webm', 'ogg', 'mov']);
                                                    });
                                                    if (count($videos) > 0): 
                                                    ?>
                                                        <div class="mb-3">
                                                            <strong><i class="fas fa-video me-1"></i>الفيديو:</strong>
                                                            <div class="mt-2">
                                                                <?php foreach ($videos as $video): ?>
                                                                    <div class="card mb-2">
                                                                        <div class="card-body">
                                                                            <video controls class="w-100" style="max-height: 400px;">
                                                                                <source src="../../<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                                                                                <source src="../../<?php echo htmlspecialchars($video['file_path']); ?>" type="video/webm">
                                                                                المتصفح لا يدعم تشغيل الفيديو.
                                                                            </video>
                                                                            <div class="mt-2">
                                                                                <strong><?php echo htmlspecialchars($video['file_name']); ?></strong>
                                                                                <br>
                                                                                <small class="text-muted">
                                                                                    <?php echo $video['attachment_type'] == 'completion' ? 'إكمال' : 'عدم إكمال'; ?>
                                                                                    | <?php echo date('Y-m-d H:i', strtotime($video['created_at'])); ?>
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    لا توجد مرفقات لهذه المهمة
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    لا توجد مهام لهذا العامل
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- نموذج عرض الصورة الكبيرة -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imageModalImg" src="" class="img-fluid" alt="">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openImageModal(imagePath, imageName) {
            document.getElementById('imageModalImg').src = '../../' + imagePath;
            document.getElementById('imageModalTitle').textContent = imageName;
            const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            imageModal.show();
        }
    </script>
</body>
</html>

