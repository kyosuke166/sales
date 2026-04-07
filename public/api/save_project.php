<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // --- 1. 基本データの登録 ---
    $id = $_POST['id'] ?? null;
    $project_name = $_POST['project_name'] ?? '';
    $memo         = $_POST['memo'] ?? '';
    
    // projectsテーブル
    if ($id) {
        $stmt = $pdo->prepare("UPDATE projects SET project_name = :name, memo = :memo, updated = NOW() WHERE id = :id");
        $stmt->execute([':name' => $project_name, ':memo' => $memo, ':id' => $id]);
        $project_id = $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO projects (project_name, memo, status, created) VALUES (:name, :memo, 'active', NOW())");
        $stmt->execute([':name' => $project_name, ':memo' => $memo]);
        $project_id = $pdo->lastInsertId();
    }

    // order_inテーブル（一旦ファイルパス抜きで登録/更新してIDを得る）
    $order_params = [
        ':p_id'         => $project_id,
        ':c_id'         => $_POST['company_id'] ?: null,
        ':contact_id'   => $_POST['contact_id'] ?: null,
        ':worker'       => $_POST['worker_name'] ?? '',
        ':order_num'    => $_POST['order_number'] ?? '',
        ':start'        => $_POST['start_date'] ?: null,
        ':end'          => $_POST['end_date'] ?: null,
        ':amt'          => ($_POST['amount'] === '' || $_POST['amount'] === null) ? 0 : $_POST['amount'],
        ':amt'          => $_POST['amount'] ?? 0,
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
        
        // 更新時は既存のIDを取得
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

    // --- 2. ファイル名の確定と保存 ---
    if (isset($_FILES['order_file']) && $_FILES['order_file']['error'] === UPLOAD_ERR_OK) {
        // フォルダを in/ に分ける
        $upload_dir = __DIR__ . '/../uploads/orders/in/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        // 規則: project_id(5桁)-order_in_id(3桁)_注文番号.pdf
        $p_id_papped = str_pad($project_id, 5, '0', STR_PAD_LEFT);
        $o_id_papped = str_pad($order_in_id, 3, '0', STR_PAD_LEFT);
        $clean_order_num = preg_replace('/[\\/:*?"<>|]/', '', $_POST['order_number'] ?? 'no-number'); // ファイル名禁止文字除外
        
        $extension = pathinfo($_FILES['order_file']['name'], PATHINFO_EXTENSION);
        $new_filename = "{$p_id_papped}-{$o_id_papped}_{$clean_order_num}.{$extension}";
        
        $target_path = $upload_dir . $new_filename;
        $db_path = 'uploads/orders/in/' . $new_filename;

        if (move_uploaded_file($_FILES['order_file']['tmp_name'], $target_path)) {
            // パスをDBに反映
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