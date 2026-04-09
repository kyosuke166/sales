<?php
/**
 * 案件一覧、または特定の1件を取得する
 */
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = get_db_connection();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // 基本となるSQL
    $sql = "SELECT 
                p.id, 
                p.project_name, 
                p.status, 
                p.memo, 
                p.created,
                -- 受注情報 (in)
                oi.company_id, 
                oi.contact_id as in_contact_id, 
                oi.worker_name, 
                oi.order_number, 
                oi.start_date, 
                oi.end_date, 
                oi.amount, 
                oi.time_range, 
                oi.payment_site, 
                oi.file_path,
                co.company_name,
                -- 姓と名を結合して担当者名とする (NULL対策でIFNULLを使用)
                CONCAT(IFNULL(ci.last_name,''), ' ', IFNULL(ci.first_name,'')) as in_contact_name,
                -- 発注情報 (out)
                ou.id as order_out_id,
                ou.company_id as out_company_id,
                ou.contact_id as out_contact_id,
                ou.worker_name as out_worker_name,
                ou.order_number as out_order_number,
                ou.start_date as out_start_date,
                ou.end_date as out_end_date,
                ou.amount as out_amount,
                ou.time_range as out_time_range,
                ou.payment_site as out_payment_site,
                ou.file_path as out_file_path,
                cou.company_name as out_company_name,
                CONCAT(IFNULL(cout.last_name,''), ' ', IFNULL(cout.first_name,'')) as out_contact_name
            FROM projects p
            LEFT JOIN order_in oi ON p.id = oi.project_id
            LEFT JOIN crm_company co ON oi.company_id = co.id
            LEFT JOIN crm_contact ci ON oi.contact_id = ci.id
            LEFT JOIN order_out ou ON p.id = ou.project_id
            LEFT JOIN crm_company cou ON ou.company_id = cou.id
            LEFT JOIN crm_contact cout ON ou.contact_id = cout.id";

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
        
        // フロントエンドで期待されているcountsの初期値
        $counts = [
            'send_invoice' => 0,
            'confirm_deposit' => 0,
            'receive_invoice' => 0,
            'make_payment' => 0
        ];
        
        echo json_encode([
            'status' => 'success', 
            'counts' => $counts, 
            'projects' => $projects
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    // 開発用に詳細なエラーを出す（本番はmessageのみに絞るのが一般的）
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}