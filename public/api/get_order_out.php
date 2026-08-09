<?php
require_once 'auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $id = $_GET['id'] ?? null;

    if (!$id) throw new Exception('IDが指定されていません');

    // 会社名なども一緒に取得しておくと、復元が楽になります
    $sql = "SELECT 
                o.*, 
                c.company_name as company_name 
            FROM order_out o 
            LEFT JOIN crm_company c ON o.company_id = c.id 
            WHERE o.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}