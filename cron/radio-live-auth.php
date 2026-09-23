<?php
/**
 * بوّاب المدخلين المباشرين — يستدعيه Liquidsoap عند كل محاولة اتصال بـ live1/live2.
 *
 *   RADIO_TOKEN=<الرمز> php radio-live-auth.php <1|2>   ← يخرج بـ 0 للقبول و1 للرفض
 *   RADIO_LIVE_BUTT=1   php radio-live-auth.php <1|2>   ← BUTT دخل بكلمة سرّه الثابتة؛
 *                                                          تسجيل فقط، المحرّك قبله أصلاً
 *
 * الرمز يصل عبر متغيّر بيئة لا وسيطاً: وسائط الأوامر تظهر لأي مستخدم على
 * السيرفر في قائمة العمليات (ps)، ومتغيّرات البيئة لا يقرأها إلا صاحب العملية.
 *
 * يعمل بمستخدم liquidsoap، لذلك يجب أن يكون عضواً في مجموعة www-data ليقرأ
 * includes/config.local.php (صلاحيته 640) — راجع radio-server/README.md.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يعمل من سطر الأوامر فقط\n");
}

$slot = (int) ($argv[1] ?? 0);

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/radio-live.php';

    if (liveSlotId($slot) === null) exit(1);

    if (getenv('RADIO_LIVE_BUTT') === '1') {
        liveMarkButt($slot);
        exit(0);
    }

    $token = (string) getenv('RADIO_TOKEN');
    exit(liveAuthorize($token, $slot) ? 0 : 1);
} catch (Throwable $e) {
    // أي عطل (قاعدة بيانات متوقفة مثلاً) = رفض. BUTT لا يتأثر لأن المحرّك
    // يقبله قبل استدعاء هذا السكربت أصلاً.
    error_log('[radio-live] auth script failed: ' . $e->getMessage());
    exit(1);
}
