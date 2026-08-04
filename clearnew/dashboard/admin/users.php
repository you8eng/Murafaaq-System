<?php
// dashboard/admin/users.php - إدارة المستخدمين
require_once '../../config.php';
require_once '../../includes/lang.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login.php');
    exit();
}

$success = '';
$error = '';

// معالجة إنشاء/تحديث المستخدم
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? null;
    $job_number = trim($_POST['job_number']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'];
    $location = trim($_POST['location']);
    $status = $_POST['status'] ?? 'active';
    
    try {
        if ($action == 'create') {
            // التحقق من وجود الرقم الوظيفي
            $stmt = $pdo->prepare("SELECT id FROM users WHERE job_number = ?");
            $stmt->execute([$job_number]);
            if ($stmt->fetch()) {
                $error = $lang['job_number_exists'];
            } elseif (empty($password) || strlen($password) < 6) {
                $error = $lang['password_short'];
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (job_number, full_name, email, phone, password, role, location, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$job_number, $full_name, $email ?: null, $phone ?: null, $hashed_password, $role, $location ?: null, $status]);
                $success = $lang['user_created'];
            }
        } elseif ($action == 'update' && $id) {
            // التحقق من وجود الرقم الوظيفي (للمستخدمين الآخرين)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE job_number = ? AND id != ?");
            $stmt->execute([$job_number, $id]);
            if ($stmt->fetch()) {
                $error = $lang['job_number_exists'];
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $error = $lang['password_short'];
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET job_number = ?, full_name = ?, email = ?, phone = ?, password = ?, role = ?, location = ?, status = ? WHERE id = ?");
                        $stmt->execute([$job_number, $full_name, $email ?: null, $phone ?: null, $hashed_password, $role, $location ?: null, $status, $id]);
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET job_number = ?, full_name = ?, email = ?, phone = ?, role = ?, location = ?, status = ? WHERE id = ?");
                    $stmt->execute([$job_number, $full_name, $email ?: null, $phone ?: null, $role, $location ?: null, $status, $id]);
                }
                if (empty($error)) {
                    $success = $lang['user_updated'];
                }
            }
        }
    } catch (PDOException $e) {
        $error = $lang['system_error'] . (ini_get('display_errors') ? ': ' . $e->getMessage() : '');
    }
}

// معالجة حذف المستخدم
if (isset($_GET['delete']) && isset($_GET['id'])) {
    try {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $success = $lang['user_deleted'];
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// جلب المستخدم للتعديل
$edit_user = null;
if (isset($_GET['edit']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $edit_user = $stmt->fetch();
    } catch (PDOException $e) {
        $error = $lang['system_error'];
    }
}

// جلب جميع المستخدمين
try {
    $search = $_GET['search'] ?? '';
    $role_filter = $_GET['role'] ?? '';
    
    $query = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (full_name LIKE ? OR job_number LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($role_filter)) {
        $query .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $error = $lang['system_error'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['users_management']; ?> - <?php echo $lang['site_title']; ?></title>
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
                            <a class="nav-link active" href="users.php">
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
                        <i class="fas fa-users me-2 text-primary"></i>
                        <?php echo $lang['users_management']; ?>
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

                <!-- نموذج إنشاء/تعديل المستخدم -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-<?php echo $edit_user ? 'edit' : 'plus'; ?> me-2"></i>
                            <?php echo $edit_user ? $lang['edit'] : $lang['create_user']; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="<?php echo $edit_user ? 'update' : 'create'; ?>">
                            <?php if ($edit_user): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo $lang['job_number']; ?> *</label>
                                    <input type="text" class="form-control" name="job_number" 
                                           value="<?php echo htmlspecialchars($edit_user['job_number'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo $lang['full_name']; ?> *</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo htmlspecialchars($edit_user['full_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo $lang['email']; ?></label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo $lang['phone']; ?></label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars($edit_user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo $lang['password']; ?> <?php echo $edit_user ? '(اتركه فارغاً للاحتفاظ بالكلمة الحالية)' : '*'; ?></label>
                                    <input type="password" class="form-control" name="password" 
                                           minlength="6" <?php echo $edit_user ? '' : 'required'; ?>>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo $lang['role']; ?> *</label>
                                    <select class="form-select" name="role" required>
                                        <option value="admin" <?php echo ($edit_user['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>
                                            <?php echo $lang['role_admin']; ?>
                                        </option>
                                        <option value="supervisor" <?php echo ($edit_user['role'] ?? '') == 'supervisor' ? 'selected' : ''; ?>>
                                            <?php echo $lang['role_supervisor']; ?>
                                        </option>
                                        <option value="worker" <?php echo ($edit_user['role'] ?? '') == 'worker' ? 'selected' : ''; ?>>
                                            <?php echo $lang['role_worker']; ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo $lang['location']; ?></label>
                                    <input type="text" class="form-control" name="location" 
                                           value="<?php echo htmlspecialchars($edit_user['location'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo $lang['status']; ?> *</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active" <?php echo ($edit_user['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>
                                            <?php echo $lang['active']; ?>
                                        </option>
                                        <option value="inactive" <?php echo ($edit_user['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>
                                            <?php echo $lang['inactive']; ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    <?php echo $lang['save']; ?>
                                </button>
                                <?php if ($edit_user): ?>
                                    <a href="users.php" class="btn btn-secondary">
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
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="<?php echo $lang['search']; ?>" 
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="role">
                                    <option value=""><?php echo $lang['all_users']; ?></option>
                                    <option value="admin" <?php echo ($_GET['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>
                                        <?php echo $lang['role_admin']; ?>
                                    </option>
                                    <option value="supervisor" <?php echo ($_GET['role'] ?? '') == 'supervisor' ? 'selected' : ''; ?>>
                                        <?php echo $lang['role_supervisor']; ?>
                                    </option>
                                    <option value="worker" <?php echo ($_GET['role'] ?? '') == 'worker' ? 'selected' : ''; ?>>
                                        <?php echo $lang['role_worker']; ?>
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i>
                                    <?php echo $lang['filter']; ?>
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="users.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-redo me-1"></i>
                                    <?php echo $lang['reset']; ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- جدول المستخدمين -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            <?php echo $lang['all_users']; ?> (<?php echo count($users); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo $lang['job_number']; ?></th>
                                        <th><?php echo $lang['full_name']; ?></th>
                                        <th><?php echo $lang['email']; ?></th>
                                        <th><?php echo $lang['phone']; ?></th>
                                        <th><?php echo $lang['role']; ?></th>
                                        <th><?php echo $lang['location']; ?></th>
                                        <th><?php echo $lang['status']; ?></th>
                                        <th><?php echo $lang['actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $index => $user): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($user['job_number']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $lang['role_' . $user['role']]; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['location'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                        <?php echo $lang[$user['status']]; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="users.php?edit=1&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <a href="users.php?delete=1&id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('<?php echo $lang['confirm_delete']; ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
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

