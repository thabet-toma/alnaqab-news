<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة غير مسموحة']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'رمز أمان غير صالح']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    $db = db();
    
    $stmt = $db->prepare("SELECT image FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetchColumn();
    
    if ($image) {
        $imgPath = ROOT_PATH . '/uploads/' . $image;
        if (file_exists($imgPath) && is_file($imgPath)) {
            unlink($imgPath);
        }
    }
    
    $stmt = $db->prepare("DELETE FROM articles WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'تم حذف المقال بنجاح']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء الحذف']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المقال غير صالح']);
}
