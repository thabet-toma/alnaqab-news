<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/radio-control.php';

requireLogin();

$db         = db();
$error      = '';
$success    = '';
$playlistId = (int) ($_GET['id'] ?? 0);

$playlistStmt = $db->prepare('SELECT * FROM radio_playlists WHERE id = ?');
$playlistStmt->execute([$playlistId]);
$playlist = $playlistStmt->fetch();

if (!$playlist) {
    header('Location: radio-playlists.php');
    exit;
}

$isManaged = (int) $playlist['is_managed'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($isManaged) {
        // محتوى البلاي ليست المُدارة يُبنى آلياً من خارج اللوحة — نمنع أي تعديل
        // يدوي هنا في الخادم لا في الواجهة فقط، فالأزرار قد تُستدعى مباشرة
        $error = 'هذه البلاي ليست مُدارة آلياً ولا يمكن تعديلها من هنا';

    } elseif ($action === 'add_track') {
        $trackId = (int) ($_POST['track_id'] ?? 0);
        $exists  = $db->prepare('SELECT COUNT(*) FROM radio_tracks WHERE id = ?');
        $exists->execute([$trackId]);

        if (!$exists->fetchColumn()) {
            $error = 'اختر مقطعاً من المكتبة';
        } else {
            // تكرار نفس المقطع مسموح ومقصود (جينجل يتكرر)، فلا نفحص وجوده مسبقاً
            $maxOrder = $db->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM radio_playlist_items WHERE playlist_id = ?');
            $maxOrder->execute([$playlistId]);
            $next = (int) $maxOrder->fetchColumn() + 1;

            $db->prepare('INSERT INTO radio_playlist_items (playlist_id, track_id, sort_order) VALUES (?, ?, ?)')
               ->execute([$playlistId, $trackId, $next]);
            writePlaylistM3u($playlistId);
            $success = 'تمت إضافة المقطع';
        }

    } elseif ($action === 'remove_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $db->prepare('DELETE FROM radio_playlist_items WHERE id = ? AND playlist_id = ?')
           ->execute([$itemId, $playlistId]);
        writePlaylistM3u($playlistId);
        $success = 'تمت إزالة المقطع';

    } elseif ($action === 'move_up' || $action === 'move_down') {
        $itemId = (int) ($_POST['item_id'] ?? 0);

        $ordered = $db->prepare('SELECT id, sort_order FROM radio_playlist_items WHERE playlist_id = ? ORDER BY sort_order, id');
        $ordered->execute([$playlistId]);
        $rows = $ordered->fetchAll();

        $idx = null;
        foreach ($rows as $i => $row) {
            if ((int) $row['id'] === $itemId) { $idx = $i; break; }
        }

        $swapIdx = $idx === null ? null : ($action === 'move_up' ? $idx - 1 : $idx + 1);

        if ($idx !== null && $swapIdx !== null && isset($rows[$swapIdx])) {
            $a = $rows[$idx];
            $b = $rows[$swapIdx];
            $update = $db->prepare('UPDATE radio_playlist_items SET sort_order = ? WHERE id = ?');
            $update->execute([$b['sort_order'], $a['id']]);
            $update->execute([$a['sort_order'], $b['id']]);
            writePlaylistM3u($playlistId);
            $success = 'تم تحريك المقطع';
        }
    }
}

$items = $db->prepare(
    "SELECT i.id, i.track_id, i.sort_order, t.title, t.duration, t.status
       FROM radio_playlist_items i
       JOIN radio_tracks t ON t.id = i.track_id
      WHERE i.playlist_id = ?
      ORDER BY i.sort_order, i.id"
);
$items->execute([$playlistId]);
$items = $items->fetchAll();

$tracks = $db->query('SELECT id, title, status FROM radio_tracks ORDER BY title')->fetchAll();

$totalDuration = 0;
foreach ($items as $it) {
    if ($it['status'] === 'ok') $totalDuration += (int) $it['duration'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($playlist['name']); ?> - البلاي ليست - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/radio-admin.css">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <aside class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><?php echo e(SITE_NAME); ?></h2>
                <button class="close-sidebar" id="closeSidebar">&times;</button>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php">الرئيسية</a>
                <a href="articles.php">المقالات والأخبار</a>
                <a href="categories.php">الأقسام</a>
                <a href="ads.php">الإعلانات</a>
                <a href="radio-settings.php">إعدادات الراديو</a>
                <a href="radio-library.php">مكتبة الصوتيات</a>
                <a href="radio-playlists.php" class="active">البلاي ليست</a>
                <a href="radio-schedule.php">جدولة البث</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1><?php echo e($playlist['name']); ?></h1>
            </header>

            <div class="admin-content">
                <p><a href="radio-playlists.php">&laquo; كل البلاي ليست</a></p>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>
                <?php if ($isManaged): ?>
                    <div class="alert alert-danger">
                        هذه البلاي ليست مُدارة آلياً — محتواها يُبنى من خارج اللوحة وأي
                        تعديل يدوي يُمحى، لذلك أزرار التحرير مخفية هنا.
                    </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-main">
                        <div class="content-panel">
                            <h3>المقاطع (<?php echo count($items); ?>) — <?php echo formatDuration($totalDuration ?: null); ?></h3>

                            <?php if (empty($items)): ?>
                                <p class="empty-note">لا يوجد أي مقطع في هذه البلاي ليست بعد.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                <table class="tracks-table">
                                    <thead>
                                        <tr>
                                            <th>الترتيب</th>
                                            <th>المقطع</th>
                                            <th>المدة</th>
                                            <?php if (!$isManaged): ?><th>إجراءات</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($items as $pos => $it): ?>
                                        <?php $isMissing = $it['status'] === 'missing'; ?>
                                        <tr class="<?php echo $isMissing ? 'is-missing' : ''; ?>">
                                            <td class="num"><?php echo $pos + 1; ?></td>
                                            <td>
                                                <?php echo e($it['title']); ?>
                                                <?php if ($isMissing): ?>
                                                    <span class="badge badge-missing" title="الملف غير موجود على القرص حالياً">مفقود</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num"><?php echo formatDuration($it['duration'] !== null ? (int) $it['duration'] : null); ?></td>
                                            <?php if (!$isManaged): ?>
                                            <td class="actions">
                                                <form method="POST" class="inline-form">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="move_up">
                                                    <input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                                                    <button type="submit" class="btn btn-ghost btn-sm" <?php echo $pos === 0 ? 'disabled' : ''; ?>>أعلى</button>
                                                </form>
                                                <form method="POST" class="inline-form">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="move_down">
                                                    <input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                                                    <button type="submit" class="btn btn-ghost btn-sm" <?php echo $pos === count($items) - 1 ? 'disabled' : ''; ?>>أسفل</button>
                                                </form>
                                                <form method="POST" class="inline-form"
                                                      onsubmit="return confirm('إزالة «<?php echo e($it['title']); ?>» من البلاي ليست؟');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="remove_item">
                                                    <input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">إزالة</button>
                                                </form>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$isManaged): ?>
                    <div class="form-sidebar">
                        <div class="content-panel">
                            <h3>إضافة مقطع</h3>

                            <?php if (empty($tracks)): ?>
                                <p class="empty-note">
                                    لازم ترفع مقاطع أولاً من
                                    <a href="radio-library.php">مكتبة الصوتيات</a>.
                                </p>
                            <?php else: ?>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="add_track">

                                <div class="form-group">
                                    <label for="track_id">المقطع</label>
                                    <select name="track_id" id="track_id" class="form-control" required>
                                        <?php foreach ($tracks as $t): ?>
                                            <option value="<?php echo (int) $t['id']; ?>">
                                                <?php echo e($t['title']); ?><?php echo $t['status'] === 'missing' ? ' (مفقود)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">إضافة</button>
                            </form>
                            <?php endif; ?>

                            <hr class="panel-sep">
                            <p class="form-hint">
                                يمكن إضافة نفس المقطع أكثر من مرة (جينجل يتكرّر مثلاً).
                                ملف الـ .m3u يُبنى تلقائياً من هذا الترتيب ويستبعد أي
                                مقطع مفقود.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>
