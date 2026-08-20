<?php
require_once 'auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('無効なリクエストです。');
    }

    $pdo = get_db_connection();

    // フォームデータの受け取り ('draft', 'sent', 'scheduled')
    $action = $_POST['action'] ?? 'draft'; 
    
    // ステータスの決定
    if ($action === 'sent') {
        $db_status = 'waiting';
    } elseif ($action === 'scheduled') {
        $db_status = 'scheduled';
    } else {
        $db_status = 'draft';
    }
    
    $type = $_POST['type'] ?? 'free';
    $source_id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $history_id = !empty($_POST['history_id']) ? (int)$_POST['history_id'] : null;

    $subject = trim($_POST['subject'] ?? '');
    $intro = trim($_POST['intro'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $full_body = trim($_POST['full_body'] ?? '');
    $sendto = trim($_POST['sendto'] ?? '');

    // 予約日時の処理
    if ($db_status === 'scheduled' && !empty($_POST['senddate'])) {
        $senddate = str_replace('T', ' ', $_POST['senddate']);
        if (strlen($senddate) === 16) {
            $senddate .= ':00';
        }
    } else {
        $senddate = date('Y-m-d H:i:s');
    }

    if ($subject === '') {
        throw new Exception('件名が入力されていません。');
    }

    $anken_id = null;
    $engineer_id = null;
    if ($type === 'anken') {
        $anken_id = $source_id;
    } elseif ($type === 'engineer') {
        $engineer_id = $source_id;
    }

    // 既存レコードの確認
    $is_update = false;
    $old_record = null;
    if ($history_id > 0) {
        $stmtCheck = $pdo->prepare("SELECT * FROM sendmail_history WHERE id = :id");
        $stmtCheck->execute([':id' => $history_id]);
        $old_record = $stmtCheck->fetch();
        
        // 【修正点】既存のステータが 'draft' または 'scheduled'（未送信の予約）の場合のみ上書き(UPDATE)を許可する
        if ($old_record && in_array($old_record['status'], ['draft', 'scheduled'], true)) {
            $is_update = true;
        }
    }

    if ($is_update) {
        if (empty($anken_id) && empty($engineer_id) && $old_record) {
            $anken_id = $old_record['anken_id'];
            $engineer_id = $old_record['engineer_id'];
        }
        $sql = "UPDATE sendmail_history SET 
                senddate = :senddate,
                anken_id = :anken_id,
                engineer_id = :engineer_id,
                subject = :subject,
                intro = :intro,
                body = :body,
                full_body = :full_body,
                target = :target,
                status = :status
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':senddate' => $senddate,
            ':anken_id' => $anken_id,
            ':engineer_id' => $engineer_id,
            ':subject' => $subject,
            ':intro' => $intro,
            ':body' => $body,
            ':full_body' => $full_body,
            ':target' => $sendto,
            ':status' => $db_status,
            ':id' => $history_id
        ]);
        $target_history_id = $history_id;
    } else {
        if ($old_record && empty($anken_id) && empty($engineer_id)) {
            $anken_id = $old_record['anken_id'];
            $engineer_id = $old_record['engineer_id'];
        }

        $sql = "INSERT INTO sendmail_history 
                (senddate, anken_id, engineer_id, subject, intro, body, full_body, target, status) 
                VALUES 
                (:senddate, :anken_id, :engineer_id, :subject, :intro, :body, :full_body, :target, :status)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':senddate' => $senddate,
            ':anken_id' => $anken_id,
            ':engineer_id' => $engineer_id,
            ':subject' => $subject,
            ':intro' => $intro,
            ':body' => $body,
            ':full_body' => $full_body,
            ':target' => $sendto,
            ':status' => $db_status
        ]);
        $target_history_id = $pdo->lastInsertId();
    }

    // 即時配信（waiting）の場合のみバックグラウンドでcronをキック
    if ($db_status === 'waiting') {
        $cron_path = __DIR__ . '/cron_sendmail.php';
        exec("/usr/bin/php {$cron_path} > /dev/null 2>&1 &");
    }

    echo json_encode(['status' => 'success', 'history_id' => $target_history_id], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}