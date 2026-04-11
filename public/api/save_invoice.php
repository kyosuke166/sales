<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $id         = $_POST['id'] ?? null;
    $project_id = $_POST['project_id'] ?? null;
    $type       = $_POST['type'] ?? ''; 
    $work_month = $_POST['work_month'] ?? '';

    $table = ($type === 'out') ? 'invoice_out' : 'invoice_in';
    $foreign_key = ($type === 'out') ? 'order_in_id' : 'order_out_id';

    // 1. ファイル処理 (ここは共通)
    $file_path = $_POST['existing_file_path'] ?? null;
    if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . "/../uploads/invoices/{$type}/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = pathinfo($_FILES['invoice_file']['name'], PATHINFO_EXTENSION);
        $new_filename = sprintf("%05d-%s-%s.%s", $project_id, str_replace('-', '', $work_month), ($type === 'out' ? 'INV' : 'PAY'), $ext);
        if (move_uploaded_file($_FILES['invoice_file']['tmp_name'], $upload_dir . $new_filename)) {
            $file_path = "uploads/invoices/{$type}/" . $new_filename;
        }
    }

    // 2. 共通のデータ配列
    $data = [
        'worker_name'    => $_POST['worker_name'] ?? '',
        'time_range'     => $_POST['time_range'] ?? '',
        'work_hours'     => $_POST['work_hours'] ?? '',
        'invoice_number' => $_POST['invoice_number'] ?? '',
        'amount'         => (int)($_POST['amount'] ?? 0),
        'amount_tax'     => (int)($_POST['amount_tax'] ?? 0),
        'cost'           => (int)($_POST['cost'] ?? 0),
        'cost_tax'       => (int)($_POST['cost_tax'] ?? 0),
        'total'          => (int)($_POST['total'] ?? 0),
        'file_path'      => $file_path
    ];

    if ($id) {
        // --- UPDATE の場合 ---
        // SQLの中に登場するトークンだけを $params に入れる
        $sql = "UPDATE {$table} SET 
                    worker_name = :worker_name, 
                    time_range = :time_range, 
                    work_hours = :work_hours,
                    invoice_number = :invoice_number, 
                    amount = :amount, 
                    amount_tax = :amount_tax,
                    cost = :cost, 
                    cost_tax = :cost_tax, 
                    total = :total, 
                    file_path = :file_path
                WHERE id = :id";
        
        $params = [];
        foreach ($data as $key => $val) { $params[":$key"] = $val; }
        $params[':id'] = $id; // WHERE句のidを追加

    } else {
        // --- INSERT の場合 ---
        $sql = "INSERT INTO {$table} (
                    {$foreign_key}, work_month, worker_name, time_range, work_hours, 
                    invoice_number, amount, amount_tax, cost, cost_tax, total, file_path
                ) VALUES (
                    :foreign_id, :work_month, :worker_name, :time_range, :work_hours, 
                    :invoice_number, :amount, :amount_tax, :cost, :cost_tax, :total, :file_path
                )";

        $params = [
            ':foreign_id' => $project_id,
            ':work_month' => $work_month
        ];
        foreach ($data as $key => $val) { $params[":$key"] = $val; }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params); // SQLと$paramsの内容が完全に一致するようになります
    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}