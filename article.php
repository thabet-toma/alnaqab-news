<?php
// article.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("HTTP/1.0 404 Not Found");
    exit("المقال غير موجود");
}

$article = getArticleById($id);
if (!$article) {
    header("HTTP/1.0 404 Not Found");
    exit("المقال غير موجود");
}

// Increment views
incrementViews($id);

$pageTitle = $article['title'];
$metaDescription = excerpt($article['excerpt'] ?: $article['body'], 30);
if ($article['image']) {
    $metaImage = articleImage($article['image']);
}

include __DIR__ . '/includes/header.php';

$relatedArticles = getRelatedArticles($id, $article['category_id'], 4);
$currentUrl = urlencode(SITE_URL . '/article.php?id=' . $id);
$encodedTitle = urlencode($article['title']);
?>

<article class="single-article-box">
    <header class="single-article-header">
        <?php if($article['category_name']): ?>
        <a href="<?= SITE_URL ?>/category.php?id=<?= $article['category_id'] ?>" class="single-category-pill">
            <i class="fas fa-tag"></i> <?= e($article['category_name']) ?>
        </a>
        <?php endif; ?>
        
        <h1 class="single-article-title"><?= e($article['title']) ?></h1>
        
        <div class="single-article-meta">
            <div class="meta-item"><i class="far fa-clock"></i> <?= arabicDate($article['published_at']) ?></div>
            <div class="meta-item"><i class="far fa-user"></i> <?= e($article['author_name'] ?: 'فريق التحرير') ?></div>
            <div class="meta-item"><i class="far fa-eye"></i> <?= number_format($article['views'] + 1) ?> قراءة</div>
        </div>
    </header>

    <?php if ($article['image']): ?>
    <div class="single-hero-img-wrap">
        <img src="<?= articleImage($article['image']) ?>" alt="<?= e($article['title']) ?>" class="single-hero-img">
    </div>
    <?php endif; ?>

    <div class="single-article-body">
        <?= $article['body'] ?>
    </div>

    <!-- Social Share Bar -->
    <div class="single-share-bar">
        <span class="share-title"><i class="fas fa-share-alt"></i> مشاركة المقال:</span>
        <div class="share-buttons-group">
            <a href="https://api.whatsapp.com/send?text=<?= $encodedTitle ?>%20<?= $currentUrl ?>" target="_blank" class="share-btn share-btn-whatsapp" title="مشاركة عبر واتساب">
                <i class="fab fa-whatsapp"></i> واتساب
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $currentUrl ?>" target="_blank" class="share-btn share-btn-facebook" title="مشاركة عبر فيسبوك">
                <i class="fab fa-facebook-f"></i> فيسبوك
            </a>
            <a href="https://twitter.com/intent/tweet?url=<?= $currentUrl ?>&text=<?= $encodedTitle ?>" target="_blank" class="share-btn share-btn-twitter" title="مشاركة عبر X">
                <i class="fab fa-x-twitter"></i> تويتر
            </a>
            <button class="share-btn share-btn-copy" id="copy-link-btn" title="نسخ الرابط" data-url="<?= SITE_URL . '/article.php?id=' . $id ?>">
                <i class="fas fa-link"></i> نسخ الرابط
            </button>
        </div>
    </div>
</article>

<?php if (!empty($relatedArticles)): ?>
<div class="section-header-block" style="margin-top: 40px;">
    <h2 class="section-main-title">
        <span class="title-icon"><i class="fas fa-layer-group"></i></span>
        أخبار وتقارير ذات صلة
    </h2>
    <span class="section-title-line"></span>
</div>

<div class="articles-grid">
    <?php foreach ($relatedArticles as $rel): ?>
    <article class="article-card">
        <a href="<?= SITE_URL ?>/article.php?id=<?= $rel['id'] ?>" class="card-img-wrap">
            <img src="<?= articleImage($rel['image']) ?>" alt="<?= e($rel['title']) ?>" loading="lazy">
        </a>
        <div class="card-content">
            <h3 class="card-title">
                <a href="<?= SITE_URL ?>/article.php?id=<?= $rel['id'] ?>"><?= e($rel['title']) ?></a>
            </h3>
            <div class="card-meta" style="margin-top:auto;">
                <span><i class="far fa-clock"></i> <?= timeAgo($rel['published_at']) ?></span>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
