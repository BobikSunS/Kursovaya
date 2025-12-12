<?php 
require '../db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php'); 
    exit;
}

// Обработка добавления нового оператора
if (isset($_POST['action']) && $_POST['action'] === 'add_carrier') {
    $name = trim($_POST['name'] ?? '');
    $color = $_POST['color'] ?? '#000000';
    $base_cost = (float)($_POST['base_cost'] ?? 0);
    $cost_per_kg = (float)($_POST['cost_per_kg'] ?? 0);
    $cost_per_km = (float)($_POST['cost_per_km'] ?? 0);
    $max_weight = (float)($_POST['max_weight'] ?? 0);
    $speed_kmh = (float)($_POST['speed_kmh'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    if (!empty($name)) {
        $stmt = $db->prepare("INSERT INTO carriers (name, color, base_cost, cost_per_kg, cost_per_km, max_weight, speed_kmh, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $color, $base_cost, $cost_per_kg, $cost_per_km, $max_weight, $speed_kmh, $description]);
    }
}

// Обработка удаления оператора
if (isset($_POST['action']) && $_POST['action'] === 'delete_carrier') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM carriers WHERE id = ?");
        $stmt->execute([$id]);
    }
}

// Обработка редактирования тарифа
if (isset($_POST['action']) && $_POST['action'] === 'update_carrier') {
    $id = (int)($_POST['id'] ?? 0);
    $base_cost = (float)($_POST['base_cost'] ?? 0);
    $cost_per_kg = (float)($_POST['cost_per_kg'] ?? 0);
    $cost_per_km = (float)($_POST['cost_per_km'] ?? 0);
    $max_weight = (float)($_POST['max_weight'] ?? 0);
    $speed_kmh = (float)($_POST['speed_kmh'] ?? 0);

    $stmt = $db->prepare("UPDATE carriers SET base_cost=?, cost_per_kg=?, cost_per_km=?, max_weight=?, speed_kmh=? WHERE id=?");
    $stmt->execute([$base_cost, $cost_per_kg, $cost_per_km, $max_weight, $speed_kmh, $id]);
}

// Обработка изменения статуса заказа
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? 'created';
    
    // Check if tracking_status column exists
    $columns_query = $db->query("SHOW COLUMNS FROM orders");
    $existing_columns = [];
    while ($row = $columns_query->fetch()) {
        $existing_columns[] = $row['Field'];
    }
    
    // Update the tracking_status in the orders table if column exists
    if (in_array('tracking_status', $existing_columns)) {
        try {
            // Get the current status to compare with new status
            $current_order = $db->prepare("SELECT tracking_status FROM orders WHERE id = ?");
            $current_order->execute([$order_id]);
            $current_order_data = $current_order->fetch();
            $current_status = $current_order_data['tracking_status'] ?? 'created';
            
            // Update the tracking_status in the orders table
            $stmt = $db->prepare("UPDATE orders SET tracking_status=? WHERE id=?");
            $stmt->execute([$new_status, $order_id]);
            
            // If the new status is 'delivered', update the delivery date
            if ($new_status === 'delivered') {
                $delivery_stmt = $db->prepare("UPDATE orders SET delivery_date = CURDATE() WHERE id = ?");
                $delivery_stmt->execute([$order_id]);
            }
            
            // Check if tracking_status_history table exists
            $tables_query = $db->query("SHOW TABLES LIKE 'tracking_status_history'");
            if ($tables_query->rowCount() > 0) {
                // Add to status history if table exists and status actually changed
                if ($current_status !== $new_status) {
                    $stmt = $db->prepare("INSERT INTO tracking_status_history (order_id, status, description, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$order_id, $new_status, "Статус изменен с '{$current_status}' на '{$new_status}'"]);
                }
            }
        } catch (PDOException $e) {
            // Handle error silently or log it
            error_log("Error updating order status: " . $e->getMessage());
        }
    }
}

// Обработка добавления нового отделения
if (isset($_POST['action']) && $_POST['action'] === 'add_office') {
    $carrier_id = (int)($_POST['carrier_id'] ?? 0);
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if ($carrier_id > 0 && !empty($city) && !empty($address)) {
        $stmt = $db->prepare("INSERT INTO offices (carrier_id, city, address) VALUES (?, ?, ?)");
        $stmt->execute([$carrier_id, $city, $address]);
    }
}

// Обработка удаления отделения
if (isset($_POST['action']) && $_POST['action'] === 'delete_office') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM offices WHERE id = ?");
        $stmt->execute([$id]);
    }
}

// Обработка добавления нового маршрута
if (isset($_POST['action']) && $_POST['action'] === 'add_route') {
    $from_office = (int)($_POST['from_office'] ?? 0);
    $to_office = (int)($_POST['to_office'] ?? 0);
    $distance = (int)($_POST['distance'] ?? 0);
    
    if ($from_office > 0 && $to_office > 0 && $distance > 0) {
        // Проверим, что маршрут не существует
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM routes WHERE from_office = ? AND to_office = ?");
        $check_stmt->execute([$from_office, $to_office]);
        if ($check_stmt->fetchColumn() == 0) {
            $stmt = $db->prepare("INSERT INTO routes (from_office, to_office, distance_km) VALUES (?, ?, ?)");
            $stmt->execute([$from_office, $to_office, $distance]);
        }
    }
}

// Обработка удаления маршрута
if (isset($_POST['action']) && $_POST['action'] === 'delete_route') {
    $from_office = (int)($_POST['from_office'] ?? 0);
    $to_office = (int)($_POST['to_office'] ?? 0);
    
    if ($from_office > 0 && $to_office > 0) {
        $stmt = $db->prepare("DELETE FROM routes WHERE from_office = ? AND to_office = ?");
        $stmt->execute([$from_office, $to_office]);
    }
}

// Статистика
$total_orders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_revenue = $db->query("SELECT SUM(cost) FROM orders")->fetchColumn() ?: 0;
$users_count = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();

// Заказы по дням (последние 7 дней)
$stats_days = $db->query("
    SELECT DATE(created_at) as d, COUNT(*) as cnt, SUM(cost) as sum 
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d
")->fetchAll();

// По операторам
$stats_carriers = $db->query("
    SELECT c.name, COUNT(o.id) as orders, SUM(o.cost) as revenue 
    FROM orders o 
    JOIN carriers c ON o.carrier_id = c.id 
    GROUP BY c.id
")->fetchAll();

// Топ направлений
$top_routes = $db->query("
    SELECT 
        of1.city as from_city, of2.city as to_city, COUNT(*) as cnt
    FROM orders o
    JOIN offices of1 ON o.from_office = of1.id
    JOIN offices of2 ON o.to_office = of2.id
    GROUP BY o.from_office, o.to_office
    ORDER BY cnt DESC LIMIT 5
")->fetchAll();

// Заказы для отслеживания статуса
// Check if tracking_status column exists
$columns_query = $db->query("SHOW COLUMNS FROM orders");
$existing_columns = [];
while ($row = $columns_query->fetch()) {
    $existing_columns[] = $row['Field'];
}

if (in_array('tracking_status', $existing_columns)) {
    // Use tracking_status column if it exists
    $recent_orders = $db->query("
        SELECT o.*, c.name as carrier_name, u.name as user_name
        FROM orders o
        LEFT JOIN carriers c ON o.carrier_id = c.id
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 20
    ")->fetchAll();
} else {
    // Use all columns but ensure tracking_status is available with default value
    $recent_orders = $db->query("
        SELECT o.*, c.name as carrier_name, u.name as user_name, 'created' as tracking_status
        FROM orders o
        LEFT JOIN carriers c ON o.carrier_id = c.id
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 20
    ")->fetchAll();
}

$carriers = $db->query("SELECT * FROM carriers")->fetchAll();
$offices = $db->query("SELECT o.*, c.name as carrier_name FROM offices o LEFT JOIN carriers c ON o.carrier_id = c.id ORDER BY c.name, o.city")->fetchAll();

// Define status options
$status_options = [
    'created' => 'Создан',
    'paid' => 'Оплачен',
    'in_transit' => 'В пути',
    'sort_center' => 'Сорт. центр',
    'out_for_delivery' => 'У курьера',
    'delivered' => 'Доставлен',
    'delayed' => 'Задерживается',
    'cancelled' => 'Отменен',
    'returned' => 'Возвращен'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card { background: rgba(255,255,255,0.1); border-radius: 15px; padding: 20px; text-align: center; }
        body.dark .stat-card { background: rgba(255,255,255,0.08); }
        .edit-input { width: 80px; font-size: 0.9em; }
        .status-badge { font-size: 0.8em; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <span class="navbar-brand">Админ-панель</span>
        <div>
            <a href="../calculator.php" class="btn btn-outline-light me-2">На сайт</a>
            <a href="../logout.php" class="btn btn-danger">Выйти</a>
        </div>
    </div>
</nav>

<div class="container mt-4">

    <!-- Статистика -->
    <div class="row mb-5 text-white">
        <div class="col-md-3">
            <div class="stat-card">
                <h3><?= $total_orders ?></h3>
                <p>Всего заказов</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h3><?= round($total_revenue, 2) ?> BYN</h3>
                <p>Выручка</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h3><?= $users_count ?></h3>
                <p>Пользователей</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <h3><?= round($total_revenue / max($total_orders,1), 2) ?> BYN</h3>
                <p>Средний чек</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="row">
                <!-- Редактирование тарифов -->
                <div class="col-md-12 mb-4">
                    <div class="card shadow-lg">
                        <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #3498db, #2c3e50);">
                            <h4 class="mb-0"><i class="fas fa-cogs me-2"></i>Тарифы операторов</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Оператор</th>
                                            <th>База</th>
                                            <th>За кг</th>
                                            <th>За км</th>
                                            <th>Макс. вес</th>
                                            <th>Скорость</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($carriers as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge rounded-pill me-2" style="background-color: <?= $c['color'] ?>">&nbsp;</span>
                                                    <strong style="color:<?= $c['color'] ?>"><?= htmlspecialchars($c['name']) ?></strong>
                                                </div>
                                            </td>
                                            <form method="POST" class="align-middle">
                                                <input type="hidden" name="action" value="update_carrier">
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <td><input name="base_cost" value="<?= $c['base_cost'] ?>" class="form-control form-control-sm" step="0.1"></td>
                                                <td><input name="cost_per_kg" value="<?= $c['cost_per_kg'] ?>" class="form-control form-control-sm" step="0.05"></td>
                                                <td><input name="cost_per_km" value="<?= $c['cost_per_km'] ?>" class="form-control form-control-sm" step="0.001"></td>
                                                <td><input name="max_weight" value="<?= $c['max_weight'] ?>" class="form-control form-control-sm"></td>
                                                <td><input name="speed_kmh" value="<?= $c['speed_kmh'] ?>" class="form-control form-control-sm"></td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <button class="btn btn-sm btn-success me-1" title="Сохранить изменения"><i class="fas fa-save"></i></button>
                                                        <a href="routes.php?carrier=<?= $c['id'] ?>" class="btn btn-sm btn-info me-1" title="Маршруты"><i class="fas fa-route"></i></a>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Удалить оператора <?= addslashes(htmlspecialchars($c['name'])) ?>?');">
                                                            <input type="hidden" name="action" value="delete_carrier">
                                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Удалить"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </form>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <!-- Добавление нового оператора -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h4>Добавить нового оператора</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="add_carrier">
                                <div class="mb-3">
                                    <label class="form-label">Название оператора</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Цвет (HEX)</label>
                                    <input type="color" name="color" class="form-control form-control-color" value="#0066cc" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Базовая стоимость</label>
                                    <input type="number" step="0.01" name="base_cost" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Стоимость за кг</label>
                                    <input type="number" step="0.01" name="cost_per_kg" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Стоимость за км</label>
                                    <input type="number" step="0.001" name="cost_per_km" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Макс. вес (кг)</label>
                                    <input type="number" step="0.1" name="max_weight" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Скорость (км/ч)</label>
                                    <input type="number" step="0.1" name="speed_kmh" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Описание</label>
                                    <input type="text" name="description" class="form-control">
                                </div>
                                <button type="submit" class="btn btn-info btn-lg w-100">Добавить оператора</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <!-- Добавление нового отделения -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h4>Добавить новое отделение</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="add_office">
                                <div class="mb-3">
                                    <label class="form-label">Оператор</label>
                                    <select name="carrier_id" class="form-select" required>
                                        <option value="">Выберите оператора</option>
                                        <?php foreach($carriers as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Город</label>
                                    <input type="text" name="city" class="form-control" placeholder="Например: Минск" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Адрес</label>
                                    <input type="text" name="address" class="form-control" placeholder="Полный адрес отделения" required>
                                </div>
                                <button type="submit" class="btn btn-success btn-lg w-100">Добавить отделение</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Список отделений с возможностью удаления -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h4>Список отделений</h4>
                        </div>
                        <div class="card-body">
                            <!-- Search input for offices -->
                            <div class="mb-3">
                                <input type="text" id="office-search" class="form-control" placeholder="Поиск по адресу отделения...">
                            </div>
                            
                            <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                                <table class="table table-hover">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Оператор</th>
                                            <th>Город</th>
                                            <th>Адрес</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="office-table-body">
                                        <?php foreach($offices as $office): ?>
                                        <tr>
                                            <td><strong style="color:<?= $office['carrier_id'] ? $carriers[array_search($office['carrier_id'], array_column($carriers, 'id'))]['color'] ?? '#000000' : '#000000' ?>"><?= htmlspecialchars($office['carrier_name'] ?? 'Н/Д') ?></strong></td>
                                            <td><?= htmlspecialchars($office['city']) ?></td>
                                            <td id="office-address-<?= $office['id'] ?>"><?= htmlspecialchars($office['address']) ?></td>
                                            <td>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Удалить отделение в <?= addslashes(htmlspecialchars($office['city'])) ?>, <?= addslashes(htmlspecialchars($office['address'])) ?>?');">
                                                    <input type="hidden" name="action" value="delete_office">
                                                    <input type="hidden" name="id" value="<?= $office['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right column with order status management and additional information -->
        <div class="col-lg-4 mb-4">

            
            <!-- Full order status management -->
            <div class="card mb-4" id="order-status-full">
                <div class="card-header bg-warning text-dark">
                    <h5>Полное управление статусами</h5>
                </div>
                <div class="card-body">
                    <!-- Search input for orders by track number -->
                    <div class="mb-3">
                        <input type="text" id="order-track-search" class="form-control" placeholder="Поиск по трек-номеру заказа...">
                    </div>
                    
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Трек</th>
                                    <th>Статус</th>
                                    <th>Изменить</th>
                                </tr>
                            </thead>
                            <tbody id="order-status-table-body">
                                <?php foreach(array_slice($recent_orders, 0, 10) as $order): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($order['track_number']) ?></strong></td>
                                    <td>
                                        <span class="badge bg-info status-badge">
                                            <?= htmlspecialchars($status_options[$order['tracking_status'] ?? 'created'] ?? 'Обработан') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <form method="POST" class="d-inline status-form" onsubmit="updateStatus(event, <?= $order['id'] ?>)">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <select name="new_status" class="form-select form-select-sm d-inline w-auto me-1 status-select" data-order-id="<?= $order['id'] ?>">
                                                    <?php foreach($status_options as $status_key => $status_name): ?>
                                                    <option value="<?= $status_key ?>" <?= (($order['tracking_status'] ?? 'created') == $status_key) ? 'selected' : '' ?>>
                                                        <?= $status_name ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-sm btn-warning">Изменить</button>
                                            </form>
                                            <a href="../track.php?track=<?= urlencode($order['track_number']) ?>" class="btn btn-sm btn-primary">Перейти</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            

            
            <!-- Recent activity -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5>Недавние заказы</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach(array_slice($recent_orders, 0, 5) as $order): ?>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?= htmlspecialchars($order['track_number']) ?></h6>
                                <small><?= date('d.m H:i', strtotime($order['created_at'])) ?></small>
                            </div>
                            <p class="mb-1"><?= htmlspecialchars($order['user_name'] ?? 'Н/Д') ?></p>
                            <small><?= $order['cost'] ?> BYN, <?= htmlspecialchars($order['carrier_name'] ?? 'Н/Д') ?></small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>


    <!-- Графики -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5>Заказы за 7 дней</h5>
                </div>
                <div class="card-body">
                    <canvas id="chartDays"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5>Выручка по операторам</h5>
                </div>
                <div class="card-body">
                    <canvas id="chartCarriers"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// График заказов по дням
new Chart(document.getElementById('chartDays'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($stats_days, 'd')) ?>,
        datasets: [{
            label: 'Заказы',
            data: <?= json_encode(array_column($stats_days, 'cnt')) ?>,
            borderColor: '#00ff88',
            backgroundColor: 'rgba(0,255,136,0.2)',
            tension: 0.4,
            fill: true
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

// Выручка по операторам
new Chart(document.getElementById('chartCarriers'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($stats_carriers, 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($stats_carriers, 'revenue')) ?>,
            backgroundColor: ['#ff6384','#36a2eb','#ffce56','#4bc0c0','#9966ff','#ff9f40']
        }]
    },
    options: { responsive: true }
});

// Function to update status via AJAX
function updateStatus(event, orderId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Show a temporary success message
        const row = form.closest('tr');
        const statusCell = row.querySelector('.status-badge');
        const newStatusValue = form.querySelector('select[name="new_status"]').value;
        const newStatusText = form.querySelector('select[name="new_status"] option:checked').text;
        
        // Update the status badge text
        statusCell.textContent = newStatusText;
        
        // Show temporary success feedback
        statusCell.classList.remove('bg-info');
        statusCell.classList.add('bg-success');
        setTimeout(() => {
            statusCell.classList.remove('bg-success');
            statusCell.classList.add('bg-info');
        }, 1000);
    })
    .catch(error => {
        console.error('Error updating status:', error);
        alert('Ошибка при обновлении статуса заказа');
    });
}

// Search functionality for offices
document.getElementById('office-search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const tableBody = document.getElementById('office-table-body');
    const rows = tableBody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const addressCell = rows[i].querySelector('td:nth-child(3)'); // Address is in 3rd column
        if (addressCell) {
            const addressText = addressCell.textContent.toLowerCase();
            if (addressText.includes(searchTerm)) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }
});

// Search functionality for orders by track number
document.getElementById('order-track-search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const tableBody = document.getElementById('order-status-table-body');
    const rows = tableBody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const trackCell = rows[i].querySelector('td:first-child'); // Track number is in first column
        if (trackCell) {
            const trackText = trackCell.textContent.toLowerCase();
            if (trackText.includes(searchTerm)) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }
});
</script>
</body>
</html>