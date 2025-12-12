<?php
// Проверка доступных расширений PHP
echo "Доступные расширения PHP:\n";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $extension) {
    echo "- " . $extension . "\n";
}

echo "\nПроверка наличия необходимых расширений:\n";
$required_extensions = ['mysqli', 'pdo_mysql', 'curl', 'json'];
foreach ($required_extensions as $ext) {
    $status = extension_loaded($ext) ? 'ДА' : 'НЕТ';
    echo "$ext: $status\n";
}
?>