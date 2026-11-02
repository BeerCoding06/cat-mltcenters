<?php
// Fills the cat breed pages from Wikidata + Thai Wikipedia (+ TheCatAPI traits if a key is set).
// Breeds rarely change — run monthly:   php bin/fetch-breeds.php

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$result = fetch_breeds();
$result['finished_at'] = now();
file_put_contents(dirname((string) config('db_path')) . '/last-breeds.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo $result['finished_at'] . " found={$result['found']} saved={$result['saved']} traits={$result['traits']} skipped=" . count($result['skipped']) . ' errors=' . count($result['errors']) . "\n";
foreach (array_merge($result['skipped'], $result['errors']) as $line) {
    echo "  $line\n";
}
exit($result['saved'] ? 0 : 2);
