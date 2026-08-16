<?php
/**
 * موقع النقب الإخباري — سكريبت التثبيت
 * يُنشئ قاعدة البيانات والجداول وأول حساب أدمن
 * ⚠️ احذف هذا الملف بعد التثبيت لأسباب أمنية
 */

// منع التشغيل إذا كان الموقع مثبتًا
$configFile = __DIR__ . '/includes/config.php';
if (!file_exists($configFile)) {
    die('ملف config.php غير موجود. أنشئه أولاً.');
}

require_once $configFile;

$error = '';
$success = false;
$step = 'db'; // db → admin → done

// فحص هل قاعدة البيانات متصلة ومُعدّة
try {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    // إنشاء قاعدة البيانات إذا لم تكن موجودة
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    // فحص هل الجداول موجودة
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('admins', $tables)) {
        $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        if ($adminCount > 0) {
            $step = 'done';
            $success = true;
        } else {
            $step = 'admin';
        }
    }
} catch (PDOException $e) {
    $error = 'خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage();
    $step = 'error';
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step !== 'error') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_tables') {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) UNIQUE NOT NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS articles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                excerpt TEXT,
                body LONGTEXT NOT NULL,
                image VARCHAR(500),
                category_id INT NOT NULL,
                type ENUM('news','article') DEFAULT 'news',
                is_featured TINYINT(1) DEFAULT 0,
                is_breaking TINYINT(1) DEFAULT 0,
                views INT DEFAULT 0,
                author_name VARCHAR(100),
                status ENUM('draft','published') DEFAULT 'published',
                published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                image VARCHAR(500),
                link VARCHAR(500),
                position ENUM('header','sidebar','between','footer','popup') DEFAULT 'sidebar',
                is_active TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0,
                start_date DATE DEFAULT NULL,
                end_date DATE DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS radio_config (
                id INT PRIMARY KEY DEFAULT 1,
                station_name VARCHAR(100) DEFAULT 'راديو النقب',
                tagline VARCHAR(200) DEFAULT 'صوت الصحراء ونبض المجتمع',
                stream_url VARCHAR(500) DEFAULT 'https://ice1.somafm.com/groovesalad-128-mp3',
                description TEXT,
                images JSON,
                ticker JSON,
                ads JSON,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";

            // تنفيذ كل جملة منفصلة
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (!empty($stmt)) $pdo->exec($stmt);
            }

            // إدخال تصنيفات افتراضية
            $defaultCats = [
                ['أخبار محلية', 'اخبار-محلية', 1],
                ['أخبار عاجلة', 'اخبار-عاجلة', 2],
                ['رياضة', 'رياضة', 3],
                ['اقتصاد', 'اقتصاد', 4],
                ['منوعات', 'منوعات', 5],
                ['مقالات وآراء', 'مقالات-وآراء', 6],
                ['تكنولوجيا', 'تكنولوجيا', 7],
                ['صحة', 'صحة', 8],
            ];

            $catStmt = $pdo->prepare('INSERT IGNORE INTO categories (name, slug, sort_order) VALUES (?, ?, ?)');
            foreach ($defaultCats as $cat) {
                $catStmt->execute($cat);
            }

            // إدخال إعدادات افتراضية
            $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
                ('site_name', 'موقع النقب الإخباري'),
                ('site_description', 'أخبار، مقالات، وراديو مباشر من قلب النقب'),
                ('facebook_url', ''),
                ('twitter_url', ''),
                ('instagram_url', ''),
                ('whatsapp_number', ''),
                ('contact_email', '')
            ");

            // إدخال إعدادات الراديو الافتراضية
            $pdo->exec("INSERT IGNORE INTO radio_config (id, station_name, tagline, stream_url, description, images, ticker, ads)
                VALUES (1, 'راديو النقب', 'صوت الصحراء ونبض المجتمع', 'https://ice1.somafm.com/groovesalad-128-mp3',
                'راديو النقب — إذاعة عربية تبث على مدار الساعة من قلب الصحراء.',
                '[{\"src\":\"https://picsum.photos/seed/negev-radio-1/900/560\"},{\"src\":\"https://picsum.photos/seed/negev-radio-2/900/560\"}]',
                '[\"أهلاً بكم في البث المباشر لراديو النقب\",\"شاركونا عبر صفحاتنا\"]',
                '[{\"title\":\"إعلان تجريبي\",\"text\":\"هذا إعلان تجريبي للراديو\",\"image\":\"\",\"link\":\"\",\"active\":true}]'
            )");

            $step = 'admin';
        } catch (PDOException $e) {
            $error = 'خطأ في إنشاء الجداول: ' . $e->getMessage();
        }
    }

    if ($action === 'create_admin') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (mb_strlen($username) < 3) {
            $error = 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل';
        } elseif (strlen($password) < 6) {
            $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
        } elseif ($password !== $password2) {
            $error = 'كلمتا المرور غير متطابقتين';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$username, $hash]);
                $step = 'done';
                $success = true;
            } catch (PDOException $e) {
                $error = 'خطأ: ' . ($e->getCode() == 23000 ? 'اسم المستخدم موجود مسبقاً' : $e->getMessage());
            }
        }
    }
}

// إنشاء مجلد uploads
$uploadDirs = ['uploads', 'uploads/articles', 'uploads/ads'];
foreach ($uploadDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) mkdir($path, 0755, true);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تثبيت موقع النقب الإخباري</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Tajawal',sans-serif;background:#0d1b2a;color:#e0e0e0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#1b2838;border:1px solid #2a3a4a;border-radius:16px;padding:40px;max-width:500px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4)}
h1{font-size:28px;font-weight:800;color:#d4213d;margin-bottom:6px;text-align:center}
.sub{text-align:center;color:#8899aa;font-size:14px;margin-bottom:30px}
.step{display:flex;gap:8px;margin-bottom:24px;justify-content:center}
.step span{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:800;border:2px solid #2a3a4a;color:#556677}
.step span.active{background:#d4213d;border-color:#d4213d;color:#fff}
.step span.done{background:#2a8a4a;border-color:#2a8a4a;color:#fff}
.field{margin-bottom:18px}
.field label{display:block;font-size:13px;font-weight:700;color:#aabbcc;margin-bottom:6px}
.field input{width:100%;padding:12px 14px;border:1px solid #2a3a4a;border-radius:10px;background:#0d1b2a;color:#e0e0e0;font-size:14px;font-family:inherit;outline:none;direction:ltr;text-align:right}
.field input:focus{border-color:#d4213d;box-shadow:0 0 0 3px rgba(212,33,61,.15)}
.btn{width:100%;padding:14px;border:none;border-radius:10px;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;transition:.2s}
.btn-primary{background:linear-gradient(135deg,#d4213d,#e8912d);color:#fff}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(212,33,61,.35)}
.error{background:#3d1212;border:1px solid #6b2020;color:#ffb4b4;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;text-align:center}
.success{background:#0d3320;border:1px solid #1a6640;color:#80e0a0;padding:20px;border-radius:10px;text-align:center;line-height:1.8}
.success h2{font-size:22px;margin-bottom:8px;color:#4ade80}
.success a{color:#e8912d;font-weight:700;text-decoration:none}
.success a:hover{text-decoration:underline}
.warn{background:#3d2d12;border:1px solid #6b5020;color:#ffe0a0;padding:12px;border-radius:10px;font-size:12px;margin-top:16px;text-align:center;line-height:1.7}
</style>
</head>
<body>
<div class="card">
    <h1>🏗️ تثبيت موقع النقب</h1>
    <p class="sub">إعداد قاعدة البيانات وحساب المدير</p>

    <div class="step">
        <span class="<?= $step==='done' ? 'done' : ($step==='db' ? 'active' : 'done') ?>">1</span>
        <span class="<?= $step==='done' ? 'done' : ($step==='admin' ? 'active' : '') ?>">2</span>
        <span class="<?= $step==='done' ? 'done' : '' ?>">3</span>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'error'): ?>
        <div class="error">
            تعذر الاتصال بقاعدة البيانات.<br>
            تأكد من إعدادات <code>includes/config.php</code>
        </div>

    <?php elseif ($step === 'db'): ?>
        <p style="text-align:center;color:#8899aa;margin-bottom:20px;font-size:14px">
            سيتم إنشاء الجداول والتصنيفات الافتراضية في قاعدة البيانات <b><?= DB_NAME ?></b>
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="create_tables">
            <button type="submit" class="btn btn-primary">إنشاء الجداول ▸</button>
        </form>

    <?php elseif ($step === 'admin'): ?>
        <p style="text-align:center;color:#8899aa;margin-bottom:20px;font-size:14px">
            ✅ تم إنشاء الجداول. الآن أنشئ حساب المدير الأول.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="create_admin">
            <div class="field">
                <label>اسم المستخدم</label>
                <input type="text" name="username" required minlength="3" placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="field">
                <label>كلمة المرور</label>
                <input type="password" name="password" required minlength="6" placeholder="••••••••">
            </div>
            <div class="field">
                <label>تأكيد كلمة المرور</label>
                <input type="password" name="password2" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">إنشاء الحساب ▸</button>
        </form>

    <?php elseif ($step === 'done'): ?>
        <div class="success">
            <h2>✅ تم التثبيت بنجاح!</h2>
            <p>الموقع جاهز للاستخدام</p>
            <br>
            <a href="admin/index.php">→ دخول لوحة التحكم</a><br>
            <a href="index.php">→ زيارة الموقع</a>
        </div>
        <div class="warn">⚠️ <b>مهم:</b> احذف ملف <code>install.php</code> فورًا لأسباب أمنية!</div>
    <?php endif; ?>
</div>
</body>
</html>
