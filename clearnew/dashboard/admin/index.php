<?php
// dashboard/admin/index.php - لوحة تحكم المالك
require_once '../../config.php';
require_once '../../includes/lang.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login.php');
    exit();
}

// جلب الإحصائيات
try {
    // إجمالي المستخدمين
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    
    // إجمالي المهام
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks");
    $total_tasks = $stmt->fetch()['total'];
    
    // المهام المكتملة
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks WHERE status = 'completed'");
    $completed_tasks = $stmt->fetch()['total'];
    
    // المهام المعلقة
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks WHERE status = 'pending'");
    $pending_tasks = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $error = $lang['system_error'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['dashboard']; ?> - <?php echo $lang['site_title']; ?></title>
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
                    <!-- الشعار -->
                    <div class="text-center mb-4">
                        <h3 class="text-white mb-0">
                            <i class="fas fa-sparkles me-2"></i>
                            <?php echo $lang['site_title']; ?>
                        </h3>
                        <small class="text-light"><?php echo $lang['role_admin']; ?></small>
                    </div>

                    <!-- قائمة التنقل -->
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt"></i>
                                <?php echo $lang['dashboard']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i>
                                <?php echo $lang['users_management']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tasks.php">
                                <i class="fas fa-tasks"></i>
                                <?php echo $lang['tasks']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                <?php echo $lang['reports']; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-cog"></i>
                                <?php echo $lang['settings']; ?>
                            </a>
                        </li>
                    </ul>

                    <!-- معلومات المستخدم -->
                    <div class="mt-auto p-3 text-center">
                        <div class="text-white mb-2">
                            <i class="fas fa-user-circle fa-2x"></i>
                        </div>
                        <h6 class="text-white mb-1"><?php echo $_SESSION['user_name']; ?></h6>
                        <small class="text-light"><?php echo $lang['role_admin']; ?></small>
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
                <!-- الهيدر -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                        <?php echo $lang['dashboard']; ?>
                    </h1>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo date('Y-m-d'); ?>
                        </span>
                        <a href="?lang=<?php echo $_SESSION['lang'] == 'ar' ? 'en' : 'ar'; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-language"></i>
                            <?php echo $_SESSION['lang'] == 'ar' ? 'English' : 'العربية'; ?>
                        </a>
                    </div>
                </div>

                <!-- رسالة ترحيب -->
                <div class="alert alert-primary mb-4">
                    <h4 class="alert-heading">
                        <i class="fas fa-hand-wave me-2"></i>
                        <?php echo $lang['welcome']; ?>, <?php echo $_SESSION['user_name']; ?>!
                    </h4>
                    <p class="mb-0"><?php echo $lang['role_admin_desc']; ?></p>
                </div>

                <!-- الإحصائيات -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo $total_users; ?></div>
                            <div class="stat-label"><?php echo $lang['total_users']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stat-number"><?php echo $total_tasks; ?></div>
                            <div class="stat-label"><?php echo $lang['total_tasks']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $completed_tasks; ?></div>
                            <div class="stat-label"><?php echo $lang['completed_tasks']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-number"><?php echo $pending_tasks; ?></div>
                            <div class="stat-label"><?php echo $lang['pending_tasks']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- آخر المهام -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-tasks me-2"></i>
                                    <?php echo $lang['assigned_tasks']; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th><?php echo $lang['task_name']; ?></th>
                                                <th><?php echo $lang['assigned_to']; ?></th>
                                                <th><?php echo $lang['status']; ?></th>
                                                <th><?php echo $lang['date']; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            try {
                                                $stmt = $pdo->query("
                                                    SELECT t.*, u.full_name 
                                                    FROM tasks t 
                                                    LEFT JOIN users u ON t.worker_id = u.id 
                                                    ORDER BY t.assigned_at DESC 
                                                    LIMIT 5
                                                ");
                                                $tasks = $stmt->fetchAll();
                                                
                                                if ($tasks && count($tasks) > 0):
                                                    foreach ($tasks as $index => $task):
                                            ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td><?php echo htmlspecialchars($task['full_name'] ? $task['full_name'] : '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $task['status'] == 'completed' ? 'success' : 
                                                             ($task['status'] == 'in_progress' ? 'warning' : 
                                                             ($task['status'] == 'approved' ? 'info' : 'secondary'));
                                                    ?>">
                                                        <?php 
                                                        echo isset($lang[$task['status']]) ? $lang[$task['status']] : $task['status'];
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $task['assigned_at'] ? date('Y-m-d', strtotime($task['assigned_at'])) : '-'; ?></td>
                                            </tr>
                                            <?php
                                                    endforeach;
                                                else:
                                            ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <?php echo $lang['no_data']; ?>
                                                </td>
                                            </tr>
                                            <?php
                                                endif;
                                            } catch (PDOException $e) {
                                                if (ini_get('display_errors')) {
                                                    echo "<tr><td colspan='5' class='text-center text-danger'>" . $lang['system_error'] . ": " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                                } else {
                                                    echo "<tr><td colspan='5' class='text-center text-danger'>" . $lang['system_error'] . "</td></tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الإجراءات السريعة -->
                    <div class="col-lg-4">
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt me-2"></i>
                                    <?php echo $lang['actions']; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="users.php?action=create" class="btn btn-primary">
                                        <i class="fas fa-user-plus me-2"></i>
                                        <?php echo $lang['create_user']; ?>
                                    </a>
                                    <a href="reports.php" class="btn btn-success">
                                        <i class="fas fa-chart-bar me-2"></i>
                                        <?php echo $lang['generate_report']; ?>
                                    </a>
                                    <a href="tasks.php" class="btn btn-warning">
                                        <i class="fas fa-tasks me-2"></i>
                                        <?php echo $lang['view']; ?> <?php echo $lang['tasks']; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- النشاط الأخير -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    النشاط الأخير
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-user-check text-success me-2"></i>
                                            تسجيل دخول جديد
                                        </span>
                                        <small class="text-muted">قبل 5 دقائق</small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-user-plus text-primary me-2"></i>
                                            إنشاء حساب مشرف
                                        </span>
                                        <small class="text-muted">قبل ساعتين</small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-file-alt text-warning me-2"></i>
                                            تقرير شهري
                                        </span>
                                        <small class="text-muted">قبل يوم</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>
</html>

