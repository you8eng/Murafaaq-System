<?php
// dashboard/admin/reports.php - التقارير
require_once '../../config.php';
require_once '../../includes/lang.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login.php');
    exit();
}

// جلب الإحصائيات
$users_by_role = [];
$tasks_by_status = [];
$tasks_by_priority = [];
$completed_this_month = 0;
$pending_tasks = 0;
$total_tasks = 0;
$top_workers = [];
$tasks_by_month = [];

try {
    // إحصائيات المستخدمين
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
    $users_by_role = $stmt->fetchAll();
    
    // إحصائيات المهام
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status");
    $tasks_by_status = $stmt->fetchAll();
    
    // إحصائيات المهام حسب الأولوية
    $stmt = $pdo->query("SELECT priority, COUNT(*) as count FROM tasks GROUP BY priority");
    $tasks_by_priority = $stmt->fetchAll();
    
    // المهام المكتملة هذا الشهر
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed' AND MONTH(completed_at) = MONTH(CURRENT_DATE()) AND YEAR(completed_at) = YEAR(CURRENT_DATE())");
    $completed_this_month = $stmt->fetch()['count'];
    
    // المهام المعلقة
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'pending'");
    $pending_tasks = $stmt->fetch()['count'];
    
    // إجمالي المهام
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
    $total_tasks = $stmt->fetch()['count'];
    
    // أفضل العمال (حسب المهام المكتملة)
    $stmt = $pdo->query("
        SELECT u.full_name, COUNT(t.id) as completed_count 
        FROM users u 
        LEFT JOIN tasks t ON u.id = t.worker_id AND t.status = 'completed'
        WHERE u.role = 'worker' AND u.status = 'active'
        GROUP BY u.id, u.full_name
        ORDER BY completed_count DESC
        LIMIT 5
    ");
    $top_workers = $stmt->fetchAll();
    
    // المهام حسب الشهر
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(assigned_at, '%Y-%m') as month, COUNT(*) as count 
        FROM tasks 
        WHERE assigned_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY month 
        ORDER BY month ASC
    ");
    $tasks_by_month = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = $lang['system_error'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['reports']; ?> - <?php echo $lang['site_title']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <small class="text-light"><?php echo $lang['role_admin']; ?></small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
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
                            <a class="nav-link active" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                <?php echo $lang['reports']; ?>
                            </a>
                        </li>
                    </ul>
                    <div class="mt-auto p-3 text-center">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        <?php echo $lang['reports']; ?>
                    </h1>
                    <div class="d-flex align-items-center gap-2">
                        <a href="?lang=<?php echo $_SESSION['lang'] == 'ar' ? 'en' : 'ar'; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-language"></i>
                            <?php echo $_SESSION['lang'] == 'ar' ? 'English' : 'العربية'; ?>
                        </a>
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="fas fa-print me-1"></i>
                            <?php echo $lang['print']; ?>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            <?php echo $lang['back_to_home']; ?>
                        </a>
                    </div>
                </div>

                <!-- بطاقات الإحصائيات -->
                <div class="row g-4 mb-4">
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
                            <div class="stat-number"><?php echo $completed_this_month; ?></div>
                            <div class="stat-label">مكتملة هذا الشهر</div>
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
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo array_sum(array_column($users_by_role, 'count')); ?></div>
                            <div class="stat-label"><?php echo $lang['total_users']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- الرسوم البيانية -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    المهام حسب الحالة
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="tasksStatusChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    المهام حسب الأولوية
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="tasksPriorityChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    المستخدمين حسب الدور
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="usersRoleChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    المهام حسب الشهر (آخر 6 أشهر)
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="tasksMonthChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- أفضل العمال -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>
                            أفضل العمال (حسب المهام المكتملة)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo $lang['full_name']; ?></th>
                                        <th>عدد المهام المكتملة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($top_workers) > 0): ?>
                                        <?php foreach ($top_workers as $index => $worker): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($worker['full_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo $worker['completed_count']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">
                                                <?php echo $lang['no_data']; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- تقارير المشرفين -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>
                            تقارير المشرفين
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // جلب جميع المشرفين
                        try {
                            $stmt = $pdo->query("
                                SELECT u.*, 
                                       COUNT(t.id) as total_tasks,
                                       COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks
                                FROM users u
                                LEFT JOIN tasks t ON u.id = t.supervisor_id
                                WHERE u.role = 'supervisor' AND u.status = 'active'
                                GROUP BY u.id
                                ORDER BY u.full_name
                            ");
                            $supervisors = $stmt->fetchAll();
                        } catch (PDOException $e) {
                            $supervisors = [];
                        }
                        ?>
                        
                        <?php if (count($supervisors) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>اسم المشرف</th>
                                            <th>الرقم الوظيفي</th>
                                            <th>إجمالي المهام</th>
                                            <th>مكتملة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($supervisors as $index => $supervisor): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($supervisor['full_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($supervisor['job_number']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $supervisor['total_tasks']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $supervisor['completed_tasks']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="viewSupervisorReport(<?php echo $supervisor['id']; ?>, 'pdf')"
                                                                title="عرض تقرير PDF">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                onclick="viewSupervisorReport(<?php echo $supervisor['id']; ?>, 'excel')"
                                                                title="عرض تقرير Excel">
                                                            <i class="fas fa-file-excel"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                لا يوجد مشرفين في النظام
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // المهام حسب الحالة
        <?php
        $status_labels = [];
        $status_data = [];
        foreach ($tasks_by_status as $item) {
            $status_labels[] = "'" . $lang[$item['status']] . "'";
            $status_data[] = $item['count'];
        }
        ?>
        const tasksStatusData = {
            labels: [<?php echo implode(', ', $status_labels); ?>],
            datasets: [{
                data: [<?php echo implode(', ', $status_data); ?>],
                backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#6c757d']
            }]
        };

        new Chart(document.getElementById('tasksStatusChart'), {
            type: 'doughnut',
            data: tasksStatusData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // المهام حسب الأولوية
        <?php
        $priority_labels = [];
        $priority_data = [];
        foreach ($tasks_by_priority as $item) {
            $priority_labels[] = "'" . $lang[$item['priority']] . "'";
            $priority_data[] = $item['count'];
        }
        ?>
        const tasksPriorityData = {
            labels: [<?php echo implode(', ', $priority_labels); ?>],
            datasets: [{
                data: [<?php echo implode(', ', $priority_data); ?>],
                backgroundColor: ['#dc3545', '#ffc107', '#17a2b8']
            }]
        };

        new Chart(document.getElementById('tasksPriorityChart'), {
            type: 'doughnut',
            data: tasksPriorityData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // المستخدمين حسب الدور
        <?php
        $role_labels = [];
        $role_data = [];
        foreach ($users_by_role as $item) {
            $role_labels[] = "'" . $lang['role_' . $item['role']] . "'";
            $role_data[] = $item['count'];
        }
        ?>
        const usersRoleData = {
            labels: [<?php echo implode(', ', $role_labels); ?>],
            datasets: [{
                data: [<?php echo implode(', ', $role_data); ?>],
                backgroundColor: ['#007bff', '#28a745', '#ffc107']
            }]
        };

        new Chart(document.getElementById('usersRoleChart'), {
            type: 'pie',
            data: usersRoleData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // المهام حسب الشهر
        <?php
        $month_labels = [];
        $month_data = [];
        foreach ($tasks_by_month as $item) {
            $month_labels[] = "'" . $item['month'] . "'";
            $month_data[] = $item['count'];
        }
        ?>
        const tasksMonthData = {
            labels: [<?php echo implode(', ', $month_labels); ?>],
            datasets: [{
                label: 'عدد المهام',
                data: [<?php echo implode(', ', $month_data); ?>],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            }]
        };

        new Chart(document.getElementById('tasksMonthChart'), {
            type: 'line',
            data: tasksMonthData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // عرض تقرير المشرف
        function viewSupervisorReport(supervisorId, type) {
            const date_from = '<?php echo date('Y-m-01'); ?>';
            const date_to = '<?php echo date('Y-m-d'); ?>';
            
            if (type === 'pdf') {
                window.open('../supervisor/export_pdf.php?supervisor_id=' + supervisorId + '&date_from=' + date_from + '&date_to=' + date_to, '_blank');
            } else if (type === 'excel') {
                window.location.href = '../supervisor/export_excel.php?supervisor_id=' + supervisorId + '&date_from=' + date_from + '&date_to=' + date_to;
            }
        }
    </script>
</body>
</html>

