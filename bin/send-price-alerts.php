<?php
// Emails price-drop alerts that are due (does nothing until mail.resend_key and mail.from are set). Run every few minutes.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

$result = alerts_send_due();
if ($result['sent'] || $result['failed']) {
    echo now() . ' price alerts sent=' . $result['sent'] . ' failed=' . $result['failed'] . "\n";
}
