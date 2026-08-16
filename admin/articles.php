<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$db = db();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
$type = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;

$where = [];
$params = [];

if ($categoryId) {
    $where[] = "a.category_id = ?";
    $params[] = $categoryId;
}
if ($type) {
    $where[] = "a.type = ?";
    $params[] = $type;
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$totalStmt = $db->prepare("SELECT COUNT(*) FROM articles a $whereClause");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();

$perPage = defined('ADMIN_ARTICLES_PER_PAGE') ? ADMIN_ARTICLES_PER_PAGE : 20;
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$query = "SELECT a.id, a.title, c.name as category_name, a.type, a.status, a.views, a.created_at 
          FROM articles a 
          LEFT JOIN categories c ON a.category_id = c.id 
          $whereClause 
          ORDER BY a.created_at DESC 
          LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$categories = getCategories(false);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المقالات والأخبار - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
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
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>المقالات والأخبار</h1>
            </header>
            
            <div class="admin-content">
                <div class="content-panel">
                    <div class="panel-header">
                        <form class="filter-form" method="GET" action="articles.php">
                            <select name="category_id" class="form-control form-control-sm">
                                <option value="">كل الأقسام</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="type" class="form-control form-control-sm">
                                <option value="">كل الأنواع</option>
                                <option value="news" <?php echo $type == 'news' ? 'selected' : ''; ?>>خبر</option>
                                <option value="article" <?php echo $type == 'article' ? 'selected' : ''; ?>>مقال</option>
                            </select>
                            <button type="submit" class="btn btn-ghost btn-sm">تصفية</button>
                        </form>
                        <a href="article-edit.php" class="btn btn-primary">إضافة جديد</a>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>العنوان</th>
                                    <th>القسم</th>
                                    <th>النوع</th>
                                    <th>المشاهدات</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($articles as $article): ?>
                                <tr id="row-<?php echo $article['id']; ?>">
                                    <td><?php echo $article['id']; ?></td>
                                    <td><?php echo e($article['title']); ?></td>
                                    <td><?php echo e($article['category_name']); ?></td>
                                    <td><?php echo $article['type'] == 'news' ? 'خبر' : 'مقال'; ?></td>
                                    <td><?php echo $article['views']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $article['status'] == 'published' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo $article['status'] == 'published' ? 'منشور' : 'مسودة'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo arabicDate($article['created_at']); ?></td>
                                    <td>
                                        <a href="article-edit.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-ghost">تعديل</a>
                                        <button class="btn btn-sm btn-danger delete-article-btn" data-id="<?php echo $article['id']; ?>">حذف</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($articles)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">لا توجد مقالات</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php echo paginationHtml($page, $totalPages, "?category_id=$categoryId&type=$type"); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        const csrfTokenStr = '<?php echo csrfToken(); ?>';
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>
