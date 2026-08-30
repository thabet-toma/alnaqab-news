<?php
/**
 * مشغّل المواعيد — يعمل كل دقيقة عبر cron.
 *
 * يفحص جدول radio_schedule: أي موعد حان وقته اليوم ولم يُشغّل بعد،
 * يدفع مقطعه إلى طابور Liquidsoap فيشتغل فوراً.
 *
 * التثبيت: /etc/cron.d/naqab-radio
 *     * * * * * www-data php /var/www/.../cron/radio-scheduler.php
 *
 * يعمل بمستخدم www-data نفسه الذي يشغّل الموقع، ليكون له نفس صلاحية
 * الكتابة على سوكيت Liquidsoap.
 */

// يمنع تشغيله من المتصفح — مهمة سيرفر داخلية فقط
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يعمل من سطر الأوامر فقط\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/radio-control.php';

$now       = new DateTimeImmutable('now');
$todayDow  = (int) $now->format('w');   // 0 = الأحد
$nowTime   = $now->format('H:i:00');
$todayDate = $now->format('Y-m-d');

$db = db();

/**
 * نأخذ المواعيد المفعّلة التي:
 *  - حان وقتها اليوم (نسمح بتأخّر دقيقتين تحسّباً لتأخّر cron)
 *  - ولم تُشغّل بعد اليوم (last_run قبل موعد اليوم)
 */
$stmt = $db->prepare(
    "SELECT s.id, s.track_id, s.play_at, s.days, t.filename, t.title
       FROM radio_schedule s
       JOIN radio_tracks t ON t.id = s.track_id
      WHERE s.active = 1
        AND s.play_at <= ?
        AND s.play_at >  SUBTIME(?, '00:02:00')
        AND (s.last_run IS NULL OR s.last_run < CONCAT(?, ' ', s.play_at))"
);
$stmt->execute([$nowTime, $nowTime, $todayDate]);
$due = $stmt->fetchAll();

if (!$due) exit(0);

$mark = $db->prepare('UPDATE radio_schedule SET last_run = NOW() WHERE id = ?');

foreach ($due as $item) {
    // فلترة الأيام: قائمة فارغة تعني كل يوم
    $days = array_filter(explode(',', (string) $item['days']), 'strlen');
    if (!empty($days) && !in_array((string) $todayDow, $days, true)) {
        continue;
    }

    $path = rtrim(RADIO_MUSIC_DIR, '/') . '/' . $item['filename'];

    if (!is_readable($path)) {
        error_log("[radio-scheduler] ملف مفقود: {$path}");
        // نعلّمه كمُشغَّل كي لا يعيد المحاولة كل دقيقة على ملف غير موجود
        $mark->execute([$item['id']]);
        continue;
    }

    if (radioPlayNow($path)) {
        $mark->execute([$item['id']]);
        error_log("[radio-scheduler] شُغّل: {$item['title']} ({$item['play_at']})");
    } else {
        // لا نعلّمه، ليحاول ثانيةً بالدقيقة التالية ضمن نافذة الدقيقتين
        error_log("[radio-scheduler] تعذّر تشغيل: {$item['title']} — المحرّك لا يستجيب");
    }
}
