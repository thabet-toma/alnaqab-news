<?php
/**
 * موقع النقب الإخباري — دوال مشتركة
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* ===== تنسيق النصوص والتواريخ ===== */

/** تنظيف النص للعرض (حماية XSS) */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** تقطيع نص لعدد كلمات محدد */
function excerpt(string $text, int $words = 25): string {
    $text = strip_tags($text);
    $arr = explode(' ', $text);
    if (count($arr) <= $words) return $text;
    return implode(' ', array_slice($arr, 0, $words)) . '…';
}

/** تنسيق تاريخ عربي */
function arabicDate(string $datetime): string {
    $ts = strtotime($datetime);
    $months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو',
               'يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    $day = date('d', $ts);
    $month = $months[(int)date('m', $ts) - 1];
    $year = date('Y', $ts);
    $time = date('H:i', $ts);
    return "$day $month $year — $time";
}

/** تاريخ نسبي (منذ X) */
function timeAgo(string $datetime): string {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'الآن';
    if ($diff < 3600) return 'منذ ' . floor($diff/60) . ' دقيقة';
    if ($diff < 86400) return 'منذ ' . floor($diff/3600) . ' ساعة';
    if ($diff < 604800) return 'منذ ' . floor($diff/86400) . ' يوم';
    return arabicDate($datetime);
}

/* ===== إنشاء Slug ===== */

function slugify(string $text): string {
    // إزالة التشكيل العربي
    $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
    // استبدال المسافات بشرطة
    $text = preg_replace('/\s+/', '-', trim($text));
    // إزالة الأحرف غير المسموحة (نبقي العربية والإنجليزية والأرقام)
    $text = preg_replace('/[^\p{L}\p{N}\-]/u', '', $text);
    // إزالة الشرطات المتكررة
    $text = preg_replace('/-+/', '-', $text);
    return mb_substr(trim($text, '-'), 0, 200);
}

/* ===== استعلامات الأخبار ===== */

/** جلب الأخبار المميزة */
function getFeaturedArticles(int $limit = 5): array {
    $stmt = db()->prepare('
        SELECT a.*, c.name AS category_name, c.slug AS category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = "published" AND a.is_featured = 1
        ORDER BY a.published_at DESC
        LIMIT ?
    ');
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/** جلب الأخبار العاجلة */
function getBreakingNews(int $limit = 10): array {
    $stmt = db()->prepare('
        SELECT id, title, slug, published_at
        FROM articles
        WHERE status = "published" AND is_breaking = 1
          AND published_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY published_at DESC
        LIMIT ?
    ');
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/** جلب أحدث الأخبار مع ترقيم */
function getLatestArticles(int $page = 1, int $perPage = 12, ?int $categoryId = null, ?string $type = null): array {
    $where = ['a.status = "published"'];
    $params = [];

    if ($categoryId) {
        $where[] = 'a.category_id = ?';
        $params[] = $categoryId;
    }
    if ($type) {
        $where[] = 'a.type = ?';
        $params[] = $type;
    }

    $whereSQL = implode(' AND ', $where);
    $offset = ($page - 1) * $perPage;

    // العدد الإجمالي
    $countStmt = db()->prepare("SELECT COUNT(*) FROM articles a WHERE $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // الأخبار
    $params[] = $perPage;
    $params[] = $offset;
    $stmt = db()->prepare("
        SELECT a.*, c.name AS category_name, c.slug AS category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE $whereSQL
        ORDER BY a.published_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);

    return [
        'articles' => $stmt->fetchAll(),
        'total'    => $total,
        'pages'    => ceil($total / $perPage),
        'page'     => $page,
    ];
}

/** الأكثر قراءة */
function getMostViewed(int $limit = 5): array {
    $stmt = db()->prepare('
        SELECT id, title, slug, image, views, published_at
        FROM articles
        WHERE status = "published"
          AND published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY views DESC
        LIMIT ?
    ');
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/** خبر واحد بالـ ID */
function getArticleById(int $id): ?array {
    $stmt = db()->prepare('
        SELECT a.*, c.name AS category_name, c.slug AS category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    return $article ?: null;
}

/** زيادة المشاهدات */
function incrementViews(int $articleId): void {
    db()->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')->execute([$articleId]);
}

/** أخبار ذات صلة */
function getRelatedArticles(int $articleId, int $categoryId, int $limit = 4): array {
    $stmt = db()->prepare('
        SELECT id, title, slug, image, published_at
        FROM articles
        WHERE status = "published" AND category_id = ? AND id != ?
        ORDER BY published_at DESC
        LIMIT ?
    ');
    $stmt->execute([$categoryId, $articleId, $limit]);
    return $stmt->fetchAll();
}

/** بحث */
function searchArticles(string $term, int $page = 1, int $perPage = 12): array {
    $like = '%' . $term . '%';
    $offset = ($page - 1) * $perPage;

    $countStmt = db()->prepare('SELECT COUNT(*) FROM articles WHERE status = "published" AND (title LIKE ? OR body LIKE ?)');
    $countStmt->execute([$like, $like]);
    $total = (int) $countStmt->fetchColumn();

    $stmt = db()->prepare('
        SELECT a.*, c.name AS category_name, c.slug AS category_slug
        FROM articles a
        JOIN categories c ON a.category_id = c.id
        WHERE a.status = "published" AND (a.title LIKE ? OR a.body LIKE ?)
        ORDER BY a.published_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$like, $like, $perPage, $offset]);

    return [
        'articles' => $stmt->fetchAll(),
        'total'    => $total,
        'pages'    => ceil($total / $perPage),
        'page'     => $page,
    ];
}

/* ===== التصنيفات ===== */

function getCategories(bool $activeOnly = true): array {
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    return db()->query("SELECT * FROM categories $where ORDER BY sort_order, name")->fetchAll();
}

function getCategoryById(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    return $cat ?: null;
}

/* ===== الإعلانات ===== */

function getActiveAds(?string $position = null): array {
    $where = 'WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())';
    $params = [];
    if ($position) {
        $where .= ' AND position = ?';
        $params[] = $position;
    }
    $stmt = db()->prepare("SELECT * FROM ads $where ORDER BY sort_order, id");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* ===== الراديو ===== */

function getRadioConfig(): array {
    $stmt = db()->query('SELECT * FROM radio_config WHERE id = 1 LIMIT 1');
    $config = $stmt->fetch();
    if (!$config) {
        return [
            'station_name' => 'راديو النقب',
            'tagline' => 'صوت الصحراء ونبض المجتمع',
            'stream_url' => 'https://ice1.somafm.com/groovesalad-128-mp3',
            'description' => 'راديو النقب — إذاعة عربية تبث على مدار الساعة.',
            'images' => '[]',
            'ticker' => '[]',
            'ads' => '[]',
        ];
    }
    return $config;
}

/**
 * بصمة نسخة للملفات الثابتة (CSS/JS) حتى لا يخدم المتصفح نسخة قديمة بعد أي تعديل.
 */
function assetVersion(string $relativePath): string {
    $full = __DIR__ . '/..' . $relativePath;
    $time = @filemtime($full);
    return $time ? (string)$time : '1';
}

/**
 * تطبيع قائمة صور الراديو لمصفوفة روابط نصية.
 * البيانات المخزّنة قد تكون ["url"] أو [{"src":"url"}] حسب مصدرها.
 */
function radioImages($raw): array {
    if (is_string($raw)) $raw = json_decode($raw, true);
    if (!is_array($raw)) return [];

    $urls = [];
    foreach ($raw as $item) {
        if (is_string($item)) {
            $url = trim($item);
        } elseif (is_array($item)) {
            $url = trim((string)($item['src'] ?? $item['url'] ?? $item['image'] ?? ''));
        } else {
            continue;
        }
        if ($url !== '') $urls[] = $url;
    }
    return $urls;
}

/**
 * استخراج معرّف الـ mount من رابط بث Zeno.FM لاستخدامه مع الـ Metadata API.
 * https://stream.zeno.fm/abc123  ->  abc123
 * يرجّع '' لأي رابط بث آخر (Icecast عادي مثلاً) فتُعطّل ميزة "شو شغال هلق" بهدوء.
 */
function zenoMount(string $streamUrl): string {
    $host = parse_url($streamUrl, PHP_URL_HOST) ?: '';
    if (!preg_match('/(^|\.)zeno\.fm$/i', $host)) return '';

    $path = trim(parse_url($streamUrl, PHP_URL_PATH) ?: '', '/');
    if ($path === '') return '';

    // البث قد يكون بصيغة "abc123" أو "abc123/source" أو "abc123.mp3"
    $mount = explode('/', $path)[0];
    $mount = preg_replace('/\.(mp3|aac|ogg)$/i', '', $mount);

    return preg_match('/^[A-Za-z0-9_-]+$/', $mount) ? $mount : '';
}

/* ===== الإعدادات ===== */

function getSetting(string $key, string $default = ''): string {
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function setSetting(string $key, string $value): void {
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute([$key, $value, $value]);
}

/* ===== رفع الصور ومساراتها ===== */

function articleImage(?string $image): string {
    if (!empty($image)) {
        if (strpos($image, 'http') === 0) return $image;
        if (strpos($image, 'articles/') === 0) return UPLOADS_URL . '/' . $image;
        return UPLOADS_URL . '/articles/' . $image;
    }
    return SITE_URL . '/assets/img/placeholder.svg';
}

function uploadImage(array $file, string $subdir = 'articles'): ?string {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null; // 5MB max

    // نفحص النوع الحقيقي لمحتوى الملف، لا $file['type'] لأنه قادم من المتصفح
    // وقابل للتزوير، ولا امتداد اسم الملف الأصلي (shell.php باسم صورة).
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']) ?: null;
            finfo_close($finfo);
        }
    }
    if ($mime === null) {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? null;
    }
    if ($mime === null || !isset($allowed[$mime])) return null;

    // الامتداد يُشتق من النوع الحقيقي، فلا يمكن حقن امتداد قابل للتنفيذ
    $name = uniqid('img_', true) . '.' . $allowed[$mime];
    $dir = UPLOADS_PATH . $subdir . '/';

    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $path = $dir . $name;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $subdir . '/' . $name;
    }
    return null;
}

/* ===== ترقيم الصفحات (Pagination HTML) ===== */

function paginationHtml(int $currentPage, int $totalPages, string $baseUrl): string {
    if ($totalPages <= 1) return '';
    $html = '<nav class="pagination-nav"><ul class="pagination">';

    if ($currentPage > 1) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($currentPage - 1) . '">« السابق</a></li>';
    }

    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i === $currentPage) ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($currentPage + 1) . '">التالي »</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}
