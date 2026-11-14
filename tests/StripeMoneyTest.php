<?php declare(strict_types=1);

namespace LanSuite\Tests;

use LanSuite\Module\Stripe\StripeMoney;
use PHPUnit\Framework\TestCase;

class StripeMoneyTest extends TestCase
{
    public function testChfUsesTwoDecimalMinorUnits(): void
    {
        $this->assertSame(1050, StripeMoney::toMinor(10.50, 'CHF'));
        $this->assertSame('10.50', StripeMoney::formatMinor(1050, 'chf'));
    }

    public function testYenHasNoDecimals(): void
    {
        $this->assertSame(500, StripeMoney::toMinor(500, 'JPY'));
        $this->assertSame('500', StripeMoney::formatMinor(500, 'JPY'));
    }

    public function testThreeDecimalCurrenciesEndWithZero(): void
    {
        $this->assertSame(5120, StripeMoney::toMinor(5.124, 'KWD'));
        $this->assertSame('5.120', StripeMoney::formatMinor(5120, 'KWD'));
    }

    public function testNegativeRefundAmount(): void
    {
        $this->assertSame('-12.00', StripeMoney::formatMinor(-1200, 'EUR'));
    }
}
