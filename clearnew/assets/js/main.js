// main.js - الجافاسكريبت الرئيسي

// معالجة التنبيهات
document.addEventListener('DOMContentLoaded', function() {
    // إخفاء التنبيهات تلقائياً بعد 5 ثوان
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // تأكيد الحذف
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('هل أنت متأكد من الحذف؟')) {
                e.preventDefault();
            }
        });
    });

    // تحديث الوقت
    function updateTime() {
        const timeElements = document.querySelectorAll('.current-time');
        const now = new Date();
        const timeString = now.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
        timeElements.forEach(function(el) {
            el.textContent = timeString;
        });
    }
    setInterval(updateTime, 1000);
    updateTime();
});

