<?php require 'db.php';
if (!isset($_SESSION['user'])) header('Location: index.php');
$user = $_SESSION['user'];

$carriers = $db->query("SELECT * FROM carriers ORDER BY name")->fetchAll();

// Получаем параметры из GET запроса (если пришли из калькулятора)
$preselected_carrier = isset($_GET['carrier']) ? (int)$_GET['carrier'] : 0;
$preselected_weight = isset($_GET['weight']) ? floatval($_GET['weight']) : 0;
$preselected_cost = isset($_GET['cost']) ? floatval($_GET['cost']) : 0;
$preselected_from_office = isset($_GET['from']) ? (int)$_GET['from'] : 0;
$preselected_to_office = isset($_GET['to']) ? (int)$_GET['to'] : 0;

// Получаем информацию об офисах если они были переданы
$from_office_info = null;
$to_office_info = null;

if ($preselected_from_office > 0) {
    $stmt = $db->prepare("SELECT o.*, c.name as carrier_name FROM offices o LEFT JOIN carriers c ON o.carrier_id = c.id WHERE o.id = ?");
    $stmt->execute([$preselected_from_office]);
    $from_office_info = $stmt->fetch();
}

if ($preselected_to_office > 0) {
    $stmt = $db->prepare("SELECT o.*, c.name as carrier_name FROM offices o LEFT JOIN carriers c ON o.carrier_id = c.id WHERE o.id = ?");
    $stmt->execute([$preselected_to_office]);
    $to_office_info = $stmt->fetch();
}

// Обработка POST запроса для создания заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация и очистка данных
    $full_name = trim($_POST['full_name'] ?? '');
    $home_address = trim($_POST['home_address'] ?? '');
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $recipient_address = trim($_POST['recipient_address'] ?? '');
    $weight = floatval($_POST['weight'] ?? 0);
    $carrier_id = intval($_POST['carrier'] ?? 0);
    $from_office = intval($_POST['from_office'] ?? 0);
    $to_office = intval($_POST['to_office'] ?? 0);
    $desired_date = trim($_POST['desired_date'] ?? '');
    $insurance = isset($_POST['insurance']);
    $packaging = isset($_POST['packaging']);
    $fragile = isset($_POST['fragile']);
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    $comment = trim($_POST['comment'] ?? '');

    // Валидация обязательных полей
    if (empty($full_name) || empty($home_address) || empty($recipient_name) || empty($recipient_address) || $weight <= 0 || $carrier_id <= 0 || $from_office <= 0 || $to_office <= 0) {
        $error = "Пожалуйста, заполните все обязательные поля!";
    } else {
        try {
            // Получаем информацию о выбранном перевозчике
            $carrier = $db->query("SELECT * FROM carriers WHERE id = $carrier_id")->fetch();
            if (!$carrier) {
                throw new Exception("Неверный перевозчик");
            }

            // Используем переданную стоимость из калькулятора, если она есть
            $cost = floatval($_POST['cost'] ?? 0);
            if ($cost <= 0) {
                // Если стоимость не передана, вычисляем её
                $cost = $carrier['base_cost'] + $weight * $carrier['cost_per_kg'];
                
                // Если выбрана страховка, добавляем 2%
                if ($insurance) {
                    $cost *= 1.02;
                }
                
                // Если выбрана упаковка, добавляем фиксированную стоимость
                if ($packaging) {
                    $cost += 3.00;
                }
                
                // Если хрупкая посылка, добавляем 1%
                if ($fragile) {
                    $cost *= 1.01;
                }
                
                $cost = round($cost, 2);
            }

            // Генерируем трек-номер
            $track = strtoupper(substr(md5(uniqid()), 0, 12));

            // Вставляем заказ в базу данных (без колонок, которые могут отсутствовать в БД)
            // Статус заказа будет pending до подтверждения оплаты
            $stmt = $db->prepare("INSERT INTO orders (user_id, carrier_id, from_office, to_office, weight, cost, track_number, tracking_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $user['id'], $carrier_id, $from_office, $to_office, $weight, $cost, $track
            ]);
            
            // Получаем ID созданного заказа
            $order_id = $db->lastInsertId();
            
            // Теперь обновляем заказ с дополнительными полями, игнорируя ошибки для отсутствующих колонок
            $update_fields = [];
            $update_values = [];
            
            // Проверяем существование колонок перед обновлением
            $columns_query = $db->query("SHOW COLUMNS FROM orders");
            $existing_columns = [];
            while ($row = $columns_query->fetch()) {
                $existing_columns[] = $row['Field'];
            }
            
            if (in_array('full_name', $existing_columns)) {
                $update_fields[] = "full_name = ?";
                $update_values[] = $full_name;
            }
            if (in_array('home_address', $existing_columns)) {
                $update_fields[] = "home_address = ?";
                $update_values[] = $home_address;
            }
            if (in_array('recipient_name', $existing_columns)) {
                $update_fields[] = "recipient_name = ?";
                $update_values[] = $recipient_name;
            }
            if (in_array('recipient_address', $existing_columns)) {
                $update_fields[] = "recipient_address = ?";
                $update_values[] = $recipient_address;
            }
            if (in_array('desired_date', $existing_columns)) {
                $update_fields[] = "desired_date = ?";
                $update_values[] = $desired_date;
            }
            if (in_array('insurance', $existing_columns)) {
                $update_fields[] = "insurance = ?";
                $update_values[] = $insurance;
            }
            if (in_array('packaging', $existing_columns)) {
                $update_fields[] = "packaging = ?";
                $update_values[] = $packaging;
            }
            if (in_array('fragile', $existing_columns)) {
                $update_fields[] = "fragile = ?";
                $update_values[] = $fragile;
            }
            if (in_array('payment_method', $existing_columns)) {
                $update_fields[] = "payment_method = ?";
                $update_values[] = $payment_method;
            }
            if (in_array('comment', $existing_columns)) {
                $update_fields[] = "comment = ?";
                $update_values[] = $comment;
            }
            if (in_array('tracking_status', $existing_columns)) {
                $update_fields[] = "tracking_status = ?";
                $update_values[] = 'created';
            }
            
            if (!empty($update_fields)) {
                $update_values[] = $order_id; // for WHERE clause
                $stmt_update = $db->prepare("UPDATE orders SET " . implode(", ", $update_fields) . " WHERE id = ?");
                $stmt_update->execute($update_values);
            }

            // Add initial status to tracking history
            $tables_query = $db->query("SHOW TABLES LIKE 'tracking_status_history'");
            if ($tables_query->rowCount() > 0) {
                $status_stmt = $db->prepare("INSERT INTO tracking_status_history (order_id, status, description) VALUES (?, ?, ?)");
                $status_stmt->execute([$order_id, 'pending', 'Заказ создан, ожидает оплаты']);
            }

            // Redirect to payment page after successful order creation
            header("Location: payment.php?order_id=" . $order_id);
            exit;

        } catch (Exception $e) {
            $error = "Ошибка при создании заказа: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .form-section {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        body.dark .form-section {
            background: #16213e !important;
        }
        .section-title {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #495057;
        }
        body.dark .section-title {
            border-color: #444;
            color: #e0e0e0;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        body.dark .info-box {
            background: #1a2a4a;
        }
        .order-summary {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
        }
        body.dark .order-summary {
            background: #1a2a4a;
            border-color: #444;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-primary shadow-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand">Оформление заказа</a>
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
            <?php if (isset($success)): ?>
                <!-- Успешное создание заказа -->
                <div class="card bg-success text-white text-center p-5 mb-5">
                    <h2 class="text-white">Заказ успешно оформлен!</h2>
                    <p class="lead text-white">Ваш заказ №<?= htmlspecialchars($track_number) ?> принят в обработку</p>
                    <h3 class="text-warning"><?= number_format($total_cost, 2) ?> BYN</h3>
                    <p class="text-white">Спасибо за доверие к нашей службе доставки!</p>
                    <a href="history.php" class="btn btn-light btn-lg mt-3">Перейти в историю заказов</a>
                </div>
            <?php else: ?>
                <h2 class="text-center mb-4">Форма оформления заказа</h2>
                
                <?php if ($preselected_carrier > 0 || $preselected_weight > 0): ?>
                    <div class="alert alert-info text-center">
                        <strong>Информация из калькулятора:</strong> 
                        <?php if($preselected_carrier > 0): ?>
                            <?php 
                            $selected_carrier = null;
                            foreach($carriers as $carrier) {
                                if($carrier['id'] == $preselected_carrier) {
                                    $selected_carrier = $carrier;
                                    break;
                                }
                            }
                            if($selected_carrier) echo htmlspecialchars($selected_carrier['name']);
                            ?>
                        <?php endif; ?>
                        <?php if($preselected_weight > 0): ?>
                            , вес: <?= $preselected_weight ?> кг
                        <?php endif; ?>
                        <?php if($preselected_cost > 0): ?>
                            , расчетная стоимость: <?= number_format($preselected_cost, 2) ?> BYN
                        <?php endif; ?>
                        <?php if($from_office_info && $to_office_info): ?>
                            <br><strong>Маршрут:</strong> <?= htmlspecialchars($from_office_info['city']) ?> — <?= htmlspecialchars($to_office_info['city']) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <!-- Скрытое поле для передачи стоимости из калькулятора -->
                    <input type="hidden" name="cost" value="<?= $preselected_cost ?>">
                    
                    <!-- Личные данные -->
                    <div class="form-section">
                        <h4 class="section-title">Личные данные</h4>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">ФИО отправителя <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" required 
                                       placeholder="Иванов Иван Иванович" 
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Домашний адрес отправителя <span class="text-danger">*</span></label>
                                <textarea name="home_address" class="form-control" rows="2" required 
                                          placeholder="Укажите ваш постоянный адрес проживания"><?= htmlspecialchars($_POST['home_address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Данные получателя -->
                    <div class="form-section">
                        <h4 class="section-title">Данные получателя</h4>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">ФИО получателя <span class="text-danger">*</span></label>
                                <input type="text" name="recipient_name" class="form-control" required 
                                       placeholder="ФИО получателя" 
                                       value="<?= htmlspecialchars($_POST['recipient_name'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Адрес получателя <span class="text-danger">*</span></label>
                                <textarea name="recipient_address" class="form-control" rows="2" required 
                                          placeholder="Адрес, куда будет доставлена посылка"><?= htmlspecialchars($_POST['recipient_address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Выбор оператора -->
                    <div class="form-section">
                        <h4 class="section-title">Выбор оператора</h4>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Служба доставки <span class="text-danger">*</span></label>
                                <select name="carrier" class="form-select" required id="carrier-select">
                                    <option value="">Выберите службу доставки</option>
                                    <?php foreach($carriers as $carrier): ?>
                                        <option value="<?= $carrier['id'] ?>" 
                                            <?= (isset($_POST['carrier']) && $_POST['carrier'] == $carrier['id']) ? 'selected' : (isset($preselected_carrier) && $preselected_carrier == $carrier['id'] ? 'selected' : '') ?>>
                                            <?= htmlspecialchars($carrier['name']) ?> (до <?= $carrier['max_weight'] ?> кг)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Информация о доставке -->
                    <div class="form-section">
                        <h4 class="section-title">Информация о доставке</h4>
                        
                        <div class="info-box">
                            <strong>Важно:</strong> Выберите отделения получения и доставки посылки.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Отделение получения <span class="text-danger">*</span></label>
                                <select name="from_office" class="form-select" required id="from-office-select" disabled>
                                    <option value="">Выберите отделение</option>
                                    <?php 
                                    $offices = $db->query("SELECT o.*, c.name as carrier_name FROM offices o LEFT JOIN carriers c ON o.carrier_id = c.id ORDER BY c.name, o.city")->fetchAll();
                                    foreach($offices as $office): 
                                    ?>
                                        <option value="<?= $office['id'] ?>" <?= ($preselected_from_office == $office['id']) ? 'selected' : '' ?> data-carrier="<?= $office['carrier_id'] ?>">
                                            <?= htmlspecialchars($office['carrier_name']) ?>, <?= htmlspecialchars($office['city']) ?> — <?= htmlspecialchars($office['address']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Выберите отделение после выбора оператора</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Отделение доставки <span class="text-danger">*</span></label>
                                <select name="to_office" class="form-select" required id="to-office-select" disabled>
                                    <option value="">Выберите отделение</option>
                                    <?php foreach($offices as $office): ?>
                                        <option value="<?= $office['id'] ?>" <?= ($preselected_to_office == $office['id']) ? 'selected' : '' ?> data-carrier="<?= $office['carrier_id'] ?>">
                                            <?= htmlspecialchars($office['carrier_name']) ?>, <?= htmlspecialchars($office['city']) ?> — <?= htmlspecialchars($office['address']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Выберите отделение после выбора оператора</div>
                            </div>
                        </div>
                    </div>

                    <!-- Детали посылки -->
                    <div class="form-section">
                        <h4 class="section-title">Детали посылки</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Вес посылки (кг) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" name="weight" class="form-control" required 
                                       min="0.1" max="50" 
                                       value="<?= htmlspecialchars($_POST['weight'] ?? ($preselected_weight > 0 ? $preselected_weight : '1')) ?>">
                                <div class="form-text">Максимальный вес зависит от выбранной службы доставки</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Желаемая дата получения</label>
                                <input type="date" name="desired_date" class="form-control" 
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>" 
                                       value="<?= htmlspecialchars($_POST['desired_date'] ?? '') ?>">
                                <div class="form-text">Дата, когда вы хотите получить посылку (не ранее завтрашнего дня)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Дополнительные услуги -->
                    <div class="form-section">
                        <h4 class="section-title">Дополнительные услуги</h4>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="insurance" id="insurance" 
                                           <?= (isset($_POST['insurance'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="insurance">
                                        Страховка (+2%)
                                    </label>
                                </div>
                                <div class="form-text">Защита стоимости посылки</div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="packaging" id="packaging" 
                                           <?= (isset($_POST['packaging'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="packaging">
                                        Упаковка (+5 BYN)
                                    </label>
                                </div>
                                <div class="form-text">Профессиональная упаковка</div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="fragile" id="fragile" 
                                           <?= (isset($_POST['fragile'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="fragile">
                                        Хрупкая посылка (+1%)
                                    </label>
                                </div>
                                <div class="form-text">Особое обращение</div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label fw-bold">Комментарий к заказу</label>
                            <textarea name="comment" class="form-control" rows="3" 
                                      placeholder="Дополнительная информация, пожелания"><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
                        </div>
                    </div>



                    <!-- Кнопки управления -->
                    <div class="d-grid gap-3 d-md-flex justify-content-md-center">
                        <a href="calculator.php" class="btn btn-secondary btn-lg">Вернуться к калькулятору</a>
                        <button type="submit" class="btn btn-success btn-lg px-5">К Оплате</button>
                    </div>
                </form>
            <?php endif; ?>
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
    
    // Enable office selection when carrier is selected
    const carrierSelect = document.getElementById('carrier-select');
    const fromOfficeSelect = document.getElementById('from-office-select');
    const toOfficeSelect = document.getElementById('to-office-select');
    
    if (carrierSelect && fromOfficeSelect && toOfficeSelect) {
        // Initially check if carrier is already selected
        if (carrierSelect.value) {
            fromOfficeSelect.disabled = false;
            toOfficeSelect.disabled = false;
            
            // Filter offices by selected carrier
            filterOfficesByCarrier(carrierSelect.value);
        }
        
        carrierSelect.addEventListener('change', function() {
            if (this.value) {
                fromOfficeSelect.disabled = false;
                toOfficeSelect.disabled = false;
                
                // Filter offices by selected carrier
                filterOfficesByCarrier(this.value);
            } else {
                fromOfficeSelect.disabled = true;
                toOfficeSelect.disabled = true;
                
                // Reset selections
                fromOfficeSelect.value = '';
                toOfficeSelect.value = '';
            }
        });
    }
    
    function filterOfficesByCarrier(carrierId) {
        // Show all options first
        const fromOptions = fromOfficeSelect.querySelectorAll('option');
        const toOptions = toOfficeSelect.querySelectorAll('option');
        
        // Filter from office options
        fromOptions.forEach(option => {
            if (option.value === '') {
                option.style.display = '';
            } else {
                const optionCarrierId = option.getAttribute('data-carrier');
                if (optionCarrierId === carrierId || carrierId === '') {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        });
        
        // Filter to office options
        toOptions.forEach(option => {
            if (option.value === '') {
                option.style.display = '';
            } else {
                const optionCarrierId = option.getAttribute('data-carrier');
                if (optionCarrierId === carrierId || carrierId === '') {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        });
    }
});
</script>
</body>
</html>