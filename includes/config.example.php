<?php
/**
 * موقع النقب الإخباري — قالب الإعدادات
 *
 * ⚠️ هذا الملف قالب فقط. انسخه باسم config.php ثم عبّئ القيم:
 *
 *     cp includes/config.example.php includes/config.php
 *
 * ملف config.php لا يُرفع إلى Git لأنه يحتوي كلمات السر،
 * فلكل سيرفر (جهازك / السيرفر المباشر) نسخته الخاصة.
 */

// ===== البيئة =====
// 'production' على السيرفر المباشر  |  'development' على جهازك
define('APP_ENV', 'production');

// ===== قاعدة البيانات =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'naqab_news');
define('DB_USER', 'ضع_اسم_مستخدم_قاعدة_البيانات');
define('DB_PASS', 'ضع_كلمة_سر_قاعدة_البيانات');
define('DB_CHARSET', 'utf8mb4');

// ===== الموقع =====
define('SITE_NAME', 'موقع النقب الإخباري');

// ⬇️⬇️ غيّر هذا لدومينك. بدون شرطة مائلة / في النهاية ⬇️⬇️
define('SITE_URL', 'https://example.com');

define('SITE_DESC', 'موقع النقب الإخباري — أخبار، مقالات، وراديو مباشر من قلب النقب');

// ===== مسارات =====
define('ROOT_PATH', dirname(__DIR__) . '/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('UPLOADS_URL', SITE_URL . '/uploads/');

// ===== إعدادات الجلسة =====
define('SESSION_LIFETIME', 86400); // 24 ساعة

// ===== إعدادات عامة =====
define('ARTICLES_PER_PAGE', 12);
define('ADMIN_ARTICLES_PER_PAGE', 20);

// ===== عرض الأخطاء =====
// في الإنتاج نُخفي تفاصيل الأخطاء عن الزوار ونسجّلها في سجلّ السيرفر،
// لأن رسائل الخطأ قد تكشف مسارات الملفات وبنية قاعدة البيانات.
error_reporting(E_ALL);
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
}

// ===== منع الوصول المباشر =====
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Access denied');
}
