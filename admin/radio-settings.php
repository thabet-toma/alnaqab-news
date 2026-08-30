<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$db = db();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    $station_name = trim($_POST['station_name'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $stream_url = trim($_POST['stream_url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    $images = array_filter(array_map('trim', explode("\n", $_POST['images'] ?? '')));
    $ticker = array_filter(array_map('trim', explode("\n", $_POST['ticker'] ?? '')));
    
    $ads = [];
    if (!empty($_POST['ad_title'])) {
        for ($i = 0; $i < count($_POST['ad_title']); $i++) {
            if (!empty($_POST['ad_title'][$i])) {
                $ads[] = [
                    'title' => trim($_POST['ad_title'][$i]),
                    'text' => trim($_POST['ad_text'][$i]),
                    'image' => trim($_POST['ad_image'][$i]),
                    'link' => trim($_POST['ad_link'][$i]),
                    'active' => isset($_POST['ad_active'][$i]) ? true : false
                ];
            }
        }
    }
    
    $images_json = json_encode(array_values($images));
    $ticker_json = json_encode(array_values($ticker));
    $ads_json = json_encode($ads);
    
    $stmt = $db->prepare("UPDATE radio_config SET 
        station_name = ?, tagline = ?, stream_url = ?, description = ?, 
        images = ?, ticker = ?, ads = ?, updated_at = NOW() WHERE id = 1");
    
    $stmt->execute([
        $station_name, $tagline, $stream_url, $description,
        $images_json, $ticker_json, $ads_json
    ]);
    
    $success = 'تم حفظ إعدادات الراديو بنجاح';
}

$radio = getRadioConfig();
$images_list = implode("\n", radioImages($radio['images']));
$ticker_list = implode("\n", json_decode($radio['ticker'], true) ?? []);
$radio_ads = json_decode($radio['ads'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات الراديو - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
    <style>
        .radio-ad-item {
            border: 1px solid #2a3a4a;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #0d1b2a;
        }
    </style>
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
                <a href="radio-settings.php" class="active">إعدادات الراديو</a>
                <a href="radio-library.php">مكتبة الصوتيات</a>
                <a href="radio-playlists.php">البلاي ليست</a>
                <a href="radio-schedule.php">جدولة البث</a>
                <a href="settings.php">الإعدادات العامة</a>
                <a href="index.php?action=logout" class="text-danger">تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <button class="toggle-sidebar" id="toggleSidebar">☰</button>
                <h1>إعدادات الراديو</h1>
            </header>
            
            <div class="admin-content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" class="content-panel">
                    <?php echo csrfField(); ?>
                    
                    <div class="form-grid">
                        <div class="form-main">
                            <h3>المعلومات الأساسية</h3>
                            <div class="form-group">
                                <label for="station_name">اسم المحطة</label>
                                <input type="text" name="station_name" id="station_name" class="form-control" value="<?php echo e($radio['station_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="tagline">الشعار اللفظي (Tagline)</label>
                                <input type="text" name="tagline" id="tagline" class="form-control" value="<?php echo e($radio['tagline']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="stream_url">رابط البث (Stream URL)</label>
                                <input type="url" name="stream_url" id="stream_url" class="form-control" value="<?php echo e($radio['stream_url']); ?>" required dir="ltr">
                                <small class="form-hint">
                                    إذا ركّبت الراديو على سيرفرك، الرابط هو:
                                    <code dir="ltr">https://دومينك/stream</code><br>
                                    لازم يبدأ بـ <code dir="ltr">https</code> — الروابط بـ
                                    <code dir="ltr">http</code> يحجبها المتصفح ولا يخرج صوت.
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="description">الوصف</label>
                                <textarea name="description" id="description" class="form-control" rows="3"><?php echo e($radio['description']); ?></textarea>
                            </div>
                            
                            <h3 class="mt-4">الصور وشريط الأخبار</h3>
                            <div class="form-group">
                                <label for="images">صور المعرض (رابط لكل سطر)</label>
                                <textarea name="images" id="images" class="form-control" rows="5" dir="ltr"><?php echo e($images_list); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="ticker">شريط الأخبار العاجلة (خبر لكل سطر)</label>
                                <textarea name="ticker" id="ticker" class="form-control" rows="5"><?php echo e($ticker_list); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-sidebar">
                            <h3>إعلانات الراديو</h3>
                            <div id="radio-ads-container">
                                <?php 
                                $adCount = max(1, count($radio_ads));
                                for ($i = 0; $i < $adCount; $i++): 
                                    $ad = $radio_ads[$i] ?? ['title'=>'', 'text'=>'', 'image'=>'', 'link'=>'', 'active'=>true];
                                ?>
                                <div class="radio-ad-item">
                                    <div class="form-group">
                                        <label>العنوان</label>
                                        <input type="text" name="ad_title[]" class="form-control form-control-sm" value="<?php echo e($ad['title']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>النص</label>
                                        <input type="text" name="ad_text[]" class="form-control form-control-sm" value="<?php echo e($ad['text']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>رابط الصورة</label>
                                        <input type="text" name="ad_image[]" class="form-control form-control-sm" value="<?php echo e($ad['image']); ?>" dir="ltr">
                                    </div>
                                    <div class="form-group">
                                        <label>رابط الإعلان</label>
                                        <input type="text" name="ad_link[]" class="form-control form-control-sm" value="<?php echo e($ad['link']); ?>" dir="ltr">
                                    </div>
                                    <div class="form-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="ad_active[<?php echo $i; ?>]" value="1" <?php echo $ad['active'] ? 'checked' : ''; ?>>
                                            نشط
                                        </label>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <button type="button" id="add-radio-ad" class="btn btn-ghost btn-sm mb-4">إضافة إعلان آخر</button>
                            
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary w-100">حفظ التغييرات</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script>
        document.getElementById('add-radio-ad').addEventListener('click', function() {
            var container = document.getElementById('radio-ads-container');
            var items = container.querySelectorAll('.radio-ad-item');
            var index = items.length;
            
            var html = `
                <div class="radio-ad-item">
                    <div class="form-group">
                        <label>العنوان</label>
                        <input type="text" name="ad_title[]" class="form-control form-control-sm">
                    </div>
                    <div class="form-group">
                        <label>النص</label>
                        <input type="text" name="ad_text[]" class="form-control form-control-sm">
                    </div>
                    <div class="form-group">
                        <label>رابط الصورة</label>
                        <input type="text" name="ad_image[]" class="form-control form-control-sm" dir="ltr">
                    </div>
                    <div class="form-group">
                        <label>رابط الإعلان</label>
                        <input type="text" name="ad_link[]" class="form-control form-control-sm" dir="ltr">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="ad_active[${index}]" value="1" checked>
                            نشط
                        </label>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        });
    </script>
</body>
</html>
