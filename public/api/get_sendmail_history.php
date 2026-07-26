<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = get_db_connection();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // 共通のベース結合句
    $baseJoin = "FROM sendmail_history h
        LEFT JOIN engineer e ON h.engineer_id = e.id
        LEFT JOIN anken a ON h.anken_id = a.id
        LEFT JOIN crm_company co ON COALESCE(a.company_id, e.company_id) = co.id
        LEFT JOIN crm_contact c ON COALESCE(a.contact_id, e.contact_id) = c.id";

    // 1. 特定の履歴IDが指定された場合（詳細取得：すべてのカラムを取得）
    if ($id > 0) {
        $sql = "SELECT
                h.*,
                COALESCE(a.received, e.received) AS received,
                COALESCE(a.contact_method, e.contact_method) AS contact_method,
                co.company_name,
                CONCAT(IFNULL(c.last_name, ''), ' ', IFNULL(c.first_name, '')) AS contact_name,
                COALESCE(a.original, e.original) AS original,
                CASE 
                    WHEN h.engineer_id IS NOT NULL THEN e.name
                    WHEN h.anken_id IS NOT NULL THEN a.name
                    ELSE NULL 
                END AS target_name
            " . $baseJoin . " WHERE h.id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('配信履歴が見つかりません');
        }
        echo json_encode(['status' => 'success', 'data' => $row], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. 履歴一覧の取得（一覧用：巨大な target や body を除外して軽量化・高速化）
    $sql = "SELECT
            h.id,
            h.senddate,
            h.status,
            h.anken_id,
            h.engineer_id,
            h.subject,
            COALESCE(a.received, e.received) AS received,
            COALESCE(a.contact_method, e.contact_method) AS contact_method,
            co.company_name,
            CONCAT(IFNULL(c.last_name, ''), ' ', IFNULL(c.first_name, '')) AS contact_name,
            CASE 
                WHEN h.engineer_id IS NOT NULL THEN e.name
                WHEN h.anken_id IS NOT NULL THEN a.name
                ELSE NULL 
            END AS target_name
        " . $baseJoin . " 
        ORDER BY h.senddate DESC, h.id DESC 
        LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}