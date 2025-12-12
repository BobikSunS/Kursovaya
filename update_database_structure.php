<?php
require_once 'db.php';

try {
    // Add latitude and longitude fields to offices table if they don't exist
    $sql = "SHOW COLUMNS FROM offices LIKE 'lat';";
    $result = $db->query($sql);
    if ($result->rowCount() == 0) {
        $sql = "ALTER TABLE offices ADD COLUMN lat DECIMAL(10, 8) NULL, ADD COLUMN lng DECIMAL(11, 8) NULL;";
        $db->exec($sql);
        echo "Added lat/lng columns to offices table.\n";
    } else {
        echo "Lat/lng columns already exist in offices table.\n";
    }

    // Check if routes table exists
    $sql = "SHOW TABLES LIKE 'routes';";
    $result = $db->query($sql);
    if ($result->rowCount() == 0) {
        // Create the routes table with the expected structure
        $sql = "CREATE TABLE routes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_office INT NOT NULL,
            to_office INT NOT NULL,
            distance_km DECIMAL(8,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (from_office) REFERENCES offices(id) ON DELETE CASCADE,
            FOREIGN KEY (to_office) REFERENCES offices(id) ON DELETE CASCADE,
            UNIQUE KEY unique_route (from_office, to_office)
        );";
        $db->exec($sql);
        echo "Created routes table.\n";
    } else {
        echo "Routes table already exists.\n";
    }
    
    echo "Database updated successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>