<?php
// dashboard/supervisor/export_pdf.php - تصدير تقرير PDF
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
    
    // المهام المكتملة مع التفاصيل
    $stmt = $pdo->prepare("
        SELECT t.*, w.full_name as worker_name
        FROM tasks t
        LEFT JOIN users w ON t.worker_id = w.id
        WHERE t.supervisor_id = ?
        AND t.status = 'completed'
        AND DATE(t.completed_at) BETWEEN ? AND ?
        ORDER BY t.completed_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id, $date_from, $date_to]);
    $completed_tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("خطأ في جلب البيانات: " . $e->getMessage());
}

// استخدام مكتبة TCPDF أو إنشاء PDF بسيط
// سنستخدم مكتبة بسيطة عبر HTML to PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير المهام - PDF</title>
    <style>
        @page {
            margin: 20mm;
        }
        body {
            font-family: 'Arial', 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: right;
        }
        th {
            background: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #666;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 تقرير المهام</h1>
        <p><strong>المشرف:</strong> <?php echo htmlspecialchars($supervisor['full_name']); ?></p>
        <p><strong>الرقم الوظيفي:</strong> <?php echo htmlspecialchars($supervisor['job_number']); ?></p>
        <p><strong>الفترة:</strong> من <?php echo date('Y-m-d', strtotime($date_from)); ?> إلى <?php echo date('Y-m-d', strtotime($date_to)); ?></p>
        <p><strong>تاريخ التقرير:</strong> <?php echo date('Y-m-d H:i'); ?></p>
    </div>

    <div class="info-box">
        <h3>📈 ملخص الإحصائيات</h3>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">إجمالي المهام</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #28a745;"><?php echo $stats['completed']; ?></div>
            <div class="stat-label">مكتملة</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #ffc107;"><?php echo $stats['in_progress']; ?></div>
            <div class="stat-label">قيد التنفيذ</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #6c757d;"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">معلقة</div>
        </div>
    </div>

    <div class="info-box">
        <h3>👥 المهام حسب العامل</h3>
    </div>

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

    <?php if (count($completed_tasks) > 0): ?>
        <div class="info-box">
            <h3>✅ المهام المكتملة (آخر 50 مهمة)</h3>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم المهمة</th>
                    <th>العامل</th>
                    <th>الموقع</th>
                    <th>تاريخ الإكمال</th>
                    <th>الأولوية</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completed_tasks as $index => $task): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                        <td><?php echo htmlspecialchars($task['worker_name'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($task['location'] . ' - ' . $task['area'] . ' - ' . $task['building']); ?></td>
                        <td><?php echo $task['completed_at'] ? date('Y-m-d H:i', strtotime($task['completed_at'])) : '-'; ?></td>
                        <td>
                            <?php 
                            $priority_colors = ['high' => 'عالي', 'medium' => 'متوسط', 'low' => 'منخفض'];
                            echo $priority_colors[$task['priority']] ?? $task['priority'];
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        <p>تم إنشاء هذا التقرير تلقائياً من نظام إدارة المهام</p>
        <p>© <?php echo date('Y'); ?> - جميع الحقوق محفوظة</p>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>

