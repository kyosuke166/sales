<?php
/**
 * 同期ステータス取得API
 */
require_once 'auth_check.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

$pdo = get_db_connection();

try {
    // 1. CRM側の同期対象件数 (sort: 0,1,3 & 電話番号あり)
    $stmt = $pdo->prepare("SELECT count(*) FROM crm_contact WHERE sort IN (0,1,3) AND tel IS NOT NULL AND tel != ''");
    $stmt->execute();
    $crm_total = $stmt->fetchColumn();

    // 2. そのうち、すでにGoogleリソースIDを持っている（同期済み）件数
    $stmt = $pdo->prepare("SELECT count(*) FROM crm_contact WHERE sort IN (0,1,3) AND tel IS NOT NULL AND google_resource_id IS NOT NULL");
    $stmt->execute();
    $synced_count = $stmt->fetchColumn();

    // 3. Google側の実件数を取得
    $stmt = $pdo->prepare("SELECT google_token FROM members WHERE id = 1");
    $stmt->execute();
    $member = $stmt->fetch();

    $google_count = 0;
    if ($member && !empty($member['google_token'])) {
        $client = new Google\Client();
        $client->setClientId(GOOGLE_CLIENT_ID);
        $client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $client->refreshToken($member['google_token']);
        $service = new Google\Service\PeopleService($client);
        
        // とりあえず最小限の項目（namesのみ）を指定して総数を取得
        $results = $service->people_connections->listPeopleConnections('people/me', [
            'pageSize' => 1,
            'personFields' => 'names' // これを追加！
        ]);
        $google_count = $results->getTotalPeople();
    }

    echo json_encode([
        'success' => true,
        'crm_total' => (int)$crm_total,
        'synced_count' => (int)$synced_count,
        'google_total' => (int)$google_count,
        'diff_count' => (int)($crm_total - $synced_count) // 未登録分
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}