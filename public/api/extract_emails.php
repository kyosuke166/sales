<?php
// extract_emails.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';
$exclude_company_id = $_GET['exclude_company_id'] ?? null;

if (!in_array($type, ['send_project', 'send_engineer', 'send_event'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
    exit;
}

try {
    $pdo = get_db_connection();
    
    $exclude_group_name = null;
    
    // 1. 除外するグループ系列名を取得
    if (!empty($exclude_company_id) && is_numeric($exclude_company_id)) {
        $stmt_g = $pdo->prepare("SELECT company_group FROM crm_company WHERE id = ?");
        $stmt_g->execute([$exclude_company_id]);
        $row_g = $stmt_g->fetch();
        if ($row_g && !empty($row_g['company_group'])) {
            $exclude_group_name = $row_g['company_group'];
        }
    }

    // 2. 配信対象者の抽出
    $sql = "SELECT c.email, c.last_name, co.company_name 
            FROM crm_contact c
            JOIN crm_company co ON c.company_id = co.id
            WHERE c.{$type} = 1 
            AND c.email IS NOT NULL 
            AND c.email != ''
            AND c.deleted IS NULL
            AND co.deleted IS NULL"; // 会社側も削除済みは除外

    // 除外条件（選択された会社のグループ系列名と同じ会社をすべて除外）
    if ($exclude_group_name !== null) {
        $sql .= " AND (co.company_group != " . $pdo->quote($exclude_group_name) . " OR co.company_group IS NULL OR co.company_group = '')";
    }

    $sql .= " ORDER BY co.company_name ASC, c.sort ASC";

    $stmt = $pdo->query($sql);
    $contacts = $stmt->fetchAll();

    $resultLines = [];
    foreach ($contacts as $c) {
        $email = trim($c['email']);
        $lastName = trim($c['last_name'] ?? '担当者');
        $companyName = trim($c['company_name'] ?? '');

        if ($companyName === "個人事業主" || empty($companyName)) {
            $resultLines[] = "{$email},{$lastName}様";
        } else {
            $resultLines[] = "{$email},{$companyName} {$lastName}様";
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => implode("\n", $resultLines)
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}