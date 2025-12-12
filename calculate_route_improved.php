<?php
header('Content-Type: application/json');
require_once 'db.php';

// Enable CORS for local development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['from_office_id']) || !isset($input['to_office_id'])) {
        throw new Exception('Invalid input data');
    }
    
    $fromOfficeId = (int)$input['from_office_id'];
    $toOfficeId = (int)$input['to_office_id'];
    $weight = isset($input['weight']) ? floatval($input['weight']) : 1.0;
    
    if ($fromOfficeId === $toOfficeId) {
        throw new Exception('From and To offices must be different');
    }
    
    // Get coordinates for both offices
    $stmt = $pdo->prepare("SELECT id, city, address, lat, lng, carrier_id FROM offices WHERE id = ? OR id = ?");
    $stmt->execute([$fromOfficeId, $toOfficeId]);
    $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($offices) < 2) {
        throw new Exception('One or both offices not found');
    }
    
    $fromOffice = null;
    $toOffice = null;
    
    foreach ($offices as $office) {
        if ($office['id'] == $fromOfficeId) {
            $fromOffice = $office;
        } elseif ($office['id'] == $toOfficeId) {
            $toOffice = $office;
        }
    }
    
    if (!$fromOffice || !$toOffice) {
        throw new Exception('Could not find both offices');
    }
    
    if (!$fromOffice['lat'] || !$fromOffice['lng'] || !$toOffice['lat'] || !$toOffice['lng']) {
        throw new Exception('Coordinates not available for one or both offices');
    }
    
    // Get carrier info for cost calculation
    $stmt = $pdo->prepare("SELECT base_cost, cost_per_km, cost_per_kg, speed_kmh FROM carriers WHERE id = ?");
    $stmt->execute([$fromOffice['carrier_id']]);
    $carrier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$carrier) {
        throw new Exception('Carrier not found');
    }
    
    // Try to get route from OSRM server (if available)
    $routeData = getRouteFromOSRM($fromOffice, $toOffice);
    
    if ($routeData) {
        // Use real route data
        $distanceKm = $routeData['distance_km'];
        $durationMin = $routeData['duration_min'];
        $coordinates = $routeData['coordinates'];
        $instructions = $routeData['instructions'];
    } else {
        // Fallback to haversine calculation with approximation
        $distanceKm = calculateHaversineDistance(
            $fromOffice['lat'], 
            $fromOffice['lng'], 
            $toOffice['lat'], 
            $toOffice['lng']
        );
        
        // Apply road distance factor (typically 1.3x straight-line distance)
        $realisticDistanceKm = $distanceKm * 1.3;
        $distanceKm = $realisticDistanceKm;
        
        // Estimate duration based on distance and carrier speed
        $durationHours = $realisticDistanceKm / $carrier['speed_kmh'];
        $durationMin = round($durationHours * 60);
        
        // Generate mock coordinates and instructions
        $coordinates = generateRouteCoordinates(
            [$fromOffice['lat'], $fromOffice['lng']], 
            [$toOffice['lat'], $toOffice['lng']]
        );
        $instructions = generateMockInstructions($fromOffice, $toOffice, $realisticDistanceKm);
    }
    
    // Calculate cost based on distance and other factors
    $cost = $carrier['base_cost'] + ($weight * $carrier['cost_per_kg']) + ($distanceKm * $carrier['cost_per_km']);
    
    // Prepare response
    $response = [
        'success' => true,
        'from_office' => $fromOffice,
        'to_office' => $toOffice,
        'distance_km' => $distanceKm,
        'duration_min' => $durationMin,
        'cost' => $cost,
        'coordinates' => $coordinates,
        'instructions' => $instructions
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Try to get route from OSRM server
 */
function getRouteFromOSRM($fromOffice, $toOffice) {
    // OSRM demo server URL - in production, you'd run your own OSRM server
    $osrmUrl = "http://router.project-osrm.org/route/v1/driving/" . 
               $fromOffice['lng'] . "," . $fromOffice['lat'] . ";" . 
               $toOffice['lng'] . "," . $toOffice['lat'] . 
               "?overview=full&steps=true";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,  // 10 seconds timeout
            'user_agent' => 'Belarus-Delivery-System/1.0'
        ]
    ]);
    
    $response = @file_get_contents($osrmUrl, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        
        if (isset($data['routes']) && count($data['routes']) > 0) {
            $route = $data['routes'][0];
            
            // Convert distance from meters to kilometers
            $distanceKm = $route['distance'] / 1000;
            
            // Convert duration from seconds to minutes
            $durationMin = $route['duration'] / 60;
            
            // Extract coordinates
            $coordinates = [];
            if (isset($route['geometry']) && isset($route['geometry']['coordinates'])) {
                foreach ($route['geometry']['coordinates'] as $coord) {
                    $coordinates[] = [$coord[1], $coord[0]]; // [lat, lng] format
                }
            }
            
            // Extract turn-by-turn instructions
            $instructions = [];
            if (isset($route['legs']) && count($route['legs']) > 0) {
                foreach ($route['legs'] as $leg) {
                    if (isset($leg['steps'])) {
                        foreach ($leg['steps'] as $step) {
                            $instructions[] = [
                                'text' => $step['maneuver']['instruction'] ?? 'Continue',
                                'distance' => $step['distance'],
                                'duration' => $step['duration']
                            ];
                        }
                    }
                }
            }
            
            return [
                'distance_km' => $distanceKm,
                'duration_min' => $durationMin,
                'coordinates' => $coordinates,
                'instructions' => $instructions
            ];
        }
    }
    
    return null; // Return null if OSRM request failed
}

/**
 * Calculate the Haversine distance between two points in kilometers
 */
function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;
    
    return $distance;
}

/**
 * Generate mock turn-by-turn instructions
 */
function generateMockInstructions($fromOffice, $toOffice, $distanceKm) {
    $instructions = [];
    
    // Add starting instruction
    $instructions[] = [
        'text' => 'Начните движение от ' . $fromOffice['city'] . ', ' . $fromOffice['address'],
        'distance' => 0
    ];
    
    // Add some mock directions
    $directions = [
        ['text' => 'Поверните направо на главную дорогу', 'distance' => round($distanceKm * 0.2 * 1000)],
        ['text' => 'Продолжайте движение прямо', 'distance' => round($distanceKm * 0.5 * 1000)],
        ['text' => 'Поверните налево', 'distance' => round($distanceKm * 0.2 * 1000)],
        ['text' => 'Продолжайте движение до ' . $toOffice['city'], 'distance' => round($distanceKm * 0.1 * 1000)]
    ];
    
    $instructions = array_merge($instructions, $directions);
    
    // Add destination instruction
    $instructions[] = [
        'text' => 'Прибытие в ' . $toOffice['city'] . ', ' . $toOffice['address'],
        'distance' => 0
    ];
    
    return $instructions;
}

/**
 * Generate route coordinates (for now, just a straight line with some intermediate points)
 */
function generateRouteCoordinates($start, $end) {
    $coordinates = [];
    $steps = 10; // Number of intermediate points
    
    for ($i = 0; $i <= $steps; $i++) {
        $ratio = $i / $steps;
        $lat = $start[0] + ($end[0] - $start[0]) * $ratio;
        $lng = $start[1] + ($end[1] - $start[1]) * $ratio;
        $coordinates[] = [$lat, $lng];
    }
    
    return $coordinates;
}
?>