<?php
/**
 * 案件一覧、または特定の1件を取得する
 */
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = get_db_connection();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $today = new DateTime('now');
    $thisMonthEnd = new DateTime('last day of this month 23:59:59');

    // 基本となるSQL
    $sql = "SELECT 
                p.id, 
                p.project_name, 
                p.status, 
                p.memo, 
                p.created,
                -- 受注情報 (in)
                oi.id as order_in_id,
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
        $sql .= " WHERE p.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) throw new Exception('案件が見つかりません');
        echo json_encode(['status' => 'success', 'data' => $result], JSON_UNESCAPED_UNICODE);
    } else {
        $sql .= " ORDER BY p.created ASC";
        $stmt = $pdo->query($sql);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- タスクカウント集計ロジック ---
        $counts = [
            'send_invoice' => 0,    // 売上請求書を送る(Out)
            'confirm_deposit' => 0, // 入金を確認する(In)
            'receive_invoice' => 0, // 支払請求書を貰う(In)
            'make_payment' => 0     // お金を払う(Out)
        ];

        foreach ($projects as $p) {
            // ステータスが「稼働中(1)」でないものは集計対象外
            if ($p['status'] != 1) continue;

            // --- 1. 売上側 (受注 order_in に対しての invoice_out) ---
            if ($p['order_in_id'] && $p['start_date']) {
                $start = new DateTime($p['start_date']);
                $end   = $p['end_date'] ? new DateTime($p['end_date']) : clone $thisMonthEnd;
                $limit = ($end < $thisMonthEnd) ? $end : $thisMonthEnd;

                $interval = new DateInterval('P1M');
                $period = new DatePeriod($start, $interval, $limit->modify('+1 day'));

                foreach ($period as $dt) {
                    $month = $dt->format('Y-m');
                    $stInv = $pdo->prepare("SELECT file_path, deposit_date FROM invoice_out WHERE order_in_id = ? AND work_month = ?");
                    $stInv->execute([$p['order_in_id'], $month]);
                    $inv = $stInv->fetch(PDO::FETCH_ASSOC);

                    if (!$inv) {
                        $counts['send_invoice']++;
                        $counts['confirm_deposit']++;
                    } else {
                        if (empty($inv['file_path'])) $counts['send_invoice']++;
                        if (empty($inv['deposit_date'])) $counts['confirm_deposit']++;
                    }
                }
            }

            // --- 2. 支払側 (発注 order_out に対しての invoice_in) ---
            if ($p['order_out_id'] && $p['out_start_date']) {
                $start = new DateTime($p['out_start_date']);
                $end   = $p['out_end_date'] ? new DateTime($p['out_end_date']) : clone $thisMonthEnd;
                $limit = ($end < $thisMonthEnd) ? $end : $thisMonthEnd;

                $interval = new DateInterval('P1M');
                $period = new DatePeriod($start, $interval, $limit->modify('+1 day'));

                foreach ($period as $dt) {
                    $month = $dt->format('Y-m');
                    $stInv = $pdo->prepare("SELECT file_path, paid_date FROM invoice_in WHERE order_out_id = ? AND work_month = ?");
                    $stInv->execute([$p['order_out_id'], $month]);
                    $inv = $stInv->fetch(PDO::FETCH_ASSOC);

                    if (!$inv) {
                        $counts['receive_invoice']++;
                        $counts['make_payment']++;
                    } else {
                        if (empty($inv['file_path'])) $counts['receive_invoice']++;
                        if (empty($inv['paid_date'])) $counts['make_payment']++;
                    }
                }
            }
        }

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