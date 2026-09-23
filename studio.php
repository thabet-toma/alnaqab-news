<?php
/**
 * شاشة الاستوديو — يلتقطها OBS كـ Browser Source ويبثها لفيسبوك وتيك توك.
 *
 * ليست بثاً ثانياً: هي البث نفسه (صوت /stream) بشكل شاشة تلفزيون — اسم المحطة،
 * «مباشر»، من يتكلم، الصورة أو الفيديو المعروض من غرفة التحكم، وشريط العاجل.
 * لا أزرار ولا تفاعل: OBS لا يضغط شيئاً، ويسمح بتشغيل الصوت تلقائياً.
 *
 *   studio.php                  ← أفقي 1920×1080 (فيسبوك، يوتيوب)
 *   studio.php?layout=vertical  ← عمودي 1080×1920 (تيك توك)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$radioConfig = getRadioConfig();
$images      = radioImages($radioConfig['images'] ?? '[]');
$ticker      = json_decode($radioConfig['ticker'] ?? '[]', true);
if (!is_array($ticker)) $ticker = [];
$vertical    = ($_GET['layout'] ?? '') === 'vertical';

header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>شاشة الاستوديو — <?= e($radioConfig['station_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@500;700;800&family=Lalezar&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(SITE_URL) ?>/assets/css/studio.css?v=<?= e(assetVersion('/assets/css/studio.css')) ?>">
</head>
<body class="studio<?= $vertical ? ' studio-vertical' : '' ?>">
    <header class="studio-top">
        <div class="studio-brand"><?= e($radioConfig['station_name']) ?></div>
        <div class="studio-badge" id="studioBadge"><span class="studio-dot"></span><span id="studioBadgeText">راديو</span></div>
    </header>

    <section class="studio-stage">
        <div class="studio-slides" id="studioSlides">
            <?php if (empty($images)): ?>
                <img class="studio-slide is-active" src="<?= e(SITE_URL) ?>/assets/img/placeholder.jpg" alt="">
            <?php else: ?>
                <?php foreach ($images as $i => $img): ?>
                    <img class="studio-slide<?= $i === 0 ? ' is-active' : '' ?>" src="<?= e($img) ?>" alt="">
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="studio-visual" id="studioVisual"></div>
    </section>

    <div class="studio-lower">
        <span class="studio-lower-label" id="studioLowerLabel">الآن</span>
        <span class="studio-lower-text" id="studioLowerText"><?= e($radioConfig['tagline']) ?></span>
    </div>

    <?php if (!empty($ticker)): ?>
    <footer class="studio-ticker">
        <span class="studio-ticker-label">عاجل</span>
        <div class="studio-ticker-wrap">
            <div class="studio-ticker-move">
                <?php foreach ($ticker as $msg): ?>
                    <span class="studio-ticker-item"><?= e((string) $msg) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <audio id="studioAudio" src="<?= e($radioConfig['stream_url']) ?>" autoplay></audio>
    <button type="button" class="studio-unmute" id="studioUnmute" hidden>اضغط لتشغيل الصوت</button>

    <script>
        const STUDIO_SCREEN = {
            stateUrl: <?= json_encode(SITE_URL . '/api/live-state.php') ?>,
            nowPlayingUrl: <?= json_encode(SITE_URL . '/api/nowplaying.php') ?>,
            tagline: <?= json_encode($radioConfig['tagline'], JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="<?= e(SITE_URL) ?>/assets/js/live-visual.js?v=<?= e(assetVersion('/assets/js/live-visual.js')) ?>"></script>
    <script src="<?= e(SITE_URL) ?>/assets/js/studio.js?v=<?= e(assetVersion('/assets/js/studio.js')) ?>"></script>
</body>
</html>
