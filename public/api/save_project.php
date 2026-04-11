<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $id = $_POST['id'] ?? null;

    // --- 【追加】ステータスのみ更新のショートカット処理 ---
    // project_name がなく、status がある場合は一覧画面からの切り替えとみなす
    if ($id && !isset($_POST['project_name']) && isset($_POST['status'])) {
        $stmt = $pdo->prepare("UPDATE projects SET status = :status, updated = NOW() WHERE id = :id");
        $stmt->execute([
            ':status' => (int)$_POST['status'],
            ':id' => $id
        ]);
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'ステータスを更新しました']);
        exit;
    }

    // --- 以下、通常の登録・更新ロジック ---
    $project_name = $_POST['project_name'] ?? '';
    $memo         = $_POST['memo'] ?? '';
    
    // projectsテーブル
    if ($id) {
        // 更新時は status も一緒に送られてくる可能性があるので含めておく
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
        $stmt = $pdo->prepare("UPDATE projects SET project_name = :name, memo = :memo, status = :status, updated = NOW() WHERE id = :id");
        $stmt->execute([':name' => $project_name, ':memo' => $memo, ':status' => $status, ':id' => $id]);
        $project_id = $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO projects (project_name, memo, status, created) VALUES (:name, :memo, 1, NOW())");
        $stmt->execute([':name' => $project_name, ':memo' => $memo]);
        $project_id = $pdo->lastInsertId();
    }

    // order_inテーブル
    $order_params = [
        ':p_id'         => $project_id,
        ':c_id'         => $_POST['company_id'] ?: null,
        ':contact_id'   => $_POST['contact_id'] ?? $_POST['crm_contact_id'] ?? null,
        ':worker'       => $_POST['worker_name'] ?? '',
        ':order_num'    => $_POST['order_number'] ?? '',
        ':start'        => $_POST['start_date'] ?: null,
        ':end'          => $_POST['end_date'] ?: null,
        ':amt'          => (isset($_POST['amount']) && $_POST['amount'] !== '') ? $_POST['amount'] : 0,
        ':range'        => $_POST['time_range'] ?? '',
        ':site'         => $_POST['payment_site'] ?? ''
    ];

    if ($id) {
        $sql_order = "UPDATE order_in SET 
                      company_id = :c_id, 
                      contact_id = :contact_id, 
                      worker_name = :worker, 
                      order_number = :order_num, 
                      start_date = :start, 
                      end_date = :end, 
                      amount = :amt, 
                      time_range = :range, 
                      payment_site = :site 
                      WHERE project_id = :p_id";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute($order_params);
        
        $stmt_get_id = $pdo->prepare("SELECT id FROM order_in WHERE project_id = :p_id");
        $stmt_get_id->execute([':p_id' => $project_id]);
        $order_in_id = $stmt_get_id->fetchColumn();
    } else {
        $sql_order = "INSERT INTO order_in (project_id, company_id, contact_id, worker_name, order_number, start_date, end_date, amount, time_range, payment_site) 
                      VALUES (:p_id, :c_id, :contact_id, :worker, :order_num, :start, :end, :amt, :range, :site)";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute($order_params);
        $order_in_id = $pdo->lastInsertId();
    }

    // --- ファイル保存ロジック (変更なし) ---
    if (isset($_FILES['order_file']) && $_FILES['order_file']['error'] === UPLOAD_ERR_OK) {
        // 絶対パスによる保存先指定
        $base_upload_path = "/home/sbt-inc/www/sales/upload/order_in/";
        if (!is_dir($base_upload_path)) mkdir($base_upload_path, 0755, true);

        // 命名規則: プロジェクトID(5桁)-受注ID(5桁)_注文番号
        $p_id_padded = str_pad($project_id, 5, '0', STR_PAD_LEFT);
        $o_id_padded = str_pad($order_in_id, 5, '0', STR_PAD_LEFT);
        $clean_order_num = preg_replace('/[\\/:*?"<>|]/', '', $_POST['order_number'] ?? 'no-number');
        
        $extension = pathinfo($_FILES['order_file']['name'], PATHINFO_EXTENSION);
        $new_filename = "{$p_id_padded}-{$o_id_padded}_{$clean_order_num}.{$extension}";
        
        $target_path = $base_upload_path . $new_filename;
        // DBにはWEB公開用の相対パスを保存
        $db_path = 'upload/order_in/' . $new_filename;

        if (move_uploaded_file($_FILES['order_file']['tmp_name'], $target_path)) {
            $stmt_update_file = $pdo->prepare("UPDATE order_in SET file_path = :path WHERE id = :oid");
            $stmt_update_file->execute([':path' => $db_path, ':oid' => $order_in_id]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => '保存しました']);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}