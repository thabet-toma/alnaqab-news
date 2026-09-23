<?php
/**
 * أوامر غرفة التحكم (admin/radio-live.php) — نقطة JSON واحدة بحقل action.
 * كل رقم مدخل يُحوَّل لـ int ويُفحص عبر liveSlotId() قبل أن يقترب من السوكيت.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/radio-live.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function liveJson(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isLoggedIn()) {
    liveJson(['success' => false, 'error' => 'غير مصرح لك بالوصول'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['csrf_token']) || !verifyCsrf($input['csrf_token'])) {
    liveJson(['success' => false, 'error' => 'رمز التحقق غير صالح — حدّث الصفحة'], 403);
}

$action  = (string) ($input['action'] ?? '');
$slot    = (int) ($input['slot'] ?? 0);
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

/** يرفض أي رقم مدخل غير 1 أو 2 قبل تنفيذ الأمر */
function requireSlot(int $slot): void {
    if (liveSlotId($slot) === null) liveJson(['success' => false, 'error' => 'مدخل غير معروف'], 400);
}

try {
    switch ($action) {
        case 'state':
            $guests = array_map(fn($g) => [
                'id'         => (int) $g['id'],
                'name'       => $g['name'],
                'expires_at' => $g['expires_at'],
                'used'       => $g['last_auth_at'] !== null,
            ], liveGuestTokens());
            liveJson([
                'success' => true,
                'engine'  => radioEngineUp(),
                'slots'   => [liveSlotState(1), liveSlotState(2)],
                'guests'  => $guests,
                'visual'  => liveCurrentVisual(),
                'delay'   => liveVisualDelay(),
            ]);

        case 'mute':
        case 'unmute':
            requireSlot($slot);
            $ok = liveSetMuted($slot, $action === 'mute');
            liveJson(['success' => $ok, 'error' => $ok ? null : 'المحرّك لم يستجب — هل radio.liq المحدَّث منشور؟']);

        case 'kick':
            requireSlot($slot);
            $ok = liveKick($slot);
            liveJson(['success' => $ok, 'error' => $ok ? null : 'المحرّك لم يستجب']);

        case 'host_token':
            // مذيع واحد على المدخل 1: الرمز الجديد يُلغي ما قبله
            liveRevokeHostTokens();
            $name = (string) ($_SESSION['admin_user'] ?? 'المذيع');
            $tok  = liveCreateToken('host', 1, $name, LIVE_HOST_TTL, $adminId);
            if ($tok === null) liveJson(['success' => false, 'error' => 'تعذّر إنشاء جلسة البث'], 500);
            liveJson(['success' => true, 'url' => liveWsUrl(1), 'password' => $tok['token']]);

        case 'guest_create':
            $hours = (int) ($input['hours'] ?? 2);
            $name  = trim((string) ($input['name'] ?? ''));
            if (!isset(LIVE_GUEST_TTLS[$hours])) liveJson(['success' => false, 'error' => 'مدة غير مسموحة'], 400);
            if ($name === '') liveJson(['success' => false, 'error' => 'اكتب اسم الضيف'], 400);
            $tok = liveCreateToken('guest', 2, $name, LIVE_GUEST_TTLS[$hours], $adminId);
            if ($tok === null) liveJson(['success' => false, 'error' => 'تعذّر إنشاء الرابط'], 500);
            liveJson([
                'success'    => true,
                'id'         => $tok['id'],
                'link'       => rtrim(SITE_URL, '/') . '/guest.php?t=' . $tok['token'],
                'expires_at' => $tok['expires_at'],
            ]);

        case 'guest_revoke':
            $id = (int) ($input['id'] ?? 0);
            // إلغاء رابط ضيف على الهواء الآن يقطعه أيضاً، وإلا بقي يتكلّم برابط ملغى
            $owner = liveSlotOwner(2);
            liveRevokeToken($id);
            if ($owner['token_id'] === $id && liveSlotConnected(2)) liveKick(2);
            liveJson(['success' => true]);

        case 'visual_show':
            [$ok, $warning] = liveShowVisual((int) ($input['id'] ?? 0));
            liveJson(['success' => $ok, 'warning' => $ok ? $warning : null, 'error' => $ok ? null : $warning]);

        case 'visual_stop':
            liveStopVisual();
            liveJson(['success' => true]);

        case 'visual_delay':
            $seconds = max(0, min(30, (int) ($input['seconds'] ?? LIVE_VISUAL_DELAY_DEFAULT)));
            setSetting('radio_visual_delay', (string) $seconds);
            liveJson(['success' => true, 'delay' => $seconds]);

        default:
            liveJson(['success' => false, 'error' => 'أمر غير معروف'], 400);
    }
} catch (Throwable $e) {
    error_log('[radio-live] cid=' . liveCid() . ' action=' . preg_replace('/[^a-z_]/', '', $action) . ' failed: ' . $e->getMessage());
    liveJson(['success' => false, 'error' => 'حدث خطأ في السيرفر'], 500);
}
