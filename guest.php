<?php
/**
 * صفحة الضيف — يفتح الرابط الذي أرسله المذيع ويدخل على الهواء بزرّين.
 *
 * صفحة مستقلة لا تستعمل header/footer الموقع عن قصد: شريط الأخبار والإعلانات
 * يشتّتان ضيفاً على الهواء، ويجعلان الصفحة متاهة لقارئ الشاشة عند ضيف كفيف.
 * زر واحد فعّال في كل لحظة، وكل تغيّر حالة يُعلن صوتياً (aria-live + نغمات).
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/radio-live.php';

$token = (string) ($_GET['t'] ?? '');
$guest = null;
try {
    $row = liveFindToken($token);
    if ($row !== null && $row['role'] === 'guest') $guest = $row;
} catch (Throwable $e) {
    error_log('[radio-live] guest page lookup failed: ' . $e->getMessage());
}

$radioConfig = getRadioConfig();
if ($guest === null) http_response_code(410);

header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>دخول على الهواء — <?= e($radioConfig['station_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(SITE_URL) ?>/assets/css/guest.css?v=<?= e(assetVersion('/assets/css/guest.css')) ?>">
</head>
<body>
<main class="guest" id="guest">
    <p class="guest-station"><?= e($radioConfig['station_name']) ?></p>

<?php if ($guest === null): ?>
    <h1 class="guest-title">الرابط لم يعد صالحاً</h1>
    <p class="guest-text">انتهت صلاحية هذا الرابط أو أُلغي. اطلب من المذيع رابطاً جديداً.</p>
<?php else: ?>
    <h1 class="guest-title">أهلاً <?= e($guest['name']) ?></h1>

    <div class="guest-status" id="guestStatus" role="status" aria-live="assertive" aria-atomic="true">
        اضغط الزر لتشغيل المايك
    </div>

    <div class="guest-meter" id="guestMeter" aria-hidden="true" hidden>
        <div class="guest-meter-fill" id="guestMeterFill"></div>
    </div>

    <button type="button" class="guest-btn" id="guestBtn">تشغيل المايك</button>
    <button type="button" class="guest-btn guest-btn-secondary" id="guestLeave" hidden>اخرج من الهواء</button>

    <ul class="guest-tips" id="guestTips">
        <li>البس سماعة حتى لا يرجع صوت البث للمايك.</li>
        <li>لا تطفئ شاشة الجوال ولا تنتقل لتطبيق آخر أثناء الكلام.</li>
        <li>لسماع المذيع استعمل مكالمة واتساب معه على جهاز آخر.</li>
    </ul>

    <div class="guest-help" id="guestHelp" hidden>
        <p id="guestHelpText"></p>
        <button type="button" class="guest-btn guest-btn-secondary" id="guestCopy">انسخ الرابط</button>
    </div>

    <script>
        const GUEST_CONFIG = {
            token: <?= json_encode($token) ?>,
            name: <?= json_encode($guest['name'], JSON_UNESCAPED_UNICODE) ?>,
            wsUrl: <?= json_encode(liveWsUrl((int) $guest['slot'])) ?>,
            stateUrl: <?= json_encode(SITE_URL . '/api/live-guest.php') ?>
        };
    </script>
    <script src="<?= e(SITE_URL) ?>/assets/js/radio-broadcast.js?v=<?= e(assetVersion('/assets/js/radio-broadcast.js')) ?>"></script>
    <script src="<?= e(SITE_URL) ?>/assets/js/guest.js?v=<?= e(assetVersion('/assets/js/guest.js')) ?>"></script>
<?php endif; ?>
</main>
</body>
</html>
