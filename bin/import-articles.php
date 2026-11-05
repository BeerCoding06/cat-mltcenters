<?php
// Turns the files in content/articles into scheduled drafts — same as Admin › บทความ › นำเข้าบทความจากไฟล์.
// Articles whose slug already exists are skipped, so running it again is safe.
//
//   php bin/import-articles.php

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$result = articles_import_files();
echo date('Y-m-d H:i:s') . " created={$result['created']} skipped={$result['skipped']} errors=" . count($result['errors']) . "\n";
foreach ($result['errors'] as $file => $error) {
    echo "  $file: $error\n";
}
exit($result['errors'] ? 2 : 0);
