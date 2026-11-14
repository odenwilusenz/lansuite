<?php

if ($auth['userid'] == 0 && empty($cfg['stripe_donation'])) {
    $func->error(t('Du kannst nichts einzahlen wenn du nicht eingeloggt bist.'), 'index.php?mod=home');
} else {
    $dsp->NewContent(t('Einzahlen'), t('Hier sehen sie was für Beträge noch ausstehend sind. Wählen sie was sie bezahlen möchten.'));
    $dsp->SetForm('index.php?mod=stripe&action=createpayment', 'stripe');

    if ($auth['userid'] != 0) {
        $stripe = new \LanSuite\Module\Stripe\StripeClient();
        $currency = htmlspecialchars($stripe->getCurrency(), ENT_QUOTES, 'UTF-8');
        $payParties = StripePartyPricesForPayment((int) $auth['userid'], (int) ($auth['group_id'] ?? 0));
        $payable = [];
        foreach ($payParties as $pay) {
            if (StripeSanitizeVal($pay['price'] ?? null) > 0) {
                $payable[] = $pay;
            }
        }

        if ($payable !== []) {
            $onlyChoice = count($payable) === 1;
            foreach ($payable as $pay) {
                $checked = $onlyChoice || (int) $pay['user_price_id'] === (int) $pay['price_id'];
                $label = $pay['price_text'] . ' ' . $stripe->formatAmount($pay['price']) . ' ' . $currency;
                $dsp->AddCheckBoxRow('price[]', $pay['name'], $label, '', null, $checked, null, (int) $pay['price_id']);
                if (!empty($cfg['stripe_depot']) && $pay['depot_price'] > 0) {
                    $depotLabel = $pay['depot_desc'] . ' ' . $stripe->formatAmount($pay['depot_price']) . ' ' . $currency;
                    $dsp->AddCheckBoxRow('depot[]', $pay['name'], $depotLabel, '', null, null, null, (int) $pay['price_id']);
                }
            }
        } elseif ($payParties !== []) {
            $dsp->AddSingleRow(t('Für diese Anmeldung ist kein Preis größer 0 hinterlegt.'));
        } else {
            $dsp->AddSingleRow(t('Alle Eintrittspreise bezahlt.'));
        }

        if (!empty($cfg['stripe_catering'])) {
            $dsp->AddTextFieldRow('catering', t('Einzahlung für Catering'), 0, '');
        }
    }

    if (!empty($cfg['stripe_donation'])) {
        $dsp->AddTextFieldRow('donation', t('Spende für die Organisatoren'), 0, '');
    }

    $dsp->AddFormSubmitRow(t('Weiter'));
}
