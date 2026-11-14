<?php

if ($auth['userid'] == 0 && empty($cfg['stripe_donation'])) {
    $func->error(t('Du kannst nichts einzahlen wenn du nicht eingeloggt bist.'), 'index.php?mod=home');
} else {
    $dsp->NewContent(t('Übersicht der Zahlung'), t('Folgend alle Artikel und Kosten'));

    $stripe = new \LanSuite\Module\Stripe\StripeClient();
    $currency = htmlspecialchars($stripe->getCurrency(), ENT_QUOTES, 'UTF-8');
    $lines = [];
    $canPay = true;

    if (!empty($_POST['price']) && is_array($_POST['price'])) {
        foreach ($_POST['price'] as $priceID) {
            $details = StripeGetPriceDetails($priceID);
            if ($details === null) {
                $func->error(t('Der ausgewählte Partypreis ist nicht verfügbar.'));
                $canPay = false;
                break;
            }
            $description = $details['name'] . ' ' . $details['price_text'];
            $minor = \LanSuite\Module\Stripe\StripeMoney::toMinor((float) $details['price'], $stripe->getCurrency());
            $lines[] = [
                'description' => $description,
                'minor' => $minor,
                'sku' => 'PARTY-' . $details['party_id'] . '-' . (int) $priceID,
                'quantity' => 1,
            ];
            $dsp->AddDoubleRow($description, $stripe->formatAmount($details['price']) . ' ' . $currency);
        }
    }

    $cateringValue = StripeSanitizeVal($_POST['catering'] ?? 0);
    if ($canPay && !empty($cfg['stripe_catering']) && $cateringValue > 0 && (int) $auth['userid'] > 0) {
        $lines[] = [
            'description' => t('Guthaben Catering'),
            'minor' => \LanSuite\Module\Stripe\StripeMoney::toMinor($cateringValue, $stripe->getCurrency()),
            'sku' => 'CATERING',
            'quantity' => 1,
        ];
        $dsp->AddDoubleRow(t('Guthaben Catering'), $stripe->formatAmount($cateringValue) . ' ' . $currency);
    }

    $donationValue = StripeSanitizeVal($_POST['donation'] ?? 0);
    if ($canPay && !empty($cfg['stripe_donation']) && $donationValue > 0) {
        $lines[] = [
            'description' => t('Spende'),
            'minor' => \LanSuite\Module\Stripe\StripeMoney::toMinor($donationValue, $stripe->getCurrency()),
            'sku' => 'DONATION',
            'quantity' => 1,
        ];
        $dsp->AddDoubleRow(t('Spende'), $stripe->formatAmount($donationValue) . ' ' . $currency);
    }

    if ($canPay && $lines !== []) {
        $booker = new \LanSuite\Module\Stripe\StripeBooker();
        $pending = $booker->createPending((int) $auth['userid'], $stripe->getCurrency(), $lines, $stripe->isLive());
        if ($pending['id'] <= 0) {
            $func->error(t('Der Zahlungsbetrag muss größer als 0 sein.'));
        } else {
            $total = \LanSuite\Module\Stripe\StripeMoney::formatMinor($pending['amount'], $stripe->getCurrency());
            $dsp->AddDoubleRow(t('Gesamtsumme'), $total . ' ' . $currency);
            $session = $stripe->createSession($pending['id'], (int) $auth['userid'], $pending['items']);
            if ($session === null) {
                $func->error(t('Fehler bei der Übermittlung an Stripe') . ' ' . (string) $stripe->getLastError());
            } else {
                $paymentIntent = is_object($session->payment_intent ?? null)
                    ? (string) $session->payment_intent->id
                    : (string) ($session->payment_intent ?? '');
                $booker->attachSession($pending['id'], (string) $session->id, $paymentIntent);
                $_SESSION['stripe_payment_id'] = $pending['id'];
                $dsp->AddSingleRow($dsp->FetchSpanButton(t('Mit Stripe bezahlen'), (string) $session->url));
            }
        }
    } elseif ($canPay) {
        $func->error(t('Du hast keine Option zum Bezahlen ausgewählt'));
    }
}
