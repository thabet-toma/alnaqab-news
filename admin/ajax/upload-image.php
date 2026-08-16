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

if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $path = uploadImage($_FILES['file'], 'editor');
    if ($path) {
        echo json_encode([
            'success' => true,
            'url' => UPLOADS_URL . '/' . $path,
            'path' => $path
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فشل رفع الصورة']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'لم يتم إرسال ملف أو حدث خطأ']);
}
