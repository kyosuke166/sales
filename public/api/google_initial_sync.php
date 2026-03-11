<?php
/**
 * 初期連携：Googleに存在しない番号のみを新規登録する
 */
require_once __DIR__ . '/../../auth_check.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');
set_time_limit(600);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
// 安全のため最大値を制限（例: 500件）
if ($limit > 500) $limit = 500;
if ($limit < 1) $limit = 1;

$pdo = get_db_connection();

/**
 * 会社名の短縮変換関数
 */
function shortenCompanyName($name) {
    $map = [
        '株式会社' => '(株)',
        '合同会社' => '(同)',
        '有限会社' => '(有)',
        '一般社団法人' => '(一社)',
        '特定非営利活動法人' => '(NPO)',
    ];
    return str_replace(array_keys($map), array_values($map), $name);
}

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

    // 1.5 「SBT連携」ラベル（グループ）の準備
    $groupResourceName = null;
    try {
        $groupsResponse = $service->contactGroups->listContactGroups();
        $groups = $groupsResponse->getContactGroups();
        
        if (!empty($groups)) {
            foreach ($groups as $group) {
                // formattedName または name を確認
                $gName = $group->getName();
                if ($gName === 'SBT連携') {
                    $groupResourceName = $group->getResourceName();
                    break;
                }
            }
        }

        // なければ作成
        if (!$groupResourceName) {
            // 1. リクエスト用のオブジェクトを作る
            $newGroup = new Google\Service\PeopleService\ContactGroup();
            $newGroup->setName('SBT連携');

            // 2. CreateContactGroupRequest という箱に入れる
            $createRequest = new Google\Service\PeopleService\CreateContactGroupRequest();
            $createRequest->setContactGroup($newGroup);

            // 3. 箱を渡して作成
            $createdGroup = $service->contactGroups->create($createRequest);
            $groupResourceName = $createdGroup->getResourceName();
        }
    } catch (Exception $e) {
        // ここでエラーが出た場合、権限不足の可能性があるため、
        // 500エラーにせず一旦「ラベルなし」で進めるか、エラーを出力します
        throw new Exception("ラベル処理エラー: " . $e->getMessage());
    }

    // 2. Google側の電話番号をハッシュマップ化（照合用）
    $googlePhones = [];
    $pageToken = null;
    do {
        $optParams = [
            'pageSize' => 1000,
            'personFields' => 'phoneNumbers',
            'pageToken' => $pageToken
        ];
        $results = $service->people_connections->listPeopleConnections('people/me', $optParams);
        $connections = $results->getConnections();
        if ($connections) {
            foreach ($connections as $person) {
                $phones = $person->getPhoneNumbers();
                if ($phones) {
                    foreach ($phones as $p) {
                        $cleanNum = preg_replace('/\D/', '', $p->getValue());
                        $googlePhones[$cleanNum] = $person->getResourceName();
                    }
                }
            }
        }
        $pageToken = $results->getNextPageToken();
    } while ($pageToken);

    // 3. CRMから対象データを取得（JOINして全項目揃える）
    $sql = "SELECT 
                t1.*, 
                t2.company_name 
            FROM crm_contact t1
            LEFT JOIN crm_company t2 ON t1.company_id = t2.id
            WHERE t1.sort IN (0,1,3) 
              AND t1.tel IS NOT NULL 
              AND t1.tel != ''
              AND t1.google_resource_id IS NULL
              AND t1.deleted IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $crmContacts = $stmt->fetchAll();

    $createdCount = 0;
    $linkedCount = 0;

    foreach ($crmContacts as $contact) {
        $cleanCrmTel = preg_replace('/\D/', '', $contact['tel']);

        if (isset($googlePhones[$cleanCrmTel])) {
            // A. すでにGoogleに番号があった場合
            $updateStmt = $pdo->prepare("UPDATE crm_contact SET google_resource_id = :gid, google_last_sync = NOW() WHERE id = :id");
            $updateStmt->execute([':gid' => $googlePhones[$cleanCrmTel], ':id' => $contact['id']]);
            $linkedCount++;
        } else {
            // B. Googleに番号がなかった場合：新規作成
            $person = new Google\Service\PeopleService\Person();
            
            // --- 名前・表示名 ---
            $nameObj = new Google\Service\PeopleService\Name();
            $nameObj->setFamilyName($contact['last_name']);
            
            // 「名」の後ろに「 (株)会社名」をくっつける
            $shortCo = shortenCompanyName($contact['company_name'] ?? '');
            $customGivenName = trim(($contact['first_name'] ?? '') . " ◆ " . $shortCo);
            $nameObj->setGivenName($customGivenName); 

            $nameObj->setPhoneticFamilyName($contact['last_kana']);
            $nameObj->setPhoneticGivenName($contact['first_kana']);
            
            // これで Google 側は「姓」+「名(会社名入り)」を表示名として採用します
            $person->setNames([$nameObj]);

            // --- 会社情報 ---
            $org = new Google\Service\PeopleService\Organization();
            $org->setName($contact['company_name']);
            $org->setDepartment($contact['division']);
            $org->setTitle($contact['position']);
            $org->setType('work');
            $person->setOrganizations([$org]);

            // --- メールアドレス ---
            $emails = [];
            if (!empty($contact['email'])) {
                $e1 = new Google\Service\PeopleService\EmailAddress();
                $e1->setValue($contact['email']);
                $e1->setType('work');
                $emails[] = $e1;
            }
            if (!empty($contact['email_personal'])) {
                $e2 = new Google\Service\PeopleService\EmailAddress();
                $e2->setValue($contact['email_personal']);
                $e2->setType('home');
                $emails[] = $e2;
            }
            $person->setEmailAddresses($emails);

            // --- 電話番号 ---
            $phone = new Google\Service\PeopleService\PhoneNumber();
            $phone->setValue($contact['tel']);
            // 090/080/070なら携帯、それ以外は仕事
            $isMobile = preg_match('/^0[789]0\d{8}$/', $cleanCrmTel);
            $phone->setType($isMobile ? 'mobile' : 'work');
            $person->setPhoneNumbers([$phone]);

            // --- ラベル（SBT連携）の紐付け ---
            $membership = new Google\Service\PeopleService\Membership();
            $contactGroupMembership = new Google\Service\PeopleService\ContactGroupMembership();
            $contactGroupMembership->setContactGroupResourceName($groupResourceName);
            $membership->setContactGroupMembership($contactGroupMembership);
            $person->setMemberships([$membership]);

            try {
                $created = $service->people->createContact($person);
                $newGid = $created->getResourceName();

                $updateStmt = $pdo->prepare("UPDATE crm_contact SET google_resource_id = :gid, google_last_sync = NOW() WHERE id = :id");
                $updateStmt->execute([':gid' => $newGid, ':id' => $contact['id']]);
                $createdCount++;
            } catch (Exception $e) {
                continue;
            }
        }
        
        // 入力した件数で停止
        if (($createdCount + $linkedCount) >= $limit) break;
    }

    echo json_encode([
        'success' => true,
        'created' => $createdCount,
        'linked' => $linkedCount,
        'message' => "新規登録: {$createdCount}件 / 既存紐付: {$linkedCount}件"
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}