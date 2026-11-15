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

$dsp->AddDoubleRow(
    t('Welcher Schlüssel wohin'),
    t('In den geheimen Schlüssel kommt sk_ oder rk_ des gewählten Modus. Der öffentliche Schlüssel pk_ bleibt leer. Das Webhook Secret beginnt mit whsec_ und steht beim Webhook, nicht beim API-Schlüssel.')
);
$dsp->AddDoubleRow(
    t('Rechte eines eingeschränkten Schlüssels'),
    t('Checkout Sessions: Lesen und Schreiben. PaymentIntents: Schreiben. Balance: Lesen, nur für den Verbindungstest. Karte, TWINT und die übrigen Methoden brauchen kein eigenes Schlüsselrecht. TWINT erscheint bei Währung CHF, wenn es im Stripe-Dashboard aktiviert ist.')
);
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
        'Prüft den geheimen Schlüssel des gewählten Modus. sk_ und rk_ sind erlaubt.'
    )
);
