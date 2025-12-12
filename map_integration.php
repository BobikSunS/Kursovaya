<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Карта доставки по Беларуси</title>
    <!-- Подключение Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        
        #map {
            height: 600px;
            width: 100%;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .controls {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .control-group {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        select, input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 25px;
        }
        
        button:hover {
            background-color: #0056b3;
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
        
        .office-marker {
            cursor: pointer;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Карта доставки по Беларуси</h1>
        
        <div class="controls">
            <div class="control-group">
                <label for="carrier-select">Выберите компанию:</label>
                <select id="carrier-select">
                    <option value="">Все компании</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="from-office">Отделение отправления:</label>
                <select id="from-office">
                    <option value="">Выберите отделение</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="to-office">Отделение получения:</label>
                <select id="to-office">
                    <option value="">Выберите отделение</option>
                </select>
            </div>
            
            <button id="calculate-route-btn">Рассчитать маршрут</button>
        </div>
        
        <div class="loading" id="loading-indicator">
            Рассчитываем маршрут...
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
        </div>
    </div>

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
        
        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            loadCarriers();
            loadOffices();
            
            // Event listeners
            document.getElementById('carrier-select').addEventListener('change', filterOfficesByCarrier);
            document.getElementById('calculate-route-btn').addEventListener('click', calculateRoute);
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
                fromOption.textContent = `${office.city}, ${office.address} (${getCarrierName(office.carrier_id)})`;
                fromOfficeSelect.appendChild(fromOption);
                
                const toOption = document.createElement('option');
                toOption.value = office.id;
                toOption.textContent = `${office.city}, ${office.address} (${getCarrierName(office.carrier_id)})`;
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
        }
        
        // Get carrier name by ID
        function getCarrierName(carrierId) {
            const carrier = carriersData.find(c => c.id == carrierId);
            return carrier ? carrier.name : 'Неизвестно';
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
        
        // Calculate route between two offices
        async function calculateRoute() {
            const fromOfficeId = document.getElementById('from-office').value;
            const toOfficeId = document.getElementById('to-office').value;
            
            if (!fromOfficeId || !toOfficeId) {
                alert('Пожалуйста, выберите оба отделения');
                return;
            }
            
            if (fromOfficeId === toOfficeId) {
                alert('Отделения отправления и получения должны быть разными');
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
                        to_office_id: parseInt(toOfficeId)
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
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
                <p><strong>Расстояние:</strong> ${routeData.distance_km.toFixed(2)} км</p>
                <p><strong>Примерное время в пути:</strong> ${Math.ceil(routeData.duration_min / 60)} ч ${routeData.duration_min % 60} мин</p>
                <p><strong>Стоимость доставки:</strong> ${routeData.cost.toFixed(2)} BYN</p>
            `;
            
            // Display turn-by-turn instructions
            if (routeData.instructions && routeData.instructions.length > 0) {
                let instructionsHtml = '<h4>Маршрутные указания:</h4><ol>';
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
    </script>
</body>
</html>