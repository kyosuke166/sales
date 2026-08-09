<?php
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? ''; // 'anken' or 'engineer'
$target_type = $_GET['target_type'] ?? 'all'; // 'common'(共通), 'personal'(個人), 'all'(総合)
$exclude_company_id = $_GET['exclude_company_id'] ?? null;

// 種別をDBのカラム名にマッピング
if ($type === 'anken') {
    $send_col = 'send_project';
} elseif ($type === 'engineer') {
    $send_col = 'send_engineer';
} else {
    echo json_encode(['status' => 'error', 'message' => '無効な配信種別です']);
    exit;
}

try {
    $pdo = get_db_connection();
    $exclude_group_name = null;
    $resultLines = [];
    
    // 1. 情報元のグループ系列名を取得（自社・同グループへの配信を防ぐため）
    $exclude_conditions = [];
    if (!empty($exclude_company_id) && is_numeric($exclude_company_id)) {
        // 情報元の会社自体を「会社ID」で確実に除外する
        $exclude_conditions[] = "co.id != " . (int)$exclude_company_id;

        // 情報元のグループ系列名を取得して、同じグループの他社も除外する
        $stmt_g = $pdo->prepare("SELECT company_group FROM crm_company WHERE id = ?");
        $stmt_g->execute([$exclude_company_id]);
        $row_g = $stmt_g->fetch();
        if ($row_g && !empty($row_g['company_group'])) {
            $exclude_group_name = $row_g['company_group'];
            $exclude_conditions[] = "(co.company_group != " . $pdo->quote($exclude_group_name) . " OR co.company_group IS NULL OR co.company_group = '')";
        }
    }

    // 2. CRMの対象者を抽出
    $sql = "SELECT c.email, c.last_name, co.company_name 
            FROM crm_contact c
            JOIN crm_company co ON c.company_id = co.id
            WHERE c.{$send_col} = 1 
            AND c.email IS NOT NULL 
            AND c.email != ''
            AND c.deleted IS NULL
            AND co.deleted IS NULL";

    // ターゲット種別による絞り込み (sort: 0=代表, 1=役員, 2=共通, 3=担当)
    if ($target_type === 'common') {
        $sql .= " AND c.sort = 2";
    } elseif ($target_type === 'personal') {
        $sql .= " AND c.sort IN (0, 1, 3)";
    } // 'all'の場合は絞り込みなし

    // 除外条件（情報元と同じグループ系列の会社を除外）
    if (!empty($exclude_conditions)) {
        $sql .= " AND " . implode(" AND ", $exclude_conditions);
    }

    $sql .= " ORDER BY co.company_name ASC, c.sort ASC";
    $stmt = $pdo->query($sql);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    // 3. 交流会参加者テーブルから抽出（send_flg = 1 の未紐付けデータ）
    $sql_event = "SELECT DISTINCT company_name, participant_name, email 
                  FROM events_participant 
                  WHERE send_flg = 1";
    $stmt_event = $pdo->query($sql_event);
    while ($row = $stmt_event->fetch(PDO::FETCH_ASSOC)) {
        $email = trim($row['email']);
        $pName = trim($row['participant_name'] ?? '担当者');
        $cName = trim($row['company_name'] ?? '');
        $resultLines[] = empty($cName) ? "{$email},{$pName}様" : "{$email},{$cName} {$pName}様";
    }

    // 4. 漏洩メールテーブルから抽出（send_name がある未紐付けデータ）
    $sql_leaked = "SELECT DISTINCT email, send_name 
                   FROM leaked_contacts 
                   WHERE send_name IS NOT NULL AND send_error IS NULL";
    $stmt_leaked = $pdo->query($sql_leaked);
    while ($row = $stmt_leaked->fetch(PDO::FETCH_ASSOC)) {
        $email = trim($row['email']);
        $sName = trim($row['send_name'] ?? '担当者');
        $resultLines[] = "{$email},{$sName}様";
    }

    // 重複を排除して返す
    $resultLines = array_values(array_unique($resultLines));

    echo json_encode([
        'status' => 'success',
        'data' => implode("\n", $resultLines),
        'count' => count($resultLines)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}