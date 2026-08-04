<?php
// dashboard/supervisor/export_excel.php - تصدير تقرير Excel
require_once '../../config.php';
require_once '../../includes/lang.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'supervisor'])) {
    header('Location: ../../login.php');
    exit();
}

// إذا كان الإدمن يطلب تقرير مشرف معين
$supervisor_id = $_GET['supervisor_id'] ?? $_SESSION['user_id'];
$user_id = $_SESSION['user_role'] == 'admin' ? $supervisor_id : $_SESSION['user_id'];
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// جلب البيانات
try {
    // معلومات المشرف
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $supervisor = $stmt->fetch();
    
    // إحصائيات المهام
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress
        FROM tasks 
        WHERE supervisor_id = ? 
        AND DATE(assigned_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $date_from, $date_to]);
    $stats = $stmt->fetch();
    
    // المهام حسب العامل
    $stmt = $pdo->prepare("
        SELECT w.full_name, w.job_number,
               COUNT(t.id) as task_count,
               COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_count
        FROM users w
        LEFT JOIN tasks t ON w.id = t.worker_id AND t.supervisor_id = ?
        WHERE w.role = 'worker' AND w.status = 'active'
        AND (t.id IS NULL OR DATE(t.assigned_at) BETWEEN ? AND ?)
        GROUP BY w.id, w.full_name, w.job_number
        HAVING task_count > 0
        ORDER BY task_count DESC
    ");
    $stmt->execute([$user_id, $date_from, $date_to]);
    $workers_stats = $stmt->fetchAll();
    
    // جميع المهام
    $stmt = $pdo->prepare("
        SELECT t.*, w.full_name as worker_name
        FROM tasks t
        LEFT JOIN users w ON t.worker_id = w.id
        WHERE t.supervisor_id = ?
        AND DATE(t.assigned_at) BETWEEN ? AND ?
        ORDER BY t.assigned_at DESC
    ");
    $stmt->execute([$user_id, $date_from, $date_to]);
    $all_tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("خطأ في جلب البيانات: " . $e->getMessage());
}

// إنشاء ملف Excel بسيط باستخدام CSV أو HTML
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="تقرير_المهام_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM للـ UTF-8
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            direction: rtl;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: right;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .stats {
            background-color: #f0f0f0;
            padding: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير المهام</h1>
        <p><strong>المشرف:</strong> <?php echo htmlspecialchars($supervisor['full_name']); ?></p>
        <p><strong>الرقم الوظيفي:</strong> <?php echo htmlspecialchars($supervisor['job_number']); ?></p>
        <p><strong>الفترة:</strong> من <?php echo date('Y-m-d', strtotime($date_from)); ?> إلى <?php echo date('Y-m-d', strtotime($date_to)); ?></p>
        <p><strong>تاريخ التقرير:</strong> <?php echo date('Y-m-d H:i'); ?></p>
    </div>

    <div class="stats">
        <h3>ملخص الإحصائيات</h3>
        <p>إجمالي المهام: <?php echo $stats['total']; ?></p>
        <p>مكتملة: <?php echo $stats['completed']; ?></p>
        <p>قيد التنفيذ: <?php echo $stats['in_progress']; ?></p>
        <p>معلقة: <?php echo $stats['pending']; ?></p>
    </div>

    <h3>المهام حسب العامل</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>اسم العامل</th>
                <th>الرقم الوظيفي</th>
                <th>إجمالي المهام</th>
                <th>مكتملة</th>
                <th>نسبة الإنجاز</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($workers_stats) > 0): ?>
                <?php foreach ($workers_stats as $index => $worker): ?>
                    <?php 
                    $completion_rate = $worker['task_count'] > 0 
                        ? round(($worker['completed_count'] / $worker['task_count']) * 100, 1) 
                        : 0;
                    ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($worker['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($worker['job_number']); ?></td>
                        <td><?php echo $worker['task_count']; ?></td>
                        <td><?php echo $worker['completed_count']; ?></td>
                        <td><?php echo $completion_rate; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">لا توجد بيانات</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h3>جميع المهام</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>اسم المهمة</th>
                <th>العامل</th>
                <th>الموقع</th>
                <th>الحالة</th>
                <th>الأولوية</th>
                <th>تاريخ التعيين</th>
                <th>تاريخ الإكمال</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($all_tasks) > 0): ?>
                <?php foreach ($all_tasks as $index => $task): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                        <td><?php echo htmlspecialchars($task['worker_name'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($task['location'] . ' - ' . $task['area'] . ' - ' . $task['building']); ?></td>
                        <td>
                            <?php 
                            $status_names = [
                                'completed' => 'مكتملة',
                                'in_progress' => 'قيد التنفيذ',
                                'pending' => 'معلقة',
                                'approved' => 'معتمدة'
                            ];
                            echo $status_names[$task['status']] ?? $task['status'];
                            ?>
                        </td>
                        <td>
                            <?php 
                            $priority_names = ['high' => 'عالي', 'medium' => 'متوسط', 'low' => 'منخفض'];
                            echo $priority_names[$task['priority']] ?? $task['priority'];
                            ?>
                        </td>
                        <td><?php echo $task['assigned_at'] ? date('Y-m-d H:i', strtotime($task['assigned_at'])) : '-'; ?></td>
                        <td><?php echo $task['completed_at'] ? date('Y-m-d H:i', strtotime($task['completed_at'])) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">لا توجد بيانات</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

