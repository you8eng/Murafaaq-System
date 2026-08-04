<?php
// login.php - صفحة تسجيل الدخول
require_once 'config.php';
require_once 'includes/lang.php';

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // تنظيف الرقم الوظيفي (فقط trim بدون htmlspecialchars للاستعلامات)
    $job_number = trim($_POST['job_number']);
    $password = $_POST['password'];
    
    // التحقق من وجود البيانات
    if (empty($job_number) || empty($password)) {
        $error = $lang['login_error'];
    } else {
        try {
            // البحث عن المستخدم (التحقق من الحالة النشطة أيضاً)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE job_number = ? AND status = 'active'");
            $stmt->execute([$job_number]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // تخزين بيانات الجلسة
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['job_number'] = $user['job_number'];
                
                // توجيه حسب الدور
                switch ($user['role']) {
                    case 'admin':
                        header('Location: dashboard/admin/index.php');
                        break;
                    case 'supervisor':
                        header('Location: dashboard/supervisor/index.php');
                        break;
                    case 'worker':
                        header('Location: dashboard/worker/index.php');
                        break;
                    default:
                        $error = $lang['login_error'];
                        break;
                }
                if (!isset($error)) {
                    exit();
                }
            } else {
                $error = $lang['login_error'];
            }
        } catch (PDOException $e) {
            // في وضع التطوير، عرض الخطأ للمساعدة في التشخيص
            if (ini_get('display_errors')) {
                $error = $lang['system_error'] . ': ' . $e->getMessage();
            } else {
                $error = $lang['system_error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['login']; ?> - <?php echo $lang['site_title']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <div class="login-card shadow-lg p-4">
                    <!-- زر تبديل اللغة -->
                    <div class="text-end mb-3">
                        <a href="?lang=<?php echo $_SESSION['lang'] == 'ar' ? 'en' : 'ar'; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-language me-1"></i>
                            <?php echo $_SESSION['lang'] == 'ar' ? 'English' : 'العربية'; ?>
                        </a>
                    </div>
                    
                    <!-- الشعار -->
                    <div class="text-center mb-4">
                        <div class="login-logo mb-3">
                            <i class="fas fa-sparkles fa-3x text-white"></i>
                        </div>
                        <h3 class="fw-bold">
                            <i class="fas fa-sparkles me-2 text-primary"></i>
                            <?php echo $lang['site_title']; ?>
                        </h3>
                    </div>

                    <!-- رسائل الخطأ -->
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- نموذج تسجيل الدخول -->
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="job_number" class="form-label">
                                <i class="fas fa-id-card me-2"></i>
                                <?php echo $lang['job_number']; ?>
                            </label>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="job_number" 
                                   name="job_number" 
                                   required
                                   placeholder="<?php echo $lang['enter_job_number']; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-2"></i>
                                <?php echo $lang['password']; ?>
                            </label>
                            <input type="password" 
                                   class="form-control form-control-lg" 
                                   id="password" 
                                   name="password" 
                                   required
                                   placeholder="<?php echo $lang['enter_password']; ?>">
                        </div>

                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                <?php echo $lang['login']; ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- بيانات تجريبية -->
                        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

