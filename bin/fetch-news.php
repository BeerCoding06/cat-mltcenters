<?php
// Collects cat news headlines from the RSS feeds in config.php ('news_feeds').
// Run a few times a day:   php bin/fetch-news.php

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$result = fetch_news();
$result['finished_at'] = now();
file_put_contents(dirname((string) config('db_path')) . '/last-news.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo $result['finished_at'] . " feeds={$result['feeds']} seen={$result['seen']} added={$result['added']} errors=" . count($result['errors']) . "\n";
foreach ($result['errors'] as $line) {
    echo "  $line\n";
}
exit($result['errors'] && !$result['added'] ? 2 : 0);
