<?php
// index.php - الصفحة الرئيسية
require_once 'config.php';
require_once 'includes/lang.php';

// إذا كان المستخدم مسجل دخول، توجيهه للواجهة المناسبة
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header('Location: dashboard/admin/index.php');
            break;
        case 'supervisor':
            header('Location: dashboard/supervisor/index.php');
            break;
        case 'worker':
            header('Location: dashboard/worker/index.php');
            break;
    }
    exit();
} else {
    // إذا لم يكن مسجل دخول، توجيهه مباشرة لصفحة تسجيل الدخول
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['site_title']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="landing-page">
    <!-- الشعار -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="fas fa-sparkles fa-2x me-2"></i>
                <span class="fs-3 fw-bold"><?php echo $lang['site_title']; ?></span>
            </a>
            <div class="ms-auto">
                <a href="?lang=ar" class="btn btn-sm <?php echo $_SESSION['lang'] == 'ar' ? 'btn-light' : 'btn-outline-light'; ?>">العربية</a>
                <a href="?lang=en" class="btn btn-sm <?php echo $_SESSION['lang'] == 'en' ? 'btn-light' : 'btn-outline-light'; ?>">English</a>
            </div>
        </div>
    </nav>

    <!-- الهيدر -->
    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="d-flex gap-3">
                        <a href="login.php" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            <?php echo $lang['login']; ?>
                        </a>
                        <a href="#features" class="btn btn-outline-primary btn-lg px-4">
                            <?php echo $lang['learn_more']; ?>
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-sparkles fa-10x text-white opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- المميزات -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5"><?php echo $lang['features']; ?></h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-user-shield fa-3x text-primary"></i>
                        </div>
                        <h4><?php echo $lang['role_admin']; ?></h4>
                        <p><?php echo $lang['role_admin_desc']; ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-clipboard-check fa-3x text-primary"></i>
                        </div>
                        <h4><?php echo $lang['role_supervisor']; ?></h4>
                        <p><?php echo $lang['role_supervisor_desc']; ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-sparkles fa-3x text-primary"></i>
                        </div>
                        <h4><?php echo $lang['role_worker']; ?></h4>
                        <p><?php echo $lang['role_worker_desc']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- الفوتر -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>
                        <i class="fas fa-sparkles me-2"></i>
                        <?php echo $lang['site_title']; ?>
                    </h5>
                    <p><?php echo $lang['footer_desc']; ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo $lang['all_rights']; ?></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

