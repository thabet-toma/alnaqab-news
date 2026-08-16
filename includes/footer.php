<?php
// includes/footer.php
$sidebarAds = getActiveAds('sidebar');
$mostViewed = getMostViewed(5);
$radioConfig = getRadioConfig();
?>
            </main>
            
            <aside class="sidebar">
                <!-- Radio Premium Widget -->
                <div class="radio-widget-card">
                    <div class="radio-widget-bg-glow"></div>
                    <div class="radio-widget-header">
                        <div class="radio-live-tag">
                            <span class="live-blink"></span> مباشر
                        </div>
                        <span class="radio-freq"><i class="fas fa-signal"></i> FM</span>
                    </div>
                    
                    <div class="radio-widget-body">
                        <div class="radio-station-emblem">
                            <i class="fas fa-broadcast-tower"></i>
                        </div>
                        <h3 class="radio-widget-title"><?= e($radioConfig['station_name'] ?? 'راديو النقب') ?></h3>
                        <p class="radio-widget-desc"><?= e($radioConfig['tagline'] ?? 'صوت الصحراء ونبض المجتمع الفلسطيني') ?></p>
                        
                        <!-- Equalizer Animation -->
                        <div class="audio-equalizer" id="sidebar-equalizer">
                            <span class="eq-bar"></span>
                            <span class="eq-bar"></span>
                            <span class="eq-bar"></span>
                            <span class="eq-bar"></span>
                            <span class="eq-bar"></span>
                            <span class="eq-bar"></span>
                            <span class="eq-bar"></span>
                        </div>
                        
                        <div class="radio-actions">
                            <button id="radio-play-btn" class="radio-big-play-btn" title="تشغيل / إيقاف">
                                <i class="fas fa-play" id="radio-play-icon"></i>
                            </button>
                            <a href="<?= SITE_URL ?>/radio.php" class="radio-full-page-btn">
                                <i class="fas fa-external-link-alt"></i> الصفحة الكاملة
                            </a>
                        </div>
                    </div>
                    
                    <?php if(!empty($radioConfig['stream_url'])): ?>
                    <audio id="radio-audio" src="<?= e($radioConfig['stream_url']) ?>" preload="none"></audio>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Ads -->
                <?php if (!empty($sidebarAds)): ?>
                <?php foreach ($sidebarAds as $ad): ?>
                    <div class="ad-banner ad-sidebar">
                        <a href="<?= e($ad['link'] ?: '#') ?>" target="_blank">
                            <img src="<?= UPLOADS_URL . '/ads/' . e($ad['image']) ?>" alt="إعلان">
                        </a>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <!-- Most Viewed Widget -->
                <div class="sidebar-widget most-viewed-widget">
                    <div class="widget-header">
                        <h3 class="widget-title">
                            <i class="fas fa-fire-alt widget-icon-fire"></i> الأكثر قراءة
                        </h3>
                        <span class="widget-title-bar"></span>
                    </div>
                    <div class="most-viewed-list">
                        <?php foreach ($mostViewed as $idx => $article): ?>
                        <a href="<?= SITE_URL ?>/article.php?id=<?= $article['id'] ?>" class="ranked-article-item">
                            <span class="rank-number rank-<?= $idx + 1 ?>"><?= $idx + 1 ?></span>
                            <div class="ranked-img-wrap">
                                <img src="<?= articleImage($article['image']) ?>" alt="<?= e($article['title']) ?>" loading="lazy">
                            </div>
                            <div class="ranked-content">
                                <h4 class="ranked-title"><?= e($article['title']) ?></h4>
                                <div class="ranked-meta">
                                    <span><i class="far fa-clock"></i> <?= timeAgo($article['published_at']) ?></span>
                                    <?php if($article['views'] > 0): ?>
                                    <span><i class="far fa-eye"></i> <?= number_format($article['views']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>
        </div> <!-- end layout-grid -->
    </div> <!-- end container -->
</div> <!-- end main-wrapper -->

<!-- Site Footer -->
<footer class="site-footer">
    <div class="footer-top-accent"></div>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col footer-brand-col">
                <div class="footer-logo">
                    <div class="footer-logo-badge"><i class="fas fa-newspaper"></i></div>
                    <span class="footer-logo-title">موقع النقب الإخباري</span>
                </div>
                <p class="footer-desc"><?= e(SITE_DESC) ?> — المنصة الإخبارية المستقلة الأولى لأخبار وقضايا النقب والداخل الفلسطيني على مدار الساعة.</p>
                <div class="footer-social-bar">
                    <a href="<?= e(getSetting('facebook_url', '#')) ?>" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?= e(getSetting('twitter_url', '#')) ?>" target="_blank" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
                    <a href="<?= e(getSetting('instagram_url', '#')) ?>" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="<?= e(getSetting('youtube_url', '#')) ?>" target="_blank" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="<?= e(getSetting('whatsapp_number', '#')) ?>" target="_blank" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            
            <div class="footer-col">
                <h4 class="footer-col-title">أقسام الموقع</h4>
                <ul class="footer-links-list">
                    <?php 
                    $cats = getCategories(true);
                    foreach(array_slice($cats, 0, 6) as $cat): 
                    ?>
                    <li><a href="<?= SITE_URL ?>/category.php?id=<?= $cat['id'] ?>"><i class="fas fa-angle-left"></i> <?= e($cat['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4 class="footer-col-title">خدمات وروابط</h4>
                <ul class="footer-links-list">
                    <li><a href="<?= SITE_URL ?>/radio.php"><i class="fas fa-broadcast-tower"></i> البث المباشر للراديو</a></li>
                    <li><a href="<?= SITE_URL ?>/search.php"><i class="fas fa-search"></i> أرشيف البحث</a></li>
                    <li><a href="<?= SITE_URL ?>/admin/login.php"><i class="fas fa-lock"></i> لوحة الإدارة</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-col-title">تواصل معنا</h4>
                <div class="footer-contact-info">
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span><?= e(getSetting('contact_email', 'info@alnaqab-news.com')) ?></span>
                    </div>
                    <?php if($phone = getSetting('whatsapp_number', '')): ?>
                    <div class="contact-item">
                        <i class="fab fa-whatsapp"></i>
                        <span><?= e($phone) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom-bar">
        <div class="container footer-bottom-container">
            <p>جميع الحقوق محفوظة &copy; <?= date('Y') ?> لـ <?= e(SITE_NAME) ?> — صوت النقب الحر.</p>
        </div>
    </div>
</footer>

<button id="back-to-top" class="back-to-top-btn" title="العودة للأعلى" aria-label="العودة للأعلى">
    <i class="fas fa-chevron-up"></i>
</button>

<script src="<?= SITE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
