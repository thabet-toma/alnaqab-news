<?php
// sitemap.php — خريطة الموقع لمحركات البحث (يُشار إليها من robots.txt)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=600');

$base = rtrim(SITE_URL, '/');

$urls = [
    ['loc' => $base . '/',          'changefreq' => 'hourly', 'priority' => '1.0'],
    ['loc' => $base . '/radio.php', 'changefreq' => 'daily',  'priority' => '0.7'],
];

foreach (getCategories(true) as $cat) {
    $urls[] = ['loc' => $base . '/category.php?id=' . (int) $cat['id'], 'changefreq' => 'hourly', 'priority' => '0.8'];
}

$rows = db()->query(
    "SELECT id, published_at, updated_at FROM articles
      WHERE status = 'published' AND published_at <= NOW()
      ORDER BY id DESC LIMIT 49000"
)->fetchAll();

foreach ($rows as $r) {
    $last = max(strtotime((string) $r['updated_at']) ?: 0, strtotime((string) $r['published_at']) ?: 0);
    $urls[] = [
        'loc'        => $base . '/article.php?id=' . (int) $r['id'],
        'lastmod'    => $last ? date('c', $last) : null,
        'changefreq' => 'monthly',
        'priority'   => '0.6',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
    if (!empty($u['lastmod'])) echo '<lastmod>' . $u['lastmod'] . '</lastmod>';
    echo '<changefreq>' . $u['changefreq'] . '</changefreq><priority>' . $u['priority'] . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
