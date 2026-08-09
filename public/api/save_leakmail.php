<?php
// api/save_leakmail.php
require_once 'auth_check.php';
header('Content-Type: application/json');

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID不足']);
    exit;
}

// 更新可能なカラムを定義
$updatable = ['send_name', 'memo', 'company_id', 'contact_id'];
$updateParts = [];
$params = [];

foreach ($updatable as $col) {
    if (isset($_POST[$col])) {
        $val = trim($_POST[$col]);
        $updateParts[] = "{$col} = ?";
        $params[] = ($val === '') ? null : $val;
    }
}

if (empty($updateParts)) {
    echo json_encode(['success' => false, 'message' => '更新データがありません']);
    exit;
}

try {
    $pdo = get_db_connection();
    $sql = "UPDATE leaked_contacts SET " . implode(', ', $updateParts) . " WHERE id = ?";
    $params[] = $id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}