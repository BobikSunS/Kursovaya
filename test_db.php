<?php
// Test script to check if database has the required columns
require 'db.php';

try {
    // Check if tracking_status column exists
    $stmt = $db->query("SHOW COLUMNS FROM orders LIKE 'tracking_status'");
    $trackingStatusExists = $stmt->fetch();
    
    if ($trackingStatusExists) {
        echo "✓ Column 'tracking_status' exists\n";
    } else {
        echo "✗ Column 'tracking_status' does not exist\n";
    }
    
    // Check if payment_status column exists
    $stmt = $db->query("SHOW COLUMNS FROM orders LIKE 'payment_status'");
    $paymentStatusExists = $stmt->fetch();
    
    if ($paymentStatusExists) {
        echo "✓ Column 'payment_status' exists\n";
    } else {
        echo "✗ Column 'payment_status' does not exist\n";
    }
    
    // Check if other important columns exist
    $columns_to_check = [
        'full_name', 'home_address', 'insurance', 'packaging', 'fragile', 
        'payment_method', 'comment', 'desired_date'
    ];
    
    foreach ($columns_to_check as $col) {
        $stmt = $db->query("SHOW COLUMNS FROM orders LIKE '$col'");
        $exists = $stmt->fetch();
        if ($exists) {
            echo "✓ Column '$col' exists\n";
        } else {
            echo "✗ Column '$col' does not exist\n";
        }
    }
    
    // Check if tracking_status_history table exists
    $stmt = $db->query("SHOW TABLES LIKE 'tracking_status_history'");
    $historyTableExists = $stmt->fetch();
    
    if ($historyTableExists) {
        echo "✓ Table 'tracking_status_history' exists\n";
    } else {
        echo "✗ Table 'tracking_status_history' does not exist\n";
    }
    
    echo "\nDatabase structure check completed.\n";
    
} catch (Exception $e) {
    echo "Error checking database: " . $e->getMessage() . "\n";
}
?>