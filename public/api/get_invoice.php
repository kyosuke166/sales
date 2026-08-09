<?php
require_once 'auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $project_id = $_GET['project_id'] ?? null;

    if (!$project_id) throw new Exception('Project ID is required');

    // 1. 上位への請求(invoice_out)を取得
    $stmt_out = $pdo->prepare("
        SELECT i.* 
        FROM invoice_out i
        JOIN order_in oi ON i.order_in_id = oi.id
        WHERE oi.project_id = :p_id
        ORDER BY i.work_month DESC
    ");
    $stmt_out->execute([':p_id' => $project_id]);
    $invoices_out = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

    // 2. 下位への支払(invoice_in)を取得
    // 案件に紐づく全ての発注(order_out)から請求データを引く
    $stmt_in = $pdo->prepare("
        SELECT i.* 
        FROM invoice_in i
        JOIN order_out oo ON i.order_out_id = oo.id
        WHERE oo.project_id = :p_id
        ORDER BY i.work_month DESC, i.worker_name ASC
    ");
    $stmt_in->execute([':p_id' => $project_id]);
    $invoices_in = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'out' => $invoices_out,
        'in' => $invoices_in
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}