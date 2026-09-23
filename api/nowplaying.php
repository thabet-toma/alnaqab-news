<?php
/**
 * الحالة الجارية الكاملة للبث — تقرأ محرّك الراديو عبر السوكيت (العنوان،
 * الوقت المتبقّي، الموقع داخل البلاي ليست) واحتياطياً حالة Icecast.
 *
 * لماذا عبر PHP وليس مباشرة من المتصفح؟ لأن Icecast مربوط على 127.0.0.1
 * (غير مكشوف للإنترنت) ولا يرسل ترويسات CORS، فالمتصفح لا يستطيع قراءته،
 * وسوكيت المحرّك ملفّي أصلاً فلا يصل إليه إلا PHP على نفس السيرفر.
 *
 * يرجّع دائماً JSON صالح برمز HTTP 200 وبنية كاملة المفاتيح؛ عند أي فشل
 * تكون القيم فارغة/null فتخفي الواجهة الشارة بهدوء بدل أن تتعطل.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/radio-control.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=5');

$statusUrl = defined('ICECAST_STATUS_URL') ? ICECAST_STATUS_URL : 'http://127.0.0.1:8010/status-json.xsl';
$mount     = defined('ICECAST_MOUNT')      ? ICECAST_MOUNT      : '/radio';

// كاش قصير: الصفحة تسأل كل 15 ثانية ولكل زائر، فلا نُرهق المحرّك ولا Icecast
$cacheFile = sys_get_temp_dir() . '/naqab_nowplaying.json';
if (is_readable($cacheFile) && (time() - filemtime($cacheFile)) < 5) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false) { echo $cached; exit; }
}

function fetchStatus(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : null;
}

/** عنوان المصدر كما يراه Icecast، فارغ عند أي تعذّر أو غياب */
function fetchIcecastTitle(string $statusUrl, string $mount): string {
    $body = fetchStatus($statusUrl);
    if ($body === null) return '';

    $data    = json_decode($body, true);
    $sources = $data['icestats']['source'] ?? [];
    // Icecast يرجّع كائناً واحداً عند وجود بث واحد، ومصفوفة عند تعددها
    if (isset($sources['listenurl'])) $sources = [$sources];

    foreach ($sources as $src) {
        $listenUrl = $src['listenurl'] ?? '';
        if ($mount !== '' && $listenUrl !== '' && !str_ends_with($listenUrl, $mount)) {
            continue;
        }
        // title هو ما يرسله المذيع؛ عند التشغيل التلقائي يتكوّن من artist+track
        $title = radioCleanTitle((string)($src['title'] ?? ''));
        if ($title === '') {
            $artist = radioCleanTitle((string)($src['artist'] ?? ''));
            $track  = radioCleanTitle((string)($src['track']  ?? ''));
            $title  = trim($artist . ($artist && $track ? ' - ' : '') . $track);
        }
        return $title;
    }
    return '';
}

$state = radioNowPlaying();

// أثناء البثّ المباشر ما يُبثّ صوت المذيع لا مقطعاً، فسوكيت المحرّك لا يحمل
// عنواناً؛ Icecast يحمل العنوان الذي يرسله برنامج المذيع نفسه في هذه الحالة.
if ($state['title'] === '') {
    $state['title'] = fetchIcecastTitle($statusUrl, $mount);
}

$out = json_encode($state, JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $out, LOCK_EX);
echo $out;
