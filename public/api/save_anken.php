<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = get_db_connection();

    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $required = ['name', 'period', 'area', 'price', 'received', 'contact_method', 'original', 'status'];
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
        ':period' => trim($_POST['period']),
        ':area' => trim($_POST['area']),
        ':work_time' => $nullable('work_time'),
        ':price' => trim($_POST['price']),
        ':base_hours' => $nullable('base_hours'),
        ':recruit' => $nullable('recruit'),
        ':interview' => $nullable('interview'),
        ':note' => $nullable('note'),
        ':overview' => $nullable('overview'),
        ':skill_must' => $nullable('skill_must'),
        ':skill_want' => $nullable('skill_want'),
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
        $sql = "UPDATE anken SET
            name=:name, period=:period, area=:area, work_time=:work_time, price=:price,
            base_hours=:base_hours, recruit=:recruit, interview=:interview, note=:note,
            overview=:overview, skill_must=:skill_must, skill_want=:skill_want,
            received=:received, company_id=:company_id, contact_id=:contact_id,
            contact_method=:contact_method, original=:original, status=:status
            WHERE id=:id";
    } else {
        $sql = "INSERT INTO anken (
            name, period, area, work_time, price, base_hours, recruit, interview, note,
            overview, skill_must, skill_want, received, company_id, contact_id,
            contact_method, original, status
        ) VALUES (
            :name, :period, :area, :work_time, :price, :base_hours, :recruit, :interview, :note,
            :overview, :skill_must, :skill_want, :received, :company_id, :contact_id,
            :contact_method, :original, :status
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
