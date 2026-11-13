<?php

/**
 * Process all submitted items and get a payment link and ID from PayPal
 */

use LanSuite\Module\PayPal\PayPalItem;

if ($auth['userid'] == 0 && $cfg['paypal_donation'] == 0) {
    $func->error(t('Du kannst nichts einzahlen wenn du nicht eingeloggt bist.'), "index.php?mod=home");
} else {
    $dsp->NewContent(t('Übersicht der Zahlung'), t('Folgend alle Artikel und Kosten'));

    $paypalObj = new \LanSuite\Module\PayPal\PayPal();
    $currency = htmlspecialchars($paypalObj->getCurrency(), ENT_QUOTES, 'UTF-8');
    $canPay = true;

    if (!empty($_POST['price']) && is_array($_POST['price'])) {
        foreach ($_POST['price'] as $priceID) {
            $details = GetPriceDetails($priceID);
            if ($details === null) {
                $func->error(t('Der ausgewählte Partypreis ist nicht verfügbar.'));
                $canPay = false;
                break;
            }
            $amount = $paypalObj->formatAmount($details['price']);
            $paypalitem = new PayPalItem($details['name'] . ' ' . $details['price_text'], $details['price'], 'PARTY-' . $details['party_id'] . '-' . (int) $priceID, 1);
            $dsp->AddDoubleRow($details['name'] . ' ' . $details['price_text'], $amount . ' ' . $currency);
            $paypalObj->addItem($paypalitem);
        }
    }

    $cateringValueParameter = SanitizeVal($_POST['catering'] ?? 0);
    if ($canPay && $cateringValueParameter > 0) {
        $amount = $paypalObj->formatAmount($cateringValueParameter);
        $paypalitem = new PayPalItem(t('Guthaben Catering'), $cateringValueParameter, 'CATERING', 1);
        $dsp->AddDoubleRow(t('Guthaben Catering'), $amount . ' ' . $currency);
        $paypalObj->addItem($paypalitem);
    }

    $donationValueParameter = SanitizeVal($_POST['donation'] ?? 0);
    if ($canPay && $donationValueParameter > 0) {
        $amount = $paypalObj->formatAmount($donationValueParameter);
        $paypalitem = new PayPalItem(t('Spende'), $donationValueParameter, 'DONATION', 1);
        $dsp->AddDoubleRow(t('Spende'), $amount . ' ' . $currency);
        $paypalObj->addItem($paypalitem);
    }

    if ($canPay && (!empty($_POST['price']) || $donationValueParameter > 0 || $cateringValueParameter > 0)) {
        $dsp->AddDoubleRow(t('Gesamtsumme'), $paypalObj->formatAmount($paypalObj->calcItemsTotal()) . ' ' . $currency);
        $paymentLink = $paypalObj->createPaymentLink();
        if ($paymentLink) {
            $dsp->AddSingleRow($dsp->FetchSpanButton(t('Mit PayPal Bezahlen'), $paymentLink));
        }
    } elseif ($canPay) {
        $func->error(t('Du hast keine Option zum Bezahlen ausgewählt'));
    }
}
