<?php

namespace LanSuite\Module\Stripe;

/**
 * Books a Stripe Checkout payment into the existing LanSuite accounts.
 *
 * A return to the success page does not book anything by itself. Booking runs
 * only after Stripe reports payment_status paid, from the webhook or from a
 * server-side retrieval of that same session.
 */
class StripeBooker
{
    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{id: int, items: array<int, array<string, mixed>>, amount: int}
     */
    public function createPending(int $userId, string $currency, array $lines, bool $live): array
    {
        global $database;

        $items = [];
        $total = 0;
        foreach ($lines as $line) {
            $minor = (int) ($line['minor'] ?? 0);
            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            if ($minor <= 0) {
                continue;
            }
            $items[] = [
                'description' => (string) ($line['description'] ?? ''),
                'minor' => $minor,
                'sku' => (string) ($line['sku'] ?? ''),
                'quantity' => $quantity,
                'booked' => 0,
                'reversed' => 0,
            ];
            $total += $minor * $quantity;
        }
        if ($items === [] || $total <= 0) {
            return ['id' => 0, 'items' => [], 'amount' => 0];
        }

        $statement = $database->query(
            'INSERT INTO %prefix%stripe_checkout
                SET user_id = ?, amount_minor = ?, currency = ?, status = ?, items_json = ?, livemode = ?, created = NOW()',
            [
                $userId,
                $total,
                strtolower($currency),
                'pending',
                $this->encode($items),
                $live ? 1 : 0,
            ]
        );

        return [
            'id' => (int) $database->getStatementInsertID($statement),
            'items' => $items,
            'amount' => $total,
        ];
    }

    public function attachSession(int $paymentId, string $sessionId, string $paymentIntent): void
    {
        global $database;

        if ($paymentId <= 0 || $sessionId === '') {
            return;
        }

        $database->query(
            'UPDATE %prefix%stripe_checkout SET session_id = ? WHERE id = ? AND session_id IS NULL',
            [$sessionId, $paymentId]
        );
        if ($paymentIntent !== '') {
            $this->storePaymentIntent($paymentId, $paymentIntent);
        }
    }

    /**
     * @return string paid|pending|mismatch|missing
     */
    public function bookFromSession(\Stripe\Checkout\Session $session): string
    {
        $row = $this->rowForSession($session);
        if ($row === null) {
            return 'missing';
        }

        $this->rememberStripeIds($row, $session);
        if (!$this->amountsMatch($row, (int) ($session->amount_total ?? -1), (string) ($session->currency ?? ''))) {
            $this->log(t('Stripe-Betrag oder Währung passt nicht zur Zahlung %1. Es wurde nichts verbucht.', (int) $row['id']));
            return 'mismatch';
        }
        if ((bool) $session->livemode !== ((int) $row['livemode'] === 1)) {
            $this->log(t('Der Stripe-Modus passt nicht zur Zahlung %1. Es wurde nichts verbucht.', (int) $row['id']));
            return 'mismatch';
        }
        if ((string) $session->payment_status !== 'paid') {
            return 'pending';
        }
        if ((string) $row['status'] === 'refunded') {
            return 'refunded';
        }
        return $this->bookOpenLines((int) $row['id'], (int) $row['user_id'], (string) $row['currency']);
    }

    public function markSessionStatus(\Stripe\Checkout\Session $session, string $status): void
    {
        global $database;

        if (!in_array($status, ['failed', 'expired', 'cancelled'], true)) {
            return;
        }
        $row = $this->rowForSession($session);
        if ($row === null) {
            return;
        }
        $this->rememberStripeIds($row, $session);
        $database->query(
            "UPDATE %prefix%stripe_checkout SET status = ? WHERE id = ? AND status = 'pending'",
            [$status, (int) $row['id']]
        );
    }

    public function reverseCharge(\Stripe\Charge $charge): void
    {
        $row = $this->rowForCharge($charge);
        if ($row === null) {
            return;
        }

        $paymentIntent = $this->objectId($charge->payment_intent ?? null);
        if ($paymentIntent !== '') {
            $this->storePaymentIntent((int) $row['id'], $paymentIntent);
        }

        $full = $charge->refunded === true
            || ((int) $charge->amount > 0 && (int) $charge->amount_refunded >= (int) $charge->amount);
        if (!$full) {
            $this->log(t('Stripe-Teilrückerstattung für Zahlung %1 wurde nicht automatisch verbucht.', (int) $row['id']));
            return;
        }
        if (!$this->amountsMatch($row, (int) $charge->amount, (string) ($charge->currency ?? ''))) {
            $this->log(t('Stripe-Rückerstattung für Zahlung %1 passt nicht zum ursprünglichen Betrag.', (int) $row['id']));
            return;
        }

        $this->reverseBookedLines((int) $row['id'], (int) $row['user_id'], (string) $row['currency']);
    }

    public function handleEvent(\Stripe\Event $event): void
    {
        $object = $event->data->object ?? null;
        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                if ($object instanceof \Stripe\Checkout\Session) {
                    $this->bookFromSession($object);
                }
                break;
            case 'checkout.session.async_payment_failed':
                if ($object instanceof \Stripe\Checkout\Session) {
                    $this->markSessionStatus($object, 'failed');
                }
                break;
            case 'checkout.session.expired':
                if ($object instanceof \Stripe\Checkout\Session) {
                    $this->markSessionStatus($object, 'expired');
                }
                break;
            case 'charge.refunded':
                if ($object instanceof \Stripe\Charge) {
                    $this->reverseCharge($object);
                }
                break;
        }
    }

    /**
     * @return string new|done|busy
     */
    public function claimEvent(string $eventId): string
    {
        global $database;

        if ($eventId === '') {
            return 'busy';
        }

        try {
            $database->query(
                "INSERT INTO %prefix%stripe_event SET event_id = ?, status = 'processing', received = NOW()",
                [$eventId]
            );
            return 'new';
        } catch (\mysqli_sql_exception $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
        }

        $row = $database->queryWithOnlyFirstRow(
            'SELECT status FROM %prefix%stripe_event WHERE event_id = ?',
            [$eventId]
        );
        if (is_array($row) && ($row['status'] ?? '') === 'done') {
            return 'done';
        }

        $takeover = $database->query(
            "UPDATE %prefix%stripe_event
                SET received = NOW()
              WHERE event_id = ? AND status = 'processing' AND received < DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
            [$eventId]
        );
        if ((int) $database->getStatementAffectedRows($takeover) === 1) {
            return 'new';
        }

        return 'busy';
    }

    public function finishEvent(string $eventId): void
    {
        global $database;

        $database->query(
            "UPDATE %prefix%stripe_event SET status = 'done' WHERE event_id = ?",
            [$eventId]
        );
    }

    public function releaseEvent(string $eventId): void
    {
        global $database;

        $database->query(
            "DELETE FROM %prefix%stripe_event WHERE event_id = ? AND status = 'processing'",
            [$eventId]
        );
    }

    public function findBySession(\Stripe\Checkout\Session $session): ?array
    {
        return $this->rowForSession($session);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemsOf(array $row): array
    {
        $items = json_decode((string) ($row['items_json'] ?? ''), true);

        return is_array($items) ? $items : [];
    }

    /**
     * @return string paid|refunded|mismatch
     */
    private function bookOpenLines(int $paymentId, int $userId, string $currency): string
    {
        return $this->withPaymentLock($paymentId, function (array $row) use ($userId, $currency, $paymentId): string {
            if ((string) $row['status'] === 'refunded') {
                return 'refunded';
            }
            $items = json_decode((string) $row['items_json'], true);
            if (!is_array($items)) {
                $this->log(t('Stripe-Positionen für Zahlung %1 sind ungültig.', $paymentId));
                return 'mismatch';
            }

            foreach ($items as $index => $item) {
                if (!is_array($item) || !empty($item['booked'])) {
                    continue;
                }
                $items[$index]['booked'] = 1;
                $this->saveItems($paymentId, $items);
                try {
                    $this->applyItem($userId, $item, $currency, false);
                } catch (\Throwable $e) {
                    $items[$index]['booked'] = 0;
                    $this->saveItems($paymentId, $items);
                    throw $e;
                }
            }

            $this->markPaid($paymentId);

            return 'paid';
        });
    }

    private function reverseBookedLines(int $paymentId, int $userId, string $currency): void
    {
        $this->withPaymentLock($paymentId, function (array $row) use ($userId, $currency, $paymentId): string {
            $items = json_decode((string) $row['items_json'], true);
            if (!is_array($items)) {
                return 'mismatch';
            }

            foreach ($items as $index => $item) {
                if (!is_array($item) || empty($item['booked']) || !empty($item['reversed'])) {
                    continue;
                }
                $items[$index]['reversed'] = 1;
                $this->saveItems($paymentId, $items);
                try {
                    $this->applyItem($userId, $item, $currency, true);
                } catch (\Throwable $e) {
                    $items[$index]['reversed'] = 0;
                    $this->saveItems($paymentId, $items);
                    throw $e;
                }
            }

            $this->markRefunded($paymentId);

            return 'refunded';
        });
    }

    /**
     * @param callable(array): string $callback
     */
    private function withPaymentLock(int $paymentId, callable $callback): string
    {
        global $database;

        $lockName = 'lansuite_stripe_' . $paymentId;
        $lock = $database->queryWithOnlyFirstRow('SELECT GET_LOCK(?, 15) AS locked', [$lockName]);
        if (!is_array($lock) || (int) ($lock['locked'] ?? 0) !== 1) {
            throw new \RuntimeException('stripe lock');
        }

        try {
            $row = $this->rowById($paymentId);
            if ($row === null) {
                return 'missing';
            }

            return $callback($row);
        } finally {
            $database->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function saveItems(int $paymentId, array $items): void
    {
        global $database;

        $database->query(
            'UPDATE %prefix%stripe_checkout SET items_json = ? WHERE id = ?',
            [$this->encode($items), $paymentId]
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function applyItem(int $userId, array $item, string $currency, bool $reverse): void
    {
        global $database;

        $minor = (int) ($item['minor'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
        if ($reverse) {
            $minor *= -1;
        }
        $amount = StripeMoney::formatMinor($minor, $currency);
        $sku = (string) ($item['sku'] ?? '');

        if ($sku === 'DONATION') {
            $accounting = new \LanSuite\Module\CashMgr\Accounting(0, $userId);
            $comment = $reverse
                ? t('Rückerstattung einer Spende via Stripe')
                : t('Spende von') . ' ' . $this->username($userId);
            $accounting->booking($amount, $comment, 0, true);
            return;
        }

        if ($sku === 'CATERING') {
            if ($userId <= 0) {
                $this->log(t('Stripe-Catering ohne Benutzer wurde nicht verbucht.'));
                return;
            }
            $accounting = new \LanSuite\Module\Foodcenter\Accounting($userId);
            $comment = $reverse ? t('Rückerstattung via Stripe') : t('Einzahlung via Stripe');
            $accounting->change($amount, $comment, $userId);
            return;
        }

        $data = StripeSkuToParty($sku);
        if (!is_array($data) || empty($data['party_id']) || $userId <= 0) {
            $this->log(t('Stripe-Partyposition %1 wurde nicht verbucht.', $sku));
            return;
        }

        if ($reverse) {
            $this->guestList()->SetNotPaid($userId, (int) $data['party_id']);
            return;
        }

        $database->query(
            'UPDATE %prefix%party_user SET price_id = ? WHERE user_id = ? AND party_id = ? AND (price_id = 0 OR price_id IS NULL)',
            [(int) $data['price_id'], $userId, (int) $data['party_id']]
        );
        $this->guestList()->SetPaid($userId, (int) $data['party_id']);
    }

    private function guestList(): \LanSuite\Module\GuestList\GuestList
    {
        $seating = new \LanSuite\Module\Seating\Seat2();
        $mail = new \LanSuite\Module\Mail\Mail();
        $userManager = new \LanSuite\Module\UsrMgr\UserManager($mail);

        return new \LanSuite\Module\GuestList\GuestList($seating, $userManager);
    }

    private function markPaid(int $paymentId): void
    {
        global $database;

        $database->query(
            "UPDATE %prefix%stripe_checkout
                SET status = 'paid', booked_at = IF(booked_at IS NULL, NOW(), booked_at)
              WHERE id = ? AND status IN ('pending', 'paid')",
            [$paymentId]
        );
    }

    private function markRefunded(int $paymentId): void
    {
        global $database;

        $database->query(
            "UPDATE %prefix%stripe_checkout SET status = 'refunded' WHERE id = ? AND status IN ('pending', 'paid', 'refunded')",
            [$paymentId]
        );
    }

    private function rememberStripeIds(array $row, \Stripe\Checkout\Session $session): void
    {
        global $database;

        $paymentId = (int) $row['id'];
        if (empty($row['session_id'])) {
            $database->query(
                'UPDATE %prefix%stripe_checkout SET session_id = ? WHERE id = ? AND session_id IS NULL',
                [(string) $session->id, $paymentId]
            );
        }
        $this->storePaymentIntent($paymentId, $this->objectId($session->payment_intent ?? null));
    }

    private function storePaymentIntent(int $paymentId, string $paymentIntent): void
    {
        global $database;

        if ($paymentId <= 0 || $paymentIntent === '') {
            return;
        }

        try {
            $database->query(
                'UPDATE %prefix%stripe_checkout SET payment_intent = ? WHERE id = ? AND payment_intent IS NULL',
                [$paymentIntent, $paymentId]
            );
        } catch (\mysqli_sql_exception $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
        }
    }

    private function isDuplicateKey(\mysqli_sql_exception $e): bool
    {
        return (int) $e->getCode() === 1062 || str_contains($e->getMessage(), 'Duplicate');
    }

    private function rowForSession(\Stripe\Checkout\Session $session): ?array
    {
        $paymentId = (int) $this->metadataValue($session->metadata ?? null, 'lansuite_payment_id');
        $row = $this->rowById($paymentId);
        if ($row === null) {
            return null;
        }
        if (!empty($row['session_id']) && (string) $row['session_id'] !== (string) $session->id) {
            return null;
        }

        return $row;
    }

    private function rowForCharge(\Stripe\Charge $charge): ?array
    {
        global $database;

        $paymentIntent = $this->objectId($charge->payment_intent ?? null);
        if ($paymentIntent !== '') {
            $row = $database->queryWithOnlyFirstRow(
                'SELECT * FROM %prefix%stripe_checkout WHERE payment_intent = ?',
                [$paymentIntent]
            );
            if (is_array($row) && isset($row['id'])) {
                return $row;
            }
        }

        $paymentId = (int) $this->metadataValue($charge->metadata ?? null, 'lansuite_payment_id');

        return $this->rowById($paymentId);
    }

    private function rowById(int $paymentId): ?array
    {
        global $database;

        if ($paymentId <= 0) {
            return null;
        }
        $row = $database->queryWithOnlyFirstRow(
            'SELECT * FROM %prefix%stripe_checkout WHERE id = ?',
            [$paymentId]
        );
        if (!is_array($row) || !isset($row['id'])) {
            return null;
        }

        return $row;
    }

    private function amountsMatch(array $row, int $minor, string $currency): bool
    {
        return (int) $row['amount_minor'] === $minor
            && strcasecmp((string) $row['currency'], $currency) === 0;
    }

    private function objectId(mixed $value): string
    {
        if (is_object($value) && isset($value->id)) {
            return (string) $value->id;
        }
        if (is_string($value)) {
            return $value;
        }

        return '';
    }

    private function metadataValue(mixed $metadata, string $key): string
    {
        if (is_object($metadata) && isset($metadata->{$key})) {
            return (string) $metadata->{$key};
        }
        if (is_array($metadata) && isset($metadata[$key])) {
            return (string) $metadata[$key];
        }

        return '';
    }

    private function username(int $userId): string
    {
        global $database;

        if ($userId <= 0) {
            return t('Gast');
        }
        $row = $database->queryWithOnlyFirstRow('SELECT username FROM %prefix%user WHERE userid = ?', [$userId]);
        $name = is_array($row) ? trim((string) ($row['username'] ?? '')) : '';

        return $name !== '' ? $name : t('Gast');
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function encode(array $items): string
    {
        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function log(string $message): void
    {
        global $func;

        $func->log_event($message, 1, 'stripe', 'stripe');
    }
}
