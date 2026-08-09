<?php
require_once 'auth_check.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

$pdo = get_db_connection();
$input = json_decode(file_get_contents('php://input'), true);
$crm_id = $input['crm_id'] ?? null;
$google_id = $input['google_id'] ?? null;

if (!$crm_id || !$google_id) {
    echo json_encode(['success' => false, 'error' => 'IDが不足しています']);
    exit;
}

function shortenCompanyName($name) {
    $map = ['株式会社' => '(株)', '合同会社' => '(同)', '有限会社' => '(有)', '一般社団法人' => '(一社)', '特定非営利活動法人' => '(NPO)'];
    return str_replace(array_keys($map), array_values($map), $name);
}

try {
    // 1. CRMからデータ取得
    $sql = "SELECT c.*, comp.company_name FROM crm_contact c 
            LEFT JOIN crm_company comp ON c.company_id = comp.id WHERE c.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$crm_id]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Google Client準備
    $stmt = $pdo->prepare("SELECT google_token FROM members WHERE id = 1");
    $stmt->execute();
    $member = $stmt->fetch();
    
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->refreshToken($member['google_token']);
    $service = new Google\Service\PeopleService($client);

    // 3. 「SBT連携」ラベルのResourceNameを取得（なければ作成）
    $targetGroupName = 'SBT連携';
    $targetGroupResourceName = null;
    $groupsResponse = $service->contactGroups->listContactGroups();
    foreach ($groupsResponse->getContactGroups() as $group) {
        if ($group->getName() === $targetGroupName) {
            $targetGroupResourceName = $group->getResourceName();
            break;
        }
    }
    if (!$targetGroupResourceName) {
        $newGroup = new Google\Service\PeopleService\ContactGroup();
        $newGroup->setName($targetGroupName);
        $createRequest = new Google\Service\PeopleService\CreateContactGroupRequest();
        $createRequest->setContactGroup($newGroup);
        $createdGroup = $service->contactGroups->create($createRequest);
        $targetGroupResourceName = $createdGroup->getResourceName();
    }

    // 4. 最新のetagを取得（ラベル上書きのため、既存のmembershipsは取得不要）
    $person = $service->people->get($google_id, ['personFields' => 'metadata']);
    $etag = $person->getEtag();

    // 5. 更新用オブジェクト構築
    $updatePerson = new Google\Service\PeopleService\Person();
    $updatePerson->setEtag($etag);

    // --- 名前・表示名・カナ ---
    $shortCo = shortenCompanyName($contact['company_name'] ?? '');
    $nameObj = new Google\Service\PeopleService\Name();
    $nameObj->setFamilyName($contact['last_name']);
    $nameObj->setGivenName(trim(($contact['first_name'] ?? '') . " ◆ " . $shortCo));
    $nameObj->setPhoneticFamilyName($contact['last_kana']);
    $nameObj->setPhoneticGivenName($contact['first_kana']);
    $updatePerson->setNames([$nameObj]);

    // --- 会社情報 ---
    $org = new Google\Service\PeopleService\Organization();
    $org->setName($contact['company_name'] ?? '');
    $org->setDepartment($contact['division'] ?? '');
    $org->setTitle($contact['position'] ?? '');
    $org->setType('work');
    $updatePerson->setOrganizations([$org]);

    // --- 電話番号 (携帯/仕事判定) ---
    $phoneNumbers = [];
    if ($contact['tel']) {
        $cleanTel = preg_replace('/\D/', '', $contact['tel']);
        $isMobile = preg_match('/^0[789]0\d{8}$/', $cleanTel);
        $phone = new Google\Service\PeopleService\PhoneNumber();
        $phone->setValue($contact['tel']);
        $phone->setType($isMobile ? 'mobile' : 'work');
        $phoneNumbers[] = $phone;
    }
    $updatePerson->setPhoneNumbers($phoneNumbers);

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
    $updatePerson->setEmailAddresses($emails);

    // --- ラベル（SBT連携のみをセットして上書き） ---
    $membership = new Google\Service\PeopleService\Membership();
    $cgm = new Google\Service\PeopleService\ContactGroupMembership();
    $cgm->setContactGroupResourceName($targetGroupResourceName);
    $membership->setContactGroupMembership($cgm);
    $updatePerson->setMemberships([$membership]);

    // 6. 実行
    $service->people->updateContact($google_id, $updatePerson, [
        'updatePersonFields' => 'names,organizations,phoneNumbers,emailAddresses,memberships'
    ]);

    // 7. CRM側の同期日時を更新
    $stmt = $pdo->prepare("UPDATE crm_contact SET google_last_sync = NOW() WHERE id = ?");
    $stmt->execute([$crm_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}