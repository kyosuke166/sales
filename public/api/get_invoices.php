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
                o_in.anken_id,
                o_in.anken_name,
                o_in.order_direction,
                o_in.company_id,
                o_in.contact_id,
                o_in.start_date,
                o_in.end_date,
                o_in.unit_price AS order_unit_price,
                o_in.total_amount AS order_total_amount,
                o_in.range_min,
                o_in.range_max,
                o_in.payment_site,
                c_upper.company_name AS upper_company,
                CONCAT_WS(' ', ct_upper.last_name, ct_upper.first_name) AS upper_contact,
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
                i.work_hours,
                i.hour_range,
                i.unit_price AS invoice_unit_price,
                i.adjustment,
                i.expense,
                i.tax_amount,
                i.expense_inc,
                i.total_amount,
                i.due_date,
                i.completed_date,
                i.yayoi_kakekin,
                i.yayoi_furikae
            FROM orders o_in
            LEFT JOIN crm_company c_upper ON o_in.company_id = c_upper.id
            LEFT JOIN crm_contact ct_upper ON o_in.contact_id = ct_upper.id
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
                'companyId' => (int)$u['company_id'],
                'contactId' => $u['contact_id'] === null ? '' : (string)$u['contact_id'],
                'contact' => $u['upper_contact'] ?: '',
                'orderId' => $u['order_id'],
                'invoiceNo' => $u['invoice_id'] ? (string)$u['invoice_no'] : '',
                'filePath' => $u['file_path'] ?: '',
                'hasInvoice' => !empty($u['invoice_id']),
                'targetMonth' => $target_month_formatted,
                'totalAmount' => $amount_formatted,
                'dueDate' => $due_date_formatted,
                'person' => $u['upper_worker'] ?: '—',
                'hasCompleted' => !empty($u['completed_date']),
                'kakekin' => (bool)$u['yayoi_kakekin'],
                'furikae' => (bool)$u['yayoi_furikae'],
                'order' => [
                    'id' => (int)$u['order_id'],
                    'anken_id' => $u['anken_id'],
                    'anken_name' => $u['anken_name'],
                    'order_direction' => $u['order_direction'],
                    'company_id' => (int)$u['company_id'],
                    'company_name' => $u['upper_company'],
                    'contact_id' => $u['contact_id'],
                    'contact_name' => $u['upper_contact'],
                    'worker_name' => $u['upper_worker'],
                    'start_date' => $u['start_date'],
                    'end_date' => $u['end_date'],
                    'unit_price' => $u['order_unit_price'],
                    'total_amount' => $u['order_total_amount'],
                    'range_min' => $u['range_min'],
                    'range_max' => $u['range_max'],
                    'payment_site' => $u['payment_site']
                ],
                'invoice' => $u['invoice_id'] ? [
                    'id' => (int)$u['invoice_id'],
                    'order_id' => (int)$u['order_id'],
                    'target_month' => $u['target_month'],
                    'invoice_no' => $u['invoice_no'],
                    'work_hours' => $u['work_hours'],
                    'hour_range' => $u['hour_range'],
                    'unit_price' => $u['invoice_unit_price'],
                    'adjustment' => $u['adjustment'],
                    'expense' => $u['expense'],
                    'tax_amount' => $u['tax_amount'],
                    'expense_inc' => $u['expense_inc'],
                    'total_amount' => $u['total_amount'],
                    'due_date' => $u['due_date'],
                    'completed_date' => $u['completed_date'],
                    'yayoi_kakekin' => (int)$u['yayoi_kakekin'],
                    'yayoi_furikae' => (int)$u['yayoi_furikae'],
                    'file_path' => $u['file_path']
                ] : null
            ];
        }

        // 3. 該当案件の「所属（out）」の注文をすべて取得し、インボイス情報をLEFT JOIN
        $query_out = "
            SELECT 
                o_out.id AS order_id,
                o_out.anken_id,
                o_out.anken_name,
                o_out.order_direction,
                o_out.company_id,
                o_out.contact_id,
                o_out.start_date,
                o_out.end_date,
                o_out.unit_price AS order_unit_price,
                o_out.total_amount AS order_total_amount,
                o_out.range_min,
                o_out.range_max,
                o_out.payment_site,
                c_lower.company_name AS lower_company,
                CONCAT_WS(' ', ct_lower.last_name, ct_lower.first_name) AS lower_contact,
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
                i.work_hours,
                i.hour_range,
                i.unit_price AS invoice_unit_price,
                i.adjustment,
                i.expense,
                i.tax_amount,
                i.expense_inc,
                i.total_amount,
                i.due_date,
                i.completed_date,
                i.yayoi_kakekin,
                i.yayoi_furikae
            FROM orders o_out
            LEFT JOIN crm_company c_lower ON o_out.company_id = c_lower.id
            LEFT JOIN crm_contact ct_lower ON o_out.contact_id = ct_lower.id
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
                'companyId' => (int)$l['company_id'],
                'contactId' => $l['contact_id'] === null ? '' : (string)$l['contact_id'],
                'contact' => $l['lower_contact'] ?: '',
                'orderId' => $l['order_id'],
                'invoiceNo' => $l['invoice_id'] ? (string)$l['invoice_no'] : '',
                'filePath' => $l['file_path'] ?: '',
                'hasInvoice' => !empty($l['invoice_id']),
                'targetMonth' => $target_month_formatted,
                'totalAmount' => $amount_formatted,
                'dueDate' => $due_date_formatted,
                'person' => $l['lower_worker'] ?: '—',
                'hasCompleted' => !empty($l['completed_date']),
                'kakekin' => (bool)$l['yayoi_kakekin'],
                'furikae' => (bool)$l['yayoi_furikae'],
                'order' => [
                    'id' => (int)$l['order_id'],
                    'anken_id' => $l['anken_id'],
                    'anken_name' => $l['anken_name'],
                    'order_direction' => $l['order_direction'],
                    'company_id' => (int)$l['company_id'],
                    'company_name' => $l['lower_company'],
                    'contact_id' => $l['contact_id'],
                    'contact_name' => $l['lower_contact'],
                    'worker_name' => $l['lower_worker'],
                    'start_date' => $l['start_date'],
                    'end_date' => $l['end_date'],
                    'unit_price' => $l['order_unit_price'],
                    'total_amount' => $l['order_total_amount'],
                    'range_min' => $l['range_min'],
                    'range_max' => $l['range_max'],
                    'payment_site' => $l['payment_site']
                ],
                'invoice' => $l['invoice_id'] ? [
                    'id' => (int)$l['invoice_id'],
                    'order_id' => (int)$l['order_id'],
                    'target_month' => $l['target_month'],
                    'invoice_no' => $l['invoice_no'],
                    'work_hours' => $l['work_hours'],
                    'hour_range' => $l['hour_range'],
                    'unit_price' => $l['invoice_unit_price'],
                    'adjustment' => $l['adjustment'],
                    'expense' => $l['expense'],
                    'tax_amount' => $l['tax_amount'],
                    'expense_inc' => $l['expense_inc'],
                    'total_amount' => $l['total_amount'],
                    'due_date' => $l['due_date'],
                    'completed_date' => $l['completed_date'],
                    'yayoi_kakekin' => (int)$l['yayoi_kakekin'],
                    'yayoi_furikae' => (int)$l['yayoi_furikae'],
                    'file_path' => $l['file_path']
                ] : null
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