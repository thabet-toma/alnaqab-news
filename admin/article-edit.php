<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$db = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$article = [
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'body' => '',
    'image' => '',
    'category_id' => '',
    'type' => 'news',
    'is_featured' => 0,
    'is_breaking' => 0,
    'author_name' => '',
    'status' => 'published'
];

if ($isEdit) {
    $article = getArticleById($id);
    if (!$article) {
        die('المقال غير موجود');
    }
}

$categories = getCategories(false);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $type = in_array($_POST['type'] ?? '', ['news', 'article']) ? $_POST['type'] : 'news';
    $author_name = trim($_POST['author_name'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'draft';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_breaking = isset($_POST['is_breaking']) ? 1 : 0;
    
    if (empty($slug)) {
        $slug = slugify($title);
    }
    
    $imagePath = $article['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploaded = uploadImage($_FILES['image'], 'articles');
        if ($uploaded) {
            $imagePath = $uploaded;
        } else {
            $error = 'فشل رفع الصورة';
        }
    }
    
    if (empty($title) || empty($body) || empty($category_id)) {
        $error = 'الرجاء تعبئة الحقول المطلوبة (العنوان، المحتوى، القسم)';
    }
    
    if (empty($error)) {
        if ($isEdit) {
            $stmt = $db->prepare("UPDATE articles SET 
                title = ?, slug = ?, excerpt = ?, body = ?, image = ?, 
                category_id = ?, type = ?, is_featured = ?, is_breaking = ?, 
                author_name = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([
                $title, $slug, $excerpt, $body, $imagePath,
                $category_id, $type, $is_featured, $is_breaking,
                $author_name, $status, $id
            ]);
            $success = 'تم تحديث المقال بنجاح';
            $article = getArticleById($id);
        } else {
            $stmt = $db->prepare("INSERT INTO articles 
                (title, slug, excerpt, body, image, category_id, type, is_featured, is_breaking, author_name, status, published_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $title, $slug, $excerpt, $body, $imagePath,
                $category_id, $type, $is_featured, $is_breaking,
                $author_name, $status
            ]);
            header("Location: articles.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'تعديل' : 'إضافة'; ?> مقال - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
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
                <a href="articles.php" class="active">المقالات والأخبار</a>
                <a href="categories.php">الأقسام</a>
                <a href="ads.php">الإعلانات</a>
                <a href="radio-settings.php">إعدادات الراديو</a>
                <a href="radio-library.php">مكتبة الصوتيات</a>
                <a href="radio-playlists.php">البلاي ليست</a>
                <a href="radio-schedule.php">جدولة البث</a>
                <a href="radio-live.php">الاستوديو المباشر</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1><?php echo $isEdit ? 'تعديل مقال' : 'إضافة مقال جديد'; ?></h1>
            </header>
            
            <div class="admin-content">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" class="content-panel">
                    <?php echo csrfField(); ?>
                    
                    <div class="form-grid">
                        <div class="form-main">
                            <div class="form-group">
                                <label for="title">العنوان *</label>
                                <input type="text" name="title" id="title" class="form-control" value="<?php echo e($article['title'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="slug">الرابط (Slug)</label>
                                <input type="text" name="slug" id="slug" class="form-control" value="<?php echo e($article['slug'] ?? ''); ?>">
                                <small>اتركه فارغاً للتوليد التلقائي</small>
                            </div>
                            <div class="form-group">
                                <label for="excerpt">مقتطف (ملخص)</label>
                                <textarea name="excerpt" id="excerpt" class="form-control" rows="3"><?php echo e($article['excerpt'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="body">المحتوى *</label>
                                <textarea name="body" id="body" class="form-control" rows="15" required><?php echo e($article['body'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-sidebar">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="published" <?php echo ($article['status'] ?? '') == 'published' ? 'selected' : ''; ?>>منشور</option>
                                    <option value="draft" <?php echo ($article['status'] ?? '') == 'draft' ? 'selected' : ''; ?>>مسودة</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="type">النوع</label>
                                <select name="type" id="type" class="form-control">
                                    <option value="news" <?php echo ($article['type'] ?? '') == 'news' ? 'selected' : ''; ?>>خبر</option>
                                    <option value="article" <?php echo ($article['type'] ?? '') == 'article' ? 'selected' : ''; ?>>مقال</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="category_id">القسم *</label>
                                <select name="category_id" id="category_id" class="form-control" required>
                                    <option value="">اختر القسم</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($article['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="author_name">اسم الكاتب</label>
                                <input type="text" name="author_name" id="author_name" class="form-control" value="<?php echo e($article['author_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="image">الصورة البارزة</label>
                                <input type="file" name="image" id="image" class="form-control" accept="image/*">
                                <?php if (!empty($article['image'])): ?>
                                    <div class="mt-2">
                                        <img src="<?php echo UPLOADS_URL . '/' . $article['image']; ?>" alt="صورة المقال" style="max-width: 100%; border-radius: 4px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_featured" value="1" <?php echo !empty($article['is_featured']) ? 'checked' : ''; ?>>
                                    مقال مميز
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_breaking" value="1" <?php echo !empty($article['is_breaking']) ? 'checked' : ''; ?>>
                                    خبر عاجل
                                </label>
                            </div>
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary w-100">حفظ المقال</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script>
        // Auto slugify
        document.getElementById('title').addEventListener('blur', function() {
            var slug = document.getElementById('slug');
            if (slug.value === '') {
                var text = this.value;
                slug.value = text.toLowerCase().trim()
                    .replace(/[^\w\s-أ-ي]/g, '')
                    .replace(/[\s_-]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        });
    </script>
</body>
</html>
