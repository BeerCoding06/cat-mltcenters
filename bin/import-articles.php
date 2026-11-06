<?php
// Turns the files in content/articles into scheduled drafts — same as Admin › บทความ › นำเข้าบทความจากไฟล์.
// Articles whose slug already exists are skipped, so running it again is safe.
//
//   php bin/import-articles.php             as drafts, to approve in the admin
//   php bin/import-articles.php --publish   approved at once: each goes live by itself at its publish_at

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

$result = articles_import_files(ARTICLE_FILES_DIR, in_array('--publish', $argv, true));
$result['covers'] = articles_apply_covers();
$result['rescheduled'] = articles_sync_schedule();
echo date('Y-m-d H:i:s') . " created={$result['created']} skipped={$result['skipped']} covers={$result['covers']} rescheduled={$result['rescheduled']} errors=" . count($result['errors']) . "\n";
foreach ($result['errors'] as $file => $error) {
    echo "  $file: $error\n";
}
exit($result['errors'] ? 2 : 0);
