<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/admin/index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (attemptLogin($username, $password)) {
        header("Location: " . SITE_URL . "/admin/index.php");
        exit;
    } else {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - لوحة التحكم | <?php echo e(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
    <style>
        body.login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #0d1b2a;
            margin: 0;
            font-family: 'Tajawal', sans-serif;
        }
        .login-card {
            background-color: #1b2838;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 400px;
            border: 1px solid #2a3a4a;
        }
        .login-card h2 {
            font-family: 'Lalezar', cursive;
            color: #d4213d;
            text-align: center;
            font-size: 2rem;
            margin-bottom: 20px;
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #e8e8e8;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            background-color: #0d1b2a;
            border: 1px solid #2a3a4a;
            color: #fff;
            border-radius: 6px;
            font-family: 'Tajawal', sans-serif;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: #d4213d;
        }
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #d4213d, #e63946);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-family: 'Tajawal', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .error-msg {
            background-color: rgba(230, 57, 70, 0.1);
            color: #e63946;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #e63946;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <h2>لوحة التحكم</h2>
        <?php if ($error): ?>
            <div class="error-msg"><?php echo e($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label for="username">اسم المستخدم</label>
                <input type="text" name="username" id="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary">تسجيل الدخول</button>
        </form>
    </div>
</body>
</html>
