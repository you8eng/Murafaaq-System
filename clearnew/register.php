<?php
// register.php - صفحة إنشاء حساب جديد
require_once 'config.php';
require_once 'includes/lang.php';

$error = '';
$success = '';

// معالجة إنشاء الحساب
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // جمع البيانات
    $job_number = trim($_POST['job_number']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $location = trim($_POST['location']);
    
    // التحقق من البيانات
    if (empty($job_number) || empty($full_name) || empty($password) || empty($role)) {
        $error = $lang['register_error'];
    } elseif (strlen($password) < 6) {
        $error = $lang['password_short'];
    } elseif ($password !== $confirm_password) {
        $error = $lang['password_mismatch'];
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $lang['invalid_email'];
    } else {
        try {
            // التحقق من وجود الرقم الوظيفي
            $stmt = $pdo->prepare("SELECT id FROM users WHERE job_number = ?");
            $stmt->execute([$job_number]);
            if ($stmt->fetch()) {
                $error = $lang['job_number_exists'];
            } else {
                // تشفير كلمة المرور
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // إضافة المستخدم الجديد
                $stmt = $pdo->prepare("INSERT INTO users (job_number, full_name, email, phone, password, role, location, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$job_number, $full_name, $email ?: null, $phone ?: null, $hashed_password, $role, $location ?: null]);
                
                $success = $lang['register_success'];
                
                // إعادة توجيه بعد 2 ثانية
                header("refresh:2;url=login.php");
            }
        } catch (PDOException $e) {
            if (ini_get('display_errors')) {
                $error = $lang['register_error'] . ': ' . $e->getMessage();
            } else {
                $error = $lang['register_error'];
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
    <title><?php echo $lang['register']; ?> - <?php echo $lang['site_title']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100 py-5">
            <div class="col-md-8 col-lg-6">
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
                            <i class="fas fa-user-plus fa-3x text-white"></i>
                        </div>
                        <h3 class="fw-bold"><?php echo $lang['register']; ?></h3>
                        <p class="text-muted"><?php echo $lang['register_desc']; ?></p>
                    </div>

                    <!-- رسائل النجاح -->
                    <?php if (isset($success) && !empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- رسائل الخطأ -->
                    <?php if (isset($error) && !empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- نموذج إنشاء الحساب -->
                    <form method="POST" action="" id="registerForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="job_number" class="form-label">
                                    <i class="fas fa-id-card me-2"></i>
                                    <?php echo $lang['job_number']; ?> <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="job_number" 
                                       name="job_number" 
                                       required
                                       placeholder="<?php echo $lang['enter_job_number']; ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo $lang['full_name']; ?> <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="full_name" 
                                       name="full_name" 
                                       required
                                       placeholder="<?php echo $lang['enter_full_name']; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>
                                    <?php echo $lang['email']; ?>
                                </label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       placeholder="<?php echo $lang['enter_email']; ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">
                                    <i class="fas fa-phone me-2"></i>
                                    <?php echo $lang['phone']; ?>
                                </label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       placeholder="<?php echo $lang['enter_phone']; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>
                                    <?php echo $lang['password']; ?> <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required
                                       minlength="6"
                                       placeholder="<?php echo $lang['enter_password']; ?>">
                                <small class="form-text text-muted"><?php echo $lang['password_short']; ?></small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>
                                    <?php echo $lang['confirm_password']; ?> <span class="text-danger">*</span>
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       required
                                       minlength="6"
                                       placeholder="<?php echo $lang['confirm_password']; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">
                                    <i class="fas fa-user-tag me-2"></i>
                                    <?php echo $lang['role']; ?> <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value=""><?php echo $lang['select_role']; ?></option>
                                    <option value="admin"><?php echo $lang['role_admin']; ?></option>
                                    <option value="supervisor"><?php echo $lang['role_supervisor']; ?></option>
                                    <option value="worker"><?php echo $lang['role_worker']; ?></option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo $lang['location']; ?>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="location" 
                                       name="location" 
                                       placeholder="<?php echo $lang['enter_location']; ?>">
                            </div>
                        </div>

                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>
                                <?php echo $lang['register']; ?>
                            </button>
                        </div>

                        <div class="text-center">
                            <p class="mb-2">
                                <?php echo $lang['have_account']; ?>
                                <a href="login.php" class="text-decoration-none fw-bold">
                                    <?php echo $lang['login']; ?>
                                </a>
                            </p>
                            <a href="index.php" class="text-decoration-none">
                                <i class="fas fa-home me-1"></i>
                                <?php echo $lang['back_to_home']; ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // التحقق من تطابق كلمات المرور
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('<?php echo $lang['password_mismatch']; ?>');
                return false;
            }
        });
    </script>
</body>
</html>

