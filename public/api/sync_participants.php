<?php
require_once 'auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $event_id = $_POST['event_id'] ?? null;

    // 1. まず「CRM側でメールアドレスが1件しか存在しない担当者」をリストアップする
    // (重複があるアドレスをここで除外する)
    $sql_valid_contacts = "
        SELECT email, MIN(id) as contact_id, MIN(company_id) as company_id
        FROM crm_contact
        WHERE email IS NOT NULL AND email != ''
        GROUP BY email
        HAVING COUNT(id) = 1
    ";

    // 2. 上記の「安全なリスト」に合致する参加者だけを一括更新する
    $sql_update = "
        UPDATE events_participant ep
        INNER JOIN ($sql_valid_contacts) AS safe_cc ON ep.email = safe_cc.email
        SET 
            ep.contact_id = safe_cc.contact_id,
            ep.company_id = safe_cc.company_id
        WHERE ep.event_id = ? 
        AND ep.contact_id IS NULL
    ";

    $stmt = $pdo->prepare($sql_update);
    $stmt->execute([$event_id]);
    $count = $stmt->rowCount();

    // 3. (オプション) 重複があって自動照合できなかった件数も数える
    $sql_dupes = "
        SELECT COUNT(*) FROM events_participant 
        WHERE event_id = ? AND contact_id IS NULL 
        AND email IN (SELECT email FROM crm_contact GROUP BY email HAVING COUNT(id) > 1)
    ";
    $stmt_dupe = $pdo->prepare($sql_dupes);
    $stmt_dupe->execute([$event_id]);
    $dupe_count = $stmt_dupe->fetchColumn();

    $msg = "{$count} 件を自動照合しました。";
    if ($dupe_count > 0) {
        $msg .= " (※CRM側に重複アドレスがある {$dupe_count} 件は安全のためスキップしました。)";
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}