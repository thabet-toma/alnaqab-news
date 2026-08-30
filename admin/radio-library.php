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

    if ($action === 'upload') {
        [$ok, $msg] = uploadTrack($_FILES['audio'] ?? [], trim($_POST['title'] ?? ''));
        if ($ok) { $success = $msg; } else { $error = $msg; }

    } elseif ($action === 'delete') {
        $id    = (int) ($_POST['id'] ?? 0);
        $track = $db->prepare('SELECT filename, source FROM radio_tracks WHERE id = ?');
        $track->execute([$id]);
        $row = $track->fetch();
        $filename = $row['filename'] ?? null;

        // ملفات الدرايف تعود بالمزامنة التالية، فحذفها من هنا وهمٌ لا فائدة منه
        if ($row && $row['source'] === 'cloud') {
            $error = 'هذا المقطع مصدره Google Drive — احذفه من مجلد Radio-Naqab بالدرايف';
            $filename = null;
        }

        if ($filename) {
            $path = rtrim(RADIO_MUSIC_DIR, '/') . '/' . $filename;
            // نتأكد أن المسار داخل مجلد الأغاني قبل الحذف
            $real = realpath($path);
            $base = realpath(RADIO_MUSIC_DIR);
            if ($real && $base && str_starts_with($real, $base . '/')) {
                @unlink($real);
            }
            $db->prepare('DELETE FROM radio_tracks WHERE id = ?')->execute([$id]);
            $success = 'تم حذف المقطع';
        } else {
            $error = 'المقطع غير موجود';
        }

    } elseif ($action === 'play') {
        $id    = (int) ($_POST['id'] ?? 0);
        $track = $db->prepare('SELECT filename, title FROM radio_tracks WHERE id = ?');
        $track->execute([$id]);
        $row = $track->fetch();

        if ($row) {
            $path = rtrim(RADIO_MUSIC_DIR, '/') . '/' . $row['filename'];
            if (radioPlayNow($path)) {
                $success = 'يشتغل الآن: ' . $row['title'];
            } else {
                $error = 'تعذّر إرسال الأمر لمحرّك الراديو — تأكد أن الخدمة تعمل';
            }
        } else {
            $error = 'المقطع غير موجود';
        }

    } elseif ($action === 'rename') {
        $id    = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['new_title'] ?? '');
        if ($title !== '') {
            $db->prepare('UPDATE radio_tracks SET title = ? WHERE id = ?')->execute([$title, $id]);
            $success = 'تم تغيير الاسم';
        }

    } elseif ($action === 'skip') {
        radioSkip();
        $success = 'تم تخطّي المقطع الحالي';
    }
}

// نلتقط أي ملف رُفع يدوياً عبر SFTP ونشطب سجلّ ما حُذف من القرص
$added = syncTracksFromDisk();
if ($added > 0 && $success === '') {
    $success = "تم العثور على {$added} مقطع مرفوع يدوياً وإضافته للمكتبة";
}

$tracks     = $db->query('SELECT * FROM radio_tracks ORDER BY uploaded_at DESC')->fetchAll();
$engineUp   = radioEngineUp();
$nowPlaying = radioCurrentTitle();
$onAir      = radioLiveOnAir();
$totalSize  = array_sum(array_column($tracks, 'filesize'));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مكتبة الصوتيات - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
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
                <a href="radio-library.php" class="active">مكتبة الصوتيات</a>
                <a href="radio-schedule.php">جدولة البث</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>مكتبة الصوتيات</h1>
            </header>

            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>

                <!-- ===== حالة البث الآن ===== -->
                <div class="radio-status-bar <?php echo $engineUp ? '' : 'is-down'; ?>">
                    <?php if (!$engineUp): ?>
                        <div class="status-main">
                            <span class="status-dot down"></span>
                            <strong>محرّك الراديو متوقف</strong>
                            <span class="status-hint">الأوامر لن تعمل — راجع خدمة liquidsoap على السيرفر</span>
                        </div>
                    <?php else: ?>
                        <div class="status-main">
                            <span class="status-dot <?php echo $onAir ? 'live' : 'auto'; ?>"></span>
                            <?php if ($onAir): ?>
                                <strong>مذيع على الهواء مباشرة</strong>
                                <span class="status-hint">المايك يعلو على كل المقاطع — أوقف البث من BUTT ليرجع التشغيل التلقائي</span>
                            <?php else: ?>
                                <strong>التشغيل التلقائي</strong>
                                <span class="status-hint"><?php echo $nowPlaying !== '' ? e($nowPlaying) : 'لا يوجد مقطع'; ?></span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" class="inline-form">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="skip">
                            <button type="submit" class="btn btn-ghost btn-sm">تخطّي الحالي</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="form-grid">
                    <div class="form-main">
                        <div class="content-panel">
                            <h3>المقاطع (<?php echo count($tracks); ?>) — <?php echo formatSize((int) $totalSize); ?></h3>

                            <?php if (empty($tracks)): ?>
                                <p class="empty-note">لا يوجد أي مقطع بعد. ارفع أول مقطع من النموذج المجاور.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                <table class="tracks-table">
                                    <thead>
                                        <tr>
                                            <th>الاسم</th>
                                            <th>المصدر</th>
                                            <th>المدة</th>
                                            <th>الحجم</th>
                                            <th>إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($tracks as $t): ?>
                                        <?php $isCloud = ($t['source'] ?? 'local') === 'cloud'; ?>
                                        <tr>
                                            <td>
                                                <form method="POST" class="rename-form">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="rename">
                                                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                                                    <input type="text" name="new_title"
                                                           value="<?php echo e($t['title']); ?>"
                                                           class="form-control form-control-sm title-input"
                                                           title="عدّل الاسم ثم اضغط Enter">
                                                </form>
                                            </td>
                                            <td>
                                                <?php if ($isCloud): ?>
                                                    <span class="badge badge-cloud" title="منسوخ من مجلد Radio-Naqab بالدرايف">درايف</span>
                                                <?php else: ?>
                                                    <span class="badge badge-off">السيرفر</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num"><?php echo formatDuration($t['duration'] !== null ? (int) $t['duration'] : null); ?></td>
                                            <td class="num"><?php echo formatSize((int) $t['filesize']); ?></td>
                                            <td class="actions">
                                                <form method="POST" class="inline-form">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="play">
                                                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm"
                                                            <?php echo $engineUp ? '' : 'disabled'; ?>>شغّل الآن</button>
                                                </form>
                                                <?php if (!$isCloud): ?>
                                                <form method="POST" class="inline-form"
                                                      onsubmit="return confirm('حذف «<?php echo e($t['title']); ?>» نهائياً من السيرفر؟');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                                                </form>
                                                <?php endif; ?>
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
                            <h3>رفع مقطع جديد</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="upload">

                                <div class="form-group">
                                    <label for="audio">الملف الصوتي</label>
                                    <input type="file" name="audio" id="audio" class="form-control"
                                           accept=".mp3,.m4a,.ogg,.wav,audio/*" required>
                                    <small class="form-hint">
                                        MP3 أو M4A أو OGG أو WAV — حتى 64 ميغابايت
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="title">الاسم المعروض (اختياري)</label>
                                    <input type="text" name="title" id="title" class="form-control"
                                           placeholder="يُشتق من اسم الملف إذا تركته فارغاً">
                                </div>

                                <button type="submit" class="btn btn-primary w-100">رفع</button>
                            </form>

                            <hr class="panel-sep">
                            <h3>الرفع من Google Drive</h3>
                            <p class="form-hint">
                                بدل الرفع من هنا، تقدر تحطّ ملفاتك بمجلد
                                <strong>Radio-Naqab</strong> على درايفك من الجوال أو
                                الكمبيوتر — والسيرفر بيسحبها لحاله كل 5 دقائق وتدخل
                                الدورة. الملفات المعلّمة «درايف» مصدرها هناك، وحذفها
                                يكون من الدرايف لا من هنا.
                            </p>

                            <hr class="panel-sep">
                            <p class="form-hint">
                                المقاطع تشتغل عشوائياً على مدار الساعة. «شغّل الآن» يقطع
                                المقطع الحالي فوراً — إلا إذا كان في مذيع على الهواء،
                                فالمايك أولويته أعلى.
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
