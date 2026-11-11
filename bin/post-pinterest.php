<?php
// Pins live articles with a cover to the Pinterest board, one at a time with a gap between pins. The container runs this
// every 10 minutes (docker/entrypoint.sh); without the PINTEREST_* settings it does nothing.
//
//   php bin/post-pinterest.php

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$result = pinterest_post_due();
$result['finished_at'] = now();
file_put_contents(pinterest_status_file(), json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if ($result['pinned'] || $result['errors']) {
    echo $result['finished_at'] . ' pinned=' . count($result['pinned']) . ' errors=' . count($result['errors']) . "\n";
}
foreach ($result['errors'] as $slug => $error) {
    echo "  $slug: $error\n";
}
exit($result['errors'] ? 2 : 0);
