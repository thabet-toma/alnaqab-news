<?php
/**
 * موقع النقب الإخباري — مصادقة الأدمن (Session-based)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// بدء الجلسة إذا لم تكن مبدوءة
if (session_status() === PHP_SESSION_NONE) {
    // على HTTPS نضع secure حتى لا يُرسل كوكي الجلسة عبر اتصال غير مشفّر.
    // نراعي أيضاً الحالة التي يقف فيها بروكسي/CDN أمام السيرفر.
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    session_set_cookie_params([
        'lifetime'  => SESSION_LIFETIME,
        'path'      => '/',
        'secure'    => $isHttps,
        'httponly'  => true,
        'samesite'  => 'Lax'
    ]);
    session_start();
}

/**
 * التحقق من تسجيل دخول الأدمن
 */
function isLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

/**
 * فرض تسجيل الدخول — يوجه لصفحة الدخول إذا غير مسجل
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * محاولة تسجيل الدخول
 */
function attemptLogin(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_user'] = $username;
        session_regenerate_id(true);
        return true;
    }
    return false;
}

/**
 * تسجيل الخروج
 */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * إنشاء وتحقق من CSRF Token
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

function verifyCsrf(): bool {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrfToken(), $token);
}

/**
 * حقل CSRF مخفي للنماذج
 */
function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
        http_response_code(403);
        die('خطأ أمني: رمز CSRF غير صالح');
    }
}
