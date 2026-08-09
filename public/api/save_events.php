<?php
require_once 'auth_check.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = get_db_connection();
    
    // 空文字の場合は NULL に変換
    $capacity = ($input['capacity'] === '') ? null : $input['capacity'];
    $event_number = ($input['event_number'] === '') ? null : $input['event_number'];
    $price = ($input['price'] === '') ? null : $input['price'];

    // IDがある場合は更新、ない場合は新規
    if (!empty($input['id'])) {
        $sql = "UPDATE events SET 
                event_date = ?, 
                event_name = ?, 
                event_number = ?, 
                area = ?, 
                place = ?, 
                price = ?, 
                capacity = ?, 
                organizer = ?, 
                updated = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['event_date'], 
            $input['event_name'], 
            $event_number, 
            $input['area'], 
            $input['place'], 
            $price, 
            $capacity, 
            $input['organizer'], 
            $input['id']
        ]);
    } else {
        $sql = "INSERT INTO events 
                (event_date, event_name, event_number, area, place, price, capacity, organizer, created, updated) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['event_date'], 
            $input['event_name'], 
            $event_number,
            $input['area'], 
            $input['place'], 
            $price, 
            $capacity, 
            $input['organizer']
        ]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}