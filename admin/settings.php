<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$db = db();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    if (isset($_POST['save_settings'])) {
        $fields = ['site_name', 'site_description', 'contact_email', 'social_facebook', 'social_twitter', 'social_instagram', 'social_whatsapp'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                setSetting($field, $_POST[$field]);
            }
        }
        $success = 'تم حفظ الإعدادات بنجاح';
    } elseif (isset($_POST['change_password'])) {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password) || strlen($new_password) < 6) {
            $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
        } elseif ($new_password !== $confirm_password) {
            $error = 'كلمتا المرور غير متطابقتين';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $_SESSION['admin_id']]);
            $success = 'تم تغيير كلمة المرور بنجاح';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <aside class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><?php echo e(SITE_NAME); ?></h2>
                <button class="close-sidebar" id="closeSidebar">&times;</button>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php">الرئيسية</a>
                <a href="articles.php">المقالات والأخبار</a>
                <a href="categories.php">الأقسام</a>
                <a href="ads.php">الإعلانات</a>
                <a href="radio-settings.php">إعدادات الراديو</a>
                <a href="radio-library.php">مكتبة الصوتيات</a>
                <a href="radio-playlists.php">البلاي ليست</a>
                <a href="radio-schedule.php">جدولة البث</a>
                <a href="radio-live.php">الاستوديو المباشر</a>
                <a href="settings.php" class="active">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>الإعدادات العامة</h1>
            </header>
            
            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="content-panel">
                        <div class="panel-header">
                            <h2>إعدادات الموقع</h2>
                        </div>
                        <form method="POST" action="">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="save_settings" value="1">
                            <div class="form-group">
                                <label for="site_name">اسم الموقع</label>
                                <input type="text" name="site_name" id="site_name" class="form-control" value="<?php echo e(getSetting('site_name', 'النقب الإخباري')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="site_description">وصف الموقع (SEO)</label>
                                <textarea name="site_description" id="site_description" class="form-control" rows="3"><?php echo e(getSetting('site_description', '')); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="contact_email">البريد الإلكتروني للتواصل</label>
                                <input type="email" name="contact_email" id="contact_email" class="form-control" value="<?php echo e(getSetting('contact_email', '')); ?>" dir="ltr">
                            </div>
                            
                            <h3 class="mt-4">روابط التواصل الاجتماعي</h3>
                            <div class="form-group">
                                <label for="social_facebook">فيسبوك</label>
                                <input type="url" name="social_facebook" id="social_facebook" class="form-control" value="<?php echo e(getSetting('social_facebook', '')); ?>" dir="ltr">
                            </div>
                            <div class="form-group">
                                <label for="social_twitter">تويتر / X</label>
                                <input type="url" name="social_twitter" id="social_twitter" class="form-control" value="<?php echo e(getSetting('social_twitter', '')); ?>" dir="ltr">
                            </div>
                            <div class="form-group">
                                <label for="social_instagram">انستجرام</label>
                                <input type="url" name="social_instagram" id="social_instagram" class="form-control" value="<?php echo e(getSetting('social_instagram', '')); ?>" dir="ltr">
                            </div>
                            <div class="form-group">
                                <label for="social_whatsapp">واتساب (رقم)</label>
                                <input type="text" name="social_whatsapp" id="social_whatsapp" class="form-control" value="<?php echo e(getSetting('social_whatsapp', '')); ?>" dir="ltr">
                            </div>
                            
                            <button type="submit" class="btn btn-primary mt-3">حفظ الإعدادات</button>
                        </form>
                    </div>
                    
                    <div class="content-panel">
                        <div class="panel-header">
                            <h2>تغيير كلمة المرور</h2>
                        </div>
                        <form method="POST" action="">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label for="new_password">كلمة المرور الجديدة</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">تأكيد كلمة المرور</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-danger mt-3">تغيير كلمة المرور</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>
