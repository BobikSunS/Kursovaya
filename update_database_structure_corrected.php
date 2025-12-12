<?php
// update_database_structure_corrected.php
// Скрипт для обновления структуры базы данных с добавлением координат

// Проверка наличия расширения MySQLi
if (!extension_loaded('mysqli')) {
    die('Расширение MySQLi не загружено. Пожалуйста, включите его в php.ini');
}

// Параметры подключения к базе данных
$servername = "localhost";
$username = "root";  // по умолчанию в XAMPP
$password = "";      // по умолчанию в XAMPP
$dbname = "delivery_by";

// Создание подключения
$conn = new mysqli($servername, $username, $password, $dbname);

// Проверка подключения
if ($conn->connect_error) {
    die("Подключение к MySQL не удалось: " . $conn->connect_error);
}

echo "Подключение к базе данных успешно установлено.\n";

// Проверка существования столбцов координат
$check_coords_query = "DESCRIBE offices";
$result = $conn->query($check_coords_query);

$has_lat = false;
$has_lng = false;
$has_company = false;

if ($result) {
    while($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'lat') $has_lat = true;
        if ($row['Field'] === 'lng') $has_lng = true;
        if ($row['Field'] === 'company') $has_company = true;
    }
}

// Добавление столбцов координат, если они не существуют
if (!$has_lat) {
    $sql_add_lat = "ALTER TABLE offices ADD COLUMN lat DECIMAL(10, 8)";
    if ($conn->query($sql_add_lat) === TRUE) {
        echo "Столбец lat успешно добавлен в таблицу offices.\n";
    } else {
        echo "Ошибка при добавлении столбца lat: " . $conn->error . "\n";
    }
} else {
    echo "Столбец lat уже существует в таблице offices.\n";
}

if (!$has_lng) {
    $sql_add_lng = "ALTER TABLE offices ADD COLUMN lng DECIMAL(11, 8)";
    if ($conn->query($sql_add_lng) === TRUE) {
        echo "Столбец lng успешно добавлен в таблицу offices.\n";
    } else {
        echo "Ошибка при добавлении столбца lng: " . $conn->error . "\n";
    }
} else {
    echo "Столбец lng уже существует в таблице offices.\n";
}

if (!$has_company) {
    $sql_add_company = "ALTER TABLE offices ADD COLUMN company VARCHAR(100)";
    if ($conn->query($sql_add_company) === TRUE) {
        echo "Столбец company успешно добавлен в таблицу offices.\n";
    } else {
        echo "Ошибка при добавлении столбца company: " . $conn->error . "\n";
    }
} else {
    echo "Столбец company уже существует в таблице offices.\n";
}

// Проверка существования таблицы calculated_routes
$table_exists_query = "SHOW TABLES LIKE 'calculated_routes'";
$table_result = $conn->query($table_exists_query);

if ($table_result->num_rows == 0) {
    // Создание новой таблицы для хранения рассчитанных маршрутов
    $sql_create_routes = "CREATE TABLE calculated_routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_office_id INT NOT NULL,
        to_office_id INT NOT NULL,
        distance_km DECIMAL(8,2) NOT NULL,
        duration_min INT NOT NULL,
        route_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_office_id) REFERENCES offices(id) ON DELETE CASCADE,
        FOREIGN KEY (to_office_id) REFERENCES offices(id) ON DELETE CASCADE,
        UNIQUE KEY unique_route (from_office_id, to_office_id)
    )";
    
    if ($conn->query($sql_create_routes) === TRUE) {
        echo "Таблица calculated_routes успешно создана.\n";
    } else {
        echo "Ошибка при создании таблицы calculated_routes: " . $conn->error . "\n";
    }
} else {
    echo "Таблица calculated_routes уже существует.\n";
}

// Закрытие подключения
$conn->close();
echo "Структура базы данных обновлена.\n";
?>