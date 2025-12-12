<?php
require 'db.php';
if (!isset($_SESSION['user'])) header('Location: index.php');
$user = $_SESSION['user'];

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die('ID заказа не указан');
}

// Get order details
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    die('Заказ не найден');
}

// Get carrier information
$carrier_stmt = $db->prepare("SELECT * FROM carriers WHERE id = ?");
$carrier_stmt->execute([$order['carrier_id']]);
$carrier = $carrier_stmt->fetch();

// Get office information
$from_office_stmt = $db->prepare("SELECT * FROM offices WHERE id = ?");
$from_office_stmt->execute([$order['from_office']]);
$from_office = $from_office_stmt->fetch();

$to_office_stmt = $db->prepare("SELECT * FROM offices WHERE id = ?");
$to_office_stmt->execute([$order['to_office']]);
$to_office = $to_office_stmt->fetch();

// Calculate detailed cost breakdown
$base_cost = $carrier['base_cost'] ?? 0;
$weight_cost = $order['weight'] * ($carrier['cost_per_kg'] ?? 0);
$insurance_cost = 0;
$packaging_cost = 0;
$fragile_cost = 0;

// Calculate insurance cost (2% of base + weight cost)
if (!empty($order['insurance'])) {
    $insurance_cost = round(($base_cost + $weight_cost) * 0.02, 2);
}

// Calculate packaging cost (fixed 3 BYN)
if (!empty($order['packaging'])) {
    $packaging_cost = 3.00;
}

// Calculate fragile cost (1% of base + weight cost)
if (!empty($order['fragile'])) {
    $fragile_cost = round(($base_cost + $weight_cost) * 0.01, 2);
}

$calculated_total = $base_cost + $weight_cost + $insurance_cost + $packaging_cost + $fragile_cost;

// Generate PDF content
$pdf_content = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Чек заказа</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .company-info { margin-bottom: 20px; }
        .order-details { margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee; }
        .cost-breakdown { margin: 15px 0; }
        .cost-item { display: flex; justify-content: space-between; padding: 3px 0; }
        .total { font-size: 18px; font-weight: bold; text-align: right; margin-top: 20px; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='header'>
        <h2>Чек об оплате</h2>
        <h3>Служба доставки \\\"Express Delivery\\\"</h3>
    </div>
    
    <div class='company-info'>
        <p><strong>Адрес:</strong> г. Минск, ул. Примерная, 123</p>
        <p><strong>Телефон:</strong> +375-25-005-50-50</p>
        <p><strong>Email:</strong> freedeliverya@gmail.com</p>
    </div>
    
    <div class='order-details'>
        <p><strong>Номер чека:</strong> " . htmlspecialchars($order['track_number']) . "</p>
        <p><strong>Дата:</strong> " . date('d.m.Y H:i', strtotime($order['created_at'])) . "</p>
        <p><strong>Статус:</strong> Оплачен</p>
    </div>
    
    <div style='display: flex; margin: 20px 0;'>
        <div style='flex: 1;'>
            <h4>Отправитель:</h4>
            <p>" . htmlspecialchars($order['full_name'] ?? 'Н/Д') . "</p>
            <p>" . htmlspecialchars($order['home_address'] ?? 'Адрес не указан') . "</p>
        </div>
        <div style='flex: 1;'>
            <h4>Получатель:</h4>
            <p>" . htmlspecialchars($order['full_name'] ?? 'Н/Д') . "</p>
            <p>" . htmlspecialchars($order['home_address'] ?? 'Адрес не указан') . "</p>
        </div>
    </div>
    
    <div style='margin: 20px 0;'>
        <h4>Детали доставки:</h4>
        <div class='detail-row'>
            <span>Служба доставки:</span>
            <span style='color: " . $carrier['color'] . ";'>" . htmlspecialchars($carrier['name']) . "</span>
        </div>
        <div class='detail-row'>
            <span>Отделение отправления:</span>
            <span>" . htmlspecialchars($from_office['city'] ?? 'Н/Д') . ", " . htmlspecialchars($from_office['address'] ?? 'Н/Д') . "</span>
        </div>
        <div class='detail-row'>
            <span>Отделение получения:</span>
            <span>" . htmlspecialchars($to_office['city'] ?? 'Н/Д') . ", " . htmlspecialchars($to_office['address'] ?? 'Н/Д') . "</span>
        </div>
        <div class='detail-row'>
            <span>Вес посылки:</span>
            <span>" . $order['weight'] . " кг</span>
        </div>
        <div class='detail-row'>
            <span>Примерное время доставки:</span>
            <span>" . ($order['delivery_hours'] ?? 'Не указано') . " часов</span>
        </div>
    </div>
    
    <div class='cost-breakdown'>
        <h4>Дополнительная информация:</h4>
        <p>Документы: Накладная, Товарный чек</p>
        <p>Способ оплаты: Онлайн</p>
        
        <h4>Расшифровка стоимости:</h4>
        <div class='cost-item'><span>Базовая стоимость:</span> <span>" . number_format($base_cost, 2) . " BYN</span></div>
        <div class='cost-item'><span>Доставка (" . $order['weight'] . " кг × " . number_format($carrier['cost_per_kg'] ?? 0, 2) . " BYN/кг):</span> <span>" . number_format($weight_cost, 2) . " BYN</span></div>";
        
        if (!empty($order['insurance'])) {
            $pdf_content .= "
        <div class='cost-item'><span>Страховка (2%):</span> <span>" . number_format($insurance_cost, 2) . " BYN</span></div>";
        }
        
        if (!empty($order['packaging'])) {
            $pdf_content .= "
        <div class='cost-item'><span>Упаковка:</span> <span>" . number_format($packaging_cost, 2) . " BYN</span></div>";
        }
        
        if (!empty($order['fragile'])) {
            $pdf_content .= "
        <div class='cost-item'><span>Хрупкая посылка (1%):</span> <span>" . number_format($fragile_cost, 2) . " BYN</span></div>";
        }
        
        $pdf_content .= "
        <div class='cost-item' style='border-top: 1px solid #000; font-weight: bold;'><span>Итого:</span> <span>" . number_format($calculated_total, 2) . " BYN</span></div>
    </div>
    
    <div class='total'>
        Итоговая сумма: <span style='color: #28a745;'>" . number_format($order['cost'], 2) . " BYN</span>
    </div>
    
    <div class='footer'>
        <p>&copy; 2025 Служба доставки. Все права защищены.</p>
        <p>Данный чек является подтверждением оплаты заказа</p>
    </div>
</body>
</html>
";

// Set headers to force download
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="check_' . $order['track_number'] . '.html"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf_content;
?>