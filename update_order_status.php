<?php
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $order_id = (int)($data['order_id'] ?? 0);
    $new_status = trim($data['status'] ?? '');
    
    if (!$order_id || !$new_status) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or status']);
        exit;
    }
    
    try {
        // Verify that the order belongs to the current user
        $stmt = $db->prepare("SELECT id, tracking_status FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user['id']]);
        $order = $stmt->fetch();
        
        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found or does not belong to user']);
            exit;
        }
        
        // Update the order status
        $stmt = $db->prepare("UPDATE orders SET tracking_status = ? WHERE id = ?");
        $result = $stmt->execute([$new_status, $order_id]);
        
        if ($result) {
            // If the new status is 'delivered', update the delivery date
            if ($new_status === 'delivered') {
                $delivery_stmt = $db->prepare("UPDATE orders SET delivery_date = CURDATE() WHERE id = ?");
                $delivery_stmt->execute([$order_id]);
            }
            
            // Add to status history
            $status_stmt = $db->prepare("INSERT INTO tracking_status_history (order_id, status, description) VALUES (?, ?, ?)");
            $status_stmt->execute([
                $order_id, 
                $new_status, 
                "Статус изменен на: " . $new_status
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update status']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>