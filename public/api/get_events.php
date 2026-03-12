<?php
require_once __DIR__ . '/../../auth_check.php';
header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    
    $sql = "
        SELECT 
            e.id,
            e.event_date,
            e.event_name,
            e.event_number,
            e.area,
            e.place,
            e.capacity,
            e.organizer,
            COUNT(ep.id) AS current_count
        FROM events e
        LEFT JOIN events_participant ep ON e.id = ep.event_id
        GROUP BY e.id
        ORDER BY e.event_date ASC
    ";
    
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $events
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}