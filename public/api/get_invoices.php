<?php
require_once 'auth_check.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_db_connection();

    // 1. orders テーブルから案件（anken_id）単位でユニークなリストを取得
    $query_projects = "
        SELECT 
            anken_id,
            anken_name AS project,
            MAX(end_date) AS raw_end_date
        FROM orders
        WHERE anken_id IS NOT NULL AND anken_id != ''
        GROUP BY anken_id, anken_name
        ORDER BY MAX(id) DESC
    ";
    
    $stmt_proj = $pdo->query($query_projects);
    $invoices_data = [];

    while ($proj = $stmt_proj->fetch(PDO::FETCH_ASSOC)) {
        $anken_id = $proj['anken_id'];

        // 2. 該当案件の「上位（in）」の注文をすべて取得し、インボイス（請求）情報をLEFT JOIN
        $query_in = "
            SELECT 
                o_in.id AS order_id,
                c_upper.company_name AS upper_company,
                o_in.order_no AS upper_order_no,
                o_in.worker_name AS upper_worker,
                CONCAT(
                    COALESCE(DATE_FORMAT(o_in.start_date, '%Y/%m/%d'), '—'), 
                    ' ～ ', 
                    COALESCE(DATE_FORMAT(o_in.end_date, '%Y/%m/%d'), '—')
                ) AS upper_period,
                i.id AS invoice_id,
                i.invoice_no,
                i.file_path,
                i.target_month,
                i.total_amount,
                i.due_date,
                i.completed_date,
                i.yayoi_kakekin,
                i.yayoi_furikae
            FROM orders o_in
            LEFT JOIN crm_company c_upper ON o_in.company_id = c_upper.id
            LEFT JOIN invoices i ON o_in.id = i.order_id
            WHERE o_in.anken_id = ? AND o_in.order_direction = 'in'
        ";
        
        $stmt_in = $pdo->prepare($query_in);
        $stmt_in->execute([$anken_id]);
        $upper_rows = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

        $formatted_upper = [];
        foreach ($upper_rows as $u) {
            $target_month_formatted = '—';
            if (!empty($u['target_month'])) {
                if (preg_match('/^(\d{4})-(\d{2})$/', $u['target_month'], $m)) {
                    $target_month_formatted = $m[1] . '年' . $m[2] . '月';
                } else {
                    $target_month_formatted = $u['target_month'];
                }
            }

            $amount_formatted = isset($u['total_amount']) ? '\\' . number_format((int)$u['total_amount']) : '—';
            $due_date_formatted = $u['due_date'] ? str_replace('-', '/', $u['due_date']) : '—';

            $formatted_upper[] = [
                'company' => $u['upper_company'] ?: 'N/A',
                'orderId' => $u['order_id'],
                'invoiceNo' => $u['invoice_no'] ?: '—',
                'filePath' => $u['file_path'] ?: '',
                'hasInvoice' => !empty($u['invoice_id']),
                'targetMonth' => $target_month_formatted,
                'totalAmount' => $amount_formatted,
                'dueDate' => $due_date_formatted,
                'person' => $u['upper_worker'] ?: '—',
                'hasCompleted' => !empty($u['completed_date']),
                'kakekin' => (bool)$u['yayoi_kakekin'],
                'furikae' => (bool)$u['yayoi_furikae']
            ];
        }

        // 3. 該当案件の「所属（out）」の注文をすべて取得し、インボイス情報をLEFT JOIN
        $query_out = "
            SELECT 
                o_out.id AS order_id,
                c_lower.company_name AS lower_company,
                o_out.order_no AS lower_order_no,
                o_out.worker_name AS lower_worker,
                CONCAT(
                    COALESCE(DATE_FORMAT(o_out.start_date, '%Y/%m/%d'), '—'), 
                    ' ～ ', 
                    COALESCE(DATE_FORMAT(o_out.end_date, '%Y/%m/%d'), '—')
                ) AS lower_period,
                i.id AS invoice_id,
                i.invoice_no,
                i.file_path,
                i.target_month,
                i.total_amount,
                i.due_date,
                i.completed_date,
                i.yayoi_kakekin,
                i.yayoi_furikae
            FROM orders o_out
            LEFT JOIN crm_company c_lower ON o_out.company_id = c_lower.id
            LEFT JOIN invoices i ON o_out.id = i.order_id
            WHERE o_out.anken_id = ? AND o_out.order_direction = 'out'
        ";
        
        $stmt_out = $pdo->prepare($query_out);
        $stmt_out->execute([$anken_id]);
        $lower_rows = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

        $formatted_lower = [];
        foreach ($lower_rows as $l) {
            $target_month_formatted = '—';
            if (!empty($l['target_month'])) {
                if (preg_match('/^(\d{4})-(\d{2})$/', $l['target_month'], $m)) {
                    $target_month_formatted = $m[1] . '年' . $m[2] . '月';
                } else {
                    $target_month_formatted = $l['target_month'];
                }
            }

            $amount_formatted = isset($l['total_amount']) ? '\\' . number_format((int)$l['total_amount']) : '—';
            $due_date_formatted = $l['due_date'] ? str_replace('-', '/', $l['due_date']) : '—';

            $formatted_lower[] = [
                'company' => $l['lower_company'] ?: 'N/A',
                'orderId' => $l['order_id'],
                'invoiceNo' => $l['invoice_no'] ?: '—',
                'filePath' => $l['file_path'] ?: '',
                'hasInvoice' => !empty($l['invoice_id']),
                'targetMonth' => $target_month_formatted,
                'totalAmount' => $amount_formatted,
                'dueDate' => $due_date_formatted,
                'person' => $l['lower_worker'] ?: '—',
                'hasCompleted' => !empty($l['completed_date']),
                'kakekin' => (bool)$l['yayoi_kakekin'],
                'furikae' => (bool)$l['yayoi_furikae']
            ];
        }

        // 注文データが存在する案件グループを追加
        if (!empty($formatted_upper) || !empty($formatted_lower)) {
            $invoices_data[] = [
                'id' => $anken_id,
                'project' => $proj['project'] ?: '案件名未設定',
                'raw_end_date' => $proj['raw_end_date'],
                'upper_list' => $formatted_upper,
                'lower_list' => $formatted_lower
            ];
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $invoices_data]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
}
?>