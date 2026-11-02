<?php
// Imports (or updates) products from a CSV file — same format as Admin › นำเข้า CSV.
// Run it from cron on a file you refresh regularly so price history keeps growing:
//
//   php bin/import-csv.php path/to/products.csv

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$file = $argv[1] ?? '';
if ($file === '' || !is_readable($file)) {
    fwrite(STDERR, "Usage: php bin/import-csv.php <file.csv>\n");
    exit(1);
}

$fh = fopen($file, 'r');
$result = import_csv($fh);
fclose($fh);

echo date('Y-m-d H:i:s') . " created={$result['created']} updated={$result['updated']} errors=" . count($result['errors']) . "\n";
foreach ($result['errors'] as $line => $errors) {
    echo "  line $line: " . implode(' / ', $errors) . "\n";
}
exit($result['errors'] ? 2 : 0);
