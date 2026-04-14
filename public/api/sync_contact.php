<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

$pdo = get_db_connection();
$mode = $_POST['mode'] ?? 'preview'; // preview または execute

// --- モード1：プレビュー (SELECT) ---
if ($mode === 'preview') {
    try {
        // 交流会参加リストの候補（会社名＋氏名で照合）
        $sql_event = "SELECT 
                        ep.id,
                        ep.email, 
                        ep.participant_name as name, 
                        ep.company_name as company,
                        e.event_date,
                        e.event_number,
                        e.event_name,
                        e.area,
                        ep.entry_number,
                        c.id AS crm_contact_id, 
                        CONCAT(IFNULL(c.last_name,''), IFNULL(c.first_name,'')) AS crm_name, 
                        cp.company_name AS crm_company,
                        c.email AS crm_email
                    FROM events_participant ep
                    JOIN events e ON ep.event_id = e.id
                    /* 担当者の氏名一致 (スペース無視) */
                    JOIN crm_contact c ON (
                        REPLACE(REPLACE(ep.participant_name, ' ', ''), '　', '') = REPLACE(REPLACE(CONCAT(IFNULL(c.last_name,''), IFNULL(c.first_name,'')), ' ', ''), '　', '')
                    )
                    /* 会社名は LEFT JOIN にして、一致しなくても担当者さえいれば出す */
                    LEFT JOIN crm_company cp ON c.company_id = cp.id
                    WHERE ep.contact_id IS NULL 
                    AND c.deleted IS NULL
                    /* かつ、名簿の会社名とCRMの会社名が一致するものに絞り込む（ここもスペース無視） */
                    AND REPLACE(REPLACE(ep.company_name, ' ', ''), '　', '') = REPLACE(REPLACE(IFNULL(cp.company_name, ''), ' ', ''), '　', '')
                    ORDER BY ep.company_name ASC, ep.participant_name ASC
                    LIMIT 100";

        // 漏洩メールの候補
        $sql_leak = "SELECT 
                        lc.id, -- 個別のIDが必要
                        lc.email, 
                        lc.raw_person as name, 
                        lc.raw_company as company,
                        lc.source_date,
                        lc.source_company,
                        lc.seq_number,
                        c.id AS crm_contact_id, 
                        CONCAT(IFNULL(c.last_name,''), IFNULL(c.first_name,'')) AS crm_name, 
                        cp.company_name AS crm_company
                    FROM leaked_contacts lc
                    JOIN crm_contact c ON lc.email = c.email
                    LEFT JOIN crm_company cp ON c.company_id = cp.id
                    WHERE lc.contact_id IS NULL 
                    ORDER BY lc.source_date DESC, lc.id DESC 
                    LIMIT 100";

        // 統計データの取得
        // 交流会データ
        $stats_event = $pdo->query("SELECT 
            COUNT(*) as total, 
            COUNT(company_id) as linked_co, 
            COUNT(contact_id) as linked_user 
            FROM events_participant")->fetch(PDO::FETCH_ASSOC);

        // 外部収集データ
        $stats_leak = $pdo->query("SELECT 
            COUNT(*) as total, 
            COUNT(company_id) as linked_co, 
            COUNT(contact_id) as linked_user 
            FROM leaked_contacts")->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'event' => $pdo->query($sql_event)->fetchAll(PDO::FETCH_ASSOC),
            'leak' => $pdo->query($sql_leak)->fetchAll(PDO::FETCH_ASSOC),
            'stats' => [
                'event' => $stats_event,
                'leak' => $stats_leak
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- モード2：個別実行 (UPDATE) ---
if ($mode === 'execute_single') {
    $id = $_POST['id'] ?? '';
    $type = $_POST['type'] ?? ''; // 'event' か 'leak'
    
    if (!$id || !$type) {
        echo json_encode(['success' => false, 'message' => 'パラメータ不足']);
        exit;
    }

    try {
        $pdo = get_db_connection();
        
        if ($type === 'event') {
            $sql = "UPDATE events_participant ep 
                    JOIN crm_contact c ON (
                        REPLACE(REPLACE(ep.participant_name, ' ', ''), '　', '') = REPLACE(REPLACE(CONCAT(IFNULL(c.last_name,''), IFNULL(c.first_name,'')), ' ', ''), '　', '')
                    )
                    JOIN crm_company cp ON c.company_id = cp.id AND (
                        REPLACE(REPLACE(ep.company_name, ' ', ''), '　', '') = REPLACE(REPLACE(cp.company_name, ' ', ''), '　', '')
                    )
                    SET 
                        ep.contact_id = c.id, 
                        ep.company_id = c.company_id,
                        ep.email = CASE WHEN ep.email IS NULL OR ep.email = '' THEN c.email ELSE ep.email END
                    WHERE ep.id = ? AND ep.contact_id IS NULL";
        } else {
            $sql = "UPDATE leaked_contacts lc 
                    JOIN crm_contact c ON lc.email = c.email 
                    SET lc.contact_id = c.id, lc.company_id = c.company_id 
                    WHERE lc.id = ? AND lc.contact_id IS NULL";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}