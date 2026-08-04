<?php
// dashboard/admin/tasks.php - إدارة المهام
require_once '../../config.php';
require_once '../../includes/lang.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login.php');
    exit();
}

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
    $supervisor_id = $_POST['supervisor_id'] ?: null;
    $worker_id = $_POST['worker_id'] ?: null;
    $status = $_POST['status'];
    $priority = $_POST['priority'];
    $due_date = $_POST['due_date'] ?: null;
    
    try {
        if ($action == 'create') {
            $stmt = $pdo->prepare("INSERT INTO tasks (title, description, location, area, building, floor, supervisor_id, worker_id, status, priority, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description ?: null, $location, $area, $building, $floor, $supervisor_id, $worker_id, $status, $priority, $due_date]);
            $success = $lang['task_created'];
        } elseif ($action == 'update' && $id) {
            $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, location = ?, area = ?, building = ?, floor = ?, supervisor_id = ?, worker_id = ?, status = ?, priority = ?, due_date = ? WHERE id = ?");
            $stmt->execute([$title, $description ?: null, $location, $area, $building, $floor, $supervisor_id, $worker_id, $status, $priority, $due_date, $id]);
            $success = $lang['task_updated'];
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'] . (ini_get('display_errors') ? ': ' . $e->getMessage() : '');
    }
}

// معالجة حذف المهمة
if (isset($_GET['delete']) && isset($_GET['id'])) {
    try {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $success = $lang['task_deleted'];
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// جلب المهمة للتعديل
$edit_task = null;
if (isset($_GET['edit']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $edit_task = $stmt->fetch();
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// جلب المشرفين والعمال
try {
    $stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('supervisor', 'admin') AND status = 'active' ORDER BY full_name");
    $supervisors = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'worker' AND status = 'active' ORDER BY full_name");
    $workers = $stmt->fetchAll();
} catch (PDOException $e) {
    $supervisors = [];
    $workers = [];
}

// جلب جميع المهام
try {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $query = "SELECT t.*, 
                     s.full_name as supervisor_name, 
                     w.full_name as worker_name 
              FROM tasks t 
              LEFT JOIN users s ON t.supervisor_id = s.id 
              LEFT JOIN users w ON t.worker_id = w.id 
              WHERE 1=1";
    $params = [];
    
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
    <title><?php echo $lang['tasks']; ?> - <?php echo $lang['site_title']; ?></title>
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
                            <a class="nav-link active" href="tasks.php">
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
                        <i class="fas fa-tasks me-2 text-primary"></i>
                        <?php echo $lang['tasks']; ?>
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

                <!-- نموذج إنشاء/تعديل المهمة -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $edit_task ? 'edit' : 'plus'; ?> me-2"></i>
                            <?php echo $edit_task ? $lang['edit'] : $lang['add_new']; ?> <?php echo $lang['tasks']; ?>
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
                                    <label class="form-label"><?php echo $lang['supervisor']; ?></label>
                                    <select class="form-select" name="supervisor_id">
                                        <option value="">-- <?php echo $lang['select_role']; ?> --</option>
                                        <?php foreach ($supervisors as $supervisor): ?>
                                            <option value="<?php echo $supervisor['id']; ?>" 
                                                <?php echo ($edit_task['supervisor_id'] ?? '') == $supervisor['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supervisor['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo $lang['worker']; ?></label>
                                    <select class="form-select" name="worker_id">
                                        <option value="">-- <?php echo $lang['select_role']; ?> --</option>
                                        <?php foreach ($workers as $worker): ?>
                                            <option value="<?php echo $worker['id']; ?>" 
                                                <?php echo ($edit_task['worker_id'] ?? '') == $worker['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($worker['full_name']); ?>
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
                                        <option value="approved" <?php echo ($edit_task['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>
                                            <?php echo $lang['approved']; ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
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
                                <?php if ($edit_task): ?>
                                    <a href="tasks.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        <?php echo $lang['cancel']; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- البحث والتصفية -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-6">
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
                                    <option value="approved" <?php echo ($_GET['status'] ?? '') == 'approved' ? 'selected' : ''; ?>>
                                        <?php echo $lang['approved']; ?>
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i>
                                    <?php echo $lang['filter']; ?>
                                </button>
                            </div>
                            <div class="col-md-1">
                                <a href="tasks.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-redo"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- جدول المهام -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            <?php echo $lang['all_tasks']; ?> (<?php echo count($tasks); ?>)
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
                                        <th><?php echo $lang['supervisor']; ?></th>
                                        <th><?php echo $lang['worker']; ?></th>
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
                                                <td><?php echo htmlspecialchars($task['supervisor_name'] ?: '-'); ?></td>
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
                                                    <span class="badge bg-<?php 
                                                        echo $task['status'] == 'completed' ? 'success' : 
                                                             ($task['status'] == 'in_progress' ? 'warning' : 
                                                             ($task['status'] == 'approved' ? 'info' : 'secondary'));
                                                    ?>">
                                                        <?php echo $lang[$task['status']]; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : '-'; ?></td>
                                                <td>
                                                    <a href="tasks.php?edit=1&id=<?php echo $task['id']; ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="tasks.php?delete=1&id=<?php echo $task['id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('<?php echo $lang['confirm_delete']; ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
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
</body>
</html>

