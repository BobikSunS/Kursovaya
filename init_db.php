<?php
require 'db.php';

try {
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";
    $db->exec($sql);
    echo "Created users table.\n";

    // Create carriers table
    $sql = "CREATE TABLE IF NOT EXISTS carriers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        color VARCHAR(7) DEFAULT '#000000',
        base_cost DECIMAL(10,2) DEFAULT 0,
        cost_per_kg DECIMAL(10,2) DEFAULT 0,
        cost_per_km DECIMAL(10,2) DEFAULT 0,
        max_weight DECIMAL(8,2) DEFAULT 0,
        speed_kmh DECIMAL(8,2) DEFAULT 0,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";
    $db->exec($sql);
    echo "Created carriers table.\n";

    // Create offices table
    $sql = "CREATE TABLE IF NOT EXISTS offices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        carrier_id INT NOT NULL,
        city VARCHAR(255) NOT NULL,
        address TEXT NOT NULL,
        lat DECIMAL(10, 8) NULL,
        lng DECIMAL(11, 8) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE
    );";
    $db->exec($sql);
    echo "Created offices table.\n";

    // Create routes table
    $sql = "CREATE TABLE IF NOT EXISTS routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_office INT NOT NULL,
        to_office INT NOT NULL,
        distance_km DECIMAL(8,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_office) REFERENCES offices(id) ON DELETE CASCADE,
        FOREIGN KEY (to_office) REFERENCES offices(id) ON DELETE CASCADE,
        UNIQUE KEY unique_route (from_office, to_office)
    );";
    $db->exec($sql);
    echo "Created routes table.\n";

    // Create orders table
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        carrier_id INT NOT NULL,
        from_office INT NOT NULL,
        to_office INT NOT NULL,
        weight DECIMAL(8,2) NOT NULL,
        cost DECIMAL(10,2) NOT NULL,
        full_name VARCHAR(255) DEFAULT NULL,
        home_address TEXT DEFAULT NULL,
        pickup_city VARCHAR(100) DEFAULT NULL,
        pickup_address TEXT DEFAULT NULL,
        delivery_city VARCHAR(100) DEFAULT NULL,
        delivery_address TEXT DEFAULT NULL,
        desired_date DATE DEFAULT NULL,
        insurance TINYINT(1) DEFAULT 0,
        packaging TINYINT(1) DEFAULT 0,
        fragile TINYINT(1) DEFAULT 0,
        payment_method VARCHAR(50) DEFAULT 'cash',
        comment TEXT DEFAULT NULL,
        tracking_status VARCHAR(50) DEFAULT 'created',
        payment_status VARCHAR(20) DEFAULT 'pending',
        delivery_date DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE,
        FOREIGN KEY (from_office) REFERENCES offices(id) ON DELETE CASCADE,
        FOREIGN KEY (to_office) REFERENCES offices(id) ON DELETE CASCADE
    );";
    $db->exec($sql);
    echo "Created orders table.\n";

    // Create tracking_status_history table
    $sql = "CREATE TABLE IF NOT EXISTS tracking_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        status VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    );";
    $db->exec($sql);
    echo "Created tracking_status_history table.\n";

    // Add admin user if not exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['admin@admin.com']);
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute(['Admin', 'admin@admin.com', $password]);
        echo "Created admin user (admin@admin.com / admin123).\n";
    } else {
        echo "Admin user already exists.\n";
    }

    // Add some sample carriers if not exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM carriers");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO carriers (name, color, base_cost, cost_per_kg, cost_per_km, max_weight, speed_kmh, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['БелПочта', '#3498db', 3.50, 0.50, 0.10, 20, 40, 'Национальная почтовая служба']);
        $stmt->execute(['Еаптека', '#2ecc71', 5.00, 0.75, 0.15, 30, 60, 'Экспресс доставка']);
        $stmt->execute(['CDEK', '#e74c3c', 4.00, 0.80, 0.12, 25, 50, 'Курьерская доставка']);
        echo "Added sample carriers.\n";
    } else {
        echo "Carriers already exist.\n";
    }

    echo "Database initialized successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>