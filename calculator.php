<?php require 'db.php';
if (!isset($_SESSION['user'])) header('Location: index.php');
$user = $_SESSION['user'];

$carriers = $db->query("SELECT * FROM carriers")->fetchAll();

// Граф
$graph = [];
foreach ($db->query("SELECT from_office, to_office, distance_km FROM routes") as $r) {
    $graph[$r['from_office']][$r['to_office']] = $r['distance_km'];
    $graph[$r['to_office']][$r['from_office']] = $r['distance_km'];
}

function dijkstra($graph, $start, $end) {
    // Check if both start and end nodes exist in the graph
    if (!isset($graph[$start]) || !isset($graph[$end])) {
        return null;
    }
    
    $dist = array_fill_keys(array_keys($graph), INF);
    $prev = [];
    $dist[$start] = 0;
    $queue = [$start => 0];

    while (!empty($queue)) {
        $u = array_keys($queue, min($queue))[0];
        unset($queue[$u]);
        if (!isset($graph[$u])) continue;
        foreach ($graph[$u] as $v => $w) {
            $alt = $dist[$u] + $w;
            if ($alt < $dist[$v]) {
                $dist[$v] = $alt;
                $prev[$v] = $u;
                $queue[$v] = $alt;
            }
        }
    }
    if ($dist[$end] === INF) return null;
    $path = [];
    $u = $end;
    while ($u != $start) {
        $path[] = $u;
        $u = $prev[$u] ?? null;
        if ($u === null) return null;
    }
    $path[] = $start;
    return ['path' => array_reverse($path), 'distance' => $dist[$end]];
}

function formatDeliveryTime($hours) {
    $days = $hours / 24;
    
    if ($days < 1) {
        return "Менее суток";
    } elseif ($days <= 1.5) {
        return "1-1.5 дня";
    } elseif ($days <= 2) {
        return "1.5-2 дня";
    } elseif ($days <= 3) {
        return "2-3 дня";
    } elseif ($days <= 5) {
        return "3-5 дней";
    } elseif ($days <= 7) {
        return "5-7 дней";
    } else {
        return "Более недели";
    }
}

$result = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $carrier_id = (int)$_POST['carrier'];
    $from = (int)$_POST['from'];
    $to = (int)$_POST['to'];

    if ($from === $to) {
        $error = "Нельзя отправить в то же отделение!";
    } else {
        $carrier = $db->query("SELECT * FROM carriers WHERE id = $carrier_id")->fetch();
        $pathData = dijkstra($graph, $from, $to);
        if (!$pathData) {
            $error = "Маршрут не найден!";
        } else {
            $distance = $pathData['distance'];
            $base_hours = $distance / $carrier['speed_kmh'];

            $type = $_POST['package_type'];
            $insurance = isset($_POST['insurance']);

            $weight = $type === 'letter' 
                ? 0.02 * (int)($_POST['letter_count'] ?? 1)
                : max((float)$_POST['weight'], 0); // Remove volume weight calculation since gabarit is removed

            $max_weight = $carrier['max_weight']; // Remove gabarit-based weight increase

            if ($weight > $max_weight) {
                $error = "Вес превышает лимит оператора ($max_weight кг)!";
            } else {
                $cost = $carrier['base_cost'] 
                      + $weight * $carrier['cost_per_kg'] 
                      + $distance * $carrier['cost_per_km'];

                if ($insurance) $cost *= 1.02; // Remove gabarit and speed cost calculations
                if ($type === 'letter') $cost = max($cost, 2.5);

                $cost = round($cost, 2);
                $hours = round($base_hours, 1);

                $result = [
                    'carrier' => $carrier,
                    'cost' => $cost,
                    'hours' => $hours,
                    'distance' => $distance
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Почтовый калькулятор</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-dark bg-primary shadow-lg">
    <div class="container-fluid">
        <a class="navbar-brand">Почтовый калькулятор</a>
        <div>
            <a href="profile.php" class="btn btn-light me-2">Профиль</a>
            <a href="order_form.php" class="btn btn-success me-2">Оформить заказ</a>
            <a href="history.php" class="btn btn-warning me-2">История</a>
            <?php if($user['role']==='admin'): ?><a href="admin/index.php" class="btn btn-danger me-2">Админка</a><?php endif; ?>
            <a href="logout.php" class="btn btn-outline-light">Выйти</a>
        </div>
    </div>
</nav>

<div class="container mt-5 flex-grow-1 main-content">
    <h2 class="text-center text-white mb-4">Выберите оператора</h2>
    <div class="row justify-content-center g-4">
        <?php foreach($carriers as $c): ?>
        <div class="col-md-4 col-sm-6">
            <div class="carrier-card p-4 text-center text-white shadow-lg" 
                 style="background: <?= $c['color'] ?>;" 
                 onclick="selectCarrier(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name']) ?>')">
                <h4><?= htmlspecialchars($c['name']) ?></h4>
                <small>до <?= $c['max_weight'] ?> кг</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card mt-5 shadow-lg" id="calc-form" style="display:<?= (isset($_POST['carrier']) || isset($_GET['carrier'])) ? 'block' : 'none' ?>;">
        <div class="card-body">
            <h4 class="text-center mb-4">Расчёт для: <strong id="carrier-name"><?= isset($_POST['carrier']) ? htmlspecialchars($carriers[array_search($_POST['carrier'], array_column($carriers, 'id'))]['name'] ?? '') : (isset($_GET['carrier']) ? htmlspecialchars($carriers[array_search($_GET['carrier'], array_column($carriers, 'id'))]['name'] ?? '') : '') ?></strong></h4>
            <form method="POST">
                <input type="hidden" name="carrier" id="selected-carrier" value="<?= $_POST['carrier'] ?? $_GET['carrier'] ?? '' ?>">
                <input type="hidden" name="from" id="selected-from" value="<?= $_POST['from'] ?? $_GET['from'] ?? '' ?>">
                <input type="hidden" name="to" id="selected-to" value="<?= $_POST['to'] ?? $_GET['to'] ?? '' ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Откуда</label>
                        <select name="from" id="from-select" class="form-select" required onchange="document.getElementById('selected-from').value = this.value;">
                            <option value="">Выберите</option>
                            <?php 
                            $carrier_id = $_POST['carrier'] ?? $_GET['carrier'] ?? null;
                            if ($carrier_id) {
                                $carrier_id = (int)$carrier_id;
                                $offices = $db->query("SELECT * FROM offices WHERE carrier_id = $carrier_id ORDER BY city, address")->fetchAll();
                                foreach($offices as $o): 
                            ?>
                                <option value="<?= $o['id'] ?>" <?= (($_POST['from'] ?? $_GET['from'] ?? '') == $o['id']) ? 'selected' : '' ?>><?= htmlspecialchars($o['city']) ?> — <?= htmlspecialchars($o['address']) ?></option>
                            <?php endforeach; } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Куда</label>
                        <select name="to" id="to-select" class="form-select" required onchange="document.getElementById('selected-to').value = this.value;">
                            <option value="">Выберите</option>
                            <?php 
                            if ($carrier_id) {
                                $carrier_id = (int)$carrier_id;
                                $offices = $db->query("SELECT * FROM offices WHERE carrier_id = $carrier_id ORDER BY city, address")->fetchAll();
                                foreach($offices as $o): 
                            ?>
                                <option value="<?= $o['id'] ?>" <?= (($_POST['to'] ?? $_GET['to'] ?? '') == $o['id']) ? 'selected' : '' ?>><?= htmlspecialchars($o['city']) ?> — <?= htmlspecialchars($o['address']) ?></option>
                            <?php endforeach; } ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>Тип отправления</label>
                        <select name="package_type" class="form-select" onchange="toggleFields(this.value)" required>
                            <option value="parcel" <?= (($_POST['package_type'] ?? $_GET['package_type'] ?? '') == 'parcel') ? 'selected' : '' ?>>Посылка</option>
                            <option value="letter" <?= (($_POST['package_type'] ?? $_GET['package_type'] ?? '') == 'letter') ? 'selected' : '' ?>>Письмо</option>
                        </select>
                    </div>

                    <div class="col-md-4" id="weight-div" style="<?= (($_POST['package_type'] ?? $_GET['package_type'] ?? '') == 'letter') ? 'display:none;' : '' ?>">
                        <label>Вес (кг)</label>
                        <input type="number" step="0.1" name="weight" class="form-control" value="<?= $_POST['weight'] ?? $_GET['weight'] ?? '1' ?>" min="0.1" required>
                    </div>

                    <div class="col-md-4" id="letter-div" style="<?= (($_POST['package_type'] ?? $_GET['package_type'] ?? '') == 'letter') ? '' : 'display:none;' ?>">
                        <label>Количество писем</label>
                        <input type="number" name="letter_count" class="form-control" value="<?= $_POST['letter_count'] ?? $_GET['letter_count'] ?? '1' ?>" min="1" max="50">
                    </div>



                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="insurance" class="form-check-input" id="ins" <?= (isset($_POST['insurance']) || isset($_GET['insurance'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ins">Страховка (+2%)</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-lg mt-4 w-100">Рассчитать</button>
            </form>
        </div>
    </div>

    <?php if($result): ?>
    <div class="card mt-5 result-box">
        <div class="card-body text-center">
            <h2><?= $result['cost'] ?> BYN</h2>
            <p class="lead">Время доставки: <?= formatDeliveryTime($result['hours']) ?> (<?= round($result['distance']) ?> км)</p>
            <a href="order_form.php?carrier=<?= $result['carrier']['id'] ?>&weight=<?= ($_POST['package_type'] === 'letter' ? ($_POST['letter_count'] ?? 1) * 0.02 : $_POST['weight'] ?? 1) ?>&cost=<?= $result['cost'] ?>&from=<?= $_POST['from'] ?>&to=<?= $_POST['to'] ?>" class="btn btn-success btn-lg">
                Оформить заказ
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php 
    // Get all calculation results for comparison if form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        // Fetch all carrier options for the same route
        $from = (int)$_POST['from'];
        $to = (int)$_POST['to'];
        
        $all_results = [];
        foreach($carriers as $c) {
            $pathData = dijkstra($graph, $from, $to);
            if ($pathData) {
                $distance = $pathData['distance'];
                $base_hours = $distance / $c['speed_kmh'];

                $type = $_POST['package_type'];
                $gabarit = $_POST['gabarit'] ?? 'small';
                $speed = $_POST['delivery_speed'] ?? 'standard';
                $insurance = isset($_POST['insurance']);

                $volume_weight = 0;
                if ($type === 'parcel') {
                    if ($gabarit === 'medium') $volume_weight = 8;
                    if ($gabarit === 'large') $volume_weight = 20;
                }

                $weight = $type === 'letter' 
                    ? 0.02 * (int)($_POST['letter_count'] ?? 1)
                    : max((float)$_POST['weight'], $volume_weight);

                $max_weight = $c['max_weight'];
                if ($gabarit === 'medium') $max_weight += 8;
                if ($gabarit === 'large') $max_weight += 20;

                if ($weight <= $max_weight) {
                    $cost = $c['base_cost'] 
                          + $weight * $c['cost_per_kg'] 
                          + $distance * $c['cost_per_km'];

                    if ($gabarit === 'medium') $cost += 6;
                    if ($gabarit === 'large') $cost += 15;
                    if ($speed === 'express') { $cost *= 1.25; $base_hours *= 0.7; }
                    if ($insurance) $cost *= 1.02;
                    if ($type === 'letter') $cost = max($cost, 2.5);

                    $cost = round($cost, 2);
                    $hours = round($base_hours, 1);

                    $all_results[] = [
                        'carrier' => $c,
                        'cost' => $cost,
                        'hours' => $hours,
                        'distance' => $distance
                    ];
                }
            }
        }
        
        if (!empty($all_results)) {
            // Sort by different criteria for filters
            $cheapest = $all_results;
            $fastest = $all_results;
            
            usort($cheapest, function($a, $b) { return $a['cost'] <=> $b['cost']; });
            usort($fastest, function($a, $b) { return $a['hours'] <=> $b['hours']; });
            
            $filters = [
                'all' => $all_results,
                'cheapest' => $cheapest,
                'fastest' => $fastest
            ];
            
            $active_filter = $_GET['filter'] ?? 'all';
            $results_to_show = $filters[$active_filter];
    ?>
    <div class="card mt-5 shadow-lg">
        <div class="card-header bg-secondary text-white">
            <h4>Сравнение операторов</h4>
            <div class="btn-group" role="group">
                <a href="?filter=all&carrier=<?= $_POST['carrier'] ?? $_GET['carrier'] ?? '' ?>&from=<?= $_POST['from'] ?? $_GET['from'] ?? '' ?>&to=<?= $_POST['to'] ?? $_GET['to'] ?? '' ?>&package_type=<?= $_POST['package_type'] ?? $_GET['package_type'] ?? '' ?>&weight=<?= $_POST['weight'] ?? $_GET['weight'] ?? ($_POST['package_type'] === 'letter' ? ($_POST['letter_count'] ?? $_GET['letter_count'] ?? 1) * 0.02 : '') ?>&letter_count=<?= $_POST['letter_count'] ?? $_GET['letter_count'] ?? '' ?>&gabarit=<?= $_POST['gabarit'] ?? $_GET['gabarit'] ?? 'small' ?>&delivery_speed=<?= $_POST['delivery_speed'] ?? $_GET['delivery_speed'] ?? 'standard' ?>&insurance=<?= isset($_POST['insurance']) || isset($_GET['insurance']) ? '1' : '0' ?>" class="btn btn-sm <?= $active_filter === 'all' ? 'btn-primary' : 'btn-outline-light' ?>">Все</a>
                <a href="?filter=cheapest&carrier=<?= $_POST['carrier'] ?? $_GET['carrier'] ?? '' ?>&from=<?= $_POST['from'] ?? $_GET['from'] ?? '' ?>&to=<?= $_POST['to'] ?? $_GET['to'] ?? '' ?>&package_type=<?= $_POST['package_type'] ?? $_GET['package_type'] ?? '' ?>&weight=<?= $_POST['weight'] ?? $_GET['weight'] ?? ($_POST['package_type'] === 'letter' ? ($_POST['letter_count'] ?? $_GET['letter_count'] ?? 1) * 0.02 : '') ?>&letter_count=<?= $_POST['letter_count'] ?? $_GET['letter_count'] ?? '' ?>&gabarit=<?= $_POST['gabarit'] ?? $_GET['gabarit'] ?? 'small' ?>&delivery_speed=<?= $_POST['delivery_speed'] ?? $_GET['delivery_speed'] ?? 'standard' ?>&insurance=<?= isset($_POST['insurance']) || isset($_GET['insurance']) ? '1' : '0' ?>" class="btn btn-sm <?= $active_filter === 'cheapest' ? 'btn-success' : 'btn-outline-light' ?>">Самый дешевый</a>
                <a href="?filter=fastest&carrier=<?= $_POST['carrier'] ?? $_GET['carrier'] ?? '' ?>&from=<?= $_POST['from'] ?? $_GET['from'] ?? '' ?>&to=<?= $_POST['to'] ?? $_GET['to'] ?? '' ?>&package_type=<?= $_POST['package_type'] ?? $_GET['package_type'] ?? '' ?>&weight=<?= $_POST['weight'] ?? $_GET['weight'] ?? ($_POST['package_type'] === 'letter' ? ($_POST['letter_count'] ?? $_GET['letter_count'] ?? 1) * 0.02 : '') ?>&letter_count=<?= $_POST['letter_count'] ?? $_GET['letter_count'] ?? '' ?>&gabarit=<?= $_POST['gabarit'] ?? $_GET['gabarit'] ?? 'small' ?>&delivery_speed=<?= $_POST['delivery_speed'] ?? $_GET['delivery_speed'] ?? 'standard' ?>&insurance=<?= isset($_POST['insurance']) || isset($_GET['insurance']) ? '1' : '0' ?>" class="btn btn-sm <?= $active_filter === 'fastest' ? 'btn-info' : 'btn-outline-light' ?>">Самый быстрый</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Оператор</th>
                            <th>Стоимость</th>
                            <th>Время доставки</th>
                            <th>Расстояние</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($results_to_show as $res): ?>
                        <tr>
                            <td style="color: <?= $res['carrier']['color'] ?>"><strong><?= htmlspecialchars($res['carrier']['name']) ?></strong></td>
                            <td><strong><?= $res['cost'] ?> BYN</strong></td>
                            <td>~<?= formatDeliveryTime($res['hours']) ?></td>
                            <td><?= round($res['distance']) ?> км</td>
                            <td>
                                <a href="order_form.php?carrier=<?= $res['carrier']['id'] ?>&weight=<?= ($_POST['package_type'] === 'letter' ? ($_POST['letter_count'] ?? 1) * 0.02 : $_POST['weight'] ?? 1) ?>&cost=<?= $res['cost'] ?>&from=<?= $_POST['from'] ?>&to=<?= $_POST['to'] ?>" 
                                   class="btn btn-sm btn-success">Оформить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
        }
    }
    ?>

    <?php if($error): ?>
    <div class="alert alert-danger mt-4"><?= $error ?></div>
    <?php endif; ?>
</div>

<!-- Footer -->
<footer class="footer mt-auto py-3" style="background-color: rgba(0,0,0,0.05);">
    <div class="container text-center text-muted">
        <p class="mb-1" style="opacity: 0.5; color: #999 !important;">&copy; 2025 Служба доставки. Все права защищены.</p>
        <p class="mb-1" style="opacity: 0.5; color: #999 !important;">Контактный телефон: +375-25-005-50-50</p>
        <p class="mb-0" style="opacity: 0.5; color: #999 !important;">Email: freedeliverya@gmail.com</p>
    </div>
</footer>

<script>
let selected = null;

function selectCarrier(id, name) {
    if (selected) selected.classList.remove('selected');
    const card = event.currentTarget;
    card.classList.add('selected');
    selected = card;

    document.getElementById('selected-carrier').value = id;
    document.getElementById('carrier-name').textContent = name;
    document.getElementById('calc-form').style.display = 'block';

    // Load offices with search functionality
    fetch('get_offices.php?carrier=' + id)
        .then(r => r.json())
        .then(data => {
            ['from', 'to'].forEach(f => {
                const sel = document.querySelector(`select[name="${f}"]`);
                sel.innerHTML = '<option value="">Выберите</option>' + 
                    data.map(o => `<option value="${o.id}">${o.city} — ${o.address}</option>`).join('');
            });
            
            // Initialize search for newly loaded selects
            const fromSelect = document.querySelector('select[name="from"]');
            const toSelect = document.querySelector('select[name="to"]');
            
            // Remove existing search functionality if it exists to prevent duplication
            if (fromSelect && fromSelect.parentNode && fromSelect.parentNode.classList.contains('custom-select-wrapper')) {
                const wrapper = fromSelect.parentNode;
                const parent = wrapper.parentNode;
                parent.replaceChild(fromSelect, wrapper);
                fromSelect.style.display = 'block'; // Show the original select again
            }
            if (toSelect && toSelect.parentNode && toSelect.parentNode.classList.contains('custom-select-wrapper')) {
                const wrapper = toSelect.parentNode;
                const parent = wrapper.parentNode;
                parent.replaceChild(toSelect, wrapper);
                toSelect.style.display = 'block'; // Show the original select again
            }
            
            // Add search functionality to the selects
            if (fromSelect) addSearchToSelect(fromSelect);
            if (toSelect) addSearchToSelect(toSelect);
        });
}

// Add search functionality to select elements with collapsible dropdown
function addSearchToSelect(selectElement) {
    // Check if selectElement is already wrapped - if so, do nothing to prevent duplication
    if (selectElement.parentNode && selectElement.parentNode.classList.contains('custom-select-wrapper')) {
        // If it's already wrapped, just update the search functionality
        const searchInput = selectElement.parentNode.querySelector('input[type="text"]');
        if (searchInput) {
            // Update the search input with current selected value
            if (selectElement.value) {
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                if (selectedOption) {
                    searchInput.value = selectedOption.text;
                }
            }
        }
        return;
    }
    
    // Create a wrapper div for the custom select
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-select-wrapper';
    wrapper.style.position = 'relative';
    
    // Create input for search
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'form-control';
    searchInput.placeholder = 'Поиск...';
    searchInput.style.marginBottom = '5px';
    searchInput.style.cursor = 'pointer';
    searchInput.readOnly = false; // Allow typing for search functionality
    
    // Create a dropdown container that's initially hidden
    const dropdownContainer = document.createElement('div');
    dropdownContainer.style.position = 'absolute';
    dropdownContainer.style.top = '40px';
    dropdownContainer.style.left = '0';
    dropdownContainer.style.width = '100%';
    dropdownContainer.style.zIndex = '1000';
    dropdownContainer.style.backgroundColor = 'white';
    dropdownContainer.style.border = '1px solid #ced4da';
    dropdownContainer.style.borderRadius = '0.375rem';
    dropdownContainer.style.maxHeight = '200px';
    dropdownContainer.style.overflowY = 'auto';
    dropdownContainer.style.display = 'none'; // Initially hidden
    dropdownContainer.style.boxShadow = '0 0.5rem 1rem rgba(0,0,0,0.15)';
    
    // Add click event to toggle dropdown visibility
    searchInput.addEventListener('click', function(e) {
        e.stopPropagation();
        const isHidden = dropdownContainer.style.display === 'none';
        dropdownContainer.style.display = isHidden ? 'block' : 'none';
        
        // If showing, populate with all options
        if (isHidden) {
            updateDropdownOptions(selectElement, '');
        }
    });
    
    // Populate dropdown with options
    function updateDropdownOptions(originalSelect, searchTerm = '') {
        dropdownContainer.innerHTML = '';
        const options = Array.from(originalSelect.options);
        
        options.forEach(option => {
            if (option.value === '') return; // Skip empty option
            
            const optionText = option.text.toLowerCase();
            if (searchTerm === '' || optionText.includes(searchTerm.toLowerCase())) {
                const optionElement = document.createElement('div');
                optionElement.textContent = option.text;
                optionElement.style.padding = '8px 12px';
                optionElement.style.cursor = 'pointer';
                optionElement.style.borderBottom = '1px solid #eee';
                
                optionElement.addEventListener('click', function() {
                    originalSelect.value = option.value;
                    searchInput.value = option.text;
                    dropdownContainer.style.display = 'none';
                    
                    // Trigger change event on the original select
                    originalSelect.dispatchEvent(new Event('change'));
                });
                
                optionElement.addEventListener('mouseover', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                
                optionElement.addEventListener('mouseout', function() {
                    this.style.backgroundColor = 'white';
                });
                
                dropdownContainer.appendChild(optionElement);
            }
        });
    }
    
    // Add search event
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value;
        updateDropdownOptions(selectElement, searchTerm);
        
        // Show dropdown when searching
        dropdownContainer.style.display = 'block';
    });
    
    // Replace the select with the wrapper
    selectElement.parentNode.insertBefore(wrapper, selectElement);
    wrapper.appendChild(searchInput);
    wrapper.appendChild(dropdownContainer);
    
    // Hide the original select
    selectElement.style.display = 'none';
    
    // Initialize with the currently selected value
    if (selectElement.value) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        if (selectedOption) {
            searchInput.value = selectedOption.text;
        }
    }
    
    // Add global click listener to close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            dropdownContainer.style.display = 'none';
        }
    });
}


function toggleFields(type) {
    const isLetter = type === 'letter';
    document.getElementById('weight-div').style.display = isLetter ? 'none' : 'block';
    document.getElementById('letter-div').style.display = isLetter ? 'block' : 'none';
    document.getElementById('gabarit-div').style.display = isLetter ? 'none' : 'block';
}

// Initialize search for select elements when DOM is loaded if carrier is already selected
document.addEventListener('DOMContentLoaded', function() {
    // Check if a carrier is already selected (e.g., from a previous form submission or GET parameter)
    const selectedCarrier = document.getElementById('selected-carrier');
    if (selectedCarrier && selectedCarrier.value) {
        // Initialize search for existing selects
        const fromSelect = document.querySelector('select[name="from"]');
        const toSelect = document.querySelector('select[name="to"]');
        
        if (fromSelect) addSearchToSelect(fromSelect);
        if (toSelect) addSearchToSelect(toSelect);
    }
});

// Theme functionality for cross-page consistency
function toggleTheme() {
    document.body.classList.toggle('dark');
    // Save theme preference in localStorage
    const isDark = document.body.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    
    // Apply theme to all iframes and child elements
    applyThemeToPage(isDark ? 'dark' : 'light');
}

function applyThemeToPage(theme) {
    // This function ensures theme consistency across the site
    if (theme === 'dark') {
        document.body.classList.add('dark');
    } else {
        document.body.classList.remove('dark');
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
    
    // Prevent scrolling to top when filter links are clicked
    const filterLinks = document.querySelectorAll('.btn-group a');
    filterLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Allow the navigation but prevent the default scroll behavior
            // We'll maintain the scroll position by saving it before navigation
            sessionStorage.setItem('scrollPosition', window.scrollY);
        });
    });
    
    // Restore scroll position if available
    const scrollPosition = sessionStorage.getItem('scrollPosition');
    if (scrollPosition) {
        window.scrollTo(0, parseInt(scrollPosition));
        sessionStorage.removeItem('scrollPosition');
    }
});
</script>
</body>
</html>