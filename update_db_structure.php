<?php
require 'db.php';

try {
    // Проверяем и добавляем новые столбцы в таблицу orders, если они не существуют
    $columns_to_add = [
        'full_name VARCHAR(255) DEFAULT NULL',
        'home_address TEXT DEFAULT NULL',
        'pickup_city VARCHAR(100) DEFAULT NULL',
        'pickup_address TEXT DEFAULT NULL',
        'delivery_city VARCHAR(100) DEFAULT NULL',
        'delivery_address TEXT DEFAULT NULL',
        'desired_date DATE DEFAULT NULL',
        'insurance TINYINT(1) DEFAULT 0',
        'packaging TINYINT(1) DEFAULT 0',
        'fragile TINYINT(1) DEFAULT 0',
        'payment_method VARCHAR(50) DEFAULT \'cash\'',
        'comment TEXT DEFAULT NULL',
        'tracking_status VARCHAR(50) DEFAULT \'created\'',
        'payment_status VARCHAR(20) DEFAULT \'pending\'',
        'delivery_date DATE DEFAULT NULL'
    ];

    foreach ($columns_to_add as $column) {
        $column_name = explode(' ', $column)[0];
        
        // Проверяем, существует ли столбец
        $check_sql = "SHOW COLUMNS FROM orders LIKE '$column_name'";
        $result = $db->query($check_sql);
        
        if ($result->rowCount() == 0) {
            $sql = "ALTER TABLE orders ADD COLUMN $column";
            $db->exec($sql);
            echo "Добавлен столбец: $column_name\n";
        } else {
            echo "Столбец уже существует: $column_name\n";
        }
    }

    echo "Структура базы данных успешно обновлена!";
    
} catch (PDOException $e) {
    echo "Ошибка при обновлении структуры базы данных: " . $e->getMessage();
}
?>