<?php 
require 'db.php';
if (!isset($_SESSION['user'])) header('Location: index.php');
$user = $_SESSION['user'];

// Get order details from session or query parameters
$order_id = $_GET['order_id'] ?? null;
$track_number = $_GET['track'] ?? null;

if ($order_id) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user['id']]);
    $order = $stmt->fetch();
} elseif ($track_number) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE track_number = ? AND user_id = ?");
    $stmt->execute([$track_number, $user['id']]);
    $order = $stmt->fetch();
} else {
    header('Location: history.php');
    exit;
}

if (!$order) {
    header('Location: history.php');
    exit;
}

// Check if order is already paid
if (isset($order['payment_status']) && $order['payment_status'] === 'paid') {
    // Redirect to order details or show message that order is already paid
    header('Location: history.php?message=order_already_paid');
    exit;
}

$success = false;

// Handle payment confirmation
if (isset($_POST['confirm_payment'])) {
    // Update order status to paid/confirmed
    $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', tracking_status = 'paid' WHERE id = ?");
    $stmt->execute([$order["id"]]);
    
    // Add to status history
    $status_stmt = $db->prepare("INSERT INTO tracking_status_history (order_id, status, description, created_at) VALUES (?, ?, ?, NOW())");
    $status_stmt->execute([$order["id"], "paid", "Заказ оплачен и обработан"]);
    
    // Update the delivery date if the order is marked as delivered
    if ($order['tracking_status'] === 'delivered') {
        // Check if delivery_date column exists first
        $columns_query = $db->query("SHOW COLUMNS FROM orders");
        $existing_columns = [];
        while ($row = $columns_query->fetch()) {
            $existing_columns[] = $row['Field'];
        }
        
        if (in_array('delivery_date', $existing_columns)) {
            $update_delivery_date = $db->prepare("UPDATE orders SET delivery_date = CURDATE() WHERE id = ?");
            $update_delivery_date->execute([$order["id"]]);
        }
    }

    // Redirect to success page
    header("Location: payment_success.php?order_id=" . $order["id"]);
    exit;
}

// Get carrier information
$carrier_stmt = $db->prepare("SELECT * FROM carriers WHERE id = ?");
$carrier_stmt->execute([$order['carrier_id']]);
$carrier = $carrier_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплата заказа</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .payment-card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        body.dark .payment-card {
            background: #16213e !important;
        }
        .payment-method {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        body.dark .payment-method:hover {
            background-color: #1a2a4a;
        }
        .payment-method.selected {
            border-color: #0d6efd;
            background-color: #e7f3ff;
        }
        body.dark .payment-method.selected {
            background-color: #1a2a4a;
            border-color: #4dabf7;
        }
        .card-input {
            display: none;
        }
        .cash-notice {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        body.dark .cash-notice {
            background-color: #3a2910;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-primary shadow-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand">Оплата заказа</a>
        <div>
            <a href="calculator.php" class="btn btn-light me-2">Калькулятор</a>
            <a href="profile.php" class="btn btn-light me-2">Профиль</a>
            <a href="history.php" class="btn btn-warning me-2">История</a>
            <?php if($user['role']==='admin'): ?>
                <a href="admin/index.php" class="btn btn-danger me-2">Админка</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-light">Выйти</a>
        </div>
    </div>
</nav>

<!-- Spacer to prevent content from being hidden behind fixed navbar -->
<div style="height: 80px;"></div>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <?php if ($success): ?>
                <!-- Payment success message -->
                <div class="card bg-success text-white text-center p-5 mb-5">
                    <h2 class="text-white">Платеж подтвержден!</h2>
                    <p class="lead text-white">Ваш заказ №<?= htmlspecialchars($order['track_number']) ?> оплачен и принят в обработку</p>
                    <h3 class="text-warning"><?= number_format($order['cost'], 2) ?> BYN</h3>
                    <p class="text-white">Спасибо за доверие к нашей службе доставки!</p>
                    <a href="history.php" class="btn btn-light btn-lg mt-3">Перейти в историю заказов</a>
                </div>
            <?php else: ?>
                <h2 class="text-center mb-4">Оплата заказа №<?= htmlspecialchars($order['track_number']) ?></h2>
                
                <!-- Order summary -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5>Информация о заказе</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Служба доставки:</strong> 
                                    <span style="color: <?= $carrier['color'] ?>"><?= htmlspecialchars($carrier['name']) ?></span>
                                </p>
                                <p><strong>Вес:</strong> <?= $order['weight'] ?> кг</p>
                                <p><strong>Стоимость:</strong> <?= number_format($order['cost'], 2) ?> BYN</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Дата создания:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                                <p><strong>Статус оплаты:</strong> 
                                    <span class="badge bg-<?= (isset($order['payment_status']) && $order['payment_status'] === 'paid') ? 'success' : 'warning' ?>">
                                        <?= (isset($order['payment_status']) && $order['payment_status'] === 'paid') ? 'Оплачен' : 'Не оплачен' ?>
                                    </span>
                                </p>
                                <p><strong>Статус доставки:</strong> 
                                    <span class="badge bg-<?= 
                                        (isset($order['tracking_status']) && in_array(strtolower($order['tracking_status']), ['delivered', 'доставлен'])) ? 'success' : 
                                        ((isset($order['tracking_status']) && in_array(strtolower($order['tracking_status']), ['in_transit', 'в пути'])) ? 'warning' : 
                                        ((isset($order['tracking_status']) && in_array(strtolower($order['tracking_status']), ['paid', 'оплачен'])) ? 'info' : 
                                        'secondary'))
                                    ?>">
                                        <?= htmlspecialchars($order['tracking_status'] ?? 'Создан') ?>
                                    </span>
                                </p>
                                <p><strong>Трек-номер:</strong> <?= htmlspecialchars($order['track_number']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment methods -->
                <div class="card payment-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5>Способ оплаты</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="payment-method" onclick="selectPaymentMethod('card')">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="card" value="card">
                                        <label class="form-check-label fw-bold" for="card">
                                            Банковской картой
                                        </label>
                                    </div>
                                    <div class="mt-2">
                                        <p class="mb-1">Безопасная оплата через платежный шлюз</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="payment-method" onclick="selectPaymentMethod('cash')">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash">
                                        <label class="form-check-label fw-bold" for="cash">
                                            Наличными при получении
                                        </label>
                                    </div>
                                    <div class="mt-2">
                                        <p class="mb-1">Оплата курьеру при получении</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="cash-notice" id="cash-notice" style="display: none;">
                            <strong>Важно:</strong> При оплате наличными будут взиматься дополнительные сборы за доставку.
                        </div>
                        
                        <div id="card-form" class="card-input mt-4">
                            <h5>Информация о карте</h5>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Номер карты</label>
                                    <input type="text" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19" id="card-number" oninput="formatCardNumber()">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Срок действия</label>
                                    <input type="text" class="form-control" placeholder="MM/YY" maxlength="5" id="expiry-date">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CVV</label>
                                    <input type="text" class="form-control" placeholder="123" maxlength="3" id="cvv">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Имя владельца карты</label>
                                    <input type="text" class="form-control" placeholder="ИВАНОВ ИВАН ИВАНОВИЧ">
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" id="payment-form">
                            <input type="hidden" name="confirm_payment" value="1">
                            <button type="submit" class="btn btn-success btn-lg w-100" id="pay-button" disabled>
                                Подтвердить оплату
                            </button>
                        </form>
                        
                        <!-- Temporary button for testing -->
                        <button class="btn btn-warning btn-lg w-100 mt-3" onclick="confirmPayment()">
                            Формально оплатить (для проверки)
                        </button>
                        
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-5 py-4 bg-light border-top">
    <div class="container text-center">
        <p class="mb-1 text-muted" style="opacity: 0.7;">&copy; 2025 Служба доставки. Все права защищены.</p>
        <p class="mb-1 text-muted" style="opacity: 0.7;">Контактный телефон: +375-25-005-50-50</p>
        <p class="mb-0 text-muted" style="opacity: 0.7;">Email: freedeliverya@gmail.com</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedPaymentMethod = null;

function selectPaymentMethod(method) {
    // Update radio selection
    document.getElementById(method).checked = true;
    
    // Update UI
    const methods = document.querySelectorAll('.payment-method');
    methods.forEach(m => m.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    
    // Show/hide card form
    document.getElementById('card-form').style.display = method === 'card' ? 'block' : 'none';
    
    // Show/hide cash notice
    document.getElementById('cash-notice').style.display = method === 'cash' ? 'block' : 'none';
    
    // Enable pay button
    document.getElementById('pay-button').disabled = false;
    
    selectedPaymentMethod = method;
}

function formatCardNumber() {
    let input = document.getElementById('card-number');
    let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    let formattedValue = '';
    
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) formattedValue += ' ';
        formattedValue += value[i];
    }
    
    input.value = formattedValue;
}

function confirmPayment() {
    // Simulate payment confirmation for testing
    if (confirm('Вы уверены, что хотите подтвердить оплату?')) {
        // Submit the form
        document.getElementById('payment-form').submit();
    }
}

// Apply saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark');
    }
    
    // Set up a global theme listener for cross-page consistency
    window.addEventListener('storage', function(e) {
        if (e.key === 'theme') {
            if (e.newValue === 'dark') {
                document.body.classList.add('dark');
            } else {
                document.body.classList.remove('dark');
            }
        }
    });
});


</script>
</body>
</html>