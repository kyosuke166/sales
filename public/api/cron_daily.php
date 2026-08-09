<?php
require_once 'auth_check.php';

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 1. 交流会参加者テーブル：すでに紐付け済みのものは配信フラグを折る
    $sql1 = "UPDATE events_participant 
            SET send_flg = '0' 
            WHERE contact_id IS NOT NULL 
            AND send_flg <> '0'"; // 既に'0'のものは除外して負荷軽減

    // 2. 漏洩メールテーブル：すでに紐付け済みのものは配信対象から外す
    $sql2 = "UPDATE leaked_contacts 
            SET send_name = NULL, send_error = NULL 
            WHERE contact_id IS NOT NULL 
            AND (send_name IS NOT NULL OR send_error IS NOT NULL)";
    $pdo->exec($sql2);

    $pdo->commit();
    echo "Batch Success: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Batch Error: " . $e->getMessage());
    echo "Batch Failed: " . $e->getMessage() . "\n";
}