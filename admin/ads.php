<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$db = db();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    if (isset($_POST['add_ad'])) {
        $title = trim($_POST['title'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $position = $_POST['position'] ?? 'sidebar';
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $imagePath = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = uploadImage($_FILES['image'], 'ads');
        }
        
        if (empty($title) || empty($imagePath)) {
            $error = 'العنوان والصورة مطلوبان';
        } else {
            $stmt = $db->prepare("INSERT INTO ads (title, image, link, position, is_active, sort_order, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $imagePath, $link, $position, $is_active, $sort_order, $start_date, $end_date]);
            $success = 'تمت إضافة الإعلان بنجاح';
        }
    } elseif (isset($_POST['delete_ad'])) {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM ads WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'تم حذف الإعلان بنجاح';
    }
}

$stmt = $db->query("SELECT * FROM ads ORDER BY position, sort_order ASC, id DESC");
$ads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعلانات - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
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
                <a href="ads.php" class="active">الإعلانات</a>
                <a href="radio-settings.php">إعدادات الراديو</a>
                <a href="radio-library.php">مكتبة الصوتيات</a>
                <a href="radio-playlists.php">البلاي ليست</a>
                <a href="radio-schedule.php">جدولة البث</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>إدارة الإعلانات</h1>
            </header>
            
            <div class="admin-content">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="content-panel">
                        <div class="panel-header">
                            <h2>إضافة إعلان جديد</h2>
                        </div>
                        <form method="POST" action="" enctype="multipart/form-data">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="add_ad" value="1">
                            <div class="form-group">
                                <label for="title">عنوان الإعلان *</label>
                                <input type="text" name="title" id="title" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="image">صورة الإعلان *</label>
                                <input type="file" name="image" id="image" class="form-control" required accept="image/*">
                            </div>
                            <div class="form-group">
                                <label for="link">الرابط</label>
                                <input type="url" name="link" id="link" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="position">الموقع</label>
                                <select name="position" id="position" class="form-control">
                                    <option value="header">أعلى الموقع (Header)</option>
                                    <option value="sidebar">القائمة الجانبية (Sidebar)</option>
                                    <option value="between">بين المقالات (Between)</option>
                                    <option value="footer">أسفل الموقع (Footer)</option>
                                    <option value="popup">نافذة منبثقة (Popup)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="sort_order">الترتيب</label>
                                <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                            </div>
                            <div class="form-group">
                                <label for="start_date">تاريخ البدء</label>
                                <input type="date" name="start_date" id="start_date" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="end_date">تاريخ الانتهاء</label>
                                <input type="date" name="end_date" id="end_date" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_active" value="1" checked>
                                    نشط
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">إضافة الإعلان</button>
                        </form>
                    </div>
                    
                    <div class="content-panel">
                        <div class="panel-header">
                            <h2>الإعلانات الحالية</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>الصورة</th>
                                        <th>العنوان</th>
                                        <th>الموقع</th>
                                        <th>الحالة</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ads as $ad): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo UPLOADS_URL . '/' . $ad['image']; ?>" alt="Ad" style="max-width: 100px; max-height: 50px;">
                                        </td>
                                        <td><?php echo e($ad['title']); ?></td>
                                        <td><?php echo e($ad['position']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $ad['is_active'] ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo $ad['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="" style="display:inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="id" value="<?php echo $ad['id']; ?>">
                                                <button type="submit" name="delete_ad" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الإعلان؟');">حذف</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($ads)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">لا توجد إعلانات</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>
