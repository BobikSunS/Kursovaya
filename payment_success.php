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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Платеж подтвержден</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .receipt-card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        body.dark .receipt-card {
            background: #16213e !important;
        }
        .receipt-header {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        body.dark .receipt-header {
            border-bottom: 2px solid #4a5568;
        }
        .receipt-item {
            border-bottom: 1px solid #eee;
            padding: 8px 0;
        }
        body.dark .receipt-item {
            border-bottom: 1px solid #2d3748;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-success shadow-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand">Платеж подтвержден</a>
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
            <!-- Payment success message -->
            <div class="card bg-success text-white text-center p-5 mb-5">
                <h2 class="text-black">Платеж подтвержден!</h2>
                <p class="lead text-black">Ваш заказ №<strong class="text-warning"><?= htmlspecialchars($order['track_number']) ?></strong> оплачен и принят в обработку</p>
                <h3 class="text-warning"><?= number_format($order['cost'], 2) ?> BYN</h3>
                <p class="text-black">Спасибо за доверие к нашей службе доставки!</p>
            </div>
            
            <!-- Receipt details -->
            <div class="card receipt-card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5>Чек заказа №<?= htmlspecialchars($order['track_number']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="receipt-header">
                        <h4 class="text-center mb-3">Чек об оплате</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Компания:</strong> Служба доставки "Express Delivery"</p>
                                <p><strong>Адрес:</strong> г. Минск, ул. Примерная, 123</p>
                                <p><strong>Телефон:</strong> +375-25-005-50-50</p>
                                <p><strong>Email:</strong> freedeliverya@gmail.com</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p><strong>Номер чека:</strong> <?= htmlspecialchars($order['track_number']) ?></p>
                                <p><strong>Дата:</strong> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></p>
                                <p><strong>Статус:</strong> <span class="badge bg-success">Оплачен</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Отправитель:</h6>
                            <p><?= htmlspecialchars($order['full_name'] ?? ($user['name'] ?? $user['login'])) ?></p>
                            <p><?= htmlspecialchars($order['home_address'] ?? 'г. Минск') ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Получатель:</h6>
                            <p><?= htmlspecialchars($order['full_name'] ?? 'Н/Д') ?></p>
                            <p><?= htmlspecialchars($order['home_address'] ?? 'Адрес не указан') ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Детали доставки:</h6>
                        <div class="receipt-item">
                            <div class="row">
                                <div class="col-6">Служба доставки:</div>
                                <div class="col-6" style="color: <?= $carrier['color'] ?>"><?= htmlspecialchars($carrier['name']) ?></div>
                            </div>
                        </div>
                        <div class="receipt-item">
                            <div class="row">
                                <div class="col-6">Отделение отправления:</div>
                                <div class="col-6"><?= htmlspecialchars($from_office['city'] ?? 'Н/Д') ?>, <?= htmlspecialchars($from_office['address'] ?? 'Н/Д') ?></div>
                            </div>
                        </div>
                        <div class="receipt-item">
                            <div class="row">
                                <div class="col-6">Отделение получения:</div>
                                <div class="col-6"><?= htmlspecialchars($to_office['city'] ?? 'Н/Д') ?>, <?= htmlspecialchars($to_office['address'] ?? 'Н/Д') ?></div>
                            </div>
                        </div>
                        <div class="receipt-item">
                            <div class="row">
                                <div class="col-6">Вес посылки:</div>
                                <div class="col-6"><?= $order['weight'] ?> кг</div>
                            </div>
                        </div>
                        <div class="receipt-item">
                            <div class="row">
                                <div class="col-6">Примерное время доставки:</div>
                                <div class="col-6">
                                    <?php
                                    // Calculate approximate delivery time in days based on delivery_hours
                                    $delivery_days = $order['delivery_hours'] ? ceil($order['delivery_hours'] / 24) : 2;
                                    if ($delivery_days == 1) {
                                        echo "1 день";
                                    } elseif ($delivery_days <= 5) {
                                        echo $delivery_days . " дня";
                                    } else {
                                        echo $delivery_days . " дней";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Дополнительная информация:</h6>
                            <p>Документы: Накладная, Товарный чек</p>
                            <p>Способ оплаты: Онлайн</p>
                            
                            <?php
                            // Get carrier information for cost calculation
                            $carrier_stmt = $db->prepare("SELECT * FROM carriers WHERE id = ?");
                            $carrier_stmt->execute([$order['carrier_id']]);
                            $carrier = $carrier_stmt->fetch();
                            
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
                            ?>
                            
                            <h6 class="mt-3">Расшифровка стоимости:</h6>
                            <ul class="list-unstyled">
                                <li>Базовая стоимость: <?= number_format($base_cost, 2) ?> BYN</li>
                                <li>Доставка (<?= $order['weight'] ?> кг × <?= number_format($carrier['cost_per_kg'] ?? 0, 2) ?> BYN/кг): <?= number_format($weight_cost, 2) ?> BYN</li>
                                <?php if (!empty($order['insurance'])): ?>
                                <li>Страховка (2%): <?= number_format($insurance_cost, 2) ?> BYN</li>
                                <?php endif; ?>
                                <?php if (!empty($order['packaging'])): ?>
                                <li>Упаковка: <?= number_format($packaging_cost, 2) ?> BYN</li>
                                <?php endif; ?>
                                <?php if (!empty($order['fragile'])): ?>
                                <li>Хрупкая посылка (1%): <?= number_format($fragile_cost, 2) ?> BYN</li>
                                <?php endif; ?>
                                <li class="fw-bold border-top pt-1">Итого: <?= number_format($calculated_total, 2) ?> BYN</li>
                            </ul>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6>Итого:</h6>
                            <h4 class="text-success"><?= number_format($order['cost'], 2) ?> BYN</h4>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-light">
                    <div class="text-center">
                        <a href="generate_pdf.php?order_id=<?= $order['id'] ?>" class="btn btn-primary me-2" target="_blank">
                            <i class="fas fa-file-pdf"></i> Скачать PDF
                        </a>
                        <a href="history.php" class="btn btn-success">Перейти в историю заказов</a>
                    </div>
                </div>
            </div>
            
            <!-- FAQ section -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5>Часто задаваемые вопросы</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Как отследить мой заказ?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Вы можете отслеживать ваш заказ по трек-номеру <?= htmlspecialchars($order['track_number']) ?> в разделе "История заказов" или на официальном сайте службы доставки.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Какие гарантии доставки?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Мы предоставляем гарантию на доставку согласно условиям выбранной службы. Среднее время доставки составляет 1-2 дня.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Что делать при повреждении посылки?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    В случае повреждения посылки при доставке, пожалуйста, свяжитесь с нами по телефону +375-25-005-50-50 или через форму обратной связи. Мы поможем решить вопрос в кратчайшие сроки.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-5 py-4 bg-light border-top">
    <div class="container text-center">
        <p class="mb-1 text-muted" style="opacity: 0.5; color: #999 !important;">&copy; 2025 Служба доставки. Все права защищены.</p>
        <p class="mb-1 text-muted" style="opacity: 0.5; color: #999 !important;">Контактный телефон: +375-25-005-50-50</p>
        <p class="mb-0 text-muted" style="opacity: 0.5; color: #999 !important;">Email: freedeliverya@gmail.com</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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