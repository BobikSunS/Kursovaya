<?php
// Скрипт для заполнения таблицы routes маршрутами между офисами одного перевозчика
require 'db.php';

try {
    echo "Проверка и добавление недостающих маршрутов...\n";
    
    // Получаем все офисы, сгруппированные по перевозчику
    $offices_stmt = $db->query("SELECT id, carrier_id, city FROM offices ORDER BY carrier_id, id");
    $offices_by_carrier = [];
    
    while ($office = $offices_stmt->fetch()) {
        $offices_by_carrier[$office['carrier_id']][] = $office;
    }
    
    $added_routes = 0;
    $existing_routes = 0;
    
    foreach ($offices_by_carrier as $carrier_id => $offices) {
        echo "Обработка перевозчика ID $carrier_id (" . count($offices) . " офисов)\n";
        
        // Для каждой пары офисов одного перевозчика
        for ($i = 0; $i < count($offices); $i++) {
            for ($j = $i + 1; $j < count($offices); $j++) {
                $from_office = $offices[$i];
                $to_office = $offices[$j];
                
                $from_id = $from_office['id'];
                $to_id = $to_office['id'];
                
                // Проверяем, существует ли маршрут между этими офисами
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM routes WHERE (from_office = ? AND to_office = ?) OR (from_office = ? AND to_office = ?)");
                $check_stmt->execute([$from_id, $to_id, $to_id, $from_id]);
                
                if ($check_stmt->fetchColumn() == 0) {
                    // Добавляем маршрут с фиктивным расстоянием (например, 50 км для офисов одного города или 100 км для разных городов)
                    // Если офисы в одном городе, расстояние 10 км, иначе 50 км
                    $distance = ($from_office['city'] == $to_office['city']) ? 10 : 50;
                    
                    // Добавляем маршрут в обе стороны
                    $insert_stmt = $db->prepare("INSERT INTO routes (from_office, to_office, distance_km) VALUES (?, ?, ?)");
                    $insert_stmt->execute([$from_id, $to_id, $distance]);
                    $insert_stmt->execute([$to_id, $from_id, $distance]);
                    
                    $added_routes += 2;
                    echo "Добавлен маршрут: $from_id <-> $to_id ({$from_office['city']} <-> {$to_office['city']}, $distance км)\n";
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