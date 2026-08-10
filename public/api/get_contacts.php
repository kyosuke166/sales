<?php
require_once 'auth_check.php';

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $company_search = isset($_GET['company_search']) ? trim($_GET['company_search']) : '';
    $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    $contacts_only = isset($_GET['contacts']) && $_GET['contacts'] === '1';

    if ($company_search !== '') {
        $keywords = preg_split('/\s+/u', mb_convert_kana($company_search, 's'));
        $where = [];
        $params = [];
        foreach ($keywords as $i => $word) {
            $param = ':company_search' . $i;
            $where[] = "(company_name LIKE $param OR company_kana LIKE $param)";
            $params[$param] = "%{$word}%";
        }
        $sql = 'SELECT id, company_name, company_kana, status FROM crm_company';
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY company_kana ASC, company_name ASC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', min($limit, 50), PDO::PARAM_INT);
        $stmt->execute();

        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([ 'status' => 'success', 'data' => $companies ]);
        exit;
    }

    if ($company_id > 0 && $contacts_only) {
        $whereConditions = ['c.company_id = :company_id'];
        $params = [':company_id' => $company_id];
        if ($search !== '') {
            $keywords = preg_split('/\s+/u', mb_convert_kana($search, 's'));
            foreach ($keywords as $i => $word) {
                $param = ":q{$i}";
                $whereConditions[] = "(
                    c.last_name LIKE $param
                    OR c.first_name LIKE $param
                    OR c.last_kana LIKE $param
                    OR c.first_kana LIKE $param
                    OR c.email LIKE $param
                    OR c.email_personal LIKE $param
                    OR c.tel LIKE $param
                    OR c.division LIKE $param
                    OR c.position LIKE $param
                )";
                $params[$param] = "%{$word}%";
            }
        }
        $whereSql = ' WHERE ' . implode(' AND ', $whereConditions);
        $stmt = $pdo->prepare(
            'SELECT id, company_id, last_name, first_name, last_kana, first_kana, email, division, position'
            . ' FROM crm_contact c' . $whereSql
            . ' ORDER BY sort ASC, last_name ASC, first_name ASC'
            . ' LIMIT :limit'
        );
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', min($limit, 100), PDO::PARAM_INT);
        $stmt->execute();

        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([ 'status' => 'success', 'data' => $contacts ]);
        exit;
    }

    // 1. ベースとなる句
    //$fromSql = " FROM crm_contact c LEFT JOIN crm_company co ON c.company_id = co.id";
    $fromSql = " FROM crm_company co LEFT JOIN crm_contact c ON co.id = c.company_id";
    $whereConditions = [];
    $params = [];

    // 検索条件がある場合
    if ($search !== '') {
        $keywords = preg_split('/\s+/u', mb_convert_kana($search, 's'));
        foreach ($keywords as $i => $word) {
            $pName = ":q{$i}";
            $whereConditions[] = "(
                co.company_name LIKE $pName 
                OR co.company_kana LIKE $pName 
                OR co.company_group LIKE $pName 
                OR co.prefecture LIKE $pName 
                OR co.city LIKE $pName 
                OR co.address LIKE $pName 
                OR co.tel LIKE $pName 
                OR co.fax LIKE $pName 
                OR co.url LIKE $pName 
                OR co.memo LIKE $pName 
                OR c.last_name LIKE $pName 
                OR c.first_name LIKE $pName 
                OR c.last_kana LIKE $pName 
                OR c.first_kana LIKE $pName 
                OR c.email LIKE $pName 
                OR c.email_personal LIKE $pName 
                OR c.tel LIKE $pName 
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

    // 2. 総件数取得（担当者の延べ人数）
    $countStmt = $pdo->prepare("SELECT COUNT(co.id) " . $fromSql . $whereSql);
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetchColumn();

    // ユニークな会社数（検索条件に合致する会社が何社あるか）
    $companyCountStmt = $pdo->prepare("SELECT COUNT(DISTINCT co.id) " . $fromSql . $whereSql);
    foreach ($params as $key => $val) {
        $companyCountStmt->bindValue($key, $val);
    }
    $companyCountStmt->execute();
    $totalCompanies = $companyCountStmt->fetchColumn();

    // 3. データ取得（カラム名の競合を防ぐため「テーブル名_カラム名」で取得）
    $dataSql = "SELECT 
                    /* 担当者情報 (crm_contact) */
                    c.id AS crm_contact_id,
                    c.company_id AS crm_contact_company_id,
                    c.last_name AS crm_contact_last_name,
                    c.first_name AS crm_contact_first_name,
                    c.last_kana AS crm_contact_last_kana,
                    c.first_kana AS crm_contact_first_kana,
                    c.sort AS crm_contact_sort,
                    c.send_project AS crm_contact_send_project,
                    c.send_engineer AS crm_contact_send_engineer,
                    c.send_event AS crm_contact_send_event,
                    c.email AS crm_contact_email,
                    c.email_personal AS crm_contact_email_personal,
                    c.line AS crm_contact_line,
                    c.tel AS crm_contact_tel,
                    c.division AS crm_contact_division,
                    c.position AS crm_contact_position,
                    c.event AS crm_contact_event,
                    c.business_card AS crm_contact_business_card,
                    c.memo AS crm_contact_memo,
                    c.send_error AS crm_contact_send_error,
                    c.updated AS crm_contact_updated,

                    /* 会社情報 (crm_company) */
                    co.id AS crm_company_id,
                    co.company_name AS crm_company_company_name,
                    co.company_kana AS crm_company_company_kana,
                    co.establish AS crm_company_establish,
                    co.company_group AS crm_company_company_group,
                    co.postal_code AS crm_company_postal_code,
                    co.prefecture AS crm_company_prefecture,
                    co.city AS crm_company_city,
                    co.address AS crm_company_address,
                    co.tel AS crm_company_tel,
                    co.fax AS crm_company_fax,
                    co.url AS crm_company_url,
                    co.memo AS crm_company_memo,
                    co.status AS crm_company_status,
                    co.updated AS crm_company_updated" 
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

    echo json_encode([
        'status' => 'success',
        'total' => (int)$totalCount,
        'total_companies' => (int)$totalCompanies,
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