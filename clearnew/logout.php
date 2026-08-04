<?php
// logout.php - تسجيل الخروج
session_start();
session_destroy();
header('Location: index.php');
exit();
?>

