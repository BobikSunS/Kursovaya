# Исправления и улучшения в системе отслеживания доставки

## Ошибки, которые были исправлены:

### 1. Ошибка "Undefined array key 'delivery_date'" в track.php
- **Проблема**: При отображении страницы отслеживания возникала ошибка, когда в заказе не было поля `delivery_date`
- **Решение**: Добавлена проверка на существование ключа перед его использованием:
  ```php
  <?php if(isset($order['delivery_date']) && $order['delivery_date']): ?>
  ```

### 2. Ошибка "Undefined array key 'processed'" в track.php
- **Проблема**: В базе данных были заказы со старым статусом 'processed', которого не существует в новых определениях статусов
- **Решение**: Создан SQL-скрипт для обновления старых статусов на новые:
  ```sql
  UPDATE orders SET tracking_status = 'paid' WHERE tracking_status = 'processed';
  ```

### 3. Отсутствие колонки `delivery_date` в таблице `orders`
- **Проблема**: В track.php ожидалось наличие колонки `delivery_date`, но её не было в базе данных
- **Решение**: Добавлен SQL-запрос для создания колонки:
  ```sql
  ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_date DATE DEFAULT NULL;
  ```

## Дополнительные улучшения:

### 1. Обновление статусов заказов
- Обновлены все файлы, которые работают со статусами заказов, чтобы использовать новые статусы:
  - created → 'Создан'
  - paid → 'Оплачен'
  - in_transit → 'В пути'
  - sort_center → 'Сорт. центр'
  - out_for_delivery → 'У курьера'
  - delivered → 'Доставлен'
  - delayed → 'Задерживается'
  - cancelled → 'Отменен'
  - returned → 'Возвращен'

### 2. Автоматическое обновление даты доставки
- При изменении статуса заказа на 'delivered' автоматически устанавливается текущая дата в поле `delivery_date`:
  - В admin/index.php (при изменении статуса через админку)
  - В update_order_status.php (при изменении статуса через API)
  - В payment.php (при оплате заказа, если статус уже 'delivered')

### 3. Улучшение истории статусов
- Добавлены описания для всех новых статусов в таблицу `tracking_status_history`
- Все изменения статусов теперь записываются в историю с описанием

### 4. Исправление начального статуса заказа
- При создании заказа через order_form.php теперь устанавливается статус 'created' вместо 'paid'
- Добавлена запись в историю статусов при создании заказа

### 5. Улучшения в админ-панели
- Добавлена кнопка "Перейти" для быстрого перехода к странице отслеживания заказа
- Улучшен поиск по трек-номеру в блоке "Полное управление статусами"

## SQL-запросы для обновления базы данных:

```sql
-- Добавление колонок
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_date DATE DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Обновление старых статусов
UPDATE orders SET tracking_status = 'paid' WHERE tracking_status = 'processed';
UPDATE orders SET tracking_status = 'in_transit' WHERE tracking_status = 'sent';
UPDATE orders SET tracking_status = 'sort_center' WHERE tracking_status = 'sorting_center';
UPDATE orders SET tracking_status = 'out_for_delivery' WHERE tracking_status = 'on_the_way';
UPDATE orders SET tracking_status = 'delivered' WHERE tracking_status = 'received';

-- Обновление таблицы истории статусов
ALTER TABLE tracking_status_history ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL;

-- Обновление описаний в истории статусов
UPDATE tracking_status_history SET description = 'Заказ оплачен и обработан' WHERE status = 'paid' AND description IS NULL;
UPDATE tracking_status_history SET description = 'Посылка в пути' WHERE status = 'in_transit' AND description IS NULL;
UPDATE tracking_status_history SET description = 'Посылка в сортировочном центре' WHERE status = 'sort_center' AND description IS NULL;
UPDATE tracking_status_history SET description = 'Посылка у курьера в пути' WHERE status = 'out_for_delivery' AND description IS NULL;
UPDATE tracking_status_history SET description = 'Заказ доставлен' WHERE status = 'delivered' AND description IS NULL;
UPDATE tracking_status_history SET description = 'Возможна задержка доставки' WHERE status = 'delayed' AND description IS NULL;
UPDATE tracking_status_history SET description = 'Заказ отменен' WHERE status = 'cancelled' AND description IS NULL;
UPDATE tracking_status_history SET description = 'Заказ возвращен отправителю' WHERE status = 'returned' AND description IS NULL;
```

Все ошибки устранены, система теперь корректно работает с новыми статусами и отображает дату доставки вместо расчетного времени доставки.