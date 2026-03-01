<?php
require_once __DIR__ . '/../../auth_check.php';

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    // 1. ベースとなる句
    $fromSql = " FROM crm_contact c LEFT JOIN crm_company co ON c.company_id = co.id";
    $whereConditions = [];
    $params = [];

    // 検索条件がある場合（スペース区切りAND検索対応）
    if ($search !== '') {
        // 全角スペースを半角に変換してから分割
        $keywords = preg_split('/\s+/u', mb_convert_kana($search, 's'));
        
        foreach ($keywords as $i => $word) {
            $pName = ":q{$i}";
            // 各単語が、いずれかのカラムに含まれていることを条件にする
            $whereConditions[] = "(
                co.company_name LIKE $pName 
                OR co.company_kana LIKE $pName 
                OR c.last_name LIKE $pName 
                OR c.first_name LIKE $pName 
                OR c.last_kana LIKE $pName 
                OR c.first_kana LIKE $pName 
                OR c.email LIKE $pName 
                OR c.email_personal LIKE $pName 
                OR c.position LIKE $pName 
                OR c.memo LIKE $pName 
                OR CAST(c.send_error AS CHAR) LIKE $pName
            )";
            $params[$pName] = "%{$word}%";
        }
    }

    $whereSql = "";
    if (count($whereConditions) > 0) {
        $whereSql = " WHERE " . implode(" AND ", $whereConditions);
    }

    // 2. 総件数取得（LIMITなしの状態）
    $countStmt = $pdo->prepare("SELECT COUNT(*)" . $fromSql . $whereSql);
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetchColumn();

    // 3. データ取得（LIMITあり）
    $dataSql = "SELECT c.*, co.company_name, co.company_kana, co.url" 
             . $fromSql . $whereSql
             . " ORDER BY 
                    co.company_kana ASC, 
                    c.company_id ASC, 
                    c.sort ASC, 
                    c.email ASC, 
                    c.id DESC 
                LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($dataSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. 結果を出力（フロントの updateSearchCount(result.total) に対応）
    echo json_encode([
        'status' => 'success',
        'total' => (int)$totalCount,
        'data' => $contacts,
        'user_name' => isset($current_user_name) ? $current_user_name : 'Admin'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}