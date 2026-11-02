<?php
/**
 * النشر التلقائي على صفحة فيسبوك للأخبار التي تُضاف بغير لوحة التحكم
 * (مثل سكربت وكيل الأخبار الذي يُدخل في القاعدة مباشرة، فلا تُنادى facebookPostArticle).
 *
 * كل دورة: يأخذ الأخبار المنشورة التي fb_post_id فيها NULL ورقمها أكبر من
 * FB_AUTOPOST_AFTER_ID وعمرها أقل من يوم، من الأقدم للأحدث، وينشر منها حتى
 * FB_AUTOPOST_PER_RUN خبراً عبر facebookPostArticle (التي تحجز الخبر ذرّياً،
 * فلا تتكرر المنشورات حتى لو نُشر الخبر من اللوحة في نفس اللحظة).
 *
 *  - FB_AUTOPOST_AFTER_ID: يمنع نشر الأخبار القديمة التي سبقت تفعيل هذه الميزة.
 *  - عمر أقل من يوم: خبر يفشل نشره باستمرار يخرج من الطابور ولا يسدّه للأبد.
 *
 * التثبيت: /etc/cron.d/naqab-facebook
 *     (كل 5 دقائق) www-data php /var/www/.../cron/facebook-post-pending.php
 *
 * php cron/facebook-post-pending.php --dry-run   يعرض ما سيُنشر دون نشر.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يعمل من سطر الأوامر فقط\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

const FB_AUTOPOST_AFTER_ID = 63;
const FB_AUTOPOST_PER_RUN  = 3;

$dryRun = in_array('--dry-run', $argv ?? [], true);

// تشغيل واحد في كل مرة
$lock = fopen(sys_get_temp_dir() . '/naqab-fb-autopost.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);

if (!defined('FB_PAGE_ID') || !defined('FB_PAGE_TOKEN') || FB_PAGE_ID === '' || FB_PAGE_TOKEN === '') exit(0);

$stmt = db()->prepare(
    "SELECT id, title FROM articles
      WHERE status = 'published' AND fb_post_id IS NULL
        AND id > ? AND created_at > NOW() - INTERVAL 1 DAY
      ORDER BY id ASC LIMIT " . (int) FB_AUTOPOST_PER_RUN
);
$stmt->execute([FB_AUTOPOST_AFTER_ID]);

foreach ($stmt->fetchAll() as $row) {
    $id = (int) $row['id'];
    $stamp = date('Y-m-d H:i:s');
    if ($dryRun) {
        echo "[$stamp] dry-run: would post #$id {$row['title']}\n";
        continue;
    }
    $ok = facebookPostArticle($id);
    echo "[$stamp] article #$id " . ($ok ? 'posted' : 'not posted (see error above)') . "\n";
}
