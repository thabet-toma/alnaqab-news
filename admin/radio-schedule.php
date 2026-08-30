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

/** أيام الأسبوع — الترتيب حسب دالة date('w') حيث 0 = الأحد */
const WEEKDAYS = [
    0 => 'الأحد',
    1 => 'الاثنين',
    2 => 'الثلاثاء',
    3 => 'الأربعاء',
    4 => 'الخميس',
    5 => 'الجمعة',
    6 => 'السبت',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $trackId = (int) ($_POST['track_id'] ?? 0);
        $playAt  = trim($_POST['play_at'] ?? '');

        // نقبل أرقام الأيام الصحيحة فقط، ونرتّبها لتخزين موحّد
        $days = array_values(array_intersect(
            array_map('intval', (array) ($_POST['days'] ?? [])),
            array_keys(WEEKDAYS)
        ));
        sort($days);

        $exists = $db->prepare('SELECT COUNT(*) FROM radio_tracks WHERE id = ?');
        $exists->execute([$trackId]);

        if (!$exists->fetchColumn()) {
            $error = 'اختر مقطعاً من المكتبة';
        } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $playAt)) {
            $error = 'الوقت غير صالح';
        } else {
            $stmt = $db->prepare(
                'INSERT INTO radio_schedule (track_id, play_at, days) VALUES (?, ?, ?)'
            );
            $stmt->execute([$trackId, $playAt . ':00', implode(',', $days)]);
            $success = 'تمت إضافة الموعد';
        }

    } elseif ($action === 'delete') {
        $db->prepare('DELETE FROM radio_schedule WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        $success = 'تم حذف الموعد';

    } elseif ($action === 'toggle') {
        $db->prepare('UPDATE radio_schedule SET active = 1 - active WHERE id = ?')
           ->execute([(int) ($_POST['id'] ?? 0)]);
        $success = 'تم تغيير حالة الموعد';
    }
}

$schedule = $db->query(
    'SELECT s.*, t.title, t.duration
       FROM radio_schedule s
       JOIN radio_tracks t ON t.id = s.track_id
      ORDER BY s.play_at'
)->fetchAll();

$tracks = $db->query('SELECT id, title FROM radio_tracks ORDER BY title')->fetchAll();

// نقرأ توقيت السيرفر لنعرضه — الجدولة تعمل بتوقيته لا بتوقيت متصفح المدير
$serverNow = date('H:i');
$cronOk    = is_file('/etc/cron.d/naqab-radio');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جدولة البث - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
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
                <a href="radio-schedule.php" class="active">جدولة البث</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>جدولة البث</h1>
            </header>

            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>
                <?php if (!$cronOk): ?>
                    <div class="alert alert-danger">
                        مهمة الجدولة غير مثبّتة على السيرفر — المواعيد لن تشتغل تلقائياً.
                    </div>
                <?php endif; ?>

                <div class="radio-status-bar">
                    <div class="status-main">
                        <span class="status-dot auto"></span>
                        <strong>توقيت السيرفر الآن: <?php echo e($serverNow); ?></strong>
                        <span class="status-hint">المواعيد تعمل بتوقيت السيرفر (Asia/Hebron)</span>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-main">
                        <div class="content-panel">
                            <h3>المواعيد (<?php echo count($schedule); ?>)</h3>

                            <?php if (empty($schedule)): ?>
                                <p class="empty-note">لا يوجد أي موعد. أضف أول موعد من النموذج المجاور.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                <table class="tracks-table">
                                    <thead>
                                        <tr>
                                            <th>الوقت</th>
                                            <th>المقطع</th>
                                            <th>الأيام</th>
                                            <th>الحالة</th>
                                            <th>إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($schedule as $s): ?>
                                        <?php
                                        $dayList = array_filter(explode(',', (string) $s['days']), 'strlen');
                                        $dayText = empty($dayList)
                                            ? 'كل يوم'
                                            : implode('، ', array_map(fn($d) => WEEKDAYS[(int) $d] ?? '', $dayList));
                                        ?>
                                        <tr class="<?php echo $s['active'] ? '' : 'row-off'; ?>">
                                            <td class="num time-cell"><?php echo e(substr((string) $s['play_at'], 0, 5)); ?></td>
                                            <td><?php echo e($s['title']); ?></td>
                                            <td><?php echo e($dayText); ?></td>
                                            <td>
                                                <span class="badge <?php echo $s['active'] ? 'badge-on' : 'badge-off'; ?>">
                                                    <?php echo $s['active'] ? 'مفعّل' : 'موقوف'; ?>
                                                </span>
                                            </td>
                                            <td class="actions">
                                                <form method="POST" class="inline-form">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                                    <button type="submit" class="btn btn-ghost btn-sm">
                                                        <?php echo $s['active'] ? 'إيقاف' : 'تفعيل'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" class="inline-form"
                                                      onsubmit="return confirm('حذف هذا الموعد؟');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
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
                            <h3>إضافة موعد</h3>

                            <?php if (empty($tracks)): ?>
                                <p class="empty-note">
                                    لازم ترفع مقاطع أولاً من
                                    <a href="radio-library.php">مكتبة الصوتيات</a>.
                                </p>
                            <?php else: ?>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="add">

                                <div class="form-group">
                                    <label for="track_id">المقطع</label>
                                    <select name="track_id" id="track_id" class="form-control" required>
                                        <?php foreach ($tracks as $t): ?>
                                            <option value="<?php echo (int) $t['id']; ?>"><?php echo e($t['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="play_at">الوقت</label>
                                    <input type="time" name="play_at" id="play_at" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>الأيام</label>
                                    <div class="days-grid">
                                        <?php foreach (WEEKDAYS as $num => $name): ?>
                                            <label class="day-check">
                                                <input type="checkbox" name="days[]" value="<?php echo $num; ?>">
                                                <span><?php echo e($name); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="form-hint">اتركها كلها فارغة ليشتغل كل يوم</small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">إضافة</button>
                            </form>
                            <?php endif; ?>

                            <hr class="panel-sep">
                            <p class="form-hint">
                                بالموعد المحدد يُدفع المقطع فيقطع ما يشتغل حالياً، وبعد
                                انتهائه ترجع الأغاني تلقائياً. المذيع المباشر يبقى أولوية
                                أعلى — لو كان على الهواء، المقطع المجدول لا يقاطعه.
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
