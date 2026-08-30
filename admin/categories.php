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
    
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($slug)) {
            $slug = slugify($name);
        }
        
        if (empty($name)) {
            $error = 'اسم القسم مطلوب';
        } else {
            $stmt = $db->prepare("INSERT INTO categories (name, slug, sort_order, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $sort_order, $is_active]);
            $success = 'تمت إضافة القسم بنجاح';
        }
    } elseif (isset($_POST['edit_category'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($slug)) {
            $slug = slugify($name);
        }
        
        if (empty($name)) {
            $error = 'اسم القسم مطلوب';
        } else {
            $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, sort_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $sort_order, $is_active, $id]);
            $success = 'تم تحديث القسم بنجاح';
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = (int)$_POST['id'];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE category_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $error = 'لا يمكن حذف القسم لأنه يحتوي على مقالات';
        } else {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'تم حذف القسم بنجاح';
        }
    }
}

$stmt = $db->query("SELECT * FROM categories ORDER BY sort_order ASC, id DESC");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأقسام - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
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
                <a href="categories.php" class="active">الأقسام</a>
                <a href="ads.php">الإعلانات</a>
                <a href="radio-settings.php">إعدادات الراديو</a>
                <a href="radio-library.php">مكتبة الصوتيات</a>
                <a href="radio-schedule.php">جدولة البث</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>إدارة الأقسام</h1>
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
                            <h2>إضافة قسم جديد</h2>
                        </div>
                        <form method="POST" action="">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="add_category" value="1">
                            <div class="form-group">
                                <label for="name">اسم القسم *</label>
                                <input type="text" name="name" id="name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="slug">الرابط (Slug)</label>
                                <input type="text" name="slug" id="slug" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="sort_order">الترتيب</label>
                                <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_active" value="1" checked>
                                    نشط
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">إضافة القسم</button>
                        </form>
                    </div>
                    
                    <div class="content-panel">
                        <div class="panel-header">
                            <h2>الأقسام الحالية</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>الاسم</th>
                                        <th>الترتيب</th>
                                        <th>الحالة</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <form method="POST" action="">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                            <td>
                                                <input type="text" name="name" value="<?php echo e($cat['name']); ?>" class="form-control form-control-sm" required>
                                                <input type="hidden" name="slug" value="<?php echo e($cat['slug']); ?>">
                                            </td>
                                            <td>
                                                <input type="number" name="sort_order" value="<?php echo $cat['sort_order']; ?>" class="form-control form-control-sm" style="width: 60px;">
                                            </td>
                                            <td>
                                                <input type="checkbox" name="is_active" value="1" <?php echo $cat['is_active'] ? 'checked' : ''; ?>>
                                            </td>
                                            <td>
                                                <button type="submit" name="edit_category" class="btn btn-sm btn-ghost">تحديث</button>
                                                <button type="submit" name="delete_category" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا القسم؟');">حذف</button>
                                            </td>
                                        </form>
                                    </tr>
                                    <?php endforeach; ?>
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
