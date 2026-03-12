<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

$event_id = $_GET['event_id'] ?? null;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'イベントIDが指定されていません。']);
    exit;
}

try {
    $pdo = get_db_connection();

    // 1. イベントの基本情報を取得
    $stmtEvent = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmtEvent->execute([$event_id]);
    $event = $stmtEvent->fetch();

    if (!$event) {
        throw new Exception('イベントが見つかりません。');
    }

    // 2. 参加者名簿を取得（CRMの氏名も結合）
    $sql = "
        SELECT 
            ep.*,
            CONCAT(cc.last_name, ' ', cc.first_name) AS crm_name
        FROM events_participant ep
        LEFT JOIN crm_contact cc ON ep.contact_id = cc.id
        WHERE ep.event_id = ?
        ORDER BY ep.entry_number ASC
    ";
    
    $stmtPart = $pdo->prepare($sql);
    $stmtPart->execute([$event_id]);
    $participants = $stmtPart->fetchAll();

    echo json_encode([
        'success' => true,
        'event' => $event,
        'participants' => $participants
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}