<?php
// URLに ?debug=1 をつけるとファイル保存
$debug = isset($_GET['debug']);
// 本番運用時はエラー表示をオフにする
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('memory_limit', '512M'); 
set_time_limit(0);

require_once __DIR__ . '/../../auth_check.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

$pdo = get_db_connection();

if (!function_exists('shortenCompanyName')) {
    function shortenCompanyName($name) {
        $map = ['株式会社' => '(株)', '合同会社' => '(同)', '有限会社' => '(有)', '一般社団法人' => '(一社)', '特定非営利活動法人' => '(NPO)'];
        return str_replace(array_keys($map), array_values($map), $name);
    }
}

if (!function_exists('clean')) {
    function clean($str) {
        return str_replace([' ', '　'], '', (string)$str);
    }
}

try {
    $stmt = $pdo->prepare("SELECT google_token FROM members WHERE id = 1");
    $stmt->execute();
    $member = $stmt->fetch();
    
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->refreshToken($member['google_token']);
    $service = new Google\Service\PeopleService($client);

    // 1. CRM側の項目を取得
    $sql = "SELECT c.id, c.last_name, c.first_name, c.position, c.division, c.tel, c.email, c.google_resource_id, c.google_last_sync,
                   comp.company_name 
            FROM crm_contact c
            LEFT JOIN crm_company comp ON c.company_id = comp.id
            WHERE c.google_resource_id IS NOT NULL AND c.deleted IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $crmMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // IDから people/ を除いたものをキーにする
        $normId = str_replace('people/', '', (string)$row['google_resource_id']);
        $crmMap[$normId] = $row;
    }

    $diffList = [];
    $pageToken = null;
    $allGoogleData = [];

    do {
        // ★ Google APIからのデータ取得
        $optParams = [
            'pageSize' => 1000, 
            'personFields' => 'names,organizations,phoneNumbers,emailAddresses', 
            'pageToken' => $pageToken
        ];
        $results = $service->people_connections->listPeopleConnections('people/me', $optParams);
        $connections = $results->getConnections() ?: [];

        foreach ($connections as $person) {
            $gidFull = $person->getResourceName(); 
            $gidNormal = str_replace('people/', '', $gidFull);

            // デバッグ用
            if ($debug) {
                $allGoogleData[] = [
                    'res' => $gidFull,
                    'names' => $person->getNames(),
                    'orgs' => $person->getOrganizations()
                ];
            }

            if (!isset($crmMap[$gidNormal])) continue;
            $crm = $crmMap[$gidNormal];

            // --- Googleデータ抽出 ---
            $gNames = $person->getNames();
            $gNameObj = !empty($gNames) ? $gNames[0] : null;
            $gFamily = (string)($gNameObj ? $gNameObj->getFamilyName() : '');
            $gGiven = (string)($gNameObj ? $gNameObj->getGivenName() : '');
            $gDisplayName = (string)($gNameObj ? $gNameObj->getDisplayName() : '');

            // 「◆」より前を抽出
            $gGivenPure = trim(explode('◆', $gGiven)[0]);

            $gOrgs = $person->getOrganizations();
            $gOrg = !empty($gOrgs) ? $gOrgs[0] : null;
            $gCompany = $gOrg ? ($gOrg->getName() ?? '') : '';
            $gTitle = $gOrg ? ($gOrg->getTitle() ?? '') : '';
            $gDept = $gOrg ? ($gOrg->getDepartment() ?? '') : '';

            $gPhones = $person->getPhoneNumbers();
            $gTel = !empty($gPhones) ? ($gPhones[0]->getValue() ?? '') : '';

            $gEmails = $person->getEmailAddresses() ?: [];
            $gEmail = '';
            foreach($gEmails as $e) {
                if($e->getType() === 'work') { $gEmail = $e->getValue(); break; }
                if(!$gEmail) $gEmail = $e->getValue(); 
            }

            // --- CRM側のデータ ---
            $crmLast = (string)$crm['last_name'];
            $crmFirst = (string)$crm['first_name'];
            $crmShortCo = shortenCompanyName($crm['company_name'] ?? '');

            $details = [
                'name'    => ['crm' => $crmLast . $crmFirst, 'google' => $gDisplayName, 'diff' => false],
                'company' => ['crm' => $crm['company_name'] ?? '', 'google' => $gCompany, 'diff' => false],
                'title'   => ['crm' => $crm['position'] ?? '', 'google' => $gTitle, 'diff' => false],
                'dept'    => ['crm' => $crm['division'] ?? '', 'google' => $gDept, 'diff' => false],
                'tel'     => ['crm' => $crm['tel'] ?? '', 'google' => $gTel, 'diff' => false],
                'email'   => ['crm' => $crm['email'] ?? '', 'google' => $gEmail, 'diff' => false],
            ];

            $hasAnyDiff = false;

            // 1. 氏名判定
            if (clean($gFamily) !== clean($crmLast) || clean($gGivenPure) !== clean($crmFirst)) {
                $details['name']['diff'] = true; $hasAnyDiff = true;
            }
            // 2. 会社名判定
            if (clean($gCompany) !== clean($crm['company_name'] ?? '') && clean($gCompany) !== clean($crmShortCo)) {
                $details['company']['diff'] = true; $hasAnyDiff = true;
            }
            // 3. その他
            if (clean($details['title']['crm']) !== clean($details['title']['google'])) { $details['title']['diff'] = true; $hasAnyDiff = true; }
            if (clean($details['dept']['crm']) !== clean($details['dept']['google'])) { $details['dept']['diff'] = true; $hasAnyDiff = true; }
            if (clean($details['email']['crm']) !== clean($details['email']['google'])) { $details['email']['diff'] = true; $hasAnyDiff = true; }
            if (preg_replace('/[^0-9]/','',$details['tel']['crm']) !== preg_replace('/[^0-9]/','',$details['tel']['google'])) {
                $details['tel']['diff'] = true; $hasAnyDiff = true;
            }

            if ($hasAnyDiff) {
                $diffList[] = [
                    'crm_id' => $crm['id'], 
                    'google_id' => $gidFull, 
                    'is_google_sei' => ($crm['google_last_sync'] === null), 
                    'details' => $details
                ];
            }
        }
        $pageToken = $results->getNextPageToken();
    } while ($pageToken);

    if ($debug) file_put_contents(__DIR__ . '/google_dump.json', json_encode($allGoogleData, JSON_UNESCAPED_UNICODE));

    ob_clean();
    echo json_encode(['success' => true, 'diff_list' => $diffList]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}