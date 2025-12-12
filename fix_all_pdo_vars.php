<?php
// Скрипт для исправления всех файлов, в которых используется $pdo вместо $db

$files_to_fix = [
    'geocode_offices.php',
    'get_carriers.php', 
    'get_offices_with_coords.php',
    'map_page.php',
    'order_form_new.php'
];

foreach ($files_to_fix as $file) {
    $full_path = "/workspace/$file";
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        
        // Заменяем все вхождения $pdo на $db
        $content = str_replace('$pdo->', '$db->', $content);
        $content = str_replace('$pdo;', '$db;', $content);
        
        file_put_contents($full_path, $content);
        echo "Исправлен файл: $file\n";
    } else {
        echo "Файл не найден: $file\n";
    }
}

echo "Все файлы исправлены!\n";
?>