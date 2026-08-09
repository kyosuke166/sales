<?php
require_once 'auth_check.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id    = $data['id'] ?? null;
$type  = $data['type'] ?? '';
$field = $data['field'] ?? ''; // 'deposit_date' か 'paid_date'
$value = $data['value'] ?: null;

try {
    if (!$id || !in_array($type, ['in', 'out'])) throw new Exception('Invalid params');
    
    $table = ($type === 'out') ? 'invoice_out' : 'invoice_in';
    $pdo = get_db_connection();
    
    $stmt = $pdo->prepare("UPDATE {$table} SET {$field} = :val WHERE id = :id");
    $stmt->execute([':val' => $value, ':id' => $id]);
    
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}