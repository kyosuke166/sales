<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = get_db_connection();

    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $required = ['name', 'skillsheet', 'period', 'belong', 'price', 'skill', 'received', 'contact_method', 'original', 'status'];
    foreach ($required as $key) {
        if (!isset($_POST[$key]) || trim($_POST[$key]) === '') {
            throw new Exception("必須項目が未入力です: {$key}");
        }
    }

    $nullable = function ($key) {
        $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        return $value === '' ? null : $value;
    };
    $nullableInt = function ($key) {
        $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        return $value === '' ? null : (int)$value;
    };

    $params = [
        ':name' => trim($_POST['name']),
        ':age' => $nullable('age'),
        ':gender' => $nullable('gender'),
        ':nationality' => $nullable('nationality'),
        ':experience_years' => $nullable('experience_years'),
        ':skillsheet' => trim($_POST['skillsheet']),
        ':period' => trim($_POST['period']),
        ':area' => $nullable('area'),
        ':belong' => trim($_POST['belong']),
        ':price' => trim($_POST['price']),
        ':base_hours' => $nullable('base_hours'),
        ':hope' => $nullable('hope'),
        ':license' => $nullable('license'),
        ':skill' => trim($_POST['skill']),
        ':note' => $nullable('note'),
        ':received' => trim($_POST['received']),
        ':company_id' => $nullableInt('company_id'),
        ':contact_id' => $nullableInt('contact_id'),
        ':contact_method' => trim($_POST['contact_method']),
        ':original' => trim($_POST['original']),
        ':status' => trim($_POST['status']),
    ];

    if (!in_array($params[':status'], ['draft', 'active', 'closed'], true)) {
        throw new Exception('status の値が不正です');
    }

    if ($id) {
        $params[':id'] = $id;
        $sql = "UPDATE engineer SET
            name=:name, age=:age, gender=:gender, nationality=:nationality,
            experience_years=:experience_years, skillsheet=:skillsheet, period=:period,
            area=:area, belong=:belong, price=:price, base_hours=:base_hours,
            hope=:hope, license=:license, skill=:skill, note=:note,
            received=:received, company_id=:company_id, contact_id=:contact_id,
            contact_method=:contact_method, original=:original, status=:status
            WHERE id=:id";
    } else {
        $sql = "INSERT INTO engineer (
            name, age, gender, nationality, experience_years, skillsheet, period,
            area, belong, price, base_hours, hope, license, skill, note,
            received, company_id, contact_id, contact_method, original, status
        ) VALUES (
            :name, :age, :gender, :nationality, :experience_years, :skillsheet, :period,
            :area, :belong, :price, :base_hours, :hope, :license, :skill, :note,
            :received, :company_id, :contact_id, :contact_method, :original, :status
        )";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'status' => 'success',
        'message' => '保存しました',
        'id' => $id ?: $pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
