<?php
/**
 * الاستوديو المباشر — روابط الدخول على الهواء، الكتم والطرد، والعرض المرئي.
 *
 * المدخلان المباشران في radio.liq:
 *   المدخل 1 (live1) للمذيع — من المتصفح برمز host، أو من BUTT بكلمة سرّه الثابتة.
 *   المدخل 2 (live2) للضيف  — من رابط guest.php برمز guest، أو BUTT احتياطاً.
 *
 * الرمز هو كلمة السر التي يرسلها المتصفح للمحرّك. المحرّك لا يعرف الرموز؛ عند
 * كل اتصال يستدعي cron/radio-live-auth.php الذي يسأل هذا الملف (liveAuthorize).
 * نخزّن sha256 للرمز فقط: من يقرأ قاعدة البيانات لا يستطيع الدخول على الهواء.
 *
 * كل الأوقات تُحسب من PHP (liveNow) لا من NOW() في MySQL: قاعدة البيانات في
 * حاوية بتوقيت UTC بينما PHP مضبوط على Asia/Hebron، والخلط بينهما يجعل رابط
 * ساعتين ينتهي فوراً أو يعيش خمس ساعات.
 */

require_once __DIR__ . '/radio-control.php';

/** رقم المدخل ← معرّفه في المحرّك. أي أمر سوكيت يُبنى من هذه القيم فقط، لا من مدخل مستخدم */
const LIVE_SLOTS = [1 => 'live1', 2 => 'live2'];

/** عمر رمز المذيع — يكفي أطول حلقة، ويُنشأ من جديد عند كل ضغطة «ادخل على الهواء» */
const LIVE_HOST_TTL = 43200;

/** صلاحيات روابط الضيوف المتاحة في غرفة التحكم: عدد الساعات ← الثواني */
const LIVE_GUEST_TTLS = [2 => 7200, 24 => 86400];

/** الحد الأقصى لفيديو العرض — نفس حدّ مقاطع المكتبة */
const LIVE_VIDEO_MAX_BYTES = 64 * 1024 * 1024;

/** التأخير الافتراضي لإظهار المرئي حتى يتطابق مع صوت البث الواصل للمستمع */
const LIVE_VISUAL_DELAY_DEFAULT = 6;

/* ===== أدوات عامة ===== */

function liveNow(): string {
    return date('Y-m-d H:i:s');
}

/** معرّف قصير يربط أسطر السجل الخاصة بطلب واحد */
function liveCid(): string {
    static $cid = null;
    return $cid ??= bin2hex(random_bytes(4));
}

/**
 * سطر سجل واحد لكل حدث. لا رموز ولا أسماء ولا عناوين IP — معرّفات فقط.
 * $fields مصفوفة مفتاح ← قيمة عددية أو نصية قصيرة ثابتة من الكود.
 */
function liveLog(string $event, array $fields = []): void {
    $parts = ['[radio-live]', 'cid=' . liveCid(), 'event=' . $event];
    foreach ($fields as $key => $value) {
        $parts[] = $key . '=' . (is_bool($value) ? ($value ? '1' : '0') : $value);
    }
    error_log(implode(' ', $parts));
}

/** معرّف المدخل في المحرّك، أو null لأي رقم غير 1 أو 2 */
function liveSlotId(int $slot): ?string {
    return LIVE_SLOTS[$slot] ?? null;
}

/* ===== الرموز ===== */

function liveTokenHash(string $token): string {
    return hash('sha256', $token);
}

/** 32 بايت عشوائية بترميز base64url — 43 حرفاً آمنة داخل رابط */
function liveNewToken(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

/** رفض مبكر لأي نص ليس بشكل الرمز قبل لمس قاعدة البيانات */
function liveTokenWellFormed(string $token): bool {
    return (bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $token);
}

/**
 * ينشئ رمزاً ويرجّع [id, token, expires_at]. الرمز الخام لا يُخزَّن — يُعطى
 * للمتصل مرة واحدة فقط ليضعه في الرابط أو يرسله للمحرّك.
 */
function liveCreateToken(string $role, int $slot, string $name, int $ttl, ?int $adminId): ?array {
    if (!in_array($role, ['host', 'guest'], true) || liveSlotId($slot) === null || $ttl <= 0) {
        return null;
    }
    // preg_replace يرجّع null لنص UTF-8 تالف — يُعامل كاسم فارغ فيُرفض
    $name = mb_substr(trim(preg_replace('/\s+/u', ' ', $name) ?? ''), 0, 80);
    if ($name === '') return null;

    $token   = liveNewToken();
    $expires = date('Y-m-d H:i:s', time() + $ttl);

    $stmt = db()->prepare(
        'INSERT INTO radio_live_tokens (token_hash, role, slot, name, expires_at, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([liveTokenHash($token), $role, $slot, $name, $expires, $adminId, liveNow()]);
    $id = (int) db()->lastInsertId();

    liveLog('token_create', ['token_id' => $id, 'role' => $role, 'slot' => $slot, 'admin' => (int) $adminId]);
    return ['id' => $id, 'token' => $token, 'expires_at' => $expires];
}

/** الرمز الصالح الآن (غير ملغى وغير منتهٍ) بسجلّه، أو null */
function liveFindToken(string $token): ?array {
    if (!liveTokenWellFormed($token)) return null;

    $stmt = db()->prepare(
        'SELECT id, role, slot, name, expires_at FROM radio_live_tokens
         WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > ? LIMIT 1'
    );
    $stmt->execute([liveTokenHash($token), liveNow()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * قرار المحرّك: هل يدخل صاحب هذا الرمز على هذا المدخل؟ عند القبول يُسجَّل
 * الرمز كصاحب المدخل الحالي، فتعرف غرفة التحكم اسم من على الهواء ومن تطرد.
 */
function liveAuthorize(string $token, int $slot): bool {
    $row = liveFindToken($token);
    if ($row === null || (int) $row['slot'] !== $slot) {
        liveLog('auth_fail', ['slot' => $slot]);
        return false;
    }
    db()->prepare('UPDATE radio_live_tokens SET last_auth_at = ? WHERE id = ?')
        ->execute([liveNow(), $row['id']]);
    setSetting('radio_live_slot' . $slot, 'token:' . (int) $row['id']);

    liveLog('auth_ok', ['slot' => $slot, 'token_id' => (int) $row['id']]);
    return true;
}

/** BUTT اتصل بكلمة السر الثابتة — يُسجَّل ليظهر في غرفة التحكم كمصدر احتياطي */
function liveMarkButt(int $slot): void {
    if (liveSlotId($slot) === null) return;
    setSetting('radio_live_slot' . $slot, 'butt');
    liveLog('auth_butt', ['slot' => $slot]);
}

function liveRevokeToken(int $id): bool {
    $stmt = db()->prepare('UPDATE radio_live_tokens SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL');
    $stmt->execute([liveNow(), $id]);
    $done = $stmt->rowCount() > 0;
    if ($done) liveLog('token_revoke', ['token_id' => $id]);
    return $done;
}

/** يلغي كل رموز المذيع السابقة — مذيع واحد على المدخل 1 في كل لحظة */
function liveRevokeHostTokens(): void {
    db()->prepare("UPDATE radio_live_tokens SET revoked_at = ? WHERE role = 'host' AND revoked_at IS NULL")
        ->execute([liveNow()]);
}

/** روابط الضيوف الصالحة الآن، الأحدث أولاً */
function liveGuestTokens(): array {
    $stmt = db()->prepare(
        "SELECT id, name, expires_at, last_auth_at, created_at FROM radio_live_tokens
         WHERE role = 'guest' AND revoked_at IS NULL AND expires_at > ?
         ORDER BY created_at DESC, id DESC"
    );
    $stmt->execute([liveNow()]);
    return $stmt->fetchAll();
}

/* ===== حالة المدخلين، الكتم والطرد ===== */

/** هل يوجد مصدر متصل على المدخل؟ null إن كان المحرّك لا يردّ */
function liveSlotConnected(int $slot): ?bool {
    $id = liveSlotId($slot);
    if ($id === null) return null;
    $res = radioReadCommand($id . '.status');
    if ($res === null || !radioCommandOk($res)) return null;
    $res = trim($res);
    return $res !== '' && !str_starts_with($res, 'no ');
}

/** هل المدخل مكتوم؟ null إن تعذّرت القراءة (محرّك قديم بلا متغيّر الكتم) */
function liveSlotMuted(int $slot): ?bool {
    $id = liveSlotId($slot);
    if ($id === null) return null;
    $res = radioCommand('var.get ' . $id . '_gain');
    if ($res === null || !radioCommandOk($res) || !is_numeric(trim($res))) return null;
    return (float) trim($res) <= 0.0;
}

/**
 * من يملك المدخل حالياً حسب آخر اتصال ناجح: ['source' => 'browser'|'butt'|null,
 * 'name' => string, 'token_id' => ?int]. لا يقول إن كان متصلاً الآن — ذلك من المحرّك.
 */
function liveSlotOwner(int $slot): array {
    $owner = ['source' => null, 'name' => '', 'token_id' => null];
    $mark  = getSetting('radio_live_slot' . $slot, '');

    if ($mark === 'butt') {
        $owner['source'] = 'butt';
        return $owner;
    }
    if (str_starts_with($mark, 'token:')) {
        $stmt = db()->prepare('SELECT id, name FROM radio_live_tokens WHERE id = ?');
        $stmt->execute([(int) substr($mark, 6)]);
        $row = $stmt->fetch();
        if ($row) {
            $owner = ['source' => 'browser', 'name' => (string) $row['name'], 'token_id' => (int) $row['id']];
        }
    }
    return $owner;
}

/** الحالة الكاملة للمدخل كما تعرضها غرفة التحكم */
function liveSlotState(int $slot): array {
    $connected = liveSlotConnected($slot);
    $owner     = $connected ? liveSlotOwner($slot) : ['source' => null, 'name' => '', 'token_id' => null];
    return [
        'slot'      => $slot,
        'connected' => (bool) $connected,
        'muted'     => (bool) liveSlotMuted($slot),
        'source'    => $owner['source'],
        'name'      => $owner['name'],
    ];
}

function liveSetMuted(int $slot, bool $muted): bool {
    $id = liveSlotId($slot);
    if ($id === null) return false;
    $ok = radioCommandOk(radioCommand('var.set ' . $id . '_gain = ' . ($muted ? '0.' : '1.')));
    liveLog($muted ? 'mute' : 'unmute', ['slot' => $slot, 'ok' => $ok]);
    return $ok;
}

/**
 * يطرد من على المدخل: يلغي رمزه أولاً (فلا يعود بنفس الرابط) ثم يقطع الاتصال.
 * مع BUTT يقطع فقط — BUTT يعيد الاتصال تلقائياً بكلمة سرّه، فالكتم أداته الصحيحة.
 */
function liveKick(int $slot): bool {
    $id = liveSlotId($slot);
    if ($id === null) return false;

    $owner = liveSlotOwner($slot);
    if ($owner['token_id'] !== null) liveRevokeToken($owner['token_id']);

    $ok = radioCommandOk(radioCommand($id . '.stop'));
    // الكتم لا يبقى عالقاً على المدخل للمتصل التالي
    liveSetMuted($slot, false);
    liveLog('kick', ['slot' => $slot, 'source' => $owner['source'] ?? 'none', 'ok' => $ok]);
    return $ok;
}

/* ===== العرض المرئي ===== */

function liveVisualDelay(): int {
    $v = getSetting('radio_visual_delay', (string) LIVE_VISUAL_DELAY_DEFAULT);
    return max(0, min(30, (int) $v));
}

function liveVisualUrl(string $filename): string {
    return rtrim(UPLOADS_URL, '/') . '/' . $filename;
}

/** مكتبة العرض، الأحدث أولاً */
function liveVisuals(): array {
    return db()->query('SELECT * FROM radio_visuals ORDER BY created_at DESC, id DESC')->fetchAll();
}

/**
 * يرفع صورة أو فيديو لمكتبة العرض ويرجّع [نجاح, رسالة].
 * الصور عبر uploadImage() وحدها. الفيديو بنفس منهجها: النوع الحقيقي من
 * المحتوى، والامتداد مشتقّ منه، وvideo/mp4 فقط لأنه الوحيد الذي تشغّله كل
 * المتصفحات ويفكّه المحرّك.
 */
function liveUploadVisual(array $file, string $title = ''): array {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return [false, 'الملف أكبر من الحد المسموح على السيرفر'];
        }
        return [false, 'لم يصل أي ملف'];
    }

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']) ?: null;
            finfo_close($finfo);
        }
    }
    $title = mb_substr(trim($title), 0, 160);

    if ($mime !== null && str_starts_with($mime, 'image/')) {
        $path = uploadImage($file, 'visuals');
        if ($path === null) return [false, 'صورة غير مدعومة أو أكبر من 5 ميغابايت — المسموح: JPG, PNG, WEBP, GIF'];
        $type     = 'image';
        $duration = null;
    } elseif ($mime === 'video/mp4') {
        if ($file['size'] > LIVE_VIDEO_MAX_BYTES) return [false, 'الفيديو أكبر من 64 ميغابايت'];
        $dir = UPLOADS_PATH . 'visuals/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = uniqid('vid_', true) . '.mp4';
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return [false, 'فشل حفظ الملف على السيرفر'];
        // المحرّك يقرأ الملف كمستخدم آخر ليبثّ صوته
        @chmod($dir . $name, 0644);
        $path     = 'visuals/' . $name;
        $type     = 'video';
        $duration = audioDuration($dir . $name);
    } else {
        return [false, 'نوع الملف غير مدعوم — صورة (JPG, PNG, WEBP, GIF) أو فيديو MP4'];
    }

    $full = UPLOADS_PATH . $path;
    db()->prepare('INSERT INTO radio_visuals (type, filename, title, duration, filesize, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$type, $path, $title, $duration, filesize($full) ?: 0, liveNow()]);

    return [true, $type === 'video' ? 'تم رفع الفيديو' : 'تم رفع الصورة'];
}

function liveVisualById(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM radio_visuals WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * المرئي المعروض الآن، أو null. الفيديو ينتهي وحده: بعد مدته (مع هامش
 * التأخير) يُعدّ غير معروض دون حاجة لمهمة تنظيف.
 */
function liveCurrentVisual(): ?array {
    $raw = getSetting('radio_visual_now', '');
    if ($raw === '') return null;
    $now = json_decode($raw, true);
    if (!is_array($now) || empty($now['url']) || empty($now['type'])) return null;

    if ($now['type'] === 'video' && !empty($now['duration'])) {
        $endsAt = (int) $now['started_at'] + (int) $now['duration'] + liveVisualDelay() + 2;
        if (time() > $endsAt) return null;
    }
    return $now;
}

/**
 * يعرض عنصراً من المكتبة. للفيديو يُدفع ملفه لطابور visual في المحرّك فيُبثّ
 * صوته حسب القاعدة (صامت أثناء الكلام). يرجّع [نجاح, تحذير أو ''].
 */
function liveShowVisual(int $id): array {
    $row = liveVisualById($id);
    if ($row === null) return [false, 'العنصر غير موجود'];

    $warning = '';
    // أي فيديو سابق يتوقف صوته قبل أن يبدأ الجديد
    radioCommand('visual.flush_and_skip');

    if ($row['type'] === 'video') {
        $real = realpath(UPLOADS_PATH . $row['filename']);
        $base = realpath(UPLOADS_PATH . 'visuals');
        $rid  = null;
        if ($real !== false && $base !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            $rid = radioCommand('visual.push ' . $real);
        }
        if ($rid === null || !ctype_digit(trim(explode("\n", $rid)[0]))) {
            $warning = 'الصورة تُعرض، لكن صوت الفيديو لم يصل للمحرّك — تأكد أن خدمة الراديو شغّالة';
        }
    }

    setSetting('radio_visual_now', json_encode([
        'id'         => (int) $row['id'],
        'type'       => $row['type'],
        'url'        => liveVisualUrl($row['filename']),
        'title'      => (string) $row['title'],
        'duration'   => $row['duration'] !== null ? (int) $row['duration'] : null,
        'started_at' => time(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    liveLog('visual_show', ['visual_id' => (int) $row['id'], 'type' => $row['type']]);
    return [true, $warning];
}

function liveStopVisual(): void {
    radioCommand('visual.flush_and_skip');
    setSetting('radio_visual_now', '');
    liveLog('visual_stop');
}

/** يحذف عنصراً من المكتبة والقرص، ويوقف عرضه إن كان معروضاً */
function liveDeleteVisual(int $id): bool {
    $row = liveVisualById($id);
    if ($row === null) return false;

    $current = liveCurrentVisual();
    if ($current !== null && (int) ($current['id'] ?? 0) === $id) liveStopVisual();

    $real = realpath(UPLOADS_PATH . $row['filename']);
    $base = realpath(UPLOADS_PATH . 'visuals');
    if ($real !== false && $base !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
        @unlink($real);
    }
    db()->prepare('DELETE FROM radio_visuals WHERE id = ?')->execute([$id]);
    return true;
}

/* ===== الحالة العامة للزوار وشاشة الاستوديو ===== */

/**
 * ما يراه الجمهور: أسماء من على الهواء والمرئي الحالي فقط — لا رموز ولا
 * معرّفات. لا يرمي أبداً؛ عند أي فشل ترجع حالة فارغة صالحة.
 */
function livePublicState(): array {
    $state = ['on_air' => [], 'visual' => null, 'delay' => LIVE_VISUAL_DELAY_DEFAULT, 'now' => time()];
    try {
        foreach (array_keys(LIVE_SLOTS) as $slot) {
            if (liveSlotConnected($slot)) {
                $owner = liveSlotOwner($slot);
                $state['on_air'][] = ['slot' => $slot, 'name' => $owner['name']];
            }
        }
        $visual = liveCurrentVisual();
        if ($visual !== null) {
            $state['visual'] = [
                'type'       => $visual['type'],
                'url'        => $visual['url'],
                'title'      => $visual['title'] ?? '',
                'duration'   => $visual['duration'] ?? null,
                'started_at' => (int) $visual['started_at'],
            ];
        }
        $state['delay'] = liveVisualDelay();
    } catch (Throwable $e) {
        error_log('[radio-live] public state failed: ' . $e->getMessage());
    }
    return $state;
}

/** رابط WebSocket لمدخل: يمرّ عبر Nginx بنفس دومين الموقع (wss على HTTPS) */
function liveWsUrl(int $slot): string {
    if (defined('LIVE_WS_BASE')) return rtrim(LIVE_WS_BASE, '/') . '/' . $slot;
    return preg_replace('#^http#', 'ws', rtrim(SITE_URL, '/')) . '/live-in/' . $slot;
}
