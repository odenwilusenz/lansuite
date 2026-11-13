<?php

namespace LanSuite\Module\PayPal;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PayPal Orders API v2.
 *
 * The legacy Payments v1 SDK no longer accepts current live apps.
 */
class PayPal
{
    private const ZERO_DECIMAL_CURRENCIES = ['JPY', 'HUF', 'TWD'];

    private HttpClientInterface $httpClient;

    private array $items = [];

    private ?array $order = null;

    private string $clientId = '';

    private string $secret = '';

    private string $mode = 'sandbox';

    private string $currency = 'EUR';

    private string $description = '';

    private ?string $lastError = null;

    private string $lastErrorName = '';

    /**
     * @var string[]
     */
    private array $lastErrorIssues = [];

    public function __construct(?HttpClientInterface $client = null)
    {
        global $cfg, $httpClient;

        if ($client instanceof HttpClientInterface) {
            $this->httpClient = $client;
        } elseif ($httpClient instanceof HttpClientInterface) {
            $this->httpClient = $httpClient;
        } else {
            $this->httpClient = HttpClient::create();
        }

        $this->clientId = trim((string) ($cfg['paypal_client_ID'] ?? ''));
        $this->secret = trim((string) ($cfg['paypal_client_secret'] ?? ''));
        $this->mode = (($cfg['paypal_mode'] ?? '') === 'live') ? 'live' : 'sandbox';

        $currency = strtoupper(trim((string) ($cfg['paypal_currency_code'] ?? 'EUR')));
        $this->currency = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR';

        $description = trim((string) ($cfg['paypal_desc_name'] ?? ''));
        $this->description = $description !== '' ? $description : t('Anmeldung an Party');
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function isAlreadyCaptured(): bool
    {
        return in_array('ORDER_ALREADY_CAPTURED', $this->lastErrorIssues, true);
    }

    public function addItem(PayPalItem $item): void
    {
        $this->items[] = $item;
    }

    public function calcItemsTotal(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += (float) $item->value * (int) $item->quantity;
        }

        return $total;
    }

    public function formatAmount(float|int|string $amount): string
    {
        return $this->formatMinor($this->amountToMinor((float) $amount));
    }

    /**
     * Asks PayPal for an access token. Does not create a payment.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $modeLabel = $this->mode === 'live' ? t('Live') : t('Sandbox');

        if ($this->clientId === '' || $this->secret === '') {
            return [
                'ok' => false,
                'message' => t('PayPal Client-ID oder Secret ist leer. Modus: %1.', $modeLabel),
            ];
        }

        if ($this->fetchAccessToken() === null) {
            return [
                'ok' => false,
                'message' => t('PayPal hat die Zugangsdaten im Modus %1 abgelehnt. Live-Daten funktionieren nur im Live-Modus. %2', $modeLabel, (string) $this->lastError),
            ];
        }

        return [
            'ok' => true,
            'message' => t('Verbindung zu PayPal steht. Modus: %1, Währung: %2.', $modeLabel, $this->currency),
        ];
    }

    /**
     * @return PayPalItem[]
     */
    public function getItemsFromOrder(?array $order = null): array
    {
        $order = $order ?? $this->order ?? [];
        $items = [];
        foreach ($order['purchase_units'] ?? [] as $unit) {
            if (!is_array($unit)) {
                continue;
            }
            foreach ($unit['items'] ?? [] as $paypalItem) {
                if (!is_array($paypalItem)) {
                    continue;
                }
                $items[] = new PayPalItem(
                    (string) ($paypalItem['name'] ?? ''),
                    (string) ($paypalItem['unit_amount']['value'] ?? 0),
                    (string) ($paypalItem['sku'] ?? ''),
                    (int) ($paypalItem['quantity'] ?? 1)
                );
            }
        }

        return $items;
    }

    public function createPaymentLink(): ?string
    {
        global $func;

        $payloadItems = $this->itemsPayload();
        if ($payloadItems === null) {
            $func->error(t('Der Betrag muss größer als 0 sein.'));
            return null;
        }

        $token = $this->fetchAccessToken();
        if ($token === null) {
            $func->error(t('Fehler bei der Übermittlung an PayPal') . ' ' . (string) $this->lastError);
            return null;
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'description' => $this->clip($this->description, 127),
                'amount' => [
                    'currency_code' => $this->currency,
                    'value' => $payloadItems['total'],
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => $this->currency,
                            'value' => $payloadItems['total'],
                        ],
                    ],
                ],
                'items' => $payloadItems['items'],
            ]],
            'application_context' => [
                'return_url' => $this->buildReturnUrl(''),
                'cancel_url' => $this->buildReturnUrl('failed=1'),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];

        $data = $this->request('POST', '/v2/checkout/orders', $token, $payload);
        if ($data === null || empty($data['id'])) {
            $func->error(t('Fehler bei der Übermittlung an PayPal') . ' ' . (string) $this->lastError);
            return null;
        }

        $this->order = $data;
        $_SESSION['paypal_payment_id'] = (string) $data['id'];
        $_SESSION['paypal_order_items'] = [
            'id' => (string) $data['id'],
            'items' => array_map(static function (PayPalItem $item): array {
                return [
                    'description' => (string) $item->description,
                    'value' => $item->value,
                    'sku' => (string) $item->sku,
                    'quantity' => (int) $item->quantity,
                ];
            }, $this->items),
        ];

        $link = $this->approvalLink($data);
        if ($link === null) {
            $func->error(t('Fehler bei der Übermittlung an PayPal') . ' ' . t('PayPal hat keinen Zahlungslink geliefert.'));
            return null;
        }

        return $link;
    }

    public function captureOrder(string $orderId): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]+$/', $orderId)) {
            $this->lastError = t('Ungültige PayPal-Bestellnummer.');
            return null;
        }

        $token = $this->fetchAccessToken();
        if ($token === null) {
            return null;
        }

        $data = $this->request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', $token, null);
        if ($data === null) {
            return null;
        }

        $this->order = $data;
        return $data;
    }

    public function getOrder(string $orderId): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]+$/', $orderId)) {
            $this->lastError = t('Ungültige PayPal-Bestellnummer.');
            return null;
        }

        $token = $this->fetchAccessToken();
        if ($token === null) {
            return null;
        }

        $data = $this->request('GET', '/v2/checkout/orders/' . rawurlencode($orderId), $token);
        if ($data === null) {
            return null;
        }

        $this->order = $data;
        return $data;
    }

    private function fetchAccessToken(): ?string
    {
        if ($this->clientId === '' || $this->secret === '') {
            $this->lastError = t('PayPal Client-ID oder Secret ist leer.');
            return null;
        }

        $data = $this->request('POST', '/v1/oauth2/token', null, ['grant_type' => 'client_credentials']);
        if ($data === null || empty($data['access_token'])) {
            return null;
        }

        return (string) $data['access_token'];
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(string $method, string $path, ?string $token, ?array $body = null): ?array
    {
        $options = [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
        ];

        if ($token === null) {
            $options['auth_basic'] = [$this->clientId, $this->secret];
            $options['body'] = $body ?? [];
        } else {
            $options['auth_bearer'] = $token;
            if ($method !== 'GET') {
                $options['headers']['Content-Type'] = 'application/json';
                $options['headers']['Prefer'] = 'return=representation';
                $payload = ($body === null || $body === []) ? new \stdClass() : $body;
                $options['body'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        try {
            $response = $this->httpClient->request($method, $this->apiBase() . $path, $options);
            $status = $response->getStatusCode();
            $raw = $response->getContent(false);
            $data = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $this->lastError = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
            $this->lastErrorName = '';
            $this->lastErrorIssues = [];
            return null;
        }

        if (!is_array($data)) {
            $this->lastError = t('PayPal hat keine lesbare Antwort geschickt.');
            return null;
        }

        if ($status < 200 || $status >= 300) {
            $this->rememberError($status, $data);
            return null;
        }

        return $data;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: string}|null
     */
    private function itemsPayload(): ?array
    {
        if ($this->items === []) {
            return null;
        }

        $lines = [];
        $minorTotal = 0;
        foreach ($this->items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $unitMinor = $this->amountToMinor((float) $item->value);
            if ($unitMinor <= 0) {
                return null;
            }
            $minorTotal += $unitMinor * $quantity;
            $name = trim((string) $item->description);
            $line = [
                'name' => $this->clip($name !== '' ? $name : $this->description, 127),
                'quantity' => (string) $quantity,
                'category' => 'DIGITAL_GOODS',
                'unit_amount' => [
                    'currency_code' => $this->currency,
                    'value' => $this->formatMinor($unitMinor),
                ],
            ];
            $sku = trim((string) $item->sku);
            if ($sku !== '') {
                $line['sku'] = $this->clip($sku, 127);
            }
            $lines[] = $line;
        }

        if ($minorTotal <= 0) {
            return null;
        }

        return [
            'items' => $lines,
            'total' => $this->formatMinor($minorTotal),
        ];
    }

    private function amountToMinor(float $amount): int
    {
        $factor = 10 ** $this->currencyDecimals();
        return (int) round($amount * $factor);
    }

    private function formatMinor(int $minor): string
    {
        $decimals = $this->currencyDecimals();
        if ($decimals === 0) {
            return (string) $minor;
        }

        $factor = 10 ** $decimals;
        $negative = $minor < 0;
        $minor = abs($minor);
        $whole = intdiv($minor, $factor);
        $fraction = str_pad((string) ($minor % $factor), $decimals, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }

    private function currencyDecimals(): int
    {
        return in_array($this->currency, self::ZERO_DECIMAL_CURRENCIES, true) ? 0 : 2;
    }

    private function apiBase(): string
    {
        return $this->mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function buildReturnUrl(string $query): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $port = (string) ($_SERVER['SERVER_PORT'] ?? '');
        $portSuffix = ($port !== '' && $port !== '80' && $port !== '443') ? ':' . $port : '';
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');
        $scriptPosition = strpos($uri, 'index.php');
        $path = $scriptPosition === false ? '/' : substr($uri, 0, $scriptPosition);

        $url = ($https ? 'https://' : 'http://') . $host . $portSuffix . $path . 'index.php?mod=paypal&action=executepayment';
        if ($query !== '') {
            $url .= '&' . $query;
        }

        return $url;
    }

    private function approvalLink(array $order): ?string
    {
        foreach ($order['links'] ?? [] as $link) {
            if (!is_array($link)) {
                continue;
            }
            if (in_array($link['rel'] ?? '', ['payer-action', 'approve'], true) && !empty($link['href'])) {
                return (string) $link['href'];
            }
        }

        return null;
    }

    private function rememberError(int $status, array $data): void
    {
        $this->lastErrorName = (string) ($data['name'] ?? '');
        $this->lastErrorIssues = [];
        $parts = ['HTTP ' . $status];

        foreach (['error', 'error_description', 'name', 'message'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                $parts[] = $data[$key];
            }
        }

        foreach ($data['details'] ?? [] as $detail) {
            if (!is_array($detail)) {
                continue;
            }
            if (!empty($detail['issue']) && is_string($detail['issue'])) {
                $this->lastErrorIssues[] = $detail['issue'];
            }
            $parts[] = trim((string) ($detail['field'] ?? '') . ' ' . (string) ($detail['issue'] ?? '') . ' ' . (string) ($detail['description'] ?? ''));
        }

        $text = trim(implode(' ', array_filter($parts, static fn ($part) => $part !== '')));
        $this->lastError = htmlspecialchars($this->clip($text, 500), ENT_QUOTES, 'UTF-8');
    }

    private function clip(string $value, int $length): string
    {
        if (preg_match('/^.{0,' . $length . '}/u', $value, $match) === 1) {
            return $match[0];
        }

        return substr($value, 0, $length);
    }
}
