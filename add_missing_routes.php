<?php
require 'db.php';

try {
    echo "Проверка и добавление недостающих маршрутов...\n";
    
    // Получаем все офисы, сгруппированные по перевозчику
    $offices_stmt = $db->query("SELECT id, carrier_id FROM offices ORDER BY carrier_id, id");
    $offices_by_carrier = [];
    
    while ($office = $offices_stmt->fetch()) {
        $offices_by_carrier[$office['carrier_id']][] = $office['id'];
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
                    // Добавляем маршрут с фиктивным расстоянием (например, 50 км для офисов одного города или 100 км для разных городов)
                    // Сначала получим информацию о городах
                    $from_office = $db->prepare("SELECT city FROM offices WHERE id = ?");
                    $from_office->execute([$from_id]);
                    $from_city = $from_office->fetchColumn();
                    
                    $to_office = $db->prepare("SELECT city FROM offices WHERE id = ?");
                    $to_office->execute([$to_id]);
                    $to_city = $to_office->fetchColumn();
                    
                    // Если офисы в одном городе, расстояние 10 км, иначе 50 км
                    $distance = ($from_city == $to_city) ? 10 : 50;
                    
                    // Добавляем маршрут в обе стороны
                    $insert_stmt = $db->prepare("INSERT INTO routes (from_office, to_office, distance_km) VALUES (?, ?, ?)");
                    $insert_stmt->execute([$from_id, $to_id, $distance]);
                    $insert_stmt->execute([$to_id, $from_id, $distance]);
                    
                    $added_routes += 2;
                    echo "Добавлен маршрут: $from_id <-> $to_id ({$from_city} <-> {$to_city}, $distance км)\n";
                } else {
                    $existing_routes++;
                }
            }
        }
    }
    
    echo "\nОбновление завершено!\n";
    echo "Добавлено маршрутов: $added_routes\n";
    echo "Уже существовало маршрутов: $existing_routes\n";
    
} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
?>