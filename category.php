<?php
// category.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: " . SITE_URL);
    exit;
}

$category = getCategoryById($id);
if (!$category) {
    header("HTTP/1.0 404 Not Found");
    exit("القسم غير موجود");
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ARTICLES_PER_PAGE;

$results = getLatestArticles($page, $perPage, $id);
$articles = $results['articles'];
$total = $results['total'];
$totalPages = ceil($total / $perPage);

$pageTitle = $category['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="category-header-banner">
    <div class="category-badge-pill"><i class="fas fa-folder-open"></i> قسم</div>
    <h1 class="category-banner-title"><?= e($category['name']) ?></h1>
    <p class="category-banner-subtitle">أحدث الأخبار والتقارير في قسم <?= e($category['name']) ?> (<?= $total ?> مادة إخبارية)</p>
</div>

<?php if (empty($articles)): ?>
    <div class="empty-state-box">
        <i class="far fa-newspaper empty-icon"></i>
        <h3>لا توجد مقالات في هذا القسم حالياً</h3>
        <p>سيتم نشر الأخبار والمستجدات الخاصة بهذا القسم قريباً.</p>
        <a href="<?= SITE_URL ?>/" class="btn-back-home"><i class="fas fa-home"></i> العودة للرئيسية</a>
    </div>
<?php else: ?>
    <div class="articles-grid">
        <?php foreach ($articles as $article): ?>
        <article class="article-card">
            <a href="<?= SITE_URL ?>/article.php?id=<?= $article['id'] ?>" class="card-img-wrap">
                <img src="<?= articleImage($article['image']) ?>" alt="<?= e($article['title']) ?>" loading="lazy">
            </a>
            <div class="card-content">
                <h3 class="card-title">
                    <a href="<?= SITE_URL ?>/article.php?id=<?= $article['id'] ?>"><?= e($article['title']) ?></a>
                </h3>
                <p class="card-excerpt"><?= e(excerpt($article['excerpt'] ?: $article['body'], 20)) ?></p>
                <div class="card-meta">
                    <span><i class="far fa-clock"></i> <?= timeAgo($article['published_at']) ?></span>
                    <?php if($article['views'] > 0): ?>
                    <span><i class="far fa-eye"></i> <?= number_format($article['views']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?= paginationHtml($page, $totalPages, SITE_URL . "/category.php?id=$id") ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
