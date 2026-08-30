<?php
/**
 * موقع النقب الإخباري — اتصال قاعدة البيانات (PDO Singleton)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                die('خطأ في الاتصال بقاعدة البيانات. تحقق من إعدادات config.php');
            }
        }
        return self::$instance;
    }

    // منع الاستنساخ والإنشاء الخارجي
    private function __construct() {}
    private function __clone() {}
}

/**
 * اختصار للحصول على اتصال قاعدة البيانات
 */
function db(): PDO {
    return Database::get();
}
