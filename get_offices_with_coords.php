<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $stmt = $db->query("
        SELECT o.id, o.carrier_id, o.city, o.address, o.lat, o.lng, c.name as carrier_name 
        FROM offices o 
        LEFT JOIN carriers c ON o.carrier_id = c.id 
        ORDER BY c.name, o.city, o.address
    ");
    $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($offices);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>