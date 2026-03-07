<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();

    // IDがある場合はUPDATE、ない場合はINSERT
    $id = !empty($_POST['crm_company_id']) ? (int)$_POST['crm_company_id'] : null;

    /**
     * 入力値を NULL または 文字列に整形する関数
     */
    $toNull = function($key) {
        $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        return ($val === '') ? null : $val;
    };

    // 各項目の取得（カラム名に crm_company_ を付与したキーを想定）
    $company_name  = $toNull('crm_company_name');
    $company_kana  = $toNull('crm_company_kana');
    $establish = $toNull('crm_company_establish');
    if ($establish) {
        // 1. 全角数字を半角に変換
        $establish = mb_convert_kana($establish, 'n', 'UTF-8');
        
        // 2. 「年」「月」「-」「.」などをすべてスラッシュに置き換える
        $establish = preg_replace('/[年月日\-.]/u', '/', $establish);
        
        // 3. 数字とスラッシュ以外の不要な記号を削除
        $establish = preg_replace('/[^0-9\/]/', '', $establish);
        
        // 4. 連続するスラッシュを1つにまとめ、前後のスラッシュを削る
        $establish = trim(preg_replace('/\/+/', '/', $establish), '/');

        // 5. 「yyyy/m」や「yyyy/m/d」の形式だった場合、月・日を2桁に補完する (例: 2018/4 -> 2018/04)
        $parts = explode('/', $establish);
        if (count($parts) >= 2) {
            // 月を2桁にする
            $parts[1] = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            if (isset($parts[2])) {
                // 日があれば2桁にする
                $parts[2] = str_pad($parts[2], 2, '0', STR_PAD_LEFT);
            }
            $establish = implode('/', $parts);
        }
    }
    $company_group = $toNull('crm_company_group');
    $url           = $toNull('crm_company_url');
    $postal_code   = $toNull('crm_company_postal_code');
    $prefecture    = $toNull('crm_company_prefecture');
    $city          = $toNull('crm_company_city');
    $address       = $toNull('crm_company_address');
    $address       = $toNull('crm_company_address');
    $tel           = $toNull('crm_company_tel');
    $fax           = $toNull('crm_company_fax');
    $fax           = $toNull('crm_company_fax');
    $memo          = $toNull('crm_company_memo');
    $status        = $toNull('crm_company_status');

    if ($id) {
        // 更新処理
        $sql = "UPDATE crm_company SET 
                    company_name = :company_name,
                    company_kana = :company_kana,
                    establish = :establish,
                    company_group = :company_group,
                    postal_code = :postal_code,
                    prefecture = :prefecture,
                    city = :city,
                    address = :address,
                    tel = :tel,
                    fax = :fax,
                    url = :url,
                    memo = :memo,
                    status = :status,
                    updated = CURRENT_TIMESTAMP
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    } else {
        // 新規登録処理
        $sql = "INSERT INTO crm_company (
                    company_name, 
                    company_kana, 
                    establish, 
                    company_group, 
                    postal_code, 
                    prefecture, 
                    city, 
                    address, 
                    tel,
                    fax, 
                    url,
                    memo, 
                    status,
                    created, 
                    updated
                ) VALUES (
                    :company_name, 
                    :company_kana, 
                    :establish, 
                    :company_group, 
                    :postal_code, 
                    :prefecture, 
                    :city,
                    :address, 
                    :tel, 
                    :fax, 
                    :url, 
                    :memo, 
                    :status,
                    CURRENT_TIMESTAMP, 
                    CURRENT_TIMESTAMP
                )";
        $stmt = $pdo->prepare($sql);
    }

    // バインド
    $stmt->bindValue(':company_name', $company_name, PDO::PARAM_STR);
    $stmt->bindValue(':company_kana', $company_kana, $company_kana === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':establish', $establish, $establish === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':company_group', $company_group, $company_group === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':postal_code', $postal_code, $postal_code === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':prefecture', $prefecture, $prefecture === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':city', $city, $city === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':address', $address, $address === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':tel', $tel, $tel === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':fax', $fax, $fax === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':url', $url, $url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':memo', $memo, $memo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':status', $status, $status === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => '会社情報を保存しました']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}