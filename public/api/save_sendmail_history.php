<?php
require_once 'auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('無効なリクエストです。');
    }

    $pdo = get_db_connection();

    // フォームデータの受け取り
    $action = $_POST['action'] ?? 'draft'; // 画面からは 'draft' または 'sent' が来る
    
    // 画面から 'sent' が来たら、DBには 'waiting(順番待ち)' として登録する
    $db_status = ($action === 'sent') ? 'waiting' : 'draft';
    
    $type = $_POST['type'] ?? 'free';
    $source_id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $history_id = !empty($_POST['history_id']) ? (int)$_POST['history_id'] : null;

    $subject = trim($_POST['subject'] ?? '');
    $intro = trim($_POST['intro'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $full_body = trim($_POST['full_body'] ?? '');
    $sendto = trim($_POST['sendto'] ?? '');

    if ($subject === '') {
        throw new Exception('件名が入力されていません。');
    }

    // 種別に応じて紐付けるIDを設定
    $anken_id = null;
    $engineer_id = null;
    if ($type === 'anken') {
        $anken_id = $source_id;
    } elseif ($type === 'engineer') {
        $engineer_id = $source_id;
    }

    $senddate = date('Y-m-d H:i:s'); 

    // 既存レコードのステータスを確認し、UPDATEするかINSERTするかを判定
    $is_update = false;
    $old_record = null;
    if ($history_id > 0) {
        $stmtCheck = $pdo->prepare("SELECT * FROM sendmail_history WHERE id = :id");
        $stmtCheck->execute([':id' => $history_id]);
        $old_record = $stmtCheck->fetch();
        
        // 既存が「下書き」の場合のみ、上書き(UPDATE)を許可する
        if ($old_record && $old_record['status'] === 'draft') {
            $is_update = true;
        }
    }

    if ($is_update) {
        // 【上書き（UPDATE）】下書きを更新する場合
        // ※anken_id/engineer_idが抜けていれば、古いレコードの値を引き継ぐ
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
        // 【新規（INSERT）】新規作成、または「過去の配信済履歴」からの再配信の場合
        
        // 再配信（history_id指定かつ既存が下書き以外）で、今回のリクエストでanken/engineerが抜けている場合、
        // 元の履歴からanken_idやengineer_idを自動で引き継ぐ
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

    // ============================================
    // 実際のメール送信処理（バックグラウンド起動）
    // ============================================
    if ($db_status === 'waiting') {
        $cron_path = __DIR__ . '/cron_sendmail.php';
        
        // エラーの証拠を残すためのログファイル
        //$log_path = __DIR__ . '/exec_error.log';

        // OSのコマンド(exec)を使ってPHPを非同期で実行する
        // > /dev/null 2>&1 & により、処理の完了を待たずに次の行へ進む
        //exec("php {$cron_path} > /dev/null 2>&1 &");
        //exec("/usr/local/bin/php {$cron_path} > /dev/null 2>&1 &");
        //exec("/usr/local/php/default/bin/php {$cron_path} >> {$log_path} 2>&1 &");
        //exec("/usr/local/php/default/bin/php {$cron_path} > /dev/null 2>&1 &");
        exec("/usr/bin/php {$cron_path} > /dev/null 2>&1 &");
    }

    // バックグラウンドで実行開始後、画面には一瞬でレスポンスを返す！
    echo json_encode(['status' => 'success', 'history_id' => $target_history_id], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}