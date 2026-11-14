<?php

$paymentId = (int) ($_GET['payment'] ?? 0);
$ownId = (int) ($_SESSION['stripe_payment_id'] ?? 0);
if ($paymentId > 0 && $paymentId === $ownId) {
    $database->query(
        "UPDATE %prefix%stripe_checkout SET status = 'cancelled' WHERE id = ? AND status = 'pending'",
        [$paymentId]
    );
}

$dsp->NewContent(t('Zahlung abgebrochen'), t('Die Stripe-Zahlung wurde abgebrochen'));
$func->error(t('Die Stripe-Zahlung wurde abgebrochen'), 'index.php?mod=stripe');
