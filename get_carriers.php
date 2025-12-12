<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $stmt = $db->query("SELECT id, name, color FROM carriers ORDER BY name");
    $carriers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($carriers);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>