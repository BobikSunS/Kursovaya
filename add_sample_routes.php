<?php
require 'db.php';

try {
    // Get all offices grouped by carrier
    $stmt = $db->query("SELECT id, carrier_id FROM offices ORDER BY carrier_id, id");
    $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group offices by carrier
    $offices_by_carrier = [];
    foreach ($offices as $office) {
        $offices_by_carrier[$office['carrier_id']][] = $office['id'];
    }

    // For each carrier, create some sample routes between offices
    foreach ($offices_by_carrier as $carrier_id => $office_ids) {
        $num_offices = count($office_ids);
        
        // Create routes between adjacent offices (and some additional ones)
        for ($i = 0; $i < $num_offices; $i++) {
            for ($j = $i + 1; $j < $num_offices; $j++) {
                // Calculate a sample distance based on index difference
                $distance = abs($i - $j) * 100 + rand(50, 150); // Random distance with base
                
                // Insert route in both directions
                $stmt = $db->prepare("INSERT IGNORE INTO routes (from_office, to_office, distance_km) VALUES (?, ?, ?)");
                $stmt->execute([$office_ids[$i], $office_ids[$j], $distance]);
                $stmt->execute([$office_ids[$j], $office_ids[$i], $distance]);
            }
        }
    }

    echo "Added sample routes for all carriers.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>