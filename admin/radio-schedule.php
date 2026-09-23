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

/** تقاطع قائمتي أيام مخزّنتين كأرقام — قائمة فارغة تعني كل يوم فتتقاطع مع أي شيء */
$daysOverlap = fn(array $a, array $b): bool =>
    empty($a) || empty($b) || count(array_intersect($a, $b)) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $targetType = $_POST['target_type'] ?? '';
        $playAt     = trim($_POST['play_at'] ?? '');
        $trackId    = null;
        $playlistId = null;

        // نقبل أرقام الأيام الصحيحة فقط، ونرتّبها لتخزين موحّد
        $days = array_values(array_intersect(
            array_map('intval', (array) ($_POST['days'] ?? [])),
            array_keys(WEEKDAYS)
        ));
        sort($days);

        if ($targetType !== 'playlist' && $targetType !== 'track') {
            $error = 'اختر نوع الهدف';
        } else {
            if ($targetType === 'playlist') {
                $playlistId = (int) ($_POST['playlist_id'] ?? 0);
                $exists = $db->prepare('SELECT COUNT(*) FROM radio_playlists WHERE id = ? AND active = 1');
                $exists->execute([$playlistId]);
            } else {
                $trackId = (int) ($_POST['track_id'] ?? 0);
                $exists = $db->prepare("SELECT COUNT(*) FROM radio_tracks WHERE id = ? AND status = 'ok'");
                $exists->execute([$trackId]);
            }
            $targetOk = (bool) $exists->fetchColumn();

            if (!$targetOk) {
                $error = $targetType === 'playlist' ? 'اختر بلاي ليست مفعّلة' : 'اختر مقطعاً من المكتبة';
            } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $playAt)) {
                $error = 'الوقت غير صالح';
            } else {
                $playAtFull = $playAt . ':00';

                // فحص التعارض: بلاي ليست مع بلاي ليست بنفس الوقت وتداخل الأيام
                // (الفعّالة واحدة فقط)، أو نفس المقطع مرتين بنفس الوقت وتداخل
                // الأيام (تكرار محض). مقطع مع بلاي ليست لا يتعارض أبداً.
                if ($targetType === 'playlist') {
                    $conflictStmt = $db->prepare(
                        'SELECT s.play_at, s.days, p.name
                           FROM radio_schedule s
                           JOIN radio_playlists p ON p.id = s.playlist_id
                          WHERE s.playlist_id IS NOT NULL AND s.active = 1 AND s.play_at = ?'
                    );
                    $conflictStmt->execute([$playAtFull]);
                } else {
                    $conflictStmt = $db->prepare(
                        'SELECT s.play_at, s.days, t.title AS name
                           FROM radio_schedule s
                           JOIN radio_tracks t ON t.id = s.track_id
                          WHERE s.track_id = ? AND s.active = 1 AND s.play_at = ?'
                    );
                    $conflictStmt->execute([$trackId, $playAtFull]);
                }

                $conflict = null;
                foreach ($conflictStmt->fetchAll() as $row) {
                    $rowDays = array_map('intval', array_filter(explode(',', (string) $row['days']), 'strlen'));
                    if ($daysOverlap($days, $rowDays)) {
                        $conflict = $row;
                        break;
                    }
                }

                if ($conflict) {
                    $error = 'يتعارض مع موعد «' . $conflict['name'] . '» الساعة '
                            . substr((string) $conflict['play_at'], 0, 5) . ' في الأيام نفسها';
                } else {
                    $stmt = $db->prepare(
                        'INSERT INTO radio_schedule (track_id, playlist_id, play_at, days) VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([$trackId, $playlistId, $playAtFull, implode(',', $days)]);
                    $success = 'تمت إضافة الموعد';
                }
            }
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

// البلاي ليست ثم المقاطع، وداخل كل مجموعة حسب وقت التشغيل
$schedule = $db->query(
    'SELECT s.*, t.title AS track_title, p.name AS playlist_name
       FROM radio_schedule s
       LEFT JOIN radio_tracks t ON t.id = s.track_id
       LEFT JOIN radio_playlists p ON p.id = s.playlist_id
      ORDER BY (s.track_id IS NOT NULL), s.play_at'
)->fetchAll();

$playlists = $db->query('SELECT id, name FROM radio_playlists WHERE active = 1 ORDER BY name')->fetchAll();
$tracks    = $db->query("SELECT id, title FROM radio_tracks WHERE status = 'ok' ORDER BY title")->fetchAll();

// مجموع مدة كل بلاي ليست من مقاطعها السليمة — مقطع بمدة مجهولة يُسقط الثقة
// بالمجموع كاملاً فلا نعرض تحذيراً برقم كاذب
$playlistDurations = [];
$durRows = $db->query(
    "SELECT i.playlist_id,
            SUM(CASE WHEN t.status = 'ok' THEN t.duration END) AS total_duration,
            SUM(CASE WHEN t.status = 'ok' AND t.duration IS NULL THEN 1 ELSE 0 END) AS null_count
       FROM radio_playlist_items i
       JOIN radio_tracks t ON t.id = i.track_id
      GROUP BY i.playlist_id"
)->fetchAll();
foreach ($durRows as $row) {
    $playlistDurations[(int) $row['playlist_id']] = [
        'total'   => $row['total_duration'] !== null ? (int) $row['total_duration'] : null,
        'hasNull' => (int) $row['null_count'] > 0,
    ];
}

// مرشّحو الفجوة: مواعيد بلاي ليست مفعّلة فقط، للبحث عن أقرب موعد تالٍ لكل صفّ
$playlistCandidates = array_values(array_filter(
    $schedule,
    fn($s) => $s['playlist_id'] !== null && (int) $s['active'] === 1
));

$timeToSeconds = function (string $t): int {
    [$h, $m, $s] = array_map('intval', explode(':', $t));
    return $h * 3600 + $m * 60 + $s;
};

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
                <a href="radio-playlists.php">البلاي ليست</a>
                <a href="radio-schedule.php" class="active">جدولة البث</a>
                <a href="radio-live.php">الاستوديو المباشر</a>
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
                                            <th>الهدف</th>
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

                                        $isPlaylist = $s['playlist_id'] !== null;
                                        $targetName = $isPlaylist ? $s['playlist_name'] : $s['track_title'];

                                        // ملاحظة التكرار — لصفوف البلاي ليست فقط، ولا تُعرض إن كان
                                        // مجموع المدة غير موثوق (مقطع بمدة مجهولة داخلها)
                                        $repeatNote = null;
                                        if ($isPlaylist) {
                                            $dur = $playlistDurations[(int) $s['playlist_id']] ?? null;
                                            if ($dur && !$dur['hasNull'] && $dur['total'] !== null) {
                                                $curSec  = $timeToSeconds((string) $s['play_at']);
                                                $curDays = array_map('intval', array_filter(explode(',', (string) $s['days']), 'strlen'));
                                                // الفجوة تلتفّ حول منتصف الليل: موعد 22:00 يليه
                                                // موعد 07:00 فجوته تسع ساعات لا أربع وعشرون. الباقي
                                                // القسمي يعطي هذا مباشرةً، والصفر (الصفّ نفسه) يصير
                                                // يوماً كاملاً وهو الصحيح لموعد يتكرّر يومياً وحده.
                                                $gapSec = 86400;
                                                foreach ($playlistCandidates as $cand) {
                                                    $candDays = array_map('intval', array_filter(explode(',', (string) $cand['days']), 'strlen'));
                                                    if (!$daysOverlap($curDays, $candDays)) continue;
                                                    $delta = ($timeToSeconds((string) $cand['play_at']) - $curSec + 86400) % 86400;
                                                    if ($delta === 0) continue;
                                                    $gapSec = min($gapSec, $delta);
                                                }
                                                if ($dur['total'] < $gapSec) {
                                                    $repeatNote = sprintf(
                                                        'ستتكرّر — طولها %s والفجوة %s',
                                                        formatDuration($dur['total']),
                                                        formatDuration($gapSec)
                                                    );
                                                }
                                            }
                                        }
                                        ?>
                                        <tr class="<?php echo $s['active'] ? '' : 'row-off'; ?>">
                                            <td class="num time-cell"><?php echo e(substr((string) $s['play_at'], 0, 5)); ?></td>
                                            <td>
                                                <span class="badge <?php echo $isPlaylist ? 'badge-type-playlist' : 'badge-type-track'; ?>">
                                                    <?php echo $isPlaylist ? 'بلاي ليست' : 'مقطع'; ?>
                                                </span>
                                                <?php echo e($targetName); ?>
                                                <?php if ($repeatNote): ?>
                                                    <div class="repeat-warning"><?php echo e($repeatNote); ?></div>
                                                <?php endif; ?>
                                            </td>
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

                            <?php if (empty($playlists) && empty($tracks)): ?>
                                <p class="empty-note">
                                    لازم تنشئ بلاي ليست من
                                    <a href="radio-playlists.php">البلاي ليست</a> أو ترفع مقاطع من
                                    <a href="radio-library.php">مكتبة الصوتيات</a> أولاً.
                                </p>
                            <?php else: ?>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="add">

                                <div class="form-group">
                                    <label>نوع الهدف</label>
                                    <div class="target-type-choice">
                                        <label class="day-check">
                                            <input type="radio" name="target_type" value="playlist" checked>
                                            <span>بلاي ليست</span>
                                        </label>
                                        <label class="day-check">
                                            <input type="radio" name="target_type" value="track">
                                            <span>مقطع مفرد</span>
                                        </label>
                                    </div>
                                    <small class="form-hint">اختر النوع، ثم حدّد الهدف من القائمة الموافقة له أدناه</small>
                                </div>

                                <div class="form-group">
                                    <label for="playlist_id">البلاي ليست</label>
                                    <?php if (empty($playlists)): ?>
                                        <select name="playlist_id" id="playlist_id" class="form-control" disabled>
                                            <option value="">لا يوجد بلاي ليست مفعّلة</option>
                                        </select>
                                    <?php else: ?>
                                        <select name="playlist_id" id="playlist_id" class="form-control">
                                            <?php foreach ($playlists as $p): ?>
                                                <option value="<?php echo (int) $p['id']; ?>"><?php echo e($p['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label for="track_id">المقطع</label>
                                    <?php if (empty($tracks)): ?>
                                        <select name="track_id" id="track_id" class="form-control" disabled>
                                            <option value="">لا يوجد مقاطع سليمة في المكتبة</option>
                                        </select>
                                    <?php else: ?>
                                        <select name="track_id" id="track_id" class="form-control">
                                            <?php foreach ($tracks as $t): ?>
                                                <option value="<?php echo (int) $t['id']; ?>"><?php echo e($t['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
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
                                عند موعد بلاي ليست تصبح هي الفعّالة على المحرّك وتبقى كذلك حتى
                                الموعد التالي — لا وقت نهاية لها. أما موعد المقطع المفرد فيُدفع
                                فوق ما يشتغل حالياً كمقاطعة، وبعد انتهائه تكمل البلاي ليست الفعّالة
                                تلقائياً. المقطع المفرد الذي يتأخّر تطبيقه أكثر من دقيقتين عن موعده
                                يُتخطّى، بينما موعد البلاي ليست يُطبَّق مهما تأخّر تشغيل المهمة على
                                السيرفر. المذيع المباشر يبقى أعلى أولوية دائماً — لا يقاطعه أي موعد.
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
