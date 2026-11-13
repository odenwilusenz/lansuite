<?php

if ($auth['userid'] == 0 && $cfg['paypal_donation'] == 0) {
    $func->error(t('Du kannst nichts einzahlen wenn du nicht eingeloggt bist.'), "index.php?mod=home");
} elseif (isset($_GET['failed'])) {
    $dsp->NewContent(t('Zahlung abgebrochen'), t('Die PayPal-Zahlung wurde abgebrochen'));
    $func->error(t('Die PayPal-Zahlung wurde abgebrochen'));
} else {
    $orderId = (string) ($_GET['token'] ?? ($_SESSION['paypal_payment_id'] ?? ''));
    $paypalObj = new \LanSuite\Module\PayPal\PayPal();

    if (!empty($_SESSION['paypal_processed'][$orderId])) {
        $dsp->NewContent(t('Ergebnis der Zahlung'), t('So siehts aus!'));
        $func->confirmation(t('Diese Zahlung wurde bereits verarbeitet.'), 'index.php?mod=paypal');
    } elseif ($orderId === '') {
        $func->error(t('Fehler bei der Übermittlung an PayPal') . ' ' . t('Die PayPal-Bestellung fehlt.'));
    } else {
        $order = $paypalObj->captureOrder($orderId);
        if ($order === null && $paypalObj->isAlreadyCaptured()) {
            $order = $paypalObj->getOrder($orderId);
        }

        $status = is_array($order) ? (string) ($order['status'] ?? '') : '';
        if ($status !== 'COMPLETED') {
            $dsp->NewContent(t('Zahlung fehlgeschlagen'), t('Leider war die Zahlung nicht erfolgreich!'));
            $func->error(t('Die PayPal-Zahlung wurde abgebrochen') . ' ' . (string) $paypalObj->getLastError());
        } else {
            $dsp->NewContent(t('Ergebnis der Zahlung'), t('So siehts aus!'));
            $items = paypalPaidItems($paypalObj, $order, $orderId);
            if ($items === []) {
                $func->error(t('PayPal hat die Zahlung abgeschlossen, aber die gebuchten Positionen fehlen. Bitte einen Admin, den Bezahlstatus zu prüfen.'));
            } else {
                $_SESSION['paypal_processed'][$orderId] = true;
                $currency = htmlspecialchars($paypalObj->getCurrency(), ENT_QUOTES, 'UTF-8');
                $dsp->AddSingleRow(t('Die folgenden Artikel wurden erfolgreich bezahlt:'));
                foreach ($items as $item) {
                    $dsp->AddDoubleRow($item->quantity . 'x ' . $item->description, $paypalObj->formatAmount($item->value) . ' ' . $currency);
                    paypalBookPaidItem($item);
                }
            }
        }
    }
}

/**
 * @return \LanSuite\Module\PayPal\PayPalItem[]
 */
function paypalPaidItems(\LanSuite\Module\PayPal\PayPal $paypalObj, array $order, string $orderId): array
{
    $items = $paypalObj->getItemsFromOrder($order);
    if ($items === []) {
        $freshOrder = $paypalObj->getOrder($orderId);
        if (is_array($freshOrder)) {
            $items = $paypalObj->getItemsFromOrder($freshOrder);
        }
    }

    $storedItems = $_SESSION['paypal_order_items']['items'] ?? null;
    if ($items === [] && (($_SESSION['paypal_order_items']['id'] ?? '') === $orderId) && is_array($storedItems)) {
        foreach ($storedItems as $storedItem) {
            $items[] = new \LanSuite\Module\PayPal\PayPalItem(
                $storedItem['description'] ?? '',
                $storedItem['value'] ?? 0,
                $storedItem['sku'] ?? '',
                (int) ($storedItem['quantity'] ?? 1)
            );
        }
    }

    return $items;
}

function paypalBookPaidItem(\LanSuite\Module\PayPal\PayPalItem $item): void
{
    global $auth, $database;

    switch ($item->sku) {
        case 'DONATION':
            $accounting = new \LanSuite\Module\CashMgr\Accounting(0, $auth['userid']);
            $accounting->booking($item->value, t('Spende von') . ' ' . $auth['username'], 0, true);
            break;
        case 'CATERING':
            $accounting = new \LanSuite\Module\Foodcenter\Accounting($auth['userid']);
            $accounting->change($item->value, t('Einzahlung via PayPal'), $auth['userid']);
            break;
        default:
            $data = SKUtoParty((string) $item->sku);
            if (!is_array($data) || empty($data['party_id'])) {
                break;
            }
            $database->query(
                'UPDATE %prefix%party_user SET price_id = ? WHERE user_id = ? AND party_id = ? AND (price_id = 0 OR price_id IS NULL)',
                [(int) $data['price_id'], (int) $auth['userid'], (int) $data['party_id']]
            );
            $seating = new \LanSuite\Module\Seating\Seat2();
            $mail = new \LanSuite\Module\Mail\Mail();
            $userManager = new \LanSuite\Module\UsrMgr\UserManager($mail);
            $guestList = new \LanSuite\Module\GuestList\GuestList($seating, $userManager);
            $guestList->SetPaid($auth['userid'], $data['party_id']);
            break;
    }
}
