<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    header("Location: " . SITE_URL . "/admin/login.php");
    exit;
}

$db = db();

// Stats
$totalArticles = $db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
$totalViews = $db->query("SELECT SUM(views) FROM articles")->fetchColumn() ?: 0;
$totalAds = $db->query("SELECT COUNT(*) FROM ads")->fetchColumn();
$totalCategories = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Recent 5 articles
$stmt = $db->query("SELECT a.id, a.title, c.name as category_name, a.type, a.status, a.created_at 
                    FROM articles a 
                    LEFT JOIN categories c ON a.category_id = c.id 
                    ORDER BY a.created_at DESC LIMIT 5");
$recentArticles = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرئيسية - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
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
                <a href="index.php" class="active">الرئيسية</a>
                <a href="articles.php">المقالات والأخبار</a>
                <a href="categories.php">الأقسام</a>
                <a href="ads.php">الإعلانات</a>
                <a href="radio-settings.php">إعدادات الراديو</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>لوحة التحكم</h1>
                <div class="user-info">
                    أهلاً بك، المشرف
                </div>
            </header>
            
            <div class="admin-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>المقالات والأخبار</h3>
                        <div class="stat-value"><?php echo number_format($totalArticles); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>إجمالي المشاهدات</h3>
                        <div class="stat-value"><?php echo number_format($totalViews); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>الإعلانات</h3>
                        <div class="stat-value"><?php echo number_format($totalAds); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>الأقسام</h3>
                        <div class="stat-value"><?php echo number_format($totalCategories); ?></div>
                    </div>
                </div>
                
                <div class="content-panel">
                    <div class="panel-header">
                        <h2>أحدث المقالات والأخبار</h2>
                        <a href="article-edit.php" class="btn btn-primary">إضافة جديد</a>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>العنوان</th>
                                    <th>القسم</th>
                                    <th>النوع</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentArticles as $article): ?>
                                <tr>
                                    <td><?php echo e($article['title']); ?></td>
                                    <td><?php echo e($article['category_name']); ?></td>
                                    <td><?php echo $article['type'] == 'news' ? 'خبر' : 'مقال'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $article['status'] == 'published' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo $article['status'] == 'published' ? 'منشور' : 'مسودة'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo arabicDate($article['created_at']); ?></td>
                                    <td>
                                        <a href="article-edit.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-ghost">تعديل</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentArticles)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">لا توجد مقالات بعد</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>
