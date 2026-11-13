<?php

if (($_GET['step'] ?? '') === 'testapi') {
    $paypalTest = new \LanSuite\Module\PayPal\PayPal();
    $connection = $paypalTest->testConnection();
    if ($connection['ok']) {
        $func->confirmation($connection['message'], NO_LINK);
    } else {
        $func->error($connection['message'], NO_LINK);
    }
}

$dsp->AddDoubleRow(
    t('API-Verbindung'),
    $dsp->FetchSpanButton(
        t('PayPal-Verbindung testen'),
        'index.php?mod=install&action=mod_cfg&module=paypal&step=testapi&tab=0',
        'Prüft die gespeicherten Zugangsdaten. Live-Daten funktionieren nur im Live-Modus.'
    )
);
