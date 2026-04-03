<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

$pdo = get_db_connection();
$mode = $_POST['mode'] ?? 'preview'; // preview または execute

// --- モード1：プレビュー (SELECT) ---
if ($mode === 'preview') {
    try {
        // 交流会参加リストの候補
        $sql_event = "SELECT 
                        ep.id, -- 個別のIDが必要
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
                        cp.company_name AS crm_company
                    FROM events_participant ep
                    JOIN events e ON ep.event_id = e.id
                    JOIN crm_contact c ON ep.email = c.email
                    LEFT JOIN crm_company cp ON c.company_id = cp.id
                    WHERE ep.contact_id IS NULL 
                    ORDER BY e.event_date DESC, ep.id DESC 
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

        echo json_encode([
            'success' => true,
            'event' => $pdo->query($sql_event)->fetchAll(PDO::FETCH_ASSOC),
            'leak' => $pdo->query($sql_leak)->fetchAll(PDO::FETCH_ASSOC)
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
                    JOIN crm_contact c ON ep.email = c.email 
                    SET ep.contact_id = c.id, ep.company_id = c.company_id 
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