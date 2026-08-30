<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/radio-control.php';

requireLogin();

$db      = db();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = 'اسم البلاي ليست مطلوب';
        } else {
            try {
                $db->prepare('INSERT INTO radio_playlists (name, description) VALUES (?, ?)')
                   ->execute([$name, $description]);
                $success = 'تم إنشاء البلاي ليست';
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'يوجد بلاي ليست بهذا الاسم' : 'تعذّر الإنشاء';
            }
        }

    } elseif ($action === 'rename') {
        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = 'اسم البلاي ليست مطلوب';
        } else {
            try {
                $db->prepare('UPDATE radio_playlists SET name = ?, description = ? WHERE id = ?')
                   ->execute([$name, $description, $id]);
                $success = 'تم حفظ التعديل';
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'يوجد بلاي ليست بهذا الاسم' : 'تعذّر الحفظ';
            }
        }

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM radio_playlists WHERE id = ?')->execute([$id]);
        $success = 'تم حذف البلاي ليست';

    } elseif ($action === 'set_default') {
        $id  = (int) ($_POST['id'] ?? 0);
        $chk = $db->prepare('SELECT is_managed FROM radio_playlists WHERE id = ?');
        $chk->execute([$id]);
        $row = $chk->fetch();

        if (!$row) {
            $error = 'البلاي ليست غير موجودة';
        } elseif ((int) $row['is_managed'] === 1) {
            // محتوى البلاي ليست المُدارة يُبنى آلياً من خارج اللوحة وقد يُفرَّغ،
            // فتعيينها افتراضية يُصمِت البث — نمنع ذلك هنا في الخادم لا في الواجهة فقط
            $error = 'لا يمكن اختيار بلاي ليست مُدارة آلياً كافتراضية';
        } else {
            $db->prepare('UPDATE radio_config SET default_playlist_id = ? WHERE id = 1')->execute([$id]);
            refreshDefaultM3u();
            $success = 'تم تعيين البلاي ليست الافتراضية';
        }
    }
}

$playlists = $db->query(
    "SELECT p.*,
            COUNT(CASE WHEN t.status = 'ok' THEN 1 END) AS ok_count,
            COALESCE(SUM(CASE WHEN t.status = 'ok' THEN t.duration ELSE 0 END), 0) AS total_duration
       FROM radio_playlists p
       LEFT JOIN radio_playlist_items i ON i.playlist_id = p.id
       LEFT JOIN radio_tracks t ON t.id = i.track_id
      GROUP BY p.id
      ORDER BY p.name"
)->fetchAll();

$defaultId = (int) (getRadioConfig()['default_playlist_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>البلاي ليست - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
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
                <h1>البلاي ليست</h1>
            </header>

            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-main">
                        <div class="content-panel">
                            <h3>البلاي ليست (<?php echo count($playlists); ?>)</h3>

                            <?php if (empty($playlists)): ?>
                                <p class="empty-note">لا يوجد أي بلاي ليست بعد. أنشئ أول واحدة من النموذج المجاور.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                <table class="tracks-table">
                                    <thead>
                                        <tr>
                                            <th>الاسم</th>
                                            <th>الوصف</th>
                                            <th>المقاطع</th>
                                            <th>المدة</th>
                                            <th>الافتراضية</th>
                                            <th>إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($playlists as $p): ?>
                                        <?php
                                        $pid        = (int) $p['id'];
                                        $isManaged  = (int) $p['is_managed'] === 1;
                                        $isDefault  = $pid === $defaultId;
                                        $formId     = 'playlist-edit-' . $pid;
                                        ?>
                                        <tr>
                                            <td>
                                                <form method="POST" id="<?php echo e($formId); ?>" class="rename-form">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="rename">
                                                    <input type="hidden" name="id" value="<?php echo $pid; ?>">
                                                    <input type="text" name="name" form="<?php echo e($formId); ?>"
                                                           value="<?php echo e($p['name']); ?>"
                                                           class="form-control form-control-sm title-input" required>
                                                </form>
                                                <?php if ($isManaged): ?>
                                                    <span class="badge badge-cloud" title="محتواها يُبنى آلياً من خارج اللوحة">مُدارة</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="text" name="description" form="<?php echo e($formId); ?>"
                                                       value="<?php echo e($p['description']); ?>"
                                                       class="form-control form-control-sm title-input" placeholder="بلا وصف">
                                            </td>
                                            <td class="num"><?php echo (int) $p['ok_count']; ?></td>
                                            <td class="num"><?php echo formatDuration((int) $p['total_duration'] ?: null); ?></td>
                                            <td>
                                                <?php if ($isDefault): ?>
                                                    <span class="badge badge-on">افتراضية</span>
                                                <?php elseif (!$isManaged): ?>
                                                    <form method="POST" class="inline-form">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="set_default">
                                                        <input type="hidden" name="id" value="<?php echo $pid; ?>">
                                                        <button type="submit" class="btn btn-ghost btn-sm">تعيين كافتراضية</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="status-hint">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <button type="submit" form="<?php echo e($formId); ?>" class="btn btn-ghost btn-sm">حفظ</button>
                                                <a href="radio-playlist.php?id=<?php echo $pid; ?>" class="btn btn-primary btn-sm">التفاصيل</a>
                                                <form method="POST" class="inline-form"
                                                      onsubmit="return confirm('حذف بلاي ليست «<?php echo e($p['name']); ?>» نهائياً؟ مقاطعها ستُحذف معها.');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $pid; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-sidebar">
                        <div class="content-panel">
                            <h3>بلاي ليست جديدة</h3>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="create">

                                <div class="form-group">
                                    <label for="name">الاسم</label>
                                    <input type="text" name="name" id="name" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="description">الوصف (اختياري)</label>
                                    <input type="text" name="description" id="description" class="form-control">
                                </div>

                                <button type="submit" class="btn btn-primary w-100">إنشاء</button>
                            </form>

                            <hr class="panel-sep">
                            <p class="form-hint">
                                الترتيب داخل كل بلاي ليست يُحدَّد من صفحة تفاصيلها. البلاي
                                ليست الافتراضية هي التي يشتغل بها التشغيل التلقائي —
                                البلاي ليست المُدارة آلياً لا يمكن اختيارها لأن محتواها قد
                                يُفرَّغ من خارج اللوحة.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>
