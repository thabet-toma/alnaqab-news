<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/radio-live.php';

requireLogin();

$error   = '';
$success = '';

// رفع وحذف عناصر العرض — نماذج عادية لأن الرفع ملف. بقية الأوامر (كتم، طرد،
// روابط، عرض) عبر admin/ajax/radio-live.php لأنها تحتاج أن تكون فورية.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'visual_upload') {
        [$ok, $msg] = liveUploadVisual($_FILES['visual'] ?? [], (string) ($_POST['title'] ?? ''));
        if ($ok) { $success = $msg; } else { $error = $msg; }
    } elseif ($action === 'visual_delete') {
        if (liveDeleteVisual((int) ($_POST['id'] ?? 0))) {
            $success = 'تم حذف العنصر';
        } else {
            $error = 'العنصر غير موجود';
        }
    }
}

$visuals   = liveVisuals();
$radioUrl  = rtrim(SITE_URL, '/') . '/radio.php';
$studioUrl = rtrim(SITE_URL, '/') . '/studio.php';
$shareText = getRadioConfig()['station_name'] . ' — بث مباشر الآن';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الاستوديو المباشر - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/radio-admin.css?v=<?php echo assetVersion('/assets/css/radio-admin.css'); ?>">
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
                <a href="radio-schedule.php">جدولة البث</a>
                <a href="radio-live.php" class="active">الاستوديو المباشر</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>الاستوديو المباشر</h1>
            </header>

            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>

                <div class="alert alert-danger" id="liveNotice" role="alert" hidden></div>

                <!-- ===== من على الهواء ===== -->
                <div class="studio-slots">
                    <?php foreach ([1 => 'المذيع', 2 => 'الضيف'] as $slotNo => $slotLabel): ?>
                    <section class="content-panel studio-slot" data-slot="<?php echo $slotNo; ?>">
                        <div class="studio-slot-head">
                            <span class="status-dot auto" data-role="dot"></span>
                            <h3><?php echo e($slotLabel); ?></h3>
                            <span class="badge badge-off" data-role="badge">غير متصل</span>
                        </div>
                        <p class="studio-slot-who" data-role="who">—</p>
                        <div class="studio-slot-actions">
                            <button type="button" class="btn btn-ghost btn-sm" data-action="mute" disabled>كتم</button>
                            <button type="button" class="btn btn-ghost btn-sm" data-action="unmute" hidden>إلغاء الكتم</button>
                            <button type="button" class="btn btn-danger btn-sm" data-action="kick" disabled>طرد</button>
                        </div>
                        <p class="form-hint" data-role="butt-hint" hidden>متصل من BUTT: الطرد يقطعه لحظة ثم يعود تلقائياً — استعمل الكتم.</p>
                    </section>
                    <?php endforeach; ?>
                </div>

                <div class="form-grid">
                    <div class="form-main">
                        <!-- ===== بثّك من المتصفح ===== -->
                        <section class="content-panel">
                            <h3>بثّك من المتصفح</h3>
                            <p class="form-hint">تبثّ على مدخل المذيع. BUTT يبقى احتياطاً على نفس المدخل بكلمة سرّه.</p>

                            <div class="form-group">
                                <label for="hostDevice">جهاز الصوت</label>
                                <select id="hostDevice" class="form-control">
                                    <option value="">الافتراضي</option>
                                </select>
                                <small class="form-hint">أسماء الأجهزة تظهر بعد أول سماح بالمايك.</small>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="hostRaw">
                                    مصدر خارجي (Voicemeeter أو خلّاط) — بلا معالجة صوت من المتصفح
                                </label>
                            </div>

                            <div class="studio-meter" aria-hidden="true"><div class="studio-meter-fill" id="hostMeterFill"></div></div>
                            <p class="studio-host-status" id="hostStatus" role="status" aria-live="polite">المايك مغلق</p>

                            <div class="studio-host-actions">
                                <button type="button" class="btn btn-ghost" id="hostMic">افتح المايك</button>
                                <button type="button" class="btn btn-primary" id="hostOnAir" disabled>ادخل على الهواء</button>
                                <button type="button" class="btn btn-danger" id="hostOffAir" hidden>اخرج من الهواء</button>
                            </div>
                        </section>

                        <!-- ===== العرض المرئي ===== -->
                        <section class="content-panel">
                            <h3>العرض المرئي</h3>
                            <p class="form-hint">يظهر في صفحة الراديو وشاشة الاستوديو (فيسبوك وتيك توك). صوت الفيديو يُبثّ فقط حين لا يتكلم أحد.</p>

                            <div class="studio-now-visual" id="visualNow" hidden>
                                <span>المعروض الآن: <strong id="visualNowTitle"></strong></span>
                                <button type="button" class="btn btn-danger btn-sm" id="visualStop">أوقف العرض</button>
                            </div>

                            <?php if (empty($visuals)): ?>
                                <p class="empty-note">لا يوجد أي عنصر بعد. ارفع صورة أو فيديو من النموذج المجاور.</p>
                            <?php else: ?>
                            <div class="studio-visuals">
                                <?php foreach ($visuals as $v): ?>
                                <div class="studio-visual" data-visual-id="<?php echo (int) $v['id']; ?>">
                                    <?php if ($v['type'] === 'image'): ?>
                                        <img src="<?php echo e(liveVisualUrl($v['filename'])); ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <video src="<?php echo e(liveVisualUrl($v['filename'])); ?>#t=1" preload="metadata" muted playsinline></video>
                                        <span class="badge badge-type-playlist studio-visual-kind">فيديو<?php echo $v['duration'] ? ' · ' . e(formatDuration((int) $v['duration'])) : ''; ?></span>
                                    <?php endif; ?>
                                    <p class="studio-visual-title"><?php echo e($v['title'] !== '' ? $v['title'] : ($v['type'] === 'video' ? 'فيديو' : 'صورة')); ?></p>
                                    <div class="studio-visual-actions">
                                        <button type="button" class="btn btn-primary btn-sm" data-show="<?php echo (int) $v['id']; ?>">اعرض الآن</button>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('حذف هذا العنصر نهائياً؟');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="visual_delete">
                                            <input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <div class="form-sidebar">
                        <!-- ===== روابط الضيوف ===== -->
                        <section class="content-panel">
                            <h3>رابط ضيف جديد</h3>
                            <form id="guestForm" class="studio-guest-form">
                                <div class="form-group">
                                    <label for="guestName">اسم الضيف</label>
                                    <input type="text" id="guestName" class="form-control" maxlength="80" required>
                                </div>
                                <div class="form-group">
                                    <label for="guestHours">صلاحية الرابط</label>
                                    <select id="guestHours" class="form-control">
                                        <option value="2">ساعتان</option>
                                        <option value="24">24 ساعة</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">أنشئ الرابط</button>
                            </form>

                            <div class="studio-new-link" id="guestNewLink" hidden>
                                <label for="guestLinkField">الرابط (يظهر مرة واحدة فقط)</label>
                                <input type="text" id="guestLinkField" class="form-control" dir="ltr" readonly>
                                <div class="studio-link-actions">
                                    <button type="button" class="btn btn-ghost btn-sm" data-copy="#guestLinkField">انسخ</button>
                                    <a class="btn btn-ghost btn-sm" id="guestWhatsapp" href="#" target="_blank" rel="noopener">أرسل واتساب</a>
                                </div>
                            </div>

                            <h3 class="mt-4">الروابط الصالحة</h3>
                            <ul class="studio-guest-list" id="guestList">
                                <li class="empty-note">لا يوجد</li>
                            </ul>
                        </section>

                        <!-- ===== انشر البث ===== -->
                        <section class="content-panel">
                            <h3>انشر البث</h3>
                            <div class="form-group">
                                <label for="shareUrl">رابط صفحة الراديو</label>
                                <input type="text" id="shareUrl" class="form-control" dir="ltr" readonly value="<?php echo e($radioUrl); ?>">
                            </div>
                            <div class="studio-link-actions">
                                <button type="button" class="btn btn-ghost btn-sm" data-copy="#shareUrl">انسخ</button>
                                <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($radioUrl); ?>">فيسبوك</a>
                                <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode($shareText . "\n" . $radioUrl); ?>">واتساب</a>
                                <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode($shareText); ?>&amp;url=<?php echo rawurlencode($radioUrl); ?>">X</a>
                            </div>
                            <p class="form-hint">تيك توك لا يجعل الروابط داخل المنشورات قابلة للضغط — ضع الرابط في البايو.</p>

                            <h3 class="mt-4">شاشة الاستوديو لـ OBS</h3>
                            <div class="form-group">
                                <label for="studioH">أفقي — فيسبوك ويوتيوب (1920×1080)</label>
                                <input type="text" id="studioH" class="form-control" dir="ltr" readonly value="<?php echo e($studioUrl); ?>">
                            </div>
                            <div class="form-group">
                                <label for="studioV">عمودي — تيك توك (1080×1920)</label>
                                <input type="text" id="studioV" class="form-control" dir="ltr" readonly value="<?php echo e($studioUrl . '?layout=vertical'); ?>">
                            </div>
                            <div class="studio-link-actions">
                                <button type="button" class="btn btn-ghost btn-sm" data-copy="#studioH">انسخ الأفقي</button>
                                <button type="button" class="btn btn-ghost btn-sm" data-copy="#studioV">انسخ العمودي</button>
                            </div>

                            <div class="form-group mt-4">
                                <label for="visualDelay">تأخير العرض (ثوانٍ)</label>
                                <input type="number" id="visualDelay" class="form-control" min="0" max="30" step="1" dir="ltr">
                                <small class="form-hint">صوت البث يصل للمستمع متأخراً قليلاً؛ الصور تتأخر بنفس المقدار لتتطابق معه.</small>
                            </div>
                        </section>

                        <!-- ===== رفع للعرض ===== -->
                        <section class="content-panel">
                            <h3>ارفع صورة أو فيديو</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="visual_upload">
                                <div class="form-group">
                                    <label for="visualFile">الملف</label>
                                    <input type="file" name="visual" id="visualFile" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4" required>
                                    <small class="form-hint">صورة حتى 5 ميغابايت، أو فيديو MP4 حتى 64 ميغابايت.</small>
                                </div>
                                <div class="form-group">
                                    <label for="visualTitle">عنوان (اختياري)</label>
                                    <input type="text" name="title" id="visualTitle" class="form-control" maxlength="160">
                                </div>
                                <button type="submit" class="btn btn-primary w-100">ارفع</button>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const STUDIO_CONFIG = {
            apiUrl: <?php echo json_encode(SITE_URL . '/admin/ajax/radio-live.php'); ?>,
            csrf: <?php echo json_encode(csrfToken()); ?>,
            hostTitle: <?php echo json_encode('مباشر مع ' . ($_SESSION['admin_user'] ?? ''), JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/radio-broadcast.js?v=<?php echo assetVersion('/assets/js/radio-broadcast.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/radio-live-admin.js?v=<?php echo assetVersion('/assets/js/radio-live-admin.js'); ?>"></script>
</body>
</html>
