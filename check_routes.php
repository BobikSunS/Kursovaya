<?php
require 'db.php';

// Проверяем таблицы
echo "Таблицы в базе данных:\n";
$stmt = $db->query("SHOW TABLES");
$tables = $stmt->fetchAll();
foreach($tables as $table) {
    echo "- " . $table[0] . "\n";
}

// Проверяем существующие маршруты
echo "\nСуществующие маршруты:\n";
try {
    $stmt = $db->query('SELECT r.*, o1.city as from_city, o1.address as from_address, o2.city as to_city, o2.address as to_address FROM routes r JOIN offices o1 ON r.from_office = o1.id JOIN offices o2 ON r.to_office = o2.id');
    $routes = $stmt->fetchAll();
    foreach($routes as $route) {
        echo $route['from_city'].' - '.$route['from_address'].' -> '.$route['to_city'].' - '.$route['to_address'].' ('.$route['distance_km'].' км)'."\n";
    }
} catch (Exception $e) {
    echo "Ошибка при получении маршрутов: " . $e->getMessage() . "\n";
}

// Проверяем существующие офисы
echo "\nСуществующие офисы:\n";
try {
    $stmt = $db->query('SELECT * FROM offices');
    $offices = $stmt->fetchAll();
    foreach($offices as $office) {
        echo $office['id'] . ': ' . $office['city'] . ' - ' . $office['address'] . " (оператор ID: " . $office['carrier_id'] . ")\n";
    }
} catch (Exception $e) {
    echo "Ошибка при получении офисов: " . $e->getMessage() . "\n";
}
?>