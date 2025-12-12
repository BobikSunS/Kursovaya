<?php
require_once 'db.php';

try {
    // Add latitude and longitude fields to offices table
    $sql = "ALTER TABLE offices ADD COLUMN lat DECIMAL(10, 8) NULL, ADD COLUMN lng DECIMAL(11, 8) NULL;";
    $pdo->exec($sql);
    echo "Added lat/lng columns to offices table.\n";

    // Update offices table to include coordinates (we'll get these later through geocoding)
    // For now, we'll just update the existing data
    
    // Drop the old routes table since we'll calculate distances dynamically
    $sql = "DROP TABLE IF EXISTS routes;";
    $pdo->exec($sql);
    echo "Dropped old routes table.\n";
    
    // Create new routes table to store calculated routes
    $sql = "CREATE TABLE calculated_routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_office_id INT NOT NULL,
        to_office_id INT NOT NULL,
        distance_km DECIMAL(8,2) NOT NULL,
        duration_min INT NOT NULL,
        route_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_office_id) REFERENCES offices(id) ON DELETE CASCADE,
        FOREIGN KEY (to_office_id) REFERENCES offices(id) ON DELETE CASCADE,
        UNIQUE KEY unique_route (from_office_id, to_office_id)
    );";
    $pdo->exec($sql);
    echo "Created new calculated_routes table.\n";
    
    echo "Database updated successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>