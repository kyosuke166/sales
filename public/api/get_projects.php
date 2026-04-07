<?php
/**
 * 案件一覧、または特定の1件を取得する
 */
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = get_db_connection();
    
    // IDが指定されているか確認
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $counts = [
        'send_invoice' => 0,
        'confirm_deposit' => 0,
        'receive_invoice' => 0,
        'make_payment' => 0
    ];

    // 基本となるSQL（SELECT項目を共通化）
    $sql = "SELECT 
                p.id, p.project_name, p.status, p.memo, 
                oi.company_id, oi.contact_id, oi.worker_name, 
                oi.order_number, oi.start_date, oi.end_date, 
                oi.amount, oi.time_range, oi.payment_site, oi.file_path, 
                co.company_name
            FROM projects p
            LEFT JOIN order_in oi ON p.id = oi.project_id
            LEFT JOIN crm_company co ON oi.company_id = co.id";

    if ($id > 0) {
        // 1件取得モード
        $sql .= " WHERE p.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) throw new Exception('案件が見つかりません');
        
        echo json_encode(['status' => 'success', 'data' => $result], JSON_UNESCAPED_UNICODE);
    } else {
        // 全件取得モード
        $sql .= " ORDER BY p.created ASC";
        $stmt = $pdo->query($sql);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success', 
            'counts' => $counts, 
            'projects' => $projects
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}