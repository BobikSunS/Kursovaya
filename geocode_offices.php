<?php
require_once 'db.php';

/**
 * Geocode an address using Nominatim (OpenStreetMap geocoding service)
 */
function geocodeAddress($address, $city) {
    // Combine address and city for better geocoding accuracy
    $fullAddress = urlencode($address . ', ' . $city . ', Belarus');
    
    // Using Nominatim API - important to respect their usage policy
    $url = "https://nominatim.openstreetmap.org/search?q={$fullAddress}&format=json&limit=1&countrycodes=BY";
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Belarus-Delivery-System/1.0\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data[0])) {
        return [
            'lat' => floatval($data[0]['lat']),
            'lng' => floatval($data[0]['lon'])
        ];
    }
    
    return false;
}

try {
    // Get all offices without coordinates
    $stmt = $pdo->query("SELECT id, city, address FROM offices WHERE lat IS NULL OR lng IS NULL");
    $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($offices) . " offices without coordinates. Starting geocoding...\n";
    
    $processed = 0;
    $geocoded = 0;
    
    foreach ($offices as $office) {
        echo "Geocoding: {$office['city']}, {$office['address']}... ";
        
        $coords = geocodeAddress($office['address'], $office['city']);
        
        if ($coords) {
            // Update the office with coordinates
            $updateStmt = $pdo->prepare("UPDATE offices SET lat = ?, lng = ? WHERE id = ?");
            $updateStmt->execute([$coords['lat'], $coords['lng'], $office['id']]);
            
            echo "Success! Lat: {$coords['lat']}, Lng: {$coords['lng']}\n";
            $geocoded++;
        } else {
            echo "Failed to geocode\n";
        }
        
        $processed++;
        
        // Be respectful to the API - add a small delay
        usleep(1000000); // 1 second delay between requests
        
        // Process in batches to avoid overwhelming the API
        if ($processed % 10 == 0) {
            echo "Processed {$processed}/" . count($offices) . " offices, {$geocoded} geocoded successfully.\n";
        }
    }
    
    echo "\nGeocoding complete!\n";
    echo "Total processed: {$processed}\n";
    echo "Successfully geocoded: {$geocoded}\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Note: If you have many offices to geocode, you might want to run this script multiple times as Nominatim has usage limits.\n";
?>