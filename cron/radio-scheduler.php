<?php
/**
 * مشغّل المواعيد — يعمل كل دقيقة عبر cron.
 *
 * يفحص جدول radio_schedule بمسارين مختلفين تماماً في طبيعتهما:
 *
 *  - مسار البلاي ليست (playlist_id): **حالة لا حدث**. كل دورة يُعاد حساب أي
 *    بلاي ليست يجب أن تكون فعّالة الآن (أحدث موعد استحقّ اليوم، أو الافتراضية
 *    إن لم يستحقّ شيء)، وتُقارَن بالفعّالة الحالية على المحرّك فيُبدَّل عند
 *    الاختلاف فقط. لا last_run هنا إطلاقاً — إعادة التقييم من الصفر كل دقيقة
 *    هي ما يجعل النظام يصحّح نفسه تلقائياً بعد أي انقطاع بلا حاجة لحالة محفوظة.
 *
 *  - مسار المقطع المفرد (track_id): **حدث مربوط بلحظته**، كما كان — نافذة
 *    دقيقتين وlast_run يمنعان تكرار نفس المقطع، ويُدفع عبر طابور الطلبات.
 *
 * الترتيب داخل الدورة مقصود: البلاي ليست أولاً ثم المقطع المفرد. لو حان
 * الاثنان في نفس الدقيقة، تُحمَّل البلاي ليست الصحيحة ثم يُدفع المقطع فوقها
 * في الطابور فيُسمع ثم تكمل البلاي ليست. لو عكسنا الترتيب لابتلع تحميل
 * البلاي ليست المقطع الذي كان يُفترض أن يُسمع فوراً.
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

/** هل موعد بهذه الأيام (فارغة = كل يوم) مستحقّ اليوم؟ */
function scheduleDueToday(string $days, int $todayDow): bool {
    $list = array_filter(explode(',', $days), 'strlen');
    return empty($list) || in_array((string) $todayDow, $list, true);
}

$now       = new DateTimeImmutable('now');
$todayDow  = (int) $now->format('w');   // 0 = الأحد
$nowTime   = $now->format('H:i:00');
$todayDate = $now->format('Y-m-d');

$db = db();

// ===== مسار البلاي ليست — حالة لا حدث =====

$stmt = $db->prepare(
    "SELECT id, playlist_id, play_at, days
       FROM radio_schedule
      WHERE active = 1
        AND playlist_id IS NOT NULL
        AND play_at <= ?
      ORDER BY play_at DESC, id ASC"
);
$stmt->execute([$nowTime]);
$candidates = $stmt->fetchAll();

$winner = null;
foreach ($candidates as $row) {
    if (!scheduleDueToday((string) $row['days'], $todayDow)) continue;
    // القائمة مرتّبة تنازلياً بـ play_at ثم تصاعدياً بـ id، فأول تطابق للأيام
    // هو صاحب أكبر play_at، وعند تعادل play_at يفوز الأصغر معرّفاً
    $winner = $row;
    break;
}

$targetPlaylistId = null;
if ($winner !== null) {
    $sameTimeIds = [];
    foreach ($candidates as $row) {
        if ($row['play_at'] === $winner['play_at'] && scheduleDueToday((string) $row['days'], $todayDow)) {
            $sameTimeIds[] = (int) $row['id'];
        }
    }
    if (count($sameTimeIds) > 1) {
        error_log('[radio-scheduler] مواعيد بلاي ليست متعارضة بنفس الوقت ' . $winner['play_at']
            . '، المعرّفات: ' . implode(', ', $sameTimeIds) . ' — فاز الأصغر معرّفاً');
    }
    $targetPlaylistId = (int) $winner['playlist_id'];
} else {
    $defaultId = (int) (getRadioConfig()['default_playlist_id'] ?? 0);
    if ($defaultId > 0) $targetPlaylistId = $defaultId;
}

if ($targetPlaylistId !== null) {
    $activeId = radioActivePlaylistId();
    if ($activeId !== $targetPlaylistId) {
        if (radioSwitchPlaylist($targetPlaylistId)) {
            error_log("[radio-scheduler] تبديل البلاي ليست الفعّالة إلى #{$targetPlaylistId}");
        } else {
            error_log("[radio-scheduler] تعذّر التبديل إلى البلاي ليست #{$targetPlaylistId} — المحرّك لا يستجيب أو الملف غير جاهز");
        }
    }
}

// ===== مسار المقطع المفرد — حدث مربوط بلحظته =====

/**
 * نأخذ المواعيد المفعّلة التي:
 *  - حان وقتها اليوم (نسمح بتأخّر دقيقتين تحسّباً لتأخّر cron)
 *  - ولم تُشغّل بعد اليوم (last_run قبل موعد اليوم)
 * LEFT JOIN لا INNER: صفوف البلاي ليست في هذا الجدول لا تملك track_id،
 * وشرط s.track_id IS NOT NULL أدناه هو ما يستبعدها فعلياً.
 */
$stmt = $db->prepare(
    "SELECT s.id, s.track_id, s.play_at, s.days, t.filename, t.title, t.status
       FROM radio_schedule s
       LEFT JOIN radio_tracks t ON t.id = s.track_id
      WHERE s.active = 1
        AND s.track_id IS NOT NULL
        AND s.play_at <= ?
        AND s.play_at >  SUBTIME(?, '00:02:00')
        AND (s.last_run IS NULL OR s.last_run < CONCAT(?, ' ', s.play_at))"
);
$stmt->execute([$nowTime, $nowTime, $todayDate]);
$due = $stmt->fetchAll();

if (!$due) exit(0);

$mark = $db->prepare('UPDATE radio_schedule SET last_run = NOW() WHERE id = ?');

foreach ($due as $item) {
    if (!scheduleDueToday((string) $item['days'], $todayDow)) continue;

    if ($item['status'] === 'missing') {
        error_log("[radio-scheduler] المقطع معلَّم مفقوداً: {$item['title']}");
        // نعلّمه كمُشغَّل كي لا يعيد المحاولة كل دقيقة على ملف غير موجود
        $mark->execute([$item['id']]);
        continue;
    }

    $path = rtrim(RADIO_MUSIC_DIR, '/') . '/' . $item['filename'];

    if (!is_readable($path)) {
        error_log("[radio-scheduler] ملف مفقود: {$path}");
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
