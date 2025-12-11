<?php
require 'db.php';

echo "Проверка и добавление недостающих маршрутов...\n";

// Получаем все офисы, сгруппированные по перевозчику
$offices_stmt = $db->query("SELECT id, carrier_id, city, address FROM offices ORDER BY carrier_id, id");
$offices_by_carrier = [];
$offices_info = [];

while ($office = $offices_stmt->fetch()) {
    $offices_by_carrier[$office['carrier_id']][] = $office['id'];
    $offices_info[$office['id']] = $office;
}

$added_routes = 0;
$existing_routes = 0;

foreach ($offices_by_carrier as $carrier_id => $office_ids) {
    echo "Обработка перевозчика ID $carrier_id (" . count($office_ids) . " офисов)\n";
    
    // Для каждой пары офисов одного перевозчика
    for ($i = 0; $i < count($office_ids); $i++) {
        for ($j = $i + 1; $j < count($office_ids); $j++) {
            $from_id = $office_ids[$i];
            $to_id = $office_ids[$j];
            
            // Проверяем, существует ли маршрут между этими офисами
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM routes WHERE (from_office = ? AND to_office = ?) OR (from_office = ? AND to_office = ?)");
            $check_stmt->execute([$from_id, $to_id, $to_id, $from_id]);
            
            if ($check_stmt->fetchColumn() == 0) {
                // Добавляем маршрут с реалистичным расстоянием
                // Сначала получим информацию о городах и адресах
                $from_city = $offices_info[$from_id]['city'];
                $from_address = $offices_info[$from_id]['address'];
                $to_city = $offices_info[$to_id]['city'];
                $to_address = $offices_info[$to_id]['address'];
                
                // Определяем приблизительное расстояние в зависимости от городов
                $distance = 50; // базовое расстояние
                
                if ($from_city == $to_city) {
                    $distance = 10; // внутри города
                } elseif (
                    (strpos(strtolower($from_city), 'минск') !== false && strpos(strtolower($to_city), 'минск') !== false) ||
                    (strpos(strtolower($from_city), 'могил') !== false && strpos(strtolower($to_city), 'могил') !== false) ||
                    (strpos(strtolower($from_city), 'гомел') !== false && strpos(strtolower($to_city), 'гомел') !== false)
                ) {
                    $distance = 10; // если в названии города есть схожесть
                } else {
                    // Для разных городов устанавливаем приблизительные расстояния
                    $city_pairs = [
                        ['Минск', 'Брест'] => 370,
                        ['Минск', 'Витебск'] => 290,
                        ['Минск', 'Гомель'] => 310,
                        ['Минск', 'Гродно'] => 270,
                        ['Минск', 'Могилёв'] => 190,
                        ['Минск', 'Солигорск'] => 40,
                        ['Могилёв', 'Солигорск'] => 230,
                        ['Брест', 'Гродно'] => 160,
                        ['Витебск', 'Гродно'] => 400,
                        ['Гомель', 'Могилёв'] => 250,
                    ];
                    
                    $key1 = $from_city . '-' . $to_city;
                    $key2 = $to_city . '-' . $from_city;
                    
                    if (isset($city_pairs[$key1])) {
                        $distance = $city_pairs[$key1];
                    } elseif (isset($city_pairs[$key2])) {
                        $distance = $city_pairs[$key2];
                    }
                }
                
                // Добавляем маршрут в обе стороны
                $insert_stmt = $db->prepare("INSERT INTO routes (from_office, to_office, distance_km) VALUES (?, ?, ?)");
                $insert_stmt->execute([$from_id, $to_id, $distance]);
                $insert_stmt->execute([$to_id, $from_id, $distance]);
                
                $added_routes += 2;
                echo "Добавлен маршрут: {$from_city} - {$from_address} <-> {$to_city} - {$to_address} ($distance км)\n";
            } else {
                $existing_routes++;
            }
        }
    }
}

echo "\nОбновление завершено!\n";
echo "Добавлено маршрутов: $added_routes\n";
echo "Уже существовало маршрутов: $existing_routes\n";

?>