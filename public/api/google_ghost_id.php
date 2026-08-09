<?php
require_once 'auth_check.php';
require_once 'vendor/autoload.php';

// タイムアウトを防ぐ（5分に設定）
set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');

$pdo = get_db_connection();

try {
    // 1. Google Client準備
    $stmt = $pdo->prepare("SELECT google_token FROM members WHERE id = 1");
    $stmt->execute();
    $member = $stmt->fetch();
    
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->refreshToken($member['google_token']);
    $service = new Google\Service\PeopleService($client);

    echo "<h3>Google連絡先 全件スキャン開始（約4500件）</h3>";

    // 2. Google側の「現存するID」をすべてハッシュマップ（連想配列）に格納
    $googleActiveIds = [];
    $pageToken = null;

    do {
        $optParams = [
            'pageSize' => 1000,
            'personFields' => 'names', // 最小限のデータのみ取得
            'pageToken' => $pageToken
        ];
        $results = $service->people_connections->listPeopleConnections('people/me', $optParams);
        $connections = $results->getConnections();
        
        if ($connections) {
            foreach ($connections as $person) {
                // keyにIDを入れることで検索速度を爆速にする
                $googleActiveIds[$person->getResourceName()] = true;
            }
        }
        $pageToken = $results->getNextPageToken();
        echo "Googleから現在 " . count($googleActiveIds) . " 件のIDを読み込みました...<br>";
        ob_flush(); flush(); 
    } while ($pageToken);

    echo "<h4>Google側の有効な連絡先総数: " . count($googleActiveIds) . " 件</h4><hr>";

    // 3. CRMから「Google連携済み（IDあり）」のデータを全件取得
    $stmt = $pdo->prepare("SELECT id, google_resource_id, last_name, first_name FROM crm_contact WHERE google_resource_id IS NOT NULL");
    $stmt->execute();
    $crmContacts = $stmt->fetchAll();

    echo "CRM側の連携済みデータ " . count($crmContacts) . " 件と照合中...<br>";

    $fixCount = 0;
    $fixList = [];

    // 4. メモリ上で一気にマッチング
    foreach ($crmContacts as $contact) {
        $gid = $contact['google_resource_id'];

        // CRMが持っているIDが、Googleから取得した現役リストの中に存在しない場合
        if (!isset($googleActiveIds[$gid])) {
            // 幽霊確定：CRM側をリセット
            $update = $pdo->prepare("UPDATE crm_contact SET google_resource_id = NULL, google_last_sync = NULL WHERE id = ?");
            $update->execute([$contact['id']]);
            
            $fixCount++;
            $name = ($contact['last_name'] ?? '') . ($contact['first_name'] ?? '');
            $fixList[] = "リセット完了: " . ($name ?: "不明者") . " (ID: $gid)";
        }
    }

    echo "<h3>修復結果</h3>";
    echo "Google側から消えていた件数: <strong>{$fixCount} 件</strong><br>";
    
    if ($fixCount > 0) {
        echo "<div style='max-height:300px; overflow-y:auto; border:1px solid #ccc; padding:10px;'><ul>";
        foreach ($fixList as $item) {
            echo "<li>$item</li>";
        }
        echo "</ul></div>";
    }

    echo "<p>作業が完了しました。これで「未連携」のカウントが正しくなったはずです。</p>";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage();
}