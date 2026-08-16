<?php
/**
 * موقع النقب الإخباري — إعدادات قاعدة البيانات
 * غيّر القيم أدناه لتتوافق مع إعدادات Hostinger الخاصة بك
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'naqab_news');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الموقع
define('SITE_NAME', 'موقع النقب الإخباري');
define('SITE_URL', 'http://localhost/radio');  // غيّره لدومينك
define('SITE_DESC', 'موقع النقب الإخباري — أخبار، مقالات، وراديو مباشر من قلب النقب');

// مسارات
define('ROOT_PATH', dirname(__DIR__) . '/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('UPLOADS_URL', SITE_URL . '/uploads/');

// إعدادات الجلسة
define('SESSION_LIFETIME', 86400); // 24 ساعة

// إعدادات عامة
define('ARTICLES_PER_PAGE', 12);
define('ADMIN_ARTICLES_PER_PAGE', 20);

// منع الوصول المباشر
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Access denied');
}
