<?php
// includes/header.php
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/auth.php';
}

$page_title = isset($pageTitle) ? e($pageTitle) . ' | ' . SITE_NAME : SITE_NAME . ' | ' . SITE_DESC;
$activeCategories = getCategories(true);
$breakingNews = getBreakingNews(8);
$radioConfig = getRadioConfig();
$headerAd = getActiveAds('header');

// Get current URL path for active state
$current_page = basename($_SERVER['PHP_SELF']);
$category_id = isset($_GET['id']) && $current_page == 'category.php' ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <meta name="description" content="<?= isset($metaDescription) ? e($metaDescription) : e(SITE_DESC) ?>">
    <meta property="og:title" content="<?= $page_title ?>">
    <meta property="og:description" content="<?= isset($metaDescription) ? e($metaDescription) : e(SITE_DESC) ?>">
    <?php if(isset($metaImage)): ?>
    <meta property="og:image" content="<?= e($metaImage) ?>">
    <?php endif; ?>
    
    <!-- Google Fonts: Tajawal, Lalezar, Cairo -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Lalezar&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome / Feather Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>

<!-- Top Bar -->
<div class="top-bar">
    <div class="container top-bar-container">
        <div class="top-bar-right">
            <span class="live-indicator"><span class="pulse-dot"></span> بث مباشر 24/7</span>
            <span class="top-bar-divider">|</span>
            <span class="date-display"><i class="far fa-calendar-alt"></i> <?= arabicDate(date('Y-m-d H:i:s')) ?></span>
        </div>
        <div class="top-bar-left">
            <div class="social-links">
                <a href="<?= e(getSetting('facebook_url', '#')) ?>" target="_blank" title="فيسبوك"><i class="fab fa-facebook-f"></i></a>
                <a href="<?= e(getSetting('twitter_url', '#')) ?>" target="_blank" title="تويتر (X)"><i class="fab fa-x-twitter"></i></a>
                <a href="<?= e(getSetting('instagram_url', '#')) ?>" target="_blank" title="انستغرام"><i class="fab fa-instagram"></i></a>
                <a href="<?= e(getSetting('youtube_url', '#')) ?>" target="_blank" title="يوتيوب"><i class="fab fa-youtube"></i></a>
                <a href="<?= e(getSetting('whatsapp_number', '#')) ?>" target="_blank" title="واتساب"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="main-header">
    <div class="container header-container">
        <!-- Logo -->
        <a href="<?= SITE_URL ?>/" class="brand-logo">
            <div class="logo-badge">
                <i class="fas fa-newspaper"></i>
            </div>
            <div class="brand-text">
                <div class="site-title-wrap">
                    <span class="site-title-main">موقع النقب</span>
                    <span class="site-title-tag">الإخباري</span>
                </div>
                <span class="site-tagline">صوت النقب ونبض المجتمع الفلسطيني في الداخل</span>
            </div>
        </a>
        
        <!-- Search Bar -->
        <div class="header-search-wrap">
            <form action="<?= SITE_URL ?>/search.php" method="GET" class="header-search-form">
                <input type="text" name="q" placeholder="ابحث في الأخبار والتقارير..." required>
                <button type="submit" aria-label="بحث"><i class="fas fa-search"></i></button>
            </form>
        </div>
        
        <!-- Radio Live CTA -->
        <div class="header-cta-wrap">
            <a href="<?= SITE_URL ?>/radio.php" class="header-radio-pill">
                <div class="radio-pill-icon">
                    <i class="fas fa-broadcast-tower"></i>
                    <span class="radio-wave-anim"></span>
                </div>
                <div class="radio-pill-text">
                    <span class="pill-title">راديو النقب</span>
                    <span class="pill-status"><i class="fas fa-circle live-dot"></i> بث حي ومباشر</span>
                </div>
            </a>
        </div>
    </div>
</header>

<!-- Main Navigation -->
<nav class="main-nav">
    <div class="container nav-container">
        <button id="mobile-menu-toggle" class="mobile-menu-toggle" aria-label="القائمة">
            <i class="fas fa-bars"></i>
            <span>الأقسام</span>
        </button>
        
        <ul id="nav-links" class="nav-links">
            <li class="nav-item">
                <a href="<?= SITE_URL ?>/" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
            </li>
            <?php foreach ($activeCategories as $cat): ?>
            <li class="nav-item">
                <a href="<?= SITE_URL ?>/category.php?id=<?= $cat['id'] ?>" class="nav-link <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                    <?= e($cat['name']) ?>
                </a>
            </li>
            <?php endforeach; ?>
            <li class="nav-item nav-item-radio">
                <a href="<?= SITE_URL ?>/radio.php" class="nav-link radio-nav-link <?= $current_page == 'radio.php' ? 'active' : '' ?>">
                    <i class="fas fa-radio"></i> راديو البث المباشر
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- Breaking News Ticker -->
<?php if ($breakingNews): ?>
<div class="breaking-news-bar">
    <div class="container breaking-container">
        <div class="breaking-badge">
            <span class="breaking-pulse"></span>
            <i class="fas fa-bolt"></i> عاجل
        </div>
        <div class="ticker-wrap">
            <div class="ticker">
                <?php foreach ($breakingNews as $news): ?>
                    <div class="ticker-item">
                        <a href="<?= SITE_URL ?>/article.php?id=<?= $news['id'] ?>">
                            <span class="ticker-bullet">●</span>
                            <?= e($news['title']) ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="main-wrapper">
    <div class="container">
        <?php if (!empty($headerAd)): ?>
            <div class="ad-banner ad-header">
                <a href="<?= e($headerAd[0]['link'] ?: '#') ?>" target="_blank">
                    <img src="<?= UPLOADS_URL . '/ads/' . e($headerAd[0]['image']) ?>" alt="إعلان">
                </a>
            </div>
        <?php endif; ?>
        <div class="layout-grid">
            <main class="main-content">
