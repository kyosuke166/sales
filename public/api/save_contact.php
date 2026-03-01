<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();

    // POSTデータの取得（すべてDBカラム名に統一）
    $id             = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $company_id     = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;

    if (!$company_id) {
        throw new Exception('company_id が指定されていません。');
    }

    /**
     * 入力値を NULL または 文字列に整形する関数
     */
    $toNull = function($key) {
        $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        return ($val === '') ? null : $val;
    };

    // 文字列項目（DB: varchar / text）
    $last_name      = $toNull('last_name');
    $first_name     = $toNull('first_name');
    $last_kana      = $toNull('last_kana');
    $first_kana     = $toNull('first_kana');
    $email          = $toNull('email');
    $email_personal = $toNull('email_personal');
    $line           = $toNull('line');
    $tel            = $toNull('tel');
    $division       = $toNull('division');
    $position       = $toNull('position');
    $memo           = $toNull('memo');
    $send_error     = $toNull('send_error');

    // 数値項目（DB: int / tinyint）
    $sort           = isset($_POST['sort']) ? (int)$_POST['sort'] : 3;
    $send_project   = isset($_POST['send_project']) ? (int)$_POST['send_project'] : 0;
    $send_engineer  = isset($_POST['send_engineer']) ? (int)$_POST['send_engineer'] : 0;
    $send_event     = isset($_POST['send_event']) ? (int)$_POST['send_event'] : 0;
    $business_card  = isset($_POST['business_card']) ? (int)$_POST['business_card'] : 0;
    
    // eventはNULL許容のint型。空文字ならnull、あれば数値にキャスト
    $event_raw      = isset($_POST['event']) ? trim($_POST['event']) : '';
    $event          = ($event_raw === '') ? null : (int)$event_raw;

    if ($id) {
        $sql = "UPDATE crm_contact SET 
                    last_name=:last_name, first_name=:first_name, last_kana=:last_kana, first_kana=:first_kana,
                    sort=:sort, send_project=:send_project, send_engineer=:send_engineer, send_event=:send_event,
                    email=:email, email_personal=:email_personal, line=:line, tel=:tel,
                    division=:division, position=:position, event=:event, business_card=:business_card,
                    memo=:memo, send_error=:send_error, updated=CURRENT_TIMESTAMP
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    } else {
        $sql = "INSERT INTO crm_contact (
                    company_id, last_name, first_name, last_kana, first_kana,
                    sort, send_project, send_engineer, send_event,
                    email, email_personal, line, tel,
                    division, position, event, business_card, memo, send_error, created, updated
                ) VALUES (
                    :company_id, :last_name, :first_name, :last_kana, :first_kana,
                    :sort, :send_project, :send_engineer, :send_event,
                    :email, :email_personal, :line, :tel,
                    :division, :position, :event, :business_card, :memo, :send_error, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    }

    // 文字列項目のバインド
    $stmt->bindValue(':last_name', $last_name, $last_name === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':first_name', $first_name, $first_name === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':last_kana', $last_kana, $last_kana === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':first_kana', $first_kana, $first_kana === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':email_personal', $email_personal, $email_personal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':line', $line, $line === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':tel', $tel, $tel === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':division', $division, $division === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':position', $position, $position === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':memo', $memo, $memo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':send_error', $send_error, $send_error === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    // 数値項目のバインド
    $stmt->bindValue(':sort', $sort, PDO::PARAM_INT);
    $stmt->bindValue(':send_project', $send_project, PDO::PARAM_INT);
    $stmt->bindValue(':send_engineer', $send_engineer, PDO::PARAM_INT);
    $stmt->bindValue(':send_event', $send_event, PDO::PARAM_INT);
    $stmt->bindValue(':business_card', $business_card, PDO::PARAM_INT);

    // event項目のバインド（NULL対応）
    $stmt->bindValue(':event', $event, $event === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

    $stmt->execute();
    echo json_encode(['status' => 'success', 'message' => '保存しました']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}