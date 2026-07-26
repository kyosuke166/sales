<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('無効なリクエストです。');
    }

    $pdo = get_db_connection();

    // フォームデータの受け取り
    $action = $_POST['action'] ?? 'draft'; // 'draft' または 'sent'
    $type = $_POST['type'] ?? 'free';
    $source_id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $history_id = !empty($_POST['history_id']) ? (int)$_POST['history_id'] : null;

    $subject = trim($_POST['subject'] ?? '');
    $intro = trim($_POST['intro'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $signature = trim($_POST['signature'] ?? '');
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

    $senddate = date('Y-m-d H:i:s'); // DB制約でNOT NULLのため常に現在日時をセット（一覧ではstatusで判断）

    // 既に履歴IDがある場合はUPDATE（上書き）、なければINSERT（新規）
    if ($history_id > 0) {
        $sql = "UPDATE sendmail_history SET 
                senddate = :senddate,
                anken_id = :anken_id,
                engineer_id = :engineer_id,
                subject = :subject,
                intro = :intro,
                body = :body,
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
            ':target' => $sendto,
            ':status' => $action,
            ':id' => $history_id
        ]);
        $target_history_id = $history_id;
    } else {
        $sql = "INSERT INTO sendmail_history 
                (senddate, anken_id, engineer_id, subject, intro, body, target, status) 
                VALUES 
                (:senddate, :anken_id, :engineer_id, :subject, :intro, :body, :target, :status)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':senddate' => $senddate,
            ':anken_id' => $anken_id,
            ':engineer_id' => $engineer_id,
            ':subject' => $subject,
            ':intro' => $intro,
            ':body' => $body,
            ':target' => $sendto,
            ':status' => $action
        ]);
        $target_history_id = $pdo->lastInsertId();
    }

    // ============================================
    // ▼ ここに実際のメール送信処理を組み込みます ▼
    // ============================================
    if ($action === 'sent') {
        // 例: AWS SES, SendGrid, SMTP等へのリクエスト
        // TODO: 本番用の送信ロジック
    }

    echo json_encode(['status' => 'success', 'history_id' => $target_history_id], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}