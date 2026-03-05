<?php
// api/save_company.php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

try {
    // 1. DB接続を確実に取得する
    $pdo = get_db_connection(); 
    if (!$pdo) {
        throw new Exception("Database connection not found.");
    }
    
    // 2. IDの受け取り名を JS に合わせる (comp_id)
    $id = $_POST['comp_id'] ?? $_POST['id'] ?? ''; 
    
    // crm_companyのテーブル構造
    $fields = [
        'company_name', 
        'company_kana', 
        'establish', 
        'company_group',
        'postal_code', 
        'prefecture', 
        'city', 
        'address', 
        'tel', 
        'fax', 
        'url', 
        'memo'
    ];
    
    $data = [];
    foreach ($fields as $f) {
        $data[$f] = $_POST[$f] ?? null;
    }

    if ($id) {
        // UPDATE処理
        $setPart = [];
        $values = [];
        
        foreach ($fields as $f) {
            // 【重要】POSTデータの中にキーが存在する場合のみ更新対象にする
            if (array_key_exists($f, $_POST)) {
                $setPart[] = "{$f} = ?";
                $values[] = $_POST[$f];
            }
        }

        if (empty($setPart)) {
            throw new Exception("更新するデータがありません。");
        }

        $values[] = $id;
        $sql = "UPDATE crm_company SET " . implode(', ', $setPart) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        $rowCount = $stmt->rowCount();
        $message = ($rowCount > 0) ? '更新しました' : 'データに変更がないか、IDが見つかりません';
    } else {
        // INSERT処理
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO crm_company (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        $id = $pdo->lastInsertId();
        $message = '登録しました';
    }

    echo json_encode([
        'status' => 'success', 
        'message' => $message,
        'id' => $id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}