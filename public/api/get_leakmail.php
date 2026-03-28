<?php
// get_leakmail.php
require_once __DIR__ . '/../../auth_check.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$company = $_GET['company'] ?? '';

try {
    $pdo = get_db_connection();
    if (!$date || !$company) {
        // --- サマリー（一覧） ---
        // 取得した担当者名(source_person)も1つ取得して表示に使う
        $sql = "SELECT source_date, source_company, MAX(source_person) as source_person, COUNT(*) as total_count 
                FROM leaked_contacts 
                GROUP BY source_date, source_company 
                ORDER BY source_date ASC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // --- 詳細リスト ---
        $sql = "SELECT 
                    lc.*, 
                    cp.company_name AS crm_company_name, 
                    CONCAT(IFNULL(c.last_name,''), ' ', IFNULL(c.first_name,'')) AS crm_contact_name 
                FROM leaked_contacts lc
                LEFT JOIN crm_company cp ON lc.company_id = cp.id
                LEFT JOIN crm_contact c ON lc.contact_id = c.id
                WHERE lc.source_date = ? AND lc.source_company = ? 
                ORDER BY lc.seq_number ASC";
        
        $stmt = $pdo->prepare($sql);

        // URLパラメータの 'T' をスペースに変換しないとDBと一致しません
        $search_date = str_replace('T', ' ', $date);
        
        $stmt->execute([$search_date, $company]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}