<?php
// Sends articles that just went live to IndexNow (Bing, Yandex, Naver, Seznam), once each. The container runs this
// every 10 minutes (docker/entrypoint.sh); on a local copy without a public https site_url it does nothing.
//
//   php bin/indexnow.php

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$result = indexnow_submit_due();
$result['finished_at'] = now();
if ($result['submitted'] || $result['error']) {
    file_put_contents(indexnow_status_file(), json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo $result['finished_at'] . ' submitted=' . count($result['submitted']) . ($result['error'] ? ' error: ' . $result['error'] : '') . "\n";
exit($result['error'] && indexnow_enabled() ? 2 : 0);
