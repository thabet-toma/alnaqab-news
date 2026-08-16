<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'غير مصرح لك بالوصول']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['csrf_token']) || !verifyCsrf($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'رمز التحقق غير صالح']);
    exit;
}

$station_name = trim($input['station_name'] ?? '');
$tagline = trim($input['tagline'] ?? '');
$stream_url = trim($input['stream_url'] ?? '');
$description = trim($input['description'] ?? '');

if (empty($station_name) || empty($stream_url)) {
    echo json_encode(['success' => false, 'error' => 'اسم المحطة ورابط البث مطلوبان']);
    exit;
}

try {
    $db = db();
    $stmt = $db->prepare("UPDATE radio_config SET station_name = ?, tagline = ?, stream_url = ?, description = ?, updated_at = NOW() WHERE id = 1");
    $stmt->execute([$station_name, $tagline, $stream_url, $description]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Radio config update failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في قاعدة البيانات']);
}
