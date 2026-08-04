<?php
// dashboard/supervisor/reports.php - التقارير للمشرف
require_once '../../config.php';
require_once '../../includes/lang.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'supervisor'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// جلب الإحصائيات
$tasks_by_status = [];
$tasks_by_priority = [];
$tasks_by_worker = [];
$tasks_by_month = [];
$total_tasks = 0;
$completed_tasks = 0;
$pending_tasks = 0;
$in_progress_tasks = 0;

try {
    // إحصائيات المهام حسب الحالة
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE supervisor_id = ? GROUP BY status");
    $stmt->execute([$user_id]);
    $tasks_by_status = $stmt->fetchAll();
    
    // إحصائيات المهام حسب الأولوية
    $stmt = $pdo->prepare("SELECT priority, COUNT(*) as count FROM tasks WHERE supervisor_id = ? GROUP BY priority");
    $stmt->execute([$user_id]);
    $tasks_by_priority = $stmt->fetchAll();
    
    // إحصائيات المهام حسب العامل
    $stmt = $pdo->prepare("
        SELECT w.full_name, COUNT(t.id) as task_count, 
               COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_count
        FROM users w
        LEFT JOIN tasks t ON w.id = t.worker_id AND t.supervisor_id = ?
        WHERE w.role = 'worker' AND w.status = 'active'
        GROUP BY w.id, w.full_name
        HAVING task_count > 0
        ORDER BY task_count DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $tasks_by_worker = $stmt->fetchAll();
    
    // المهام حسب الشهر
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(assigned_at, '%Y-%m') as month, COUNT(*) as count 
        FROM tasks 
        WHERE supervisor_id = ? AND assigned_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY month 
        ORDER BY month ASC
    ");
    $stmt->execute([$user_id]);
    $tasks_by_month = $stmt->fetchAll();
    
    // إجمالي المهام
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE supervisor_id = ?");
    $stmt->execute([$user_id]);
    $total_tasks = $stmt->fetch()['count'];
    
    // المهام المكتملة
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE supervisor_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completed_tasks = $stmt->fetch()['count'];
    
    // المهام المعلقة
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE supervisor_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_tasks = $stmt->fetch()['count'];
    
    // المهام قيد التنفيذ
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE supervisor_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_id]);
    $in_progress_tasks = $stmt->fetch()['count'];
    
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
                            <a class="nav-link" href="workers.php">
                                <i class="fas fa-users"></i>
                                <?php echo $lang['role_worker']; ?>
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
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        <?php echo $lang['reports']; ?>
                    </h1>
                    <div class="d-flex align-items-center gap-2">
                        <a href="?lang=<?php echo $_SESSION['lang'] == 'ar' ? 'en' : 'ar'; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-language"></i>
                            <?php echo $_SESSION['lang'] == 'ar' ? 'English' : 'العربية'; ?>
                        </a>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-danger" onclick="exportPDF()">
                                <i class="fas fa-file-pdf me-1"></i>
                                تصدير PDF
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportExcel()">
                                <i class="fas fa-file-excel me-1"></i>
                                تصدير Excel
                            </button>
                        </div>
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

                <!-- نموذج اختيار الفترة -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            اختيار الفترة الزمنية
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="dateRangeForm" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">من تاريخ:</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo date('Y-m-01'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">إلى تاريخ:</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="button" class="btn btn-primary w-100" onclick="applyDateRange()">
                                    <i class="fas fa-filter me-1"></i>
                                    تطبيق الفترة
                                </button>
                            </div>
                        </form>
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
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <div class="stat-number"><?php echo $in_progress_tasks; ?></div>
                            <div class="stat-label"><?php echo $lang['in_progress']; ?></div>
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
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-warning text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    المهام حسب الشهر (آخر 6 أشهر)
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="tasksMonthChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- المهام حسب العامل -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            المهام حسب العامل
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo $lang['full_name']; ?></th>
                                        <th><?php echo $lang['total_tasks']; ?></th>
                                        <th><?php echo $lang['completed_tasks']; ?></th>
                                        <th>نسبة الإنجاز</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($tasks_by_worker) > 0): ?>
                                        <?php foreach ($tasks_by_worker as $index => $worker): ?>
                                            <?php 
                                            $completion_rate = $worker['task_count'] > 0 
                                                ? round(($worker['completed_count'] / $worker['task_count']) * 100, 1) 
                                                : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($worker['full_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $worker['task_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $worker['completed_count']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 25px;">
                                                        <div class="progress-bar <?php 
                                                            echo $completion_rate >= 80 ? 'bg-success' : 
                                                                 ($completion_rate >= 50 ? 'bg-warning' : 'bg-danger');
                                                        ?>" 
                                                        role="progressbar" 
                                                        style="width: <?php echo $completion_rate; ?>%"
                                                        aria-valuenow="<?php echo $completion_rate; ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100">
                                                            <?php echo $completion_rate; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
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

        // دوال التصدير
        function exportPDF() {
            const date_from = document.getElementById('date_from')?.value || '<?php echo date('Y-m-01'); ?>';
            const date_to = document.getElementById('date_to')?.value || '<?php echo date('Y-m-d'); ?>';
            window.open('export_pdf.php?date_from=' + date_from + '&date_to=' + date_to, '_blank');
        }

        function exportExcel() {
            const date_from = document.getElementById('date_from')?.value || '<?php echo date('Y-m-01'); ?>';
            const date_to = document.getElementById('date_to')?.value || '<?php echo date('Y-m-d'); ?>';
            window.location.href = 'export_excel.php?date_from=' + date_from + '&date_to=' + date_to;
        }

        function applyDateRange() {
            const date_from = document.getElementById('date_from').value;
            const date_to = document.getElementById('date_to').value;
            if (date_from && date_to) {
                window.location.href = 'reports.php?date_from=' + date_from + '&date_to=' + date_to;
            } else {
                alert('يرجى اختيار الفترة الزمنية');
            }
        }
    </script>
</body>
</html>

