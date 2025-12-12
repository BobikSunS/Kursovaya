<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user info
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Карта доставки - <?php echo htmlspecialchars($user['name']); ?></title>
    <!-- Подключение Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Подключение Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            padding-top: 70px;
        }
        
        #map {
            height: 500px;
            width: 100%;
            border-radius: 8px;
        }
        
        .route-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: none;
        }
        
        .route-details {
            margin-top: 10px;
        }
        
        .instructions {
            margin-top: 15px;
        }
        
        .instructions ol {
            padding-left: 20px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 10px;
        }
        
        .office-card {
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
        }
        
        .office-card:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">Система доставки</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Главная</a>
                <a class="nav-link" href="calculator.php">Калькулятор</a>
                <a class="nav-link active" href="map_page.php">Карта</a>
                <a class="nav-link" href="history.php">История</a>
                <a class="nav-link" href="profile.php">Профиль</a>
                <a class="nav-link" href="logout.php">Выход</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="mb-4">Карта доставки по Беларуси</h1>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Параметры маршрута</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="carrier-select" class="form-label">Выберите компанию:</label>
                            <select class="form-select" id="carrier-select">
                                <option value="">Все компании</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="from-office" class="form-label">Отделение отправления:</label>
                            <select class="form-select" id="from-office">
                                <option value="">Выберите отделение</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="to-office" class="form-label">Отделение получения:</label>
                            <select class="form-select" id="to-office">
                                <option value="">Выберите отделение</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="weight" class="form-label">Вес посылки (кг):</label>
                            <input type="number" class="form-control" id="weight" value="1.0" min="0.1" step="0.1" max="30">
                        </div>
                        
                        <button class="btn btn-primary w-100" id="calculate-route-btn">Рассчитать маршрут</button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5>Ближайшие отделения</h5>
                    </div>
                    <div class="card-body">
                        <div id="nearby-offices">
                            <p class="text-muted">Выберите компанию для отображения отделений</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="loading" id="loading-indicator">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p>Рассчитываем маршрут...</p>
                </div>
                
                <div id="map"></div>
                
                <div class="route-info" id="route-info">
                    <h3>Информация о маршруте</h3>
                    <div class="route-details" id="route-details">
                        <!-- Route details will be displayed here -->
                    </div>
                    <div class="instructions" id="route-instructions">
                        <!-- Turn-by-turn instructions will be displayed here -->
                    </div>
                    <button class="btn btn-success mt-3" id="create-order-btn">Создать заказ</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for creating order -->
    <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalLabel">Создание заказа</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="order-form">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Информация об отправителе</h5>
                                <div class="mb-3">
                                    <label for="sender-name" class="form-label">ФИО</label>
                                    <input type="text" class="form-control" id="sender-name" value="<?php echo htmlspecialchars($user['name']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="sender-phone" class="form-label">Телефон</label>
                                    <input type="text" class="form-control" id="sender-phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="sender-address" class="form-label">Адрес отправителя</label>
                                    <textarea class="form-control" id="sender-address" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Информация о получателе</h5>
                                <div class="mb-3">
                                    <label for="recipient-name" class="form-label">ФИО получателя</label>
                                    <input type="text" class="form-control" id="recipient-name">
                                </div>
                                <div class="mb-3">
                                    <label for="recipient-phone" class="form-label">Телефон получателя</label>
                                    <input type="text" class="form-control" id="recipient-phone">
                                </div>
                                <div class="mb-3">
                                    <label for="recipient-address" class="form-label">Адрес получателя</label>
                                    <textarea class="form-control" id="recipient-address" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="package-description" class="form-label">Описание посылки</label>
                                    <textarea class="form-control" id="package-description" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="delivery-date" class="form-label">Желаемая дата доставки</label>
                                    <input type="date" class="form-control" id="delivery-date">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="insurance">
                            <label class="form-check-label" for="insurance">Страхование (+10% к стоимости)</label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="packaging">
                            <label class="form-check-label" for="packaging">Упаковка (+5 BYN)</label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="fragile">
                            <label class="form-check-label" for="fragile">Хрупкое содержимое</label>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment-method" class="form-label">Способ оплаты</label>
                            <select class="form-select" id="payment-method">
                                <option value="cash">Наличные</option>
                                <option value="card">Банковская карта</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comment" class="form-label">Комментарий</label>
                            <textarea class="form-control" id="comment" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="submit-order-btn">Создать заказ</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Подключение Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Подключение Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the map
        const map = L.map('map').setView([53.904133, 27.557541], 7); // Center on Belarus
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Global variables
        let markers = [];
        let routeLine = null;
        let officesData = [];
        let carriersData = [];
        let selectedRouteData = null;
        
        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            loadCarriers();
            loadOffices();
            
            // Event listeners
            document.getElementById('carrier-select').addEventListener('change', filterOfficesByCarrier);
            document.getElementById('calculate-route-btn').addEventListener('click', calculateRoute);
            document.getElementById('create-order-btn').addEventListener('click', showOrderModal);
            document.getElementById('submit-order-btn').addEventListener('click', submitOrder);
        });
        
        // Load carriers from the database
        async function loadCarriers() {
            try {
                const response = await fetch('get_carriers.php');
                carriersData = await response.json();
                
                const carrierSelect = document.getElementById('carrier-select');
                carrierSelect.innerHTML = '<option value="">Все компании</option>';
                
                carriersData.forEach(carrier => {
                    const option = document.createElement('option');
                    option.value = carrier.id;
                    option.textContent = carrier.name;
                    carrierSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading carriers:', error);
            }
        }
        
        // Load offices from the database
        async function loadOffices() {
            try {
                const response = await fetch('get_offices_with_coords.php');
                officesData = await response.json();
                
                populateOfficeDropdowns();
                addMarkersToMap(officesData);
            } catch (error) {
                console.error('Error loading offices:', error);
            }
        }
        
        // Populate office dropdowns
        function populateOfficeDropdowns() {
            const fromOfficeSelect = document.getElementById('from-office');
            const toOfficeSelect = document.getElementById('to-office');
            
            // Clear existing options
            fromOfficeSelect.innerHTML = '<option value="">Выберите отделение</option>';
            toOfficeSelect.innerHTML = '<option value="">Выберите отделение</option>';
            
            officesData.forEach(office => {
                const fromOption = document.createElement('option');
                fromOption.value = office.id;
                fromOption.textContent = `${office.city}, ${office.address}`;
                fromOfficeSelect.appendChild(fromOption);
                
                const toOption = document.createElement('option');
                toOption.value = office.id;
                toOption.textContent = `${office.city}, ${office.address}`;
                toOfficeSelect.appendChild(toOption);
            });
        }
        
        // Filter offices by selected carrier
        function filterOfficesByCarrier() {
            const selectedCarrierId = document.getElementById('carrier-select').value;
            
            const filteredOffices = selectedCarrierId ? 
                officesData.filter(office => office.carrier_id == selectedCarrierId) : 
                officesData;
            
            // Update dropdowns with filtered offices
            const fromOfficeSelect = document.getElementById('from-office');
            const toOfficeSelect = document.getElementById('to-office');
            
            fromOfficeSelect.innerHTML = '<option value="">Выберите отделение</option>';
            toOfficeSelect.innerHTML = '<option value="">Выберите отделение</option>';
            
            filteredOffices.forEach(office => {
                const fromOption = document.createElement('option');
                fromOption.value = office.id;
                fromOption.textContent = `${office.city}, ${office.address}`;
                fromOfficeSelect.appendChild(fromOption);
                
                const toOption = document.createElement('option');
                toOption.value = office.id;
                toOption.textContent = `${office.city}, ${office.address}`;
                toOfficeSelect.appendChild(toOption);
            });
            
            // Also update markers on map
            clearMarkers();
            addMarkersToMap(filteredOffices);
            
            // Show nearby offices
            displayNearbyOffices(selectedCarrierId);
        }
        
        // Display nearby offices for the selected carrier
        function displayNearbyOffices(carrierId) {
            const carrier = carriersData.find(c => c.id == carrierId);
            const carrierOffices = officesData.filter(office => office.carrier_id == carrierId);
            
            const nearbyOfficesDiv = document.getElementById('nearby-offices');
            
            if (carrierOffices.length === 0) {
                nearbyOfficesDiv.innerHTML = '<p class="text-muted">Нет отделений для выбранной компании</p>';
                return;
            }
            
            let officesHtml = '<div class="office-list">';
            carrierOffices.slice(0, 10).forEach(office => { // Show first 10 offices
                officesHtml += `
                    <div class="office-card" onclick="selectOffice(${office.id})">
                        <div><strong>${office.city}</strong></div>
                        <div>${office.address}</div>
                    </div>
                `;
            });
            
            if (carrierOffices.length > 10) {
                officesHtml += `<p class="text-muted">... и ещё ${carrierOffices.length - 10} отделений</p>`;
            }
            
            officesHtml += '</div>';
            nearbyOfficesDiv.innerHTML = officesHtml;
        }
        
        // Function to select an office from the list
        function selectOffice(officeId) {
            const fromOfficeSelect = document.getElementById('from-office');
            const toOfficeSelect = document.getElementById('to-office');
            
            // If neither is selected, select as from office
            if (!fromOfficeSelect.value) {
                fromOfficeSelect.value = officeId;
            } 
            // If from is selected but to is not, select as to office
            else if (!toOfficeSelect.value) {
                toOfficeSelect.value = officeId;
            }
            // If both are selected, ask which one to replace
            else {
                if (confirm('Заменить одно из выбранных отделений?')) {
                    if (confirm('Заменить отделение отправления?')) {
                        fromOfficeSelect.value = officeId;
                    } else {
                        toOfficeSelect.value = officeId;
                    }
                }
            }
        }
        
        // Add markers to map
        function addMarkersToMap(offices) {
            // Clear existing markers
            clearMarkers();
            
            offices.forEach(office => {
                if (office.lat && office.lng) {
                    const carrier = carriersData.find(c => c.id == office.carrier_id);
                    const color = carrier ? carrier.color : '#3388ff';
                    
                    const marker = L.marker([parseFloat(office.lat), parseFloat(office.lng)]).addTo(map);
                    marker.bindPopup(`<b>${office.city}</b><br>${office.address}<br><small>${getCarrierName(office.carrier_id)}</small>`);
                    marker.on('click', function(e) {
                        // When a marker is clicked, select it in the dropdowns
                        if (!document.getElementById('from-office').value) {
                            document.getElementById('from-office').value = office.id;
                        } else if (!document.getElementById('to-office').value) {
                            document.getElementById('to-office').value = office.id;
                        }
                    });
                    markers.push(marker);
                }
            });
        }
        
        // Clear all markers from map
        function clearMarkers() {
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];
        }
        
        // Get carrier name by ID
        function getCarrierName(carrierId) {
            const carrier = carriersData.find(c => c.id == carrierId);
            return carrier ? carrier.name : 'Неизвестно';
        }
        
        // Calculate route between two offices
        async function calculateRoute() {
            const fromOfficeId = document.getElementById('from-office').value;
            const toOfficeId = document.getElementById('to-office').value;
            const weight = parseFloat(document.getElementById('weight').value);
            
            if (!fromOfficeId || !toOfficeId) {
                alert('Пожалуйста, выберите оба отделения');
                return;
            }
            
            if (fromOfficeId === toOfficeId) {
                alert('Отделения отправления и получения должны быть разными');
                return;
            }
            
            if (isNaN(weight) || weight <= 0) {
                alert('Пожалуйста, укажите корректный вес');
                return;
            }
            
            // Show loading indicator
            document.getElementById('loading-indicator').style.display = 'block';
            
            try {
                const response = await fetch('calculate_route.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        from_office_id: parseInt(fromOfficeId),
                        to_office_id: parseInt(toOfficeId),
                        weight: weight
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Store route data for later use in order creation
                    selectedRouteData = result;
                    
                    // Display route information
                    displayRouteInfo(result);
                    
                    // Draw the route on the map
                    drawRouteOnMap(result.coordinates);
                } else {
                    alert('Ошибка при расчете маршрута: ' + result.message);
                }
            } catch (error) {
                console.error('Error calculating route:', error);
                alert('Произошла ошибка при расчете маршрута');
            } finally {
                // Hide loading indicator
                document.getElementById('loading-indicator').style.display = 'none';
            }
        }
        
        // Display route information
        function displayRouteInfo(routeData) {
            const routeInfoDiv = document.getElementById('route-info');
            const routeDetailsDiv = document.getElementById('route-details');
            const instructionsDiv = document.getElementById('route-instructions');
            
            routeDetailsDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Отделение отправления:</strong> ${routeData.from_office.city}, ${routeData.from_office.address}</p>
                        <p><strong>Отделение получения:</strong> ${routeData.to_office.city}, ${routeData.to_office.address}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Расстояние:</strong> ${routeData.distance_km.toFixed(2)} км</p>
                        <p><strong>Примерное время в пути:</strong> ${Math.ceil(routeData.duration_min / 60)} ч ${routeData.duration_min % 60} мин</p>
                        <p><strong>Стоимость доставки:</strong> ${routeData.cost.toFixed(2)} BYN</p>
                    </div>
                </div>
            `;
            
            // Display turn-by-turn instructions
            if (routeData.instructions && routeData.instructions.length > 0) {
                let instructionsHtml = '<h5>Маршрутные указания:</h5><ol>';
                routeData.instructions.forEach(instruction => {
                    instructionsHtml += `<li>${instruction.text} (${instruction.distance} м)</li>`;
                });
                instructionsHtml += '</ol>';
                instructionsDiv.innerHTML = instructionsHtml;
            } else {
                instructionsDiv.innerHTML = '';
            }
            
            routeInfoDiv.style.display = 'block';
        }
        
        // Draw route on map
        function drawRouteOnMap(coordinates) {
            // Remove existing route line if present
            if (routeLine) {
                map.removeLayer(routeLine);
            }
            
            // Create polyline from coordinates
            routeLine = L.polyline(coordinates, {color: 'red', weight: 5}).addTo(map);
            
            // Fit map to show the entire route
            map.fitBounds(routeLine.getBounds());
        }
        
        // Show order modal
        function showOrderModal() {
            if (!selectedRouteData) {
                alert('Сначала рассчитайте маршрут');
                return;
            }
            
            // Set default delivery date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const formattedDate = tomorrow.toISOString().split('T')[0];
            document.getElementById('delivery-date').value = formattedDate;
            
            // Show modal
            const orderModal = new bootstrap.Modal(document.getElementById('orderModal'));
            orderModal.show();
        }
        
        // Submit order
        async function submitOrder() {
            if (!selectedRouteData) {
                alert('Нет данных для создания заказа');
                return;
            }
            
            // Get form values
            const formData = {
                from_office: selectedRouteData.from_office.id,
                to_office: selectedRouteData.to_office.id,
                weight: parseFloat(document.getElementById('weight').value),
                cost: selectedRouteData.cost,
                delivery_hours: selectedRouteData.duration_min / 60,
                full_name: document.getElementById('recipient-name').value,
                home_address: document.getElementById('recipient-address').value,
                pickup_city: selectedRouteData.from_office.city,
                pickup_address: selectedRouteData.from_office.address,
                delivery_city: selectedRouteData.to_office.city,
                delivery_address: selectedRouteData.to_office.address,
                desired_date: document.getElementById('delivery-date').value,
                insurance: document.getElementById('insurance').checked ? 1 : 0,
                packaging: document.getElementById('packaging').checked ? 1 : 0,
                fragile: document.getElementById('fragile').checked ? 1 : 0,
                payment_method: document.getElementById('payment-method').value,
                comment: document.getElementById('comment').value,
                carrier_id: selectedRouteData.from_office.carrier_id
            };
            
            try {
                const response = await fetch('order_form.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Заказ успешно создан! Номер трекинга: ' + result.track_number);
                    // Close modal and reset form
                    bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
                    
                    // Reset form
                    document.getElementById('order-form').reset();
                    
                    // Reset route selection
                    selectedRouteData = null;
                    document.getElementById('route-info').style.display = 'none';
                } else {
                    alert('Ошибка при создании заказа: ' + result.message);
                }
            } catch (error) {
                console.error('Error submitting order:', error);
                alert('Произошла ошибка при создании заказа');
            }
        }
    </script>
</body>
</html>