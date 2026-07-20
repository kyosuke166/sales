<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = get_db_connection();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    $select = "SELECT
            e.*,
            co.company_name,
            CONCAT(IFNULL(c.last_name, ''), ' ', IFNULL(c.first_name, '')) AS contact_name
        FROM engineer e
        LEFT JOIN crm_company co ON e.company_id = co.id
        LEFT JOIN crm_contact c ON e.contact_id = c.id";

    if ($id > 0) {
        $stmt = $pdo->prepare($select . " WHERE e.id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('エンジニアが見つかりません');
        }
        echo json_encode(['status' => 'success', 'data' => $row], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $where = [];
    $params = [];

    if ($q !== '') {
        $words = preg_split('/\s+/u', mb_convert_kana($q, 's'));
        foreach ($words as $i => $word) {
            if ($word === '') continue;
            $key = ":q{$i}";
            $where[] = "(e.name LIKE {$key}
                OR e.period LIKE {$key}
                OR e.area LIKE {$key}
                OR e.belong LIKE {$key}
                OR e.price LIKE {$key}
                OR e.hope LIKE {$key}
                OR e.license LIKE {$key}
                OR e.skill LIKE {$key}
                OR e.original LIKE {$key}
                OR co.company_name LIKE {$key}
                OR c.last_name LIKE {$key}
                OR c.first_name LIKE {$key})";
            $params[$key] = "%{$word}%";
        }
    }

    if ($status !== '') {
        $where[] = "e.status = :status";
        $params[':status'] = $status;
    }

    $sql = $select;
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY e.received DESC, e.id DESC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
