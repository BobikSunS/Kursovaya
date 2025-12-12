# Инструкция по установке и запуску системы доставки с картами

## Требования
- XAMPP (Apache, MySQL, PHP)
- Браузер с поддержкой JavaScript

## Установка

### 1. Подготовка базы данных

1. Запустите XAMPP и включите Apache и MySQL
2. Откройте phpMyAdmin в браузере (обычно http://localhost/phpmyadmin)
3. Создайте новую базу данных с названием `delivery_by`
4. Импортируйте SQL-дамп из файла `database_dump.sql` (или используйте существующую структуру)

### 2. Обновление структуры базы данных

1. Выполните скрипт обновления структуры базы данных:
   ```bash
   php update_database_structure.php
   ```

2. Если PHP не установлен в системе, выполните SQL-запросы вручную в phpMyAdmin:
   ```sql
   ALTER TABLE offices ADD COLUMN lat DECIMAL(10, 8) NULL, ADD COLUMN lng DECIMAL(11, 8) NULL;
   
   DROP TABLE IF EXISTS routes;
   
   CREATE TABLE calculated_routes (
       id INT AUTO_INCREMENT PRIMARY KEY,
       from_office_id INT NOT NULL,
       to_office_id INT NOT NULL,
       distance_km DECIMAL(8,2) NOT NULL,
       duration_min INT NOT NULL,
       route_data TEXT,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (from_office_id) REFERENCES offices(id) ON DELETE CASCADE,
       FOREIGN KEY (to_office_id) REFERENCES offices(id) ON DELETE CASCADE,
       UNIQUE KEY unique_route (from_office_id, to_office_id)
   );
   ```

### 3. Геокодирование адресов офисов

1. Запустите скрипт геокодирования:
   ```bash
   php geocode_offices.php
   ```
   
   Этот скрипт добавит координаты для всех офисов, используя Nominatim (OpenStreetMap).

2. Внимание: Nominatim имеет ограничения на количество запросов (1 запрос в секунду), поэтому процесс может занять некоторое время.

### 4. Размещение файлов

1. Поместите все файлы проекта в папку `htdocs` XAMPP (обычно `C:\xampp\htdocs\delivery_by\`)

### 5. Настройка соединения с базой данных

Убедитесь, что файл `db.php` содержит правильные настройки подключения:
```php
<?php
$host = 'localhost';
$dbname = 'delivery_by';
$username = 'root';
$password = ''; // или ваш пароль от MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>
```

## Использование системы

### Основные функции

1. **Карта доставки** (`map_page.php`):
   - Интерактивная карта с отделениями
   - Выбор компании-перевозчика
   - Выбор отделений отправления и получения
   - Расчет реальных маршрутов по дорогам
   - Отображение расстояния, времени и стоимости доставки
   - Построение маршрута на карте
   - Создание заказа прямо с карты

2. **Калькулятор доставки** (`calculator.php`):
   - Расчет стоимости доставки
   - Выбор параметров посылки

3. **Управление заказами**:
   - Создание заказов
   - Отслеживание статусов
   - История заказов

### Настройка для оффлайн использования

Если у вас нет доступа к интернету, система будет использовать приближенные расчеты на основе координат. Для полноценного оффлайн-использования с реальными маршрутами:

1. Установите локальный сервер OSRM:
   ```bash
   # Установка зависимостей
   sudo apt-get update
   sudo apt-get install build-essential libboost-all-dev libprotobuf-dev protobuf-compiler liblua5.2-dev libzip-dev libgflags-dev libgoogle-glog-dev
   
   # Клонирование и сборка OSRM
   git clone https://github.com/Project-OSRM/osrm-backend.git
   cd osrm-backend
   mkdir build && cd build
   cmake ..
   make -j$(nproc)
   sudo make install
   ```

2. Загрузите данные OpenStreetMap для Беларуси:
   - Скачайте PBF-файл для Беларуси с https://download.geofabrik.de/europe/belarus.html
   - Обработайте его с помощью OSRM:
   ```bash
   osrm-extract belarus-latest.osm.pbf -p osrm-backend/profiles/car.lua
   osrm-partition belarus-latest.osrm
   osrm-customize belarus-latest.osrm
   ```

3. Запустите локальный сервер OSRM:
   ```bash
   osrm-routed belarus-latest.osrm
   ```

4. Измените URL в `calculate_route.php` на ваш локальный сервер OSRM.

## Файлы проекта

- `map_page.php` - основная страница с картой и маршрутами
- `calculate_route.php` - расчет маршрутов (онлайн/оффлайн)
- `get_carriers.php` - получение списка перевозчиков
- `get_offices_with_coords.php` - получение офисов с координатами
- `geocode_offices.php` - геокодирование адресов офисов
- `update_database_structure.php` - обновление структуры базы данных

## API для маршрутов

Система использует следующие API:

1. **OSRM** (онлайн): http://router.project-osrm.org - для расчета реальных маршрутов по дорогам
2. **Nominatim** (онлайн): https://nominatim.openstreetmap.org - для геокодирования адресов
3. **OpenStreetMap**: https://tile.openstreetmap.org - для тайлов карты

Для оффлайн-использования рекомендуется настроить локальные сервера этих сервисов.

## Безопасность

- Скрипт `geocode_offices.php` уважает ограничения API Nominatim (1 запрос в секунду)
- Все входные данные валидируются
- Используется подготовленные выражения для защиты от SQL-инъекций

## Устранение неполадок

1. Если карта не загружается:
   - Проверьте подключение к интернету
   - Убедитесь, что браузер поддерживает JavaScript
   - Проверьте настройки CORS в браузере

2. Если не находятся координаты:
   - Проверьте, запущен ли скрипт геокодирования
   - Убедитесь, что адреса офисов корректны

3. Если не рассчитываются маршруты:
   - Проверьте, есть ли координаты у офисов
   - Убедитесь, что API OSRM доступен (или настроен локальный сервер)