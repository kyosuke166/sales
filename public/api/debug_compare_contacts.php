<?php
require_once __DIR__ . '/../../auth_check.php';
require_once 'vendor/autoload.php';
header('Content-Type: application/json');

$pdo = get_db_connection();

try {
    $stmt = $pdo->prepare("SELECT google_token FROM members WHERE id = 1");
    $stmt->execute();
    $member = $stmt->fetch();
    
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->refreshToken($member['google_token']);
    $service = new Google\Service\PeopleService($client);

    // 同期済みの最新100件を取得
    $stmt = $pdo->prepare("
        SELECT t1.id, t1.last_name, t1.first_name, t1.tel, t1.email, t1.google_resource_id, t2.company_name 
        FROM crm_contact t1 
        LEFT JOIN crm_company t2 ON t1.company_id = t2.id 
        WHERE t1.google_resource_id IS NOT NULL 
        ORDER BY t1.google_last_sync DESC LIMIT 100
    ");
    $stmt->execute();
    $contacts = $stmt->fetchAll();

    $comparison = [];
    foreach ($contacts as $c) {
        $gData = ['name' => '取得失敗', 'org' => '-', 'tel' => '-'];
        try {
            // Googleから現在の生データを取得
            $person = $service->people->get($c['google_resource_id'], [
                'personFields' => 'names,organizations,phoneNumbers,emailAddresses'
            ]);
            
            $gNames = $person->getNames();
            $gOrgs = $person->getOrganizations();
            $gTels = $person->getPhoneNumbers();
            
            $gData = [
                'name' => $gNames[0] ? $gNames[0]->getDisplayName() : '名前なし',
                'org' => $gOrgs[0] ? $gOrgs[0]->getName() : '未設定',
                'tel' => $gTels[0] ? $gTels[0]->getValue() : '番号なし'
            ];
        } catch (Exception $e) { $gData['name'] = "Error: " . $e->getMessage(); }

        $comparison[] = [
            'crm' => [
                'name' => $c['last_name'] . $c['first_name'],
                'org' => $c['company_name'],
                'tel' => $c['tel']
            ],
            'google' => $gData,
            'resource_id' => $c['google_resource_id']
        ];
    }

    echo json_encode($comparison);
} catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }