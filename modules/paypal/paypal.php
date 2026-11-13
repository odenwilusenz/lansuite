<?php

/**
 * Get form to see what you must pay
 */
if ($auth['userid'] == 0 && $cfg['paypal_donation'] == 0) {
    $func->error(t('Du kannst nichts einzahlen wenn du nicht eingeloggt bist.'), "index.php?mod=home");
} else {
    $dsp->NewContent(t('Einzahlen'), t('Hier sehen sie was für Beträge noch ausstehend sind. Wählen sie was sie bezahlen möchten.'));
    $dsp->AddSmartyTpl('javascript', 'paypal');
    $dsp->SetForm("index.php?mod=paypal&action=createpayment", "paypal");

    if ($auth['userid'] != 0) {
        $paypalObj = new \LanSuite\Module\PayPal\PayPal();
        $currency = htmlspecialchars($paypalObj->getCurrency(), ENT_QUOTES, 'UTF-8');
        $payParties = PartyPricesForPayment((int) $auth['userid'], (int) ($auth['group_id'] ?? 0));
        $payable = [];
        foreach ($payParties as $pay) {
            if (SanitizeVal($pay['price'] ?? null) > 0) {
                $payable[] = $pay;
            }
        }

        if ($payable !== []) {
            $onlyChoice = count($payable) === 1;
            foreach ($payable as $pay) {
                $checked = $onlyChoice || (int) $pay['user_price_id'] === (int) $pay['price_id'];
                $label = $pay['price_text'] . ' ' . $paypalObj->formatAmount($pay['price']) . ' ' . $currency;
                $dsp->AddCheckBoxRow('price[]', $pay['name'], $label, '', null, $checked, null, (int) $pay['price_id']);
                if ($cfg['paypal_depot'] && $pay['depot_price'] > 0) {
                    $depotLabel = $pay['depot_desc'] . ' ' . $paypalObj->formatAmount($pay['depot_price']) . ' ' . $currency;
                    $dsp->AddCheckBoxRow('depot[]', $pay['name'], $depotLabel, '', null, null, null, (int) $pay['price_id']);
                }
            }
        } elseif ($payParties !== []) {
            $dsp->AddSingleRow(t('Für diese Anmeldung ist kein Preis größer 0 hinterlegt.'));
        } else {
            $dsp->AddSingleRow(t('Alle Eintrittspreise bezahlt.'));
        }

        if ($cfg['paypal_catering']) {
            $dsp->AddTextFieldRow("catering", t('Einzahlung für Catering'), 0, "");
        }
    }

    if ($cfg['paypal_donation']) {
        $dsp->AddTextFieldRow("donation", t('Spende für die Organisatoren'), 0, "");
    }

    $dsp->AddFormSubmitRow(t('Weiter'));
}
