<?php
require 'db.php';

try {
    // Add sample offices for each carrier
    $stmt = $db->prepare("SELECT id FROM carriers");
    $stmt->execute();
    $carriers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($carriers as $carrier_id) {
        // Add some sample offices for each carrier
        $offices = [
            ['Минск', 'ул. Независимости, 1'],
            ['Гомель', 'ул. Советская, 10'],
            ['Могилёв', 'ул. Первомайская, 5'],
            ['Витебск', 'ул. Октябрьская, 15'],
            ['Гродно', 'ул. Свободы, 8'],
            ['Брест', 'ул. Ленина, 20']
        ];

        foreach ($offices as $office) {
            $stmt = $db->prepare("INSERT INTO offices (carrier_id, city, address) VALUES (?, ?, ?)");
            $stmt->execute([$carrier_id, $office[0], $office[1]]);
        }
    }

    echo "Added sample offices for all carriers.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>