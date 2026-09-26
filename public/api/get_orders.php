<?php
require_once 'auth_check.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_db_connection();

    // 1. 案件（anken_id）ごとにユニークなリストを取得
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
    $orders = [];

    while ($proj = $stmt_proj->fetch(PDO::FETCH_ASSOC)) {
        $anken_id = $proj['anken_id'];

        // 2. 該当する anken_id を持つ「上位（in）」の注文をすべて取得（複数対応）
        $query_in = "
            SELECT 
                o_in.id,
                c_upper.company_name AS upper_company,
                o_in.estimate AS upper_estimate,
                o_in.order_no AS upper_order_no,
                o_in.file_path AS upper_file_path,
                CONCAT(
                    COALESCE(DATE_FORMAT(o_in.start_date, '%Y/%m/%d'), '—'), 
                    ' ～ ', 
                    COALESCE(DATE_FORMAT(o_in.end_date, '%Y/%m/%d'), '—')
                ) AS upper_period,
                CONCAT(
                    COALESCE(FORMAT(o_in.unit_price, 0), '0'), 
                    '（', 
                    COALESCE(FORMAT(o_in.range_min, 0), '0'), 
                    '-', 
                    COALESCE(FORMAT(o_in.range_max, 0), '0'), 
                    '）'
                ) AS upper_quote,
                o_in.worker_name AS upper_worker,
                COALESCE(o_in.renewal_status, DATE_FORMAT(o_in.updated, '%Y/%m/%d')) AS upper_raw_status
            FROM orders o_in
            LEFT JOIN crm_company c_upper ON o_in.company_id = c_upper.id
            WHERE o_in.anken_id = ? AND o_in.order_direction = 'in'
        ";
        
        $stmt_in = $pdo->prepare($query_in);
        $stmt_in->execute([$anken_id]);
        $upper_rows = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

        $formatted_upper = [];
        foreach ($upper_rows as $u) {
            $upper_status_text = '要確認';
            if ($u['upper_raw_status'] === 'renewed') {
                $upper_status_text = '更新済';
            } elseif ($u['upper_raw_status'] === 'checking') {
                $upper_status_text = '確認中';
            } elseif (!empty($u['upper_raw_status']) && $u['upper_raw_status'] !== 'pending') {
                $upper_status_text = $u['upper_raw_status'];
            }

            $formatted_upper[] = [
                'company' => $u['upper_company'] ?: 'N/A',
                'estimate' => $u['upper_estimate'] ?: '—',
                'orderNo' => $u['upper_order_no'] ?: '—',
                'filePath' => $u['upper_file_path'] ?: '',
                'period' => $u['upper_period'],
                'quote' => $u['upper_quote'],
                'person' => $u['upper_worker'] ?: '—',
                'status' => $upper_status_text
            ];
        }

        // 3. 該当する anken_id を持つ「所属（out）」の注文をすべて取得（複数対応）
        $query_out = "
            SELECT 
                o_out.id,
                c_lower.company_name AS lower_company,
                o_out.estimate AS lower_estimate,
                o_out.order_no AS lower_order_no,
                o_out.file_path AS lower_file_path,
                CONCAT(
                    COALESCE(DATE_FORMAT(o_out.start_date, '%Y/%m/%d'), '—'), 
                    ' ～ ', 
                    COALESCE(DATE_FORMAT(o_out.end_date, '%Y/%m/%d'), '—')
                ) AS lower_period,
                CONCAT(
                    COALESCE(FORMAT(o_out.unit_price, 0), '0'), 
                    '（', 
                    COALESCE(FORMAT(o_out.range_min, 0), '0'), 
                    '-', 
                    COALESCE(FORMAT(o_out.range_max, 0), '0'), 
                    '）'
                ) AS lower_quote,
                o_out.worker_name AS lower_worker,
                COALESCE(o_out.renewal_status, 'pending') AS lower_status
            FROM orders o_out
            LEFT JOIN crm_company c_lower ON o_out.company_id = c_lower.id
            WHERE o_out.anken_id = ? AND o_out.order_direction = 'out'
        ";
        
        $stmt_out = $pdo->prepare($query_out);
        $stmt_out->execute([$anken_id]);
        $lower_rows = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

        $formatted_lower = [];
        foreach ($lower_rows as $l) {
            $status_text = '要確認';
            if ($l['lower_status'] === 'renewed') $status_text = '更新済';
            if ($l['lower_status'] === 'checking') $status_text = '確認中';

            $formatted_lower[] = [
                'company' => $l['lower_company'] ?: 'N/A',
                'estimate' => $l['lower_estimate'] ?: '—',
                'orderNo' => $l['lower_order_no'] ?: '—',
                'filePath' => $l['lower_file_path'] ?: '',
                'period' => $l['lower_period'],
                'quote' => $l['lower_quote'],
                'person' => $l['lower_worker'] ?: '—',
                'status' => $status_text
            ];
        }

        $orders[] = [
            'id' => $anken_id,
            'project' => $proj['project'] ?: '案件名未設定',
            'raw_end_date' => $proj['raw_end_date'],
            'upper_list' => $formatted_upper,
            'lower_list' => $formatted_lower
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $orders]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
}
?>