<?php require 'db.php';
if (!isset($_SESSION['user'])) header('Location: index.php');

$track = $_GET['track'] ?? '';

if (!$track) {
    die('Трек-номер не указан');
}

$order = $db->prepare("SELECT o.*, c.name as carrier_name FROM orders o LEFT JOIN carriers c ON o.carrier_id=c.id WHERE o.track_number=?");
$order->execute([$track]);
$order = $order->fetch();

if (!$order) {
    die('Заказ не найден');
}

// Define order status stages for timeline (normal delivery flow)
$status_stages = [
    'created' => ['name' => 'Создан', 'description' => 'Заказ создан'],
    'paid' => ['name' => 'Оплачен', 'description' => 'Заказ оплачен'], // Changed from 'Обработан' to 'Оплачен'
    'in_transit' => ['name' => 'В пути', 'description' => 'Посылка в пути'],
    'sort_center' => ['name' => 'Сорт. центр', 'description' => 'Посылка в сортировочном центре'],
    'out_for_delivery' => ['name' => 'У курьера', 'description' => 'Посылка у курьера в пути'],
    'delivered' => ['name' => 'Доставлен', 'description' => 'Заказ доставлен'] // Will show delivery date when status is delivered
];

// Define special status that are not part of normal delivery flow
$special_status = [
    'delayed' => ['name' => 'Задерживается', 'description' => 'Возможна задержка доставки'],
    'returned' => ['name' => 'Возвращен', 'description' => 'Заказ возвращен отправителю'],
    'cancelled' => ['name' => 'Отменен', 'description' => 'Заказ отменен']
];

// Get current status from the order (if exists) or default to 'created'
$current_status = $order['tracking_status'] ?? 'created';

// Check if this is a special status
$is_special_status = isset($special_status[$current_status]);

// Calculate progress percentage based on status if not special status
if (!$is_special_status) {
    $status_keys = array_keys($status_stages);
    $current_index = array_search($current_status, $status_keys);
    if ($current_index === false) {
        $current_index = 0; // Default to 'created' if status not found in normal flow
    }
    $progress_percentage = ($current_index / (count($status_keys) - 1)) * 100;
} else {
    $progress_percentage = 0; // No progress for special statuses
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отслеживание посылки</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .status-bar {
            height: 30px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        .status-progress {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #8BC34A);
            width: 0%;
            transition: width 0.5s ease;
        }
        .status-indicators {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        .status-indicator {
            text-align: center;
            flex: 1;
        }
        .status-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ddd;
            margin: 0 auto 5px;
        }
        .status-dot.active {
            background: #4CAF50;
            border: 2px solid #45a049;
        }
        .status-dot.completed {
            background: #4CAF50;
            border: 2px solid #45a049;
        }
        .status-label {
            font-size: 12px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-primary shadow-lg">
    <div class="container-fluid">
        <a class="navbar-brand">Отслеживание посылки</a>
        <div>
            <a href="calculator.php" class="btn btn-light me-2">Калькулятор</a>
            <a href="order_form.php" class="btn btn-success me-2">Оформить заказ</a>
            <a href="history.php" class="btn btn-warning me-2">История</a>
            <?php if($_SESSION['user']['role']==='admin'): ?><a href="admin/index.php" class="btn btn-danger me-2">Админка</a><?php endif; ?>
            <a href="logout.php" class="btn btn-outline-light">Выйти</a>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <div class="card shadow-lg">
        <div class="card-body">
            <h3 class="text-center">Статус посылки</h3>
            
            <!-- Status progress bar -->
            <div class="mt-4">
                <h5>Прогресс доставки:</h5>
                <?php if ($is_special_status): ?>
                    <div class="alert alert-warning">
                        <strong><?= htmlspecialchars($special_status[$current_status]['name']) ?>:</strong> 
                        <?= htmlspecialchars($special_status[$current_status]['description']) ?>
                    </div>
                <?php else: ?>
                    <div class="status-bar">
                        <div class="status-progress" style="width: <?= $progress_percentage ?>%;"></div>
                    </div>
                    
                    <div class="status-indicators">
                        <?php foreach($status_keys as $index => $status_key): ?>
                            <div class="status-indicator">
                                <div class="status-dot 
                                    <?php 
                                    if ($index < $current_index) echo 'completed'; 
                                    elseif ($index == $current_index) echo 'active'; 
                                    ?>">
                                </div>
                                <div class="status-label"><?= htmlspecialchars($status_stages[$status_key]['name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <p><strong>Трек-номер:</strong> <?= htmlspecialchars($order['track_number']) ?></p>
                    <p><strong>Оператор:</strong> <?= htmlspecialchars($order['carrier_name'] ?? 'Н/Д') ?></p>
                    <p><strong>Вес:</strong> <?= $order['weight'] ?> кг</p>
                    <p><strong>Стоимость:</strong> <?= $order['cost'] ?> BYN</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Дата создания:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                    <?php if(isset($order['delivery_date']) && $order['delivery_date']): ?>
                        <p><strong>Дата доставки:</strong> <?= date('d.m.Y', strtotime($order['delivery_date'])) ?></p>
                    <?php elseif($current_status === 'delivered'): ?>
                        <p><strong>Дата доставки:</strong> <?= date('d.m.Y') ?></p>
                    <?php endif; ?>
                    <p><strong>Текущий статус:</strong> 
                        <span class="badge bg-info"><?= htmlspecialchars($is_special_status ? $special_status[$current_status]['name'] : $status_stages[$current_status]['name']) ?></span>
                    </p>
                </div>
            </div>
            
            <?php if(isset($order['full_name']) && $order['full_name'] || isset($order['pickup_city']) && $order['pickup_city'] || isset($order['delivery_city']) && $order['delivery_city']): ?>
            <div class="mt-4">
                <h4>Детали заказа</h4>
                <div class="row">
                    <?php if(isset($order['full_name']) && $order['full_name']): ?>
                    <div class="col-md-6">
                        <p><strong>ФИО:</strong> <?= htmlspecialchars($order['full_name']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($order['home_address']) && $order['home_address']): ?>
                    <div class="col-md-6">
                        <p><strong>Домашний адрес:</strong> <?= htmlspecialchars($order['home_address']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($order['pickup_city']) && $order['pickup_city'] && isset($order['pickup_address']) && $order['pickup_address']): ?>
                    <div class="col-md-6">
                        <p><strong>Адрес получения:</strong> <?= htmlspecialchars($order['pickup_city']) ?>, <?= htmlspecialchars($order['pickup_address']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($order['delivery_city']) && $order['delivery_city'] && isset($order['delivery_address']) && $order['delivery_address']): ?>
                    <div class="col-md-6">
                        <p><strong>Адрес доставки:</strong> <?= htmlspecialchars($order['delivery_city']) ?>, <?= htmlspecialchars($order['delivery_address']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($order['insurance']) && $order['insurance'] || isset($order['packaging']) && $order['packaging'] || isset($order['fragile']) && $order['fragile']): ?>
                    <div class="col-md-12">
                        <p><strong>Дополнительно:</strong> 
                            <?php if(isset($order['insurance']) && $order['insurance']): ?><span class="badge bg-warning me-1">Страховка</span><?php endif; ?>
                            <?php if(isset($order['packaging']) && $order['packaging']): ?><span class="badge bg-info me-1">Упаковка</span><?php endif; ?>
                            <?php if(isset($order['fragile']) && $order['fragile']): ?><span class="badge bg-danger me-1">Хрупкое</span><?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="history.php" class="btn btn-primary">Вернуться к истории заказов</a>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-5 py-4 bg-light border-top">
    <div class="container text-center">
        <p class="mb-1">&copy; 2025 Служба доставки. Все права защищены.</p>
        <p class="mb-1">Контактный телефон: +375-25-005-50-50</p>
        <p class="mb-0">Email: freedeliverya@gmail.com</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>