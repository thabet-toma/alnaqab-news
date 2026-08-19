<?php
/**
 * اسم المقطع الشغّال حالياً — يقرأ حالة Icecast من داخل السيرفر.
 *
 * لماذا عبر PHP وليس مباشرة من المتصفح؟ لأن Icecast مربوط على 127.0.0.1
 * (غير مكشوف للإنترنت) ولا يرسل ترويسات CORS، فالمتصفح لا يستطيع قراءته.
 *
 * يرجّع دائماً JSON صالح؛ عند أي فشل يرجّع عنواناً فارغاً فتخفي الواجهة
 * الشارة بهدوء بدل أن تتعطل.
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=5');

$statusUrl = defined('ICECAST_STATUS_URL') ? ICECAST_STATUS_URL : 'http://127.0.0.1:8000/status-json.xsl';
$mount     = defined('ICECAST_MOUNT')      ? ICECAST_MOUNT      : '/radio';

// كاش قصير: الصفحة تسأل كل 15 ثانية ولكل زائر، فلا نُرهق Icecast
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

$title = '';
$body  = fetchStatus($statusUrl);

if ($body !== null) {
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
        $title = trim((string)($src['title'] ?? ''));
        if ($title === '') {
            $artist = trim((string)($src['artist'] ?? ''));
            $track  = trim((string)($src['track']  ?? ''));
            $title  = trim($artist . ($artist && $track ? ' - ' : '') . $track);
        }
        break;
    }
}

$out = json_encode(['title' => $title], JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $out, LOCK_EX);
echo $out;
