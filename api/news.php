<?php
/**
 * خدمة أخبار عامة للقراءة فقط — يسحبها خادم «أعراسنا» / مناسبات النقب كل بضع
 * دقائق (من خادم لخادم: بلا متصفح، بلا مصادقة، بلا جلسة).
 *
 * ثلاثة أنماط:
 *   ?zones=1                          ← المناطق النشطة
 *   ?area=<slug|name|id>&limit&offset ← أخبار منطقة
 *   ?id=<id>                          ← خبر واحد بكامل نصّه
 *
 * لا منطقة مكتوبة في الكود: كل تصنيف فرعي نشط تحت التصنيف الأب هو منطقة،
 * تُقرأ من قاعدة البيانات عند كل طلب. إضافة منطقة = إضافة تصنيف من اللوحة فقط.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// استجابة JSON نقيّة: لا يجوز أن يتسرّب أي تحذير أو إشعار PHP إلى الجسم
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// «أخبار محلية» — التصنيف الأب الموثَّق لكل مناطق النقب
const NEWS_LOCAL_PARENT_ID = 1;

/** إرسال استجابة ناجحة (كاش دقيقة، لأن الخادم الآخر يسأل كل بضع دقائق) */
function newsSend(array $out): void {
    $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('json_encode: ' . json_last_error_msg());
    }
    header('Cache-Control: public, max-age=60');
    echo $json;
}

/** إرسال خطأ: بلا كاش حتى لا يبقى الخطأ محفوظاً عند الخادم الآخر */
function newsFail(int $status, string $error, string $message): void {
    http_response_code($status);
    header('Cache-Control: no-store');
    echo json_encode(
        ['ok' => false, 'error' => $error, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/** نص عادي: إزالة الوسوم، فكّ الكيانات، طيّ المسافات */
function newsPlain(string $html): string {
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // &nbsp; ليست مسافة عادية في تعبيرات النمط، فتُستبدل صراحةً
    $text = str_replace("\u{00A0}", ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return trim($text);
}

/** مقتطف بحد أقصى 300 حرفاً شاملًا علامة القطع */
function newsExcerpt(array $row): string {
    $text = newsPlain((string) ($row['excerpt'] ?? ''));
    if ($text === '') {
        $text = newsPlain((string) ($row['body'] ?? ''));
    }
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) > 300) {
        return mb_substr($text, 0, 299) . '…';
    }
    return $text;
}

/** نص الخبر: الفقرات والأسطر تبقى، وسوم HTML تُحذف، والمسافات تُطبَّع */
function newsBodyText(string $body): string {
    $text = preg_replace('/<br\s*\/?>/i', "\n", $body) ?? '';
    $text = preg_replace('/<\/?p\b[^>]*>/i', "\n", $text) ?? '';
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\u{00A0}", ' ', $text);
    // المسافات الأفقية فقط تُطوى، والأسطر تبقى حدوداً للفقرات
    $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? '';
    $text = preg_replace('/ *\n */', "\n", $text) ?? '';
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';
    return trim($text);
}

/** رابط الصورة مطلقاً، أو null عند غيابها. لا نستدعي articleImage() لأن نسخة
 *  الإنتاج تختلف عن هذه النسخة، فنبني الرابط هنا مباشرةً. */
function newsImage(?string $image): ?string {
    $image = trim((string) $image);
    if ($image === '') {
        return null;
    }
    if (str_starts_with($image, 'http')) {
        return $image;
    }
    if (str_starts_with($image, 'articles/')) {
        return rtrim(UPLOADS_URL, '/') . '/' . $image;
    }
    return rtrim(UPLOADS_URL, '/') . '/articles/' . $image;
}

/** تاريخ النشر: القيمة مخزّنة بساعة MySQL (UTC) بينما توقيت PHP آسيا/الخليل،
 *  والمستهلك يريد ساعة التخزين نفسها موسومة بتوقيت القدس — لا تحويل، وسم فقط. */
function newsDate(string $stored): ?string {
    if ($stored === '') {
        return null;
    }
    return (new DateTime($stored, new DateTimeZone('Asia/Jerusalem')))->format('c');
}

/** بنية الخبر الواحد؛ في نمط التفصيل يُضاف اسم الكاتب ونصّ الخبر */
function newsItem(array $row, bool $detail = false): array {
    $item = [
        'id'           => (int) $row['id'],
        'title'        => (string) $row['title'],
        'excerpt'      => newsExcerpt($row),
        'image'        => newsImage($row['image'] ?? null),
        'url'          => rtrim(SITE_URL, '/') . '/article.php?id=' . (int) $row['id'],
        'published_at' => newsDate((string) ($row['published_at'] ?? '')),
        'is_breaking'  => (int) ($row['is_breaking'] ?? 0) === 1,
        'category'     => [
            'id'   => (int) $row['category_id'],
            'name' => (string) $row['category_name'],
            'slug' => (string) $row['category_slug'],
        ],
    ];
    if ($detail) {
        $item['author_name'] = (string) ($row['author_name'] ?? '');
        $item['body_text']   = newsBodyText((string) ($row['body'] ?? ''));
    }
    return $item;
}

/** التصنيف الأب نفسه (وضع general) */
function newsParent(PDO $db): array {
    $stmt = $db->prepare('SELECT id, name, slug FROM categories WHERE id = ?');
    $stmt->bindValue(1, NEWS_LOCAL_PARENT_ID, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('local news parent category ' . NEWS_LOCAL_PARENT_ID . ' is missing');
    }
    return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug']];
}

/** أبناء التصنيف الأب النشطون — هم المناطق، تُقرأ من قاعدة البيانات الآن */
function newsChildren(PDO $db): array {
    $stmt = $db->prepare(
        'SELECT id, name, slug
           FROM categories
          WHERE parent_id = ? AND is_active = 1
          ORDER BY sort_order ASC, name ASC'
    );
    $stmt->bindValue(1, NEWS_LOCAL_PARENT_ID, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** المناطق مع عدد الأخبار المنشورة في كل واحدة، باستعلام واحد (استعلام فرعي) */
function newsZones(PDO $db): array {
    $stmt = $db->prepare(
        "SELECT c.id, c.name, c.slug,
                (SELECT COUNT(*)
                   FROM articles a
                  WHERE a.category_id = c.id AND a.status = 'published') AS published_count
           FROM categories c
          WHERE c.parent_id = ? AND c.is_active = 1
          ORDER BY c.sort_order ASC, c.name ASC"
    );
    $stmt->bindValue(1, NEWS_LOCAL_PARENT_ID, PDO::PARAM_INT);
    $stmt->execute();

    $zones = [];
    foreach ($stmt->fetchAll() as $row) {
        $zones[] = [
            'id'    => (int) $row['id'],
            'name'  => (string) $row['name'],
            'slug'  => (string) $row['slug'],
            'count' => (int) $row['published_count'],
        ];
    }
    return $zones;
}

/** مطابقة نصّ المنطقة مع الأبناء النشطين: slug ثم الاسم ثم المعرّف الرقمي.
 *  لا يُمرَّر نصّ الطلب إلى SQL إطلاقاً — المطابقة هنا ثم يُمرَّر المعرّف فقط. */
function newsResolveArea(PDO $db, string $raw): array {
    $needle = preg_replace('/\s+/u', ' ', trim($raw)) ?? trim($raw);

    if ($needle === '' || $needle === 'general') {
        return newsParent($db);
    }

    $children = newsChildren($db);

    foreach ($children as $child) {
        if ((string) $child['slug'] === $needle) {
            return ['id' => (int) $child['id'], 'name' => (string) $child['name'], 'slug' => (string) $child['slug']];
        }
    }

    foreach ($children as $child) {
        $name = preg_replace('/\s+/u', ' ', trim((string) $child['name'])) ?? '';
        if ($name === $needle) {
            return ['id' => (int) $child['id'], 'name' => (string) $child['name'], 'slug' => (string) $child['slug']];
        }
    }

    if (preg_match('/^\d+$/', $needle) === 1 && (int) $needle > 0) {
        foreach ($children as $child) {
            if ((int) $child['id'] === (int) $needle) {
                return ['id' => (int) $child['id'], 'name' => (string) $child['name'], 'slug' => (string) $child['slug']];
            }
        }
    }

    newsFail(404, 'zone_not_found', 'المنطقة غير موجودة');
}

/** قراءة معامل رقمي اختياري مع قيمة افتراضية آمنة */
function newsIntParam(string $key, int $default): int {
    if (!isset($_GET[$key]) || !is_string($_GET[$key])) {
        return $default;
    }
    $raw = trim($_GET[$key]);
    if ($raw === '' || !is_numeric($raw)) {
        return $default;
    }
    return (int) $raw;
}

try {
    $db = db();

    if (array_key_exists('id', $_GET)) {
        // ===== خبر واحد =====
        $rawId = $_GET['id'];
        if (!is_string($rawId) || preg_match('/^\d+$/', trim($rawId)) !== 1 || (int) $rawId < 1) {
            newsFail(400, 'invalid_parameter', 'معامل غير صالح');
        }
        $id = (int) $rawId;

        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.excerpt, a.body, a.image, a.published_at, a.is_breaking,
                    a.author_name,
                    c.id AS category_id, c.name AS category_name, c.slug AS category_slug
               FROM articles a
               JOIN categories c ON c.id = a.category_id
              WHERE a.id = ? AND a.status = 'published'"
        );
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            newsFail(404, 'not_found', 'الخبر غير موجود');
        }

        newsSend(['ok' => true, 'item' => newsItem($row, true)]);
    } elseif (array_key_exists('zones', $_GET)) {
        // ===== قائمة المناطق =====
        newsSend(['ok' => true, 'zones' => newsZones($db)]);
    } else {
        // ===== أخبار منطقة =====
        $rawArea = array_key_exists('area', $_GET) ? $_GET['area'] : '';
        if (!is_string($rawArea)) {
            // مصفوفة بدل نص: لا يمكن أن تطابق منطقة معروفة
            newsFail(404, 'zone_not_found', 'المنطقة غير موجودة');
        }
        $area   = newsResolveArea($db, $rawArea);
        $limit  = max(1, min(50, newsIntParam('limit', 20)));
        $offset = max(0, newsIntParam('offset', 0));

        $stmt = $db->prepare(
            "SELECT a.id, a.title, a.excerpt, a.body, a.image, a.published_at, a.is_breaking,
                    c.id AS category_id, c.name AS category_name, c.slug AS category_slug
               FROM articles a
               JOIN categories c ON c.id = a.category_id
              WHERE a.category_id = ? AND a.status = 'published'
              ORDER BY a.published_at DESC, a.id DESC
              LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $area['id'], PDO::PARAM_INT);
        // صفّ زائد واحد لكشف وجود المزيد بلا استعلام عدد ثانٍ
        $stmt->bindValue(2, $limit + 1, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $limit);
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = newsItem($row);
        }

        newsSend([
            'ok'       => true,
            'area'     => $area,
            'count'    => count($items),
            'has_more' => $hasMore,
            'items'    => $items,
        ]);
    }
} catch (Throwable $e) {
    error_log('api/news.php: ' . $e->getMessage());
    newsFail(500, 'server_error', 'خطأ داخلي في الخادم');
}
