<?php

if (($_GET['step'] ?? '') === 'testapi') {
    $stripeTest = new \LanSuite\Module\Stripe\StripeClient();
    $connection = $stripeTest->testConnection();
    if ($connection['ok']) {
        $func->confirmation($connection['message'], NO_LINK);
    } else {
        $func->error($connection['message'], NO_LINK);
    }
}

$stripeSettings = new \LanSuite\Module\Stripe\StripeClient();
$webhookUrl = htmlspecialchars($stripeSettings->pageUrl('webhook'), ENT_QUOTES, 'UTF-8');

$dsp->AddDoubleRow(t('Webhook-URL'), $webhookUrl);
$dsp->AddDoubleRow(
    t('Webhook-Ereignisse'),
    'checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired, charge.refunded'
);
$dsp->AddDoubleRow(
    t('API-Verbindung'),
    $dsp->FetchSpanButton(
        t('Stripe-Verbindung testen'),
        'index.php?mod=install&action=mod_cfg&module=stripe&step=testapi&tab=0',
        'Prüft den gespeicherten Secret Key des gewählten Modus.'
    )
);
