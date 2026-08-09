<?php
// get_groups.php
require_once 'auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    
    // グループ名が入っている会社を抽出
    $sql = "SELECT id, company_name, company_kana, company_group, url 
            FROM crm_company 
            WHERE company_group IS NOT NULL AND company_group != ''
            ORDER BY company_group ASC, company_kana ASC";
    
    $stmt = $pdo->query($sql);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // グループごとに集計
    $groups = [];
    foreach ($companies as $c) {
        $gName = $c['company_group'];
        if (!isset($groups[$gName])) {
            $groups[$gName] = [
                'name' => $gName,
                'companies' => []
            ];
        }
        $groups[$gName]['companies'][] = $c;
    }

    echo json_encode(['status' => 'success', 'data' => array_values($groups)]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}