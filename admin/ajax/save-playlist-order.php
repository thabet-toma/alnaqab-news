<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/radio-control.php';

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

$playlistId = (int) ($input['playlist_id'] ?? 0);
$itemIds    = $input['item_ids'] ?? null;

if ($playlistId <= 0 || !is_array($itemIds) || empty($itemIds)) {
    echo json_encode(['success' => false, 'error' => 'بيانات الترتيب غير صالحة']);
    exit;
}

$itemIds = array_map('intval', $itemIds);

try {
    $db = db();
    $db->beginTransaction();

    // قفل صفّ البلاي ليست نفسه لا صفوف العناصر، فيسلسل محرّرين متزامنين
    // حتى لو تغيّرت مجموعة العناصر بين قراءتهما
    $lock = $db->prepare('SELECT is_managed FROM radio_playlists WHERE id = ? FOR UPDATE');
    $lock->execute([$playlistId]);
    $playlist = $lock->fetch();

    if (!$playlist) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'البلاي ليست غير موجودة']);
        exit;
    }

    if ((int) $playlist['is_managed'] === 1) {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'هذه البلاي ليست مُدارة آلياً ولا يمكن تعديل ترتيبها من هنا']);
        exit;
    }

    // المعرّفات القادمة يجب أن تطابق تماماً مجموعة عناصر البلاي ليست في
    // القاعدة — لا زيادة ولا نقصان، وإلا نرفض الطلب كاملاً بلا كتابة جزئية
    $existing = $db->prepare('SELECT id FROM radio_playlist_items WHERE playlist_id = ?');
    $existing->execute([$playlistId]);
    $existingIds = array_map('intval', $existing->fetchAll(PDO::FETCH_COLUMN));

    $sortedExisting = $existingIds;
    $sortedSent      = $itemIds;
    sort($sortedExisting);
    sort($sortedSent);

    if ($sortedExisting !== $sortedSent) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'قائمة المقاطع تغيّرت منذ فتح الصفحة — أعد تحميلها وحاول مجدداً']);
        exit;
    }

    $update = $db->prepare('UPDATE radio_playlist_items SET sort_order = ? WHERE id = ? AND playlist_id = ?');
    foreach ($itemIds as $order => $itemId) {
        $update->execute([$order, $itemId, $playlistId]);
    }

    $db->commit();
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Playlist order save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في قاعدة البيانات']);
    exit;
}

writePlaylistM3u($playlistId);
echo json_encode(['success' => true]);
