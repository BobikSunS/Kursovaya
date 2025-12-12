<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit;
}

// Handle API-style request (for map page)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }
    
    $from_office = $input['from_office'] ?? null;
    $to_office = $input['to_office'] ?? null;
    $weight = $input['weight'] ?? null;
    $cost = $input['cost'] ?? null;
    $delivery_hours = $input['delivery_hours'] ?? null;
    $full_name = $input['full_name'] ?? '';
    $home_address = $input['home_address'] ?? '';
    $pickup_city = $input['pickup_city'] ?? '';
    $pickup_address = $input['pickup_address'] ?? '';
    $delivery_city = $input['delivery_city'] ?? '';
    $delivery_address = $input['delivery_address'] ?? '';
    $desired_date = $input['desired_date'] ?? null;
    $insurance = $input['insurance'] ?? 0;
    $packaging = $input['packaging'] ?? 0;
    $fragile = $input['fragile'] ?? 0;
    $payment_method = $input['payment_method'] ?? 'cash';
    $comment = $input['comment'] ?? '';
    $carrier_id = $input['carrier_id'] ?? null;

    // Validate required fields
    if (!$from_office || !$to_office || !$weight || !$carrier_id || $cost === null) {
        echo json_encode(['success' => false, 'message' => 'Пожалуйста, заполните все обязательные поля.']);
        exit;
    }
    
    try {
        // Calculate additional costs based on options
        $additional_cost = 0;
        if ($insurance) $additional_cost += ($cost * 0.1); // 10% extra for insurance
        if ($packaging) $additional_cost += 5; // 5 BYN extra for packaging
        
        $total_cost = $cost + $additional_cost;

        // Generate tracking number
        $track_number = strtoupper(bin2hex(random_bytes(10)));

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, carrier_id, from_office, to_office, weight, cost, delivery_hours, track_number, full_name, home_address, pickup_city, pickup_address, delivery_city, delivery_address, desired_date, insurance, packaging, fragile, payment_method, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id, $carrier_id, $from_office, $to_office, $weight, $total_cost, $delivery_hours, $track_number,
            $full_name, $home_address, $pickup_city, $pickup_address, $delivery_city, $delivery_address,
            $desired_date, $insurance, $packaging, $fragile, $payment_method, $comment
        ]);

        $order_id = $pdo->lastInsertId();
        
        // Add initial status to tracking history
        $stmt = $pdo->prepare("INSERT INTO tracking_status_history (order_id, status, description) VALUES (?, 'created', 'Заказ создан')");
        $stmt->execute([$order_id]);

        echo json_encode(['success' => true, 'order_id' => $order_id, 'track_number' => $track_number]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка при создании заказа: ' . $e->getMessage()]);
        exit;
    }
}

// Handle form submission (for regular order form page)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from_office = $_POST['from_office'] ?? null;
    $to_office = $_POST['to_office'] ?? null;
    $weight = $_POST['weight'] ?? null;
    $full_name = $_POST['full_name'] ?? '';
    $home_address = $_POST['home_address'] ?? '';
    $pickup_city = $_POST['pickup_city'] ?? '';
    $pickup_address = $_POST['pickup_address'] ?? '';
    $delivery_city = $_POST['delivery_city'] ?? '';
    $delivery_address = $_POST['delivery_address'] ?? '';
    $desired_date = $_POST['desired_date'] ?? null;
    $insurance = isset($_POST['insurance']) ? 1 : 0;
    $packaging = isset($_POST['packaging']) ? 1 : 0;
    $fragile = isset($_POST['fragile']) ? 1 : 0;
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $comment = $_POST['comment'] ?? '';
    $carrier_id = $_POST['carrier_id'] ?? null;

    // Validate required fields
    if (!$from_office || !$to_office || !$weight || !$carrier_id) {
        $error = "Пожалуйста, заполните все обязательные поля.";
    } else {
        try {
            // Get carrier information
            $stmt = $pdo->prepare("SELECT base_cost, cost_per_km, cost_per_kg, speed_kmh FROM carriers WHERE id = ?");
            $stmt->execute([$carrier_id]);
            $carrier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$carrier) {
                $error = "Неверный перевозчик.";
            } else {
                // Calculate distance between offices to determine cost
                // Since we removed the routes table, we'll calculate based on coordinates if available
                $stmt = $pdo->prepare("SELECT lat, lng FROM offices WHERE id = ? OR id = ?");
                $stmt->execute([$from_office, $to_office]);
                $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($offices) < 2) {
                    $error = "Не удалось получить координаты для расчета маршрута.";
                } else {
                    $from_coords = null;
                    $to_coords = null;
                    
                    foreach ($offices as $office) {
                        if ($office['lat'] && $office['lng']) {
                            if ($from_coords === null) {
                                $from_coords = $office;
                            } else {
                                $to_coords = $office;
                            }
                        }
                    }
                    
                    if ($from_coords && $to_coords) {
                        // Calculate straight-line distance (in a real system, you'd use a routing service)
                        $distance_km = calculateHaversineDistance(
                            $from_coords['lat'], 
                            $from_coords['lng'], 
                            $to_coords['lat'], 
                            $to_coords['lng']
                        );
                        
                        // Apply road distance factor (typically 1.3x straight-line distance)
                        $realistic_distance = $distance_km * 1.3;
                        
                        $base_cost = $carrier['base_cost'];
                        $cost_per_km = $carrier['cost_per_km'];
                        $cost_per_kg = $carrier['cost_per_kg'];

                        // Calculate total cost
                        $cost = $base_cost + ($realistic_distance * $cost_per_km) + ($weight * $cost_per_kg);

                        // Add extra costs
                        if ($insurance) $cost *= 1.1; // 10% extra for insurance
                        if ($packaging) $cost += 5; // 5 BYN extra for packaging

                        // Calculate delivery time
                        $speed = $carrier['speed_kmh'];
                        $delivery_hours = $realistic_distance / $speed;
                    } else {
                        // Fallback to old method if coordinates not available
                        $stmt = $pdo->prepare("SELECT distance_km FROM routes WHERE from_office = ? AND to_office = ?");
                        $stmt->execute([$from_office, $to_office]);
                        $route = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$route) {
                            $error = "Маршрут не найден.";
                        } else {
                            $distance_km = $route['distance_km'];
                            $base_cost = $carrier['base_cost'];
                            $cost_per_km = $carrier['cost_per_km'];
                            $cost_per_kg = $carrier['cost_per_kg'];

                            // Calculate total cost
                            $cost = $base_cost + ($distance_km * $cost_per_km) + ($weight * $cost_per_kg);

                            // Add extra costs
                            if ($insurance) $cost *= 1.1; // 10% extra for insurance
                            if ($packaging) $cost += 5; // 5 BYN extra for packaging

                            // Calculate delivery time
                            $speed = $carrier['speed_kmh'];
                            $delivery_hours = $distance_km / $speed;
                        }
                    }
                    
                    if (!isset($error)) {
                        // Generate tracking number
                        $track_number = strtoupper(bin2hex(random_bytes(10)));

                        // Insert order
                        $stmt = $pdo->prepare("INSERT INTO orders (user_id, carrier_id, from_office, to_office, weight, cost, delivery_hours, track_number, full_name, home_address, pickup_city, pickup_address, delivery_city, delivery_address, desired_date, insurance, packaging, fragile, payment_method, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $user_id, $carrier_id, $from_office, $to_office, $weight, $cost, $delivery_hours, $track_number,
                            $full_name, $home_address, $pickup_city, $pickup_address, $delivery_city, $delivery_address,
                            $desired_date, $insurance, $packaging, $fragile, $payment_method, $comment
                        ]);

                        // Add initial status to tracking history
                        $stmt = $pdo->prepare("INSERT INTO tracking_status_history (order_id, status, description) VALUES (?, 'created', 'Заказ создан')");
                        $stmt->execute([$pdo->lastInsertId()]);

                        header('Location: payment.php?order_id=' . $pdo->lastInsertId());
                        exit;
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Ошибка при создании заказа: " . $e->getMessage();
        }
    }
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа - <?php echo htmlspecialchars($user['name']); ?></title>
    <!-- Подключение Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">Система доставки</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Главная</a>
                <a class="nav-link" href="calculator.php">Калькулятор</a>
                <a class="nav-link" href="map_page.php">Карта</a>
                <a class="nav-link" href="history.php">История</a>
                <a class="nav-link" href="profile.php">Профиль</a>
                <a class="nav-link" href="logout.php">Выход</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="mb-4">Оформление заказа</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <h5>Информация об отправителе</h5>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">ФИО</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="home_address" class="form-label">Домашний адрес</label>
                        <textarea class="form-control" id="home_address" name="home_address" rows="2" required></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5>Информация о получателе</h5>
                    <div class="mb-3">
                        <label for="recipient_name" class="form-label">ФИО получателя</label>
                        <input type="text" class="form-control" id="recipient_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="recipient_address" class="form-label">Адрес получателя</label>
                        <textarea class="form-control" id="recipient_address" name="home_address" rows="2" required></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="from_office" class="form-label">Отделение отправления</label>
                        <select class="form-select" id="from_office" name="from_office" required>
                            <option value="">Выберите отделение</option>
                            <?php
                            $stmt = $pdo->query("SELECT o.id, o.city, o.address, c.name as carrier_name FROM offices o JOIN carriers c ON o.carrier_id = c.id ORDER BY c.name, o.city, o.address");
                            $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($offices as $office) {
                                echo '<option value="' . $office['id'] . '">' . htmlspecialchars($office['city']) . ', ' . htmlspecialchars($office['address']) . ' (' . htmlspecialchars($office['carrier_name']) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="to_office" class="form-label">Отделение получения</label>
                        <select class="form-select" id="to_office" name="to_office" required>
                            <option value="">Выберите отделение</option>
                            <?php foreach ($offices as $office) {
                                echo '<option value="' . $office['id'] . '">' . htmlspecialchars($office['city']) . ', ' . htmlspecialchars($office['address']) . ' (' . htmlspecialchars($office['carrier_name']) . ')</option>';
                            } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="weight" class="form-label">Вес посылки (кг)</label>
                        <input type="number" class="form-control" id="weight" name="weight" value="1.0" min="0.1" step="0.1" max="30" required>
                    </div>
                    <div class="mb-3">
                        <label for="carrier_id" class="form-label">Перевозчик</label>
                        <select class="form-select" id="carrier_id" name="carrier_id" required>
                            <option value="">Выберите перевозчика</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, name FROM carriers ORDER BY name");
                            $carriers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($carriers as $carrier) {
                                echo '<option value="' . $carrier['id'] . '">' . htmlspecialchars($carrier['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="desired_date" class="form-label">Желаемая дата доставки</label>
                        <input type="date" class="form-control" id="desired_date" name="desired_date">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Способ оплаты</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="cash">Наличные</option>
                            <option value="card">Банковская карта</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="insurance" name="insurance">
                    <label class="form-check-label" for="insurance">Страхование (+10% к стоимости)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="packaging" name="packaging">
                    <label class="form-check-label" for="packaging">Упаковка (+5 BYN)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="fragile" name="fragile">
                    <label class="form-check-label" for="fragile">Хрупкое содержимое</label>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="comment" class="form-label">Комментарий</label>
                <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Создать заказ</button>
            <a href="index.php" class="btn btn-secondary">Отмена</a>
        </form>
    </div>

    <!-- Подключение Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>