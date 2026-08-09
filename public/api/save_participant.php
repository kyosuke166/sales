<?php
// api/save_participant.php
require_once 'auth_check.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? null;
$company_id = $_POST['company_id'] ?? null;
$send_flg = $_POST['send_flg'] ?? null;
$memo = $_POST['memo'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    $pdo = get_db_connection();
    $updates = [];
    $params = [];

    // 会社IDの更新がある場合
    if ($company_id !== null) {
        $updates[] = "company_id = ?";
        $params[] = $company_id;
    }

    // 配信フラグの更新がある場合
    if ($send_flg !== null) {
        $updates[] = "send_flg = ?";
        $params[] = $send_flg;
    }

    // メモの更新がある場合
    if ($memo !== null) {
        $updates[] = "memo = ?";
        $params[] = $memo;
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => '更新データがありません']);
        exit;
    }

    $params[] = $id;
    $sql = "UPDATE events_participant SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);

    echo json_encode(['success' => $result]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}