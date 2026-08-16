<?php
// index.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'الرئيسية';
include __DIR__ . '/includes/header.php';

$featuredArticles = getFeaturedArticles(5);
$latestNews = getLatestArticles(1, 10, null, 'news');
$betweenAd = getActiveAds('between');
$latestArticles = getLatestArticles(1, 6, null, 'article');
?>

<!-- Hero Slider -->
<?php if (!empty($featuredArticles)): ?>
<div class="hero-slider-wrap">
    <div class="hero-slider">
        <?php foreach ($featuredArticles as $index => $article): ?>
        <div class="slide <?= $index === 0 ? 'active' : '' ?>">
            <img src="<?= articleImage($article['image']) ?>" alt="<?= e($article['title']) ?>">
            <div class="slide-overlay"></div>
            <div class="slide-content">
                <?php if(isset($article['category_name'])): ?>
                <span class="slide-category"><i class="fas fa-tag"></i> <?= e($article['category_name']) ?></span>
                <?php endif; ?>
                <h2 class="slide-title">
                    <a href="<?= SITE_URL ?>/article.php?id=<?= $article['id'] ?>"><?= e($article['title']) ?></a>
                </h2>
                <div class="slide-meta">
                    <span><i class="far fa-clock"></i> <?= timeAgo($article['published_at']) ?></span>
                    <?php if($article['views'] > 0): ?>
                    <span><i class="far fa-eye"></i> <?= number_format($article['views']) ?> مشاهدة</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="slider-dots">
            <?php foreach ($featuredArticles as $index => $article): ?>
            <div class="dot <?= $index === 0 ? 'active' : '' ?>"></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Latest News Section -->
<div class="section-header-block">
    <h2 class="section-main-title">
        <span class="title-icon"><i class="fas fa-newspaper"></i></span>
        آخر الأخبار
    </h2>
    <span class="section-title-line"></span>
</div>

<div class="articles-grid">
    <?php foreach ($latestNews['articles'] as $article): ?>
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
            <p class="card-excerpt"><?= e(excerpt($article['excerpt'] ?: $article['body'], 18)) ?></p>
            <div class="card-meta">
                <span class="meta-time"><i class="far fa-clock"></i> <?= timeAgo($article['published_at']) ?></span>
                <?php if($article['views'] > 0): ?>
                <span class="meta-views"><i class="far fa-eye"></i> <?= number_format($article['views']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<!-- Between Ads Banner -->
<?php if (!empty($betweenAd)): ?>
<div class="ad-banner ad-between">
    <a href="<?= e($betweenAd[0]['link'] ?: '#') ?>" target="_blank">
        <img src="<?= UPLOADS_URL . '/ads/' . e($betweenAd[0]['image']) ?>" alt="إعلان" loading="lazy">
    </a>
</div>
<?php endif; ?>

<!-- Opinion / Articles Section -->
<?php if (!empty($latestArticles['articles'])): ?>
<div class="section-header-block" style="margin-top: 40px;">
    <h2 class="section-main-title">
        <span class="title-icon" style="background: linear-gradient(135deg, #f4a261, #e76f51);"><i class="fas fa-pen-nib"></i></span>
        مقالات وآراء
    </h2>
    <span class="section-title-line"></span>
</div>

<div class="articles-grid articles-opinion-grid">
    <?php foreach ($latestArticles['articles'] as $article): ?>
    <article class="article-card opinion-card">
        <a href="<?= SITE_URL ?>/article.php?id=<?= $article['id'] ?>" class="card-img-wrap">
            <span class="card-category" style="background: #e76f51;">مقال</span>
            <img src="<?= articleImage($article['image']) ?>" alt="<?= e($article['title']) ?>" loading="lazy">
        </a>
        <div class="card-content">
            <h3 class="card-title">
                <a href="<?= SITE_URL ?>/article.php?id=<?= $article['id'] ?>"><?= e($article['title']) ?></a>
            </h3>
            <div class="opinion-author-bar">
                <div class="author-avatar"><i class="fas fa-user-edit"></i></div>
                <div class="author-info">
                    <span class="author-name"><?= e($article['author_name'] ?: 'كاتب رأي') ?></span>
                    <span class="author-date"><?= timeAgo($article['published_at']) ?></span>
                </div>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
