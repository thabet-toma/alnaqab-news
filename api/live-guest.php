<?php
/**
 * حالة الضيف كما يراها هو من صفحة guest.php: هل رابطه ما زال صالحاً، وهل
 * كتمه المذيع. تسأل كل بضع ثوانٍ أثناء وجوده على الهواء.
 *
 * الرمز يصل في جسم POST لا في الرابط، حتى لا يتكرّر في سجلّات الوصول مع كل
 * استطلاع. يرجّع دائماً 200 وJSON كامل المفاتيح؛ عند أي عطل valid=true
 * و muted=false — لا نطرد ضيفاً من صفحته لأن قاعدة البيانات تلعثمت لحظة، فالمحرّك
 * نفسه هو الحَكَم الحقيقي على اتصاله.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/radio-live.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$out = ['valid' => true, 'muted' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($out);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = is_array($input) ? (string) ($input['token'] ?? '') : '';

try {
    $row = liveFindToken($token);
    if ($row === null || $row['role'] !== 'guest') {
        $out['valid'] = false;
    } else {
        $out['muted'] = (bool) liveSlotMuted((int) $row['slot']);
    }
} catch (Throwable $e) {
    error_log('[radio-live] guest state failed: ' . $e->getMessage());
}

echo json_encode($out);
