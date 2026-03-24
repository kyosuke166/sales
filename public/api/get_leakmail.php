<?php
// get_leakmail.php
require_once __DIR__ . '/../../auth_check.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$company = $_GET['company'] ?? '';

try {
    $pdo = get_db_connection();
    if (!$date || !$company) {
        // --- パラメータがない場合は「サマリー（一覧）」を返す ---
        $sql = "SELECT source_date, source_company, source_person, COUNT(*) as total_count 
                FROM leaked_contacts 
                GROUP BY source_date, source_company, source_person 
                ORDER BY source_date DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // --- パラメータがある場合は「詳細リスト」を返す ---
        $sql = "SELECT * FROM leaked_contacts 
                WHERE source_date = ? AND source_company = ? 
                ORDER BY seq_number ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date, $company]);
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