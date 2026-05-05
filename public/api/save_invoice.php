<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

/**
 * 請求書データ登録・更新およびファイルアップロード処理
 * 
 * 修正点：
 * 1. 命名規則から余計なテーブル名（invoice_out/in）を削除
 * 2. プロジェクトIDがPOSTに含まれない場合のフォールバック処理を強化
 * 3. ファイル名のセパレータを統一し、可読性を向上
 */

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $id             = $_POST['id'] ?? null;
    $project_id     = $_POST['project_id'] ?? null;
    $type           = $_POST['type'] ?? ''; // 'out' (受注/売上) or 'in' (発注/支払)
    $work_month     = $_POST['work_month'] ?? '';
    $invoice_number = $_POST['invoice_number'] ?? 'no-number';

    $table = ($type === 'out') ? 'invoice_out' : 'invoice_in';
    $foreign_key = ($type === 'out') ? 'order_in_id' : 'order_out_id';

    // 既存レコード更新時にproject_idが渡されない場合に備え、DBから取得
    if ($id && !$project_id) {
        $stmt_project = $pdo->prepare("
            SELECT oi.project_id 
            FROM {$table} t
            JOIN " . ($type === 'out' ? 'order_in' : 'order_out') . " oi ON t.{$foreign_key} = oi.id
            WHERE t.id = ?
        ");
        $stmt_project->execute([$id]);
        $project_id = $stmt_project->fetchColumn();
    }

    // 新規登録時：プロジェクトIDから対応する注文ID（外部キー）を取得
    if (!$id) { 
        $ref_table = ($type === 'out') ? 'order_in' : 'order_out';
        $stmt_ref = $pdo->prepare("SELECT id FROM {$ref_table} WHERE project_id = ?");
        $stmt_ref->execute([$project_id]);
        $target_order_id = $stmt_ref->fetchColumn();

        if (!$target_order_id) {
            throw new Exception(($type === 'out' ? '受注' : '発注') . "情報が登録されていないため、請求データを登録できません。");
        }
    }

    // 1. DB更新用データの準備
    $data = [
        'worker_name'    => $_POST['worker_name'] ?? '',
        'time_range'     => $_POST['time_range'] ?? '',
        'work_hours'     => $_POST['work_hours'] ?? '',
        'invoice_number' => $invoice_number,
        'amount'         => (int)($_POST['amount'] ?? 0),
        'amount_tax'     => (int)($_POST['amount_tax'] ?? 0),
        'cost'           => (int)($_POST['cost'] ?? 0),
        'cost_tax'       => (int)($_POST['cost_tax'] ?? 0),
        'total'          => (int)($_POST['total'] ?? 0)
    ];

    if ($id) {
        // --- UPDATE ---
        $sql = "UPDATE {$table} SET 
                    worker_name = :worker_name, time_range = :time_range, work_hours = :work_hours,
                    invoice_number = :invoice_number, amount = :amount, amount_tax = :amount_tax,
                    cost = :cost, cost_tax = :cost_tax, total = :total
                WHERE id = :id";
        $params = $data;
        $params['id'] = $id;
        $current_invoice_id = $id;
    } else {
        // --- INSERT ---
        $sql = "INSERT INTO {$table} (
                    {$foreign_key}, work_month, worker_name, time_range, work_hours, 
                    invoice_number, amount, amount_tax, cost, cost_tax, total
                ) VALUES (
                    :foreign_id, :work_month, :worker_name, :time_range, :work_hours, 
                    :invoice_number, :amount, :amount_tax, :cost, :cost_tax, :total
                )";
        $params = $data;
        $params['foreign_id'] = $target_order_id;
        $params['work_month'] = $work_month;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if (!$id) {
        $current_invoice_id = $pdo->lastInsertId();
    }

    // 2. ファイル処理（命名規則の最適化）
    if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] === UPLOAD_ERR_OK) {
        $base_upload_path = "/home/sbt-inc/www/sales/upload/{$table}/";
        if (!is_dir($base_upload_path)) mkdir($base_upload_path, 0755, true);

        // 新・命名規則: プロジェクトID(5)-請求レコードID(5)-請求番号.拡張子
        // 例: 00001_00015_YSK-4221601.pdf
        $p_id_pad = str_pad($project_id ?? 0, 5, '0', STR_PAD_LEFT);
        $i_id_pad = str_pad($current_invoice_id, 5, '0', STR_PAD_LEFT);
        
        // ファイル名に使用できない文字を排除
        $clean_invoice_num = preg_replace('/[\\/:*?"<>|]/', '', $invoice_number);
        $ext = pathinfo($_FILES['invoice_file']['name'], PATHINFO_EXTENSION);
        
        // --- 修正箇所：$tableを削除しアンダースコアで連結 ---
        $new_filename = "{$p_id_pad}_{$i_id_pad}_{$clean_invoice_num}.{$ext}";
        
        $target_path = $base_upload_path . $new_filename;
        $db_path = "upload/{$table}/" . $new_filename;

        if (move_uploaded_file($_FILES['invoice_file']['tmp_name'], $target_path)) {
            $stmt_file = $pdo->prepare("UPDATE {$table} SET file_path = ? WHERE id = ?");
            $stmt_file->execute([$db_path, $current_invoice_id]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'id' => $current_invoice_id]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}