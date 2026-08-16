<?php
// search.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ARTICLES_PER_PAGE;

$pageTitle = 'نتائج البحث' . ($q ? ': ' . $q : '');
include __DIR__ . '/includes/header.php';

$results = searchArticles($q, $page, $perPage);
$articles = $results['articles'];
$total = $results['total'];
$totalPages = ceil($total / $perPage);
?>

<div class="search-header-banner">
    <div class="search-badge-pill"><i class="fas fa-search"></i> نتائج البحث</div>
    <h1 class="search-banner-title">البحث عن: "<?= e($q) ?>"</h1>
    <p class="search-banner-subtitle">تم العثور على <?= $total ?> مادة إخبارية مطابقة لكلمات البحث</p>
</div>

<?php if (empty($articles)): ?>
    <div class="empty-state-box">
        <i class="fas fa-search-minus empty-icon"></i>
        <h3>لم يتم العثور على أي نتائج</h3>
        <p>عذراً، لم نتمكن من العثور على أي مقالات تطابق بحثك. جرب استخدام كلمات مفتاحية أخرى أو تصفح الأقسام الرئيسية.</p>
        <a href="<?= SITE_URL ?>/" class="btn-back-home"><i class="fas fa-home"></i> العودة للرئيسية</a>
    </div>
<?php else: ?>
    <div class="articles-grid">
        <?php foreach ($articles as $article): ?>
        <article class="article-card">
            <a href="<?= SITE_URL ?>/article.php?id=<?= $article['id'] ?>" class="card-img-wrap">
                <?php if(isset($article['category_name'])): ?>
                <span class="card-category"><?= e($article['category_name']) ?></span>
                <?php endif; ?>
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
    <?= paginationHtml($page, $totalPages, SITE_URL . '/search.php?q=' . urlencode($q)) ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
