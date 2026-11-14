<?php

namespace LanSuite\Module\Stripe;

/**
 * Stripe amounts are minor units. CHF and EUR use two decimals.
 * TWINT payments are CHF amounts in those minor units (10.00 CHF is 1000).
 */
class StripeMoney
{
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private const THREE_DECIMAL = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    public static function decimals(string $currency): int
    {
        $currency = strtoupper($currency);
        if (in_array($currency, self::ZERO_DECIMAL, true)) {
            return 0;
        }
        if (in_array($currency, self::THREE_DECIMAL, true)) {
            return 3;
        }

        return 2;
    }

    public static function toMinor(float $amount, string $currency): int
    {
        $decimals = self::decimals($currency);
        if ($decimals === 0) {
            return (int) round($amount);
        }
        if ($decimals === 3) {
            return (int) (round($amount * 100) * 10);
        }

        return (int) round($amount * 100);
    }

    public static function formatMinor(int $minor, string $currency): string
    {
        $decimals = self::decimals($currency);
        $negative = $minor < 0;
        $minor = abs($minor);
        if ($decimals === 0) {
            return ($negative ? '-' : '') . (string) $minor;
        }

        $factor = 10 ** $decimals;
        $whole = intdiv($minor, $factor);
        $fraction = str_pad((string) ($minor % $factor), $decimals, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }
}
