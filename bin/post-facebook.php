<?php
// Shares articles that just went live on the Facebook Page, once each. The container runs this every 10 minutes
// (docker/entrypoint.sh); without FB_PAGE_TOKEN it does nothing.
//
//   php bin/post-facebook.php

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$result = facebook_post_due();
$result['finished_at'] = now();
file_put_contents(facebook_status_file(), json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo $result['finished_at'] . ' posted=' . count($result['posted']) . ' errors=' . count($result['errors'])
    . ($result['stopped'] ? ' stopped: ' . $result['stopped'] : '') . "\n";
foreach ($result['errors'] as $slug => $error) {
    echo "  $slug: $error\n";
}
exit($result['errors'] ? 2 : 0);
