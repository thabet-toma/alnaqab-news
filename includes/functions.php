<?php
/**
 * منصة كترا الإخبارية — دوال مشتركة
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
        // القسم وأبناؤه المباشرون — قسم واحد من المستوى الأعلى يشمل فروعه
        $where[] = '(a.category_id = ? OR a.category_id IN (SELECT id FROM categories WHERE parent_id = ?))';
        $params[] = $categoryId;
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

/** أقسام المستوى الأعلى فقط (بلا أب) — الهيدر والفوتر يعرضان الرئيسية دون الفرعية */
function getCategories(bool $activeOnly = true): array {
    $sql = 'SELECT * FROM categories WHERE parent_id IS NULL';
    if ($activeOnly) $sql .= ' AND is_active = 1';
    $sql .= ' ORDER BY sort_order, name';
    return db()->query($sql)->fetchAll();
}

/** كل الأقسام: كل أب يليه أبناؤه المباشرون مرتّبين بـ sort_order ثم الاسم */
function getAllCategories(bool $activeOnly = true): array {
    $sql = 'SELECT * FROM categories';
    if ($activeOnly) $sql .= ' WHERE is_active = 1';
    $sql .= ' ORDER BY sort_order, name';
    $rows = db()->query($sql)->fetchAll();

    $parents = [];
    $childrenByParent = [];
    foreach ($rows as $row) {
        if ($row['parent_id'] === null) {
            $parents[] = $row;
        } else {
            $childrenByParent[(int) $row['parent_id']][] = $row;
        }
    }

    $ordered = [];
    foreach ($parents as $parent) {
        $ordered[] = $parent;
        foreach ($childrenByParent[(int) $parent['id']] ?? [] as $child) {
            $ordered[] = $child;
        }
        unset($childrenByParent[(int) $parent['id']]);
    }
    // أبناء غير ظاهر آباؤهم في هذه القائمة (أب معطَّل مثلاً) — لا نُسقطهم
    foreach ($childrenByParent as $orphans) {
        foreach ($orphans as $child) $ordered[] = $child;
    }

    return $ordered;
}

/** الأبناء المباشرون لقسم معيّن */
function getChildCategories(int $parentId, bool $activeOnly = true): array {
    $sql = 'SELECT * FROM categories WHERE parent_id = ?';
    if ($activeOnly) $sql .= ' AND is_active = 1';
    $sql .= ' ORDER BY sort_order, name';
    $stmt = db()->prepare($sql);
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

/** أقسام المستوى الأعلى مع مفتاح children لكل منها */
function getCategoriesWithChildren(bool $activeOnly = true): array {
    $parents = getCategories($activeOnly);
    foreach ($parents as &$parent) {
        $parent['children'] = getChildCategories((int) $parent['id'], $activeOnly);
    }
    unset($parent);
    return $parents;
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

/* ===== بث يوتيوب (StreamYard ← يوتيوب ← الموقع) ===== */

/** عمر بث يوتيوب المنشور — بعده يُعدّ منتهياً لو نسي المذيع «أنهِ البث» */
const YOUTUBE_LIVE_TTL = 21600;

/**
 * رقم فيديو يوتيوب (11 حرفاً) من أي شكل رابط شائع، أو null.
 * نخزّن الرقم وحده لا الرابط، فلا يصل للصفحة إلا [A-Za-z0-9_-].
 */
function youtubeVideoId(string $url): ?string {
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) return null;

    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '';
    $id   = '';
    if ($host === 'youtu.be') {
        $id = explode('/', ltrim($path, '/'))[0];
    } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
        if ($path === '/watch') {
            parse_str($parts['query'] ?? '', $query);
            $id = is_string($query['v'] ?? null) ? $query['v'] : '';
        } elseif (preg_match('#^/(?:live|embed|shorts)/([^/]+)#', $path, $m)) {
            $id = $m[1];
        }
    }
    return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
}

/** بث يوتيوب المنشور الآن [id, title, started_at]، أو null إن لم يوجد أو انتهت صلاحيته */
function youtubeLiveCurrent(): ?array {
    $raw = getSetting('radio_youtube_now', '');
    if ($raw === '') return null;
    $now = json_decode($raw, true);
    if (!is_array($now) || youtubeVideoId('https://youtu.be/' . ($now['id'] ?? '')) === null) return null;

    $startedAt = (int) ($now['started_at'] ?? 0);
    if (time() - $startedAt > YOUTUBE_LIVE_TTL) return null;
    return ['id' => $now['id'], 'title' => (string) ($now['title'] ?? ''), 'started_at' => $startedAt];
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

function substituteImages(): array {
    // صور استُخدمت في أكثر من خبر: بانرات/إعلانات موقع المصدر، لا صورة الخبر.
    // صورة الخبر الحقيقية فريدة، والإعلان يتكرر على كل خبر.
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    try {
        $rows = db()->query(
            "SELECT image, COUNT(*) AS c FROM articles
              WHERE image <> '' GROUP BY image HAVING c > 1"
        )->fetchAll();
        foreach ($rows as $r) $map[(string) $r['image']] = (int) $r['c'];
    } catch (Throwable $e) {
        $map = [];
    }
    return $map;
}

function isSubstituteImage(?string $image): bool {
    if (empty($image)) return false;
    $img = (string) $image;
    $low = strtolower($img);
    // أسماء ملفات معروفة على مواقع المصدر بأنها بوسترات/بانرات دعائية (للصور الخارجية فقط،
    // كي لا نرفض صوراً رفعها هو يدوياً باسم WhatsApp)
    if (strpos($low, 'http') === 0) {
        foreach (['whatsapp-image', 'mada-', '1200x600', 'telegram-cloud'] as $token) {
            if (strpos($low, $token) !== false) return true;
        }
    }
    return isset(substituteImages()[$img]);
}

function articleImage(?string $image): string {
    if (!empty($image) && !isSubstituteImage($image)) {
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

/* ===== النشر التلقائي على صفحة فيسبوك ===== */

/**
 * يحدّث معاينة الرابط المحفوظة في فيسبوك (الصورة والعنوان) قبل النشر.
 */
function facebookRefreshScrape(string $url): int {
    // فيسبوك يحفظ معاينة الرابط (الصورة) مؤقتاً. نحدّثها قبل النشر كي لا تظهر صورة قديمة/إعلان.
    if (!defined('FB_PAGE_TOKEN') || FB_PAGE_TOKEN === '' || !function_exists('curl_init')) return 0;
    $ch = curl_init('https://graph.facebook.com/v23.0/');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'id'           => $url,
            'scrape'       => 'true',
            'access_token' => FB_PAGE_TOKEN,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 6,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

function facebookPostArticle(int $articleId): bool {
    if (!defined('FB_PAGE_ID') || !defined('FB_PAGE_TOKEN') || FB_PAGE_ID === '' || FB_PAGE_TOKEN === '') return false;
    if (!function_exists('curl_init')) return false;

    $article = getArticleById($articleId);
    // غياب المفتاح يعني أن ALTER TABLE لم يُنفَّذ بعد — نمتنع كي لا يتكرر النشر
    if (!$article || !array_key_exists('fb_post_id', $article)) return false;
    if ($article['status'] !== 'published' || $article['fb_post_id'] !== null) return false;

    // نحجز الخبر ذرّياً حتى لا ينشره طلبان متزامنان (ضغطتا حفظ)
    $claim = db()->prepare("UPDATE articles SET fb_post_id = 'pending' WHERE id = ? AND fb_post_id IS NULL");
    $claim->execute([$articleId]);
    if ($claim->rowCount() === 0) return false;

    // حدّث معاينة الرابط عند فيسبوك قبل النشر (يمنع معاينة قديمة/إعلانية)
    $articleUrl = rtrim(SITE_URL, '/') . '/article.php?id=' . $articleId;
    facebookRefreshScrape($articleUrl);

    $message = $article['title'];
    $summary = excerpt($article['excerpt'] ?: $article['body'], 30);
    if ($summary !== '') $message .= "\n\n" . $summary;

    $ch = curl_init('https://graph.facebook.com/v23.0/' . rawurlencode(FB_PAGE_ID) . '/feed');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'message'      => $message,
            'link'         => $articleUrl,
            'access_token' => FB_PAGE_TOKEN,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data   = is_string($raw) ? json_decode($raw, true) : null;
    $postId = is_array($data) ? (string) ($data['id'] ?? '') : '';

    if ($postId === '') {
        db()->prepare('UPDATE articles SET fb_post_id = NULL WHERE id = ?')->execute([$articleId]);
        $why = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
        error_log("facebookPostArticle: article $articleId failed (HTTP $code) $why");
        return false;
    }

    db()->prepare('UPDATE articles SET fb_post_id = ? WHERE id = ?')->execute([$postId, $articleId]);
    return true;
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
