<?php require 'db.php'; 
if (!isset($_SESSION['user'])) header('Location: index.php');
$user = $_SESSION['user'];
$orders = $db->prepare("SELECT o.*, c.name as carrier_name FROM orders o LEFT JOIN carriers c ON o.carrier_id=c.id WHERE o.user_id=? ORDER BY o.created_at DESC");
$orders->execute([$user['id']]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>История заказов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .order-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        body.dark .order-details {
            background: #1a2a4a;
        }
        .detail-row {
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e9ecef;
        }
        body.dark .detail-row {
            border-color: #444;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-primary shadow-lg">
    <div class="container-fluid">
        <a class="navbar-brand">История заказов</a>
        <div>
            <a href="calculator.php" class="btn btn-light me-2">Калькулятор</a>
            <a href="order_form.php" class="btn btn-success me-2">Оформить заказ</a>
            <?php if($user['role']==='admin'): ?><a href="admin/index.php" class="btn btn-danger me-2">Админка</a><?php endif; ?>
            <a href="logout.php" class="btn btn-outline-light">Выйти</a>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <h2 class="text-white">История заказов</h2>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'order_already_paid'): ?>
        <div class="alert alert-info">Этот заказ уже оплачен.</div>
    <?php endif; ?>
    <div class="card shadow-lg">
        <div class="card-body">
            <table class="table table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Трек</th><th>Оператор</th><th>Вес</th><th>Стоимость</th><th>Статус</th><th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders->fetchAll() as $o): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['track_number']) ?></strong></td>
                        <td><?= htmlspecialchars($o['carrier_name'] ?? 'Н/Д') ?></td>
                        <td><?= $o['weight'] ?> кг</td>
                        <td><?= $o['cost'] ?> BYN</td>
                        <td>
                            <?php 
                            $status = $o['tracking_status'] ?? 'created';
                            $status_badge = 'bg-secondary';
                            switch(strtolower($status)) {
                                case 'created':
                                case 'создан':
                                    $status_badge = 'bg-success';
                                    $status_text = 'Создан';
                                    break;
                                case 'paid':
                                case 'оплачен':
                                    $status_badge = 'bg-info';
                                    $status_text = 'Оплачен';
                                    break;
                                case 'in_transit':
                                case 'в пути':
                                    $status_badge = 'bg-warning';
                                    $status_text = 'В пути';
                                    break;
                                case 'sort_center':
                                case 'сорт. центр':
                                    $status_badge = 'bg-warning';
                                    $status_text = 'Сорт. центр';
                                    break;
                                case 'out_for_delivery':
                                case 'у курьера':
                                    $status_badge = 'bg-warning';
                                    $status_text = 'У курьера';
                                    break;
                                case 'delivered':
                                case 'доставлен':
                                    $status_badge = 'bg-success';
                                    $status_text = 'Доставлен';
                                    break;
                                case 'delayed':
                                case 'задерживается':
                                    $status_badge = 'bg-danger';
                                    $status_text = 'Задерживается';
                                    break;
                                case 'cancelled':
                                case 'отменен':
                                    $status_badge = 'bg-dark';
                                    $status_text = 'Отменен';
                                    break;
                                case 'returned':
                                case 'возвращен':
                                    $status_badge = 'bg-secondary';
                                    $status_text = 'Возвращен';
                                    break;
                                case 'pending':
                                case 'ожидает':
                                    $status_badge = 'bg-secondary';
                                    $status_text = 'Ожидает';
                                    break;
                                default:
                                    $status_badge = 'bg-secondary';
                                    $status_text = $status;
                            }
                            ?>
                            <span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                        </td>
                        <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php if(isset($o['full_name']) && $o['full_name'] || isset($o['pickup_city']) && $o['pickup_city'] || isset($o['delivery_city']) && $o['delivery_city']): ?>
                    <tr>
                        <td colspan="6">
                            <div class="order-details">
                                <div class="row">
                                    <?php if(isset($o['full_name']) && $o['full_name']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-row"><strong>ФИО:</strong> <?= htmlspecialchars($o['full_name']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if(isset($o['home_address']) && $o['home_address']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-row"><strong>Домашний адрес:</strong> <?= htmlspecialchars($o['home_address']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if(isset($o['pickup_city']) && $o['pickup_city'] && isset($o['pickup_address']) && $o['pickup_address']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-row"><strong>Адрес получения:</strong> <?= htmlspecialchars($o['pickup_city']) ?>, <?= htmlspecialchars($o['pickup_address']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if(isset($o['delivery_city']) && $o['delivery_city'] && isset($o['delivery_address']) && $o['delivery_address']): ?>
                                    <div class="col-md-6">
                                        <div class="detail-row"><strong>Адрес доставки:</strong> <?= htmlspecialchars($o['delivery_city']) ?>, <?= htmlspecialchars($o['delivery_address']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if(isset($o['insurance']) && $o['insurance'] || isset($o['packaging']) && $o['packaging'] || isset($o['fragile']) && $o['fragile']): ?>
                                    <div class="col-md-12">
                                        <div class="detail-row">
                                            <strong>Дополнительно:</strong> 
                                            <?php if(isset($o['insurance']) && $o['insurance']): ?><span class="badge bg-warning me-1">Страховка</span><?php endif; ?>
                                            <?php if(isset($o['packaging']) && $o['packaging']): ?><span class="badge bg-info me-1">Упаковка</span><?php endif; ?>
                                            <?php if(isset($o['fragile']) && $o['fragile']): ?><span class="badge bg-danger me-1">Хрупкое</span><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if(isset($o['comment']) && $o['comment']): ?>
                                    <div class="col-md-12">
                                        <div class="detail-row"><strong>Комментарий:</strong> <?= htmlspecialchars($o['comment']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
</body>
</html>