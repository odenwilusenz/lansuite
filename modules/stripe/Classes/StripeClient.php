<?php

namespace LanSuite\Module\Stripe;

use LanSuite\Module\Mail\SmtpSecret;

/**
 * Stripe Checkout on the hosted payment page.
 *
 * payment_method_types is omitted on purpose. Stripe then offers every method
 * enabled for this account and currency, including TWINT when the currency is CHF.
 */
class StripeClient
{
    private string $mode = 'test';

    private string $currency = 'EUR';

    private string $description = '';

    private ?string $lastError = null;

    private ?\Stripe\StripeClient $api = null;

    public function __construct()
    {
        global $cfg;

        $this->mode = (($cfg['stripe_mode'] ?? '') === 'live') ? 'live' : 'test';

        $currency = strtoupper(trim((string) ($cfg['stripe_currency_code'] ?? 'EUR')));
        $this->currency = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR';

        $description = trim((string) ($cfg['stripe_desc_name'] ?? ''));
        $this->description = $description !== '' ? $description : t('Anmeldung an Party');
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function formatAmount(float|int|string $amount): string
    {
        return StripeMoney::formatMinor(StripeMoney::toMinor((float) $amount, $this->currency), $this->currency);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $keyError = $this->keyProblem($this->secretKey());
        if ($keyError !== null) {
            return ['ok' => false, 'message' => $keyError];
        }

        try {
            $this->api()->balance->retrieve();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->safeError($e)];
        }

        $modeLabel = $this->mode === 'live' ? t('Live') : t('Test');

        return [
            'ok' => true,
            'message' => t('Verbindung zu Stripe steht. Modus: %1, Währung: %2.', $modeLabel, $this->currency),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function createSession(int $paymentId, int $userId, array $items): ?\Stripe\Checkout\Session
    {
        $keyError = $this->keyProblem($this->secretKey());
        if ($keyError !== null) {
            $this->lastError = $keyError;
            return null;
        }

        $lineItems = [];
        foreach ($items as $item) {
            $minor = (int) ($item['minor'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            if ($minor <= 0) {
                continue;
            }
            $name = trim((string) ($item['description'] ?? ''));
            if ($name === '') {
                $name = t('Einzahlung');
            }
            $lineItems[] = [
                'quantity' => $quantity,
                'price_data' => [
                    'currency' => strtolower($this->currency),
                    'unit_amount' => $minor,
                    'product_data' => [
                        'name' => $this->clip($name, 250),
                    ],
                ],
            ];
        }
        if ($lineItems === []) {
            $this->lastError = t('Der Zahlungsbetrag muss größer als 0 sein.');
            return null;
        }

        $metadata = [
            'lansuite_payment_id' => (string) $paymentId,
            'user_id' => (string) $userId,
        ];
        $params = [
            'mode' => 'payment',
            'client_reference_id' => (string) $userId,
            'success_url' => $this->pageUrl('return') . '&payment=' . $paymentId . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->pageUrl('cancel') . '&payment=' . $paymentId,
            'line_items' => $lineItems,
            'metadata' => $metadata,
            'payment_intent_data' => [
                'metadata' => $metadata,
                'description' => $this->clip($this->description, 500),
            ],
        ];

        $email = $this->userEmail($userId);
        if ($email !== '') {
            $params['customer_email'] = $email;
        }

        try {
            $session = $this->api()->checkout->sessions->create($params, [
                'idempotency_key' => 'lansuite-stripe-' . $paymentId,
            ]);
        } catch (\Throwable $e) {
            $this->lastError = $this->safeError($e);
            return null;
        }

        $url = (string) ($session->url ?? '');
        if (!str_starts_with($url, 'https://checkout.stripe.com/')) {
            $this->lastError = t('Stripe hat keine Zahlungsseite zurückgegeben.');
            return null;
        }

        return $session;
    }

    public function retrieveSession(string $sessionId): ?\Stripe\Checkout\Session
    {
        if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
            $this->lastError = t('Die Stripe-Sitzung fehlt.');
            return null;
        }
        $keyError = $this->keyProblem($this->secretKey());
        if ($keyError !== null) {
            $this->lastError = $keyError;
            return null;
        }

        try {
            return $this->api()->checkout->sessions->retrieve($sessionId);
        } catch (\Throwable $e) {
            $this->lastError = $this->safeError($e);
            return null;
        }
    }

    public function constructEvent(string $payload, string $signature): ?\Stripe\Event
    {
        $secret = $this->webhookSecret();
        if ($secret === '' || !str_starts_with($secret, 'whsec_') || $signature === '' || $payload === '') {
            $this->lastError = t('Stripe-Webhook-Secret oder Signatur fehlt. Modus: %1.', $this->mode === 'live' ? t('Live') : t('Test'));
            return null;
        }

        try {
            return \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Throwable $e) {
            $this->lastError = $this->safeError($e);
            return null;
        }
    }

    public function pageUrl(string $action): string
    {
        if (!preg_match('/^[a-z]+$/', $action)) {
            $action = 'stripe';
        }

        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $https = $forwarded === 'https'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $port = (string) ($_SERVER['SERVER_PORT'] ?? '');
        $portSuffix = ($port !== '' && $port !== '80' && $port !== '443') ? ':' . $port : '';
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');
        $scriptPosition = strpos($uri, 'index.php');
        $path = $scriptPosition === false ? '/' : substr($uri, 0, $scriptPosition);

        return ($https ? 'https://' : 'http://') . $host . $portSuffix . $path . 'index.php?mod=stripe&action=' . $action;
    }

    private function api(): \Stripe\StripeClient
    {
        if ($this->api === null) {
            $this->api = new \Stripe\StripeClient($this->secretKey());
        }

        return $this->api;
    }

    private function secretKey(): string
    {
        global $cfg;

        $name = $this->mode === 'live' ? 'stripe_secret_key_live' : 'stripe_secret_key_test';

        return $this->decrypt((string) ($cfg[$name] ?? ''));
    }

    private function webhookSecret(): string
    {
        global $cfg;

        $name = $this->mode === 'live' ? 'stripe_webhook_secret_live' : 'stripe_webhook_secret_test';

        return $this->decrypt((string) ($cfg[$name] ?? ''));
    }

    private function decrypt(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }

        return trim(SmtpSecret::decrypt($stored));
    }

    private function keyProblem(string $key): ?string
    {
        $modeLabel = $this->mode === 'live' ? t('Live') : t('Test');
        if ($key === '') {
            return t('Stripe Secret Key fehlt. Modus: %1.', $modeLabel);
        }
        if (str_starts_with($key, 'pk_')) {
            return t('Der öffentliche Schlüssel (pk_) wird hier nicht eingetragen. Verwende den geheimen Schlüssel (sk_ oder rk_).');
        }
        $suffix = $this->mode === 'live' ? 'live' : 'test';
        $accepted = str_starts_with($key, 'sk_' . $suffix . '_') || str_starts_with($key, 'rk_' . $suffix . '_');
        if (!$accepted) {
            return $this->mode === 'live'
                ? t('Der Live-Modus erwartet einen Schlüssel, der mit sk_live_ oder rk_live_ beginnt.')
                : t('Der Test-Modus erwartet einen Schlüssel, der mit sk_test_ oder rk_test_ beginnt.');
        }

        return null;
    }

    private function userEmail(int $userId): string
    {
        global $database;

        if ($userId <= 0) {
            return '';
        }
        $row = $database->queryWithOnlyFirstRow('SELECT email FROM %prefix%user WHERE userid = ?', [$userId]);
        $email = is_array($row) ? trim((string) ($row['email'] ?? '')) : '';
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return $email;
    }

    private function safeError(\Throwable $e): string
    {
        $text = preg_replace('/\b(?:sk|rk|pk|whsec)_[A-Za-z0-9_]+/', '[redacted]', $e->getMessage()) ?? '';

        return htmlspecialchars($this->clip(trim($text), 500), ENT_QUOTES, 'UTF-8');
    }

    private function clip(string $value, int $length): string
    {
        if (preg_match('/^.{0,' . $length . '}/u', $value, $match) === 1) {
            return $match[0];
        }

        return substr($value, 0, $length);
    }
}
