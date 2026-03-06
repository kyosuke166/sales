<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();

    // POSTデータの取得（HTMLのname）
    $id         = !empty($_POST['crm_contact_id']) ? (int)$_POST['crm_contact_id'] : null;
    $company_id = !empty($_POST['crm_contact_company_id']) ? (int)$_POST['crm_contact_company_id'] : null;

    if (!$company_id) {
        throw new Exception('crm_contact_company_id が指定されていません。');
    }

    $toNull = function($key) {
        $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        return ($val === '') ? null : $val;
    };

    // 変数への代入（POSTキー）
    $last_name      = $toNull('crm_contact_last_name');
    $first_name     = $toNull('crm_contact_first_name');
    $last_kana      = $toNull('crm_contact_last_kana');
    $first_kana     = $toNull('crm_contact_first_kana');
    $email          = $toNull('crm_contact_email');
    $email_personal = $toNull('crm_contact_email_personal');
    $line           = $toNull('crm_contact_line');
    $tel            = $toNull('crm_contact_tel');
    $division       = $toNull('crm_contact_division');
    $position       = $toNull('crm_contact_position');
    $memo           = $toNull('crm_contact_memo');
    $send_error     = $toNull('crm_contact_send_error');
    $sort           = isset($_POST['crm_contact_sort']) ? (int)$_POST['crm_contact_sort'] : 3;
    $send_project   = isset($_POST['crm_contact_send_project']) ? (int)$_POST['crm_contact_send_project'] : 0;
    $send_engineer  = isset($_POST['crm_contact_send_engineer']) ? (int)$_POST['crm_contact_send_engineer'] : 0;
    $send_event     = isset($_POST['crm_contact_send_event']) ? (int)$_POST['crm_contact_send_event'] : 0;
    $business_card  = isset($_POST['crm_contact_business_card']) ? (int)$_POST['crm_contact_business_card'] : 0;
    $event_raw      = isset($_POST['crm_contact_event']) ? trim($_POST['crm_contact_event']) : '';
    $event          = ($event_raw === '') ? null : (int)$event_raw;

    if ($id) {
        $sql = "UPDATE crm_contact SET 
                    last_name=:last_name, 
                    first_name=:first_name, 
                    last_kana=:last_kana, 
                    first_kana=:first_kana,
                    sort=:sort, 
                    send_project=:send_project, 
                    send_engineer=:send_engineer, 
                    send_event=:send_event,
                    email=:email, 
                    email_personal=:email_personal, 
                    line=:line, 
                    tel=:tel,
                    division=:division, 
                    position=:position, 
                    event=:event, 
                    business_card=:business_card,
                    memo=:memo, 
                    send_error=:send_error, 
                    updated=CURRENT_TIMESTAMP
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    } else {
        $sql = "INSERT INTO crm_contact (
                    company_id, last_name, 
                    first_name, 
                    last_kana, 
                    first_kana,
                    sort, 
                    send_project, 
                    send_engineer, 
                    send_event,
                    email, 
                    email_personal, 
                    line, 
                    tel,
                    division, 
                    position, 
                    event, business_card, 
                    memo, 
                    send_error, 
                    created, 
                    updated
                ) VALUES (
                    :company_id, 
                    :last_name, 
                    :first_name, 
                    :last_kana, 
                    :first_kana,
                    :sort, 
                    :send_project, 
                    :send_engineer, 
                    :send_event,
                    :email, 
                    :email_personal, 
                    :line, 
                    :tel,
                    :division, 
                    :position, 
                    :event, 
                    :business_card, 
                    :memo, 
                    :send_error, 
                    CURRENT_TIMESTAMP, 
                    CURRENT_TIMESTAMP
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    }

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
    $stmt->bindValue(':sort', $sort, PDO::PARAM_INT);
    $stmt->bindValue(':send_project', $send_project, PDO::PARAM_INT);
    $stmt->bindValue(':send_engineer', $send_engineer, PDO::PARAM_INT);
    $stmt->bindValue(':send_event', $send_event, PDO::PARAM_INT);
    $stmt->bindValue(':business_card', $business_card, PDO::PARAM_INT);
    $stmt->bindValue(':event', $event, $event === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

    $stmt->execute();
    echo json_encode(['status' => 'success', 'message' => '保存しました']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}