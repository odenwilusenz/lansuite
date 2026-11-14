<?php

$dsp->NewContent(t('Ergebnis der Zahlung'), t('So siehts aus!'));

$sessionId = (string) ($_GET['session_id'] ?? '');
$stripe = new \LanSuite\Module\Stripe\StripeClient();
$session = $stripe->retrieveSession($sessionId);

if ($session === null) {
    $func->error(t('Die Zahlung ist bei Stripe nicht bestätigt.') . ' ' . (string) $stripe->getLastError(), 'index.php?mod=stripe');
} else {
    $booker = new \LanSuite\Module\Stripe\StripeBooker();
    $result = $booker->bookFromSession($session);
    $row = $booker->findBySession($session);

    if ($result === 'paid' && is_array($row)) {
        $owner = (int) $row['user_id'];
        $visitor = (int) ($auth['userid'] ?? 0);
        if ($owner !== $visitor) {
            $func->confirmation(t('Stripe hat die Zahlung bestätigt. Der Bezahlstatus wird für den angemeldeten Benutzer gesetzt.'), 'index.php?mod=stripe');
        } else {
            $currency = htmlspecialchars($stripe->getCurrency(), ENT_QUOTES, 'UTF-8');
            $dsp->AddSingleRow(t('Die folgenden Artikel wurden erfolgreich bezahlt:'));
            foreach ($booker->itemsOf($row) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $minor = (int) ($item['minor'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
                $amount = \LanSuite\Module\Stripe\StripeMoney::formatMinor($minor, (string) $row['currency']);
                $dsp->AddDoubleRow(
                    (int) ($item['quantity'] ?? 1) . 'x ' . (string) ($item['description'] ?? ''),
                    $amount . ' ' . $currency
                );
            }
            $func->confirmation(t('Die Zahlung wurde verbucht.'), 'index.php?mod=stripe');
        }
    } elseif ($result === 'refunded') {
        $func->information(t('Diese Stripe-Zahlung wurde zurückerstattet.'), 'index.php?mod=stripe');
    } elseif ($result === 'pending') {
        $func->information(t('Stripe hat die Zahlung noch nicht bestätigt. Der Bezahlstatus wird gesetzt, sobald die Bestätigung eintrifft.'), 'index.php?mod=stripe');
    } elseif ($result === 'mismatch') {
        $func->error(t('Der Betrag bei Stripe stimmt nicht mit der LanSuite-Zahlung überein. Es wurde nichts als bezahlt markiert.'), 'index.php?mod=stripe');
    } else {
        $func->error(t('Die Stripe-Zahlung konnte keiner offenen LanSuite-Zahlung zugeordnet werden.'), 'index.php?mod=stripe');
    }
}
