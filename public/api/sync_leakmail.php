<?php
// api/sync_leakmail.php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

$date = $_POST['date'] ?? '';
$company = $_POST['company'] ?? '';

if (!$date || !$company) {
    echo json_encode(['success' => false, 'message' => 'パラメータ不足']);
    exit;
}

$search_date = str_replace('T', ' ', $date);

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 1. メールアドレス完全一致 (担当者と会社を同時に紐付け)
    $sql1 = "UPDATE leaked_contacts lc
             JOIN crm_contact c ON lc.email = c.email
             SET lc.contact_id = c.id, lc.company_id = c.company_id
             WHERE lc.source_date = ? AND lc.source_company = ?";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute([$search_date, $company]);
    $hit1 = $stmt1->rowCount();

    // 2. 会社名完全一致 (未紐付けのもののみ)
    $sql2 = "UPDATE leaked_contacts lc
             JOIN crm_company cp ON lc.raw_company = cp.company_name
             SET lc.company_id = cp.id
             WHERE lc.source_date = ? AND lc.source_company = ?
             AND lc.company_id IS NULL";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$search_date, $company]);
    $hit2 = $stmt2->rowCount();

    // 3. ドメイン一致 (未紐付け、かつフリーメールを除外)
    // crm_companyにdomainがないため、crm_contactのemailの後方にそのドメインが含まれる会社を特定
    $sql3 = "UPDATE leaked_contacts lc
             JOIN crm_contact c ON c.email LIKE CONCAT('%@', lc.email_domain)
             SET lc.company_id = c.company_id
             WHERE lc.source_date = ? AND lc.source_company = ?
             AND lc.company_id IS NULL
             AND lc.email_domain NOT IN ('gmail.com', 'yahoo.co.jp', 'outlook.jp', 'icloud.com', 'nifty.com', 'hotmail.com')
             AND c.company_id IS NOT NULL";
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute([$search_date, $company]);
    $hit3 = $stmt3->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "照合完了！\n・メール(担当者)一致: {$hit1}件\n・会社名一致: {$hit2}件\n・ドメイン一致: {$hit3}件"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '照合エラー: ' . $e->getMessage()]);
}