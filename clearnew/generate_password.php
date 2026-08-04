<?php
// generate_password.php - أداة لتوليد كلمات المرور المشفرة
// استخدم هذا الملف لتوليد كلمات مرور مشفرة للمستخدمين

// حذف هذا الملف بعد الاستخدام في الإنتاج!

$passwords = [
    'admin123',
    'supervisor123',
    'worker123'
];

echo "<h2>كلمات المرور المشفرة:</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>كلمة المرور الأصلية</th><th>كلمة المرور المشفرة</th></tr>";

foreach ($passwords as $password) {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    echo "<tr>";
    echo "<td>$password</td>";
    echo "<td style='font-family: monospace; word-break: break-all;'>$hashed</td>";
    echo "</tr>";
}

echo "</table>";
echo "<br><br>";
echo "<strong>ملاحظة:</strong> انسخ كلمات المرور المشفرة والصقها في ملف database.sql";
echo "<br><br>";
echo "<strong>تحذير:</strong> احذف هذا الملف بعد الاستخدام!";
?>

