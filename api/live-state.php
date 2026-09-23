<?php
/**
 * حالة الاستوديو للجمهور — تستطلعها صفحة الراديو وشاشة الاستوديو (OBS):
 * أسماء من على الهواء، والصورة أو الفيديو المعروض، ومقدار تأخير العرض.
 *
 * مثل nowplaying.php: يرجّع دائماً 200 وJSON كامل المفاتيح، وعند أي فشل حالة
 * فارغة فتبقى الصفحات على شاشتها الافتراضية بهدوء. كاش ثانيتين لأن كل زائر
 * يسأل كل بضع ثوانٍ. لا سجلّات هنا: هذا المسار الساخن.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/radio-live.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cacheFile = sys_get_temp_dir() . '/naqab_live_state.json';
if (is_readable($cacheFile) && (time() - filemtime($cacheFile)) < 2) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false) {
        // وقت السيرفر يُحدَّث دائماً: الصفحات تحسب منه موضع الفيديو وموعد إظهاره
        $state = json_decode($cached, true);
        if (is_array($state)) {
            $state['now'] = time();
            echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

$out = json_encode(livePublicState(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@file_put_contents($cacheFile, $out, LOCK_EX);
echo $out;
