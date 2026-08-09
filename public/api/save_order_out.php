<?php
require_once 'auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $project_id   = $_POST['project_id'] ?? null;
    $id           = $_POST['id'] ?? null; // 更新用（あれば）

    if (!$project_id) throw new Exception('案件IDが指定されていません');

    // order_outテーブルのパラメータ
    $params = [
        ':p_id'         => $project_id,
        ':c_id'         => $_POST['company_id'] ?: null,
        ':contact_id'   => $_POST['contact_id'] ?: null,
        ':worker'       => $_POST['worker_name'] ?? '',
        ':order_num'    => $_POST['order_number'] ?? '',
        ':start'        => $_POST['start_date'] ?: null,
        ':end'          => $_POST['end_date'] ?: null,
        ':amt'          => $_POST['amount'] ?: 0,
        ':range'        => $_POST['time_range'] ?? '',
        ':site'         => $_POST['payment_site'] ?? ''
    ];

    if ($id) {
        // UPDATE文には :p_id が登場しない
        $sql = "UPDATE order_out SET company_id = :c_id, contact_id = :contact_id, worker_name = :worker, 
                order_number = :order_num, start_date = :start, end_date = :end, amount = :amt, 
                time_range = :range, payment_site = :site WHERE id = :id";
        $params[':id'] = $id;
        // --- UPDATEの時だけ不要な :p_id を消す ---
        unset($params[':p_id']); 
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $order_out_id = $id;
    } else {
        $sql = "INSERT INTO order_out (project_id, company_id, contact_id, worker_name, order_number, start_date, end_date, amount, time_range, payment_site) 
                VALUES (:p_id, :c_id, :contact_id, :worker, :order_num, :start, :end, :amt, :range, :site)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $order_out_id = $pdo->lastInsertId();
    }

    // --- ファイル保存 (発注注文書用フォルダ) ---
    if (isset($_FILES['order_file']) && $_FILES['order_file']['error'] === UPLOAD_ERR_OK) {
        // 絶対パスによる保存先指定
        $base_upload_path = "/home/sbt-inc/www/sales/upload/order_out/";
        if (!is_dir($base_upload_path)) mkdir($base_upload_path, 0755, true);

        // 命名規則: プロジェクトID(5桁)-発注ID(5桁)_注文番号
        $p_id_pad = str_pad($project_id, 5, '0', STR_PAD_LEFT);
        $o_id_pad = str_pad($order_out_id, 5, '0', STR_PAD_LEFT);
        $clean_num = preg_replace('/[\\/:*?"<>|]/', '', $_POST['order_number'] ?? 'no-number');
        
        $ext = pathinfo($_FILES['order_file']['name'], PATHINFO_EXTENSION);
        $new_filename = "{$p_id_pad}-{$o_id_pad}_{$clean_num}.{$ext}";
        
        $target_path = $base_upload_path . $new_filename;
        // DBにはWEB公開用の相対パスを保存
        $db_path = 'upload/order_out/' . $new_filename;

        if (move_uploaded_file($_FILES['order_file']['tmp_name'], $target_path)) {
            $stmt = $pdo->prepare("UPDATE order_out SET file_path = :path WHERE id = :id");
            $stmt->execute([':path' => $db_path, ':id' => $order_out_id]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'id' => $order_out_id]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}