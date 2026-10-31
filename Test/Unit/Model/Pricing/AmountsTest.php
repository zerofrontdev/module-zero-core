<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Model\Pricing;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Pricing\Amounts;
use Zero\Core\Test\Unit\Fixture\RatePriceCurrency;

/**
 * Display amounts are formatted, never converted; thresholds compare in base.
 */
class AmountsTest extends TestCase
{
    public function testFormatNeverConverts(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->expects($this->never())->method('convertAndFormat');
        $priceCurrency->expects($this->never())->method('convert');
        $priceCurrency->expects($this->exactly(2))->method('format')
            ->willReturnCallback(static fn ($amount, $container = true) => ($container ? '<span>' : '')
                . sprintf('€%.2f', $amount) . ($container ? '</span>' : ''));
        $amounts = new Amounts($priceCurrency);

        $this->assertSame('€80.00', $amounts->format(80.0));
        $this->assertSame('<span>€80.00</span>', $amounts->format(80.0, true));
    }

    public function testToBaseDividesByTheRate(): void
    {
        $this->assertEqualsWithDelta(100.0, $this->amounts(0.8)->toBase(80.0), 1e-9);
        $this->assertEqualsWithDelta(40.0, $this->amounts(1.25)->toBase(50.0), 1e-9);
        $this->assertSame(80.0, $this->amounts(1.0)->toBase(80.0));
    }

    public function testToBaseKeepsTheAmountWithoutAPositiveRate(): void
    {
        $this->assertSame(80.0, $this->amounts(0.0)->toBase(80.0));
        $this->assertSame(80.0, $this->amounts(-2.0)->toBase(80.0));
    }

    /**
     * Over the shared fixture (base x 0.8 = display): a display amount shows
     * as it is, and a base threshold of 100 is reached by 80 shown, not by
     * 100 shown converted again.
     */
    public function testWithTheRatePriceCurrencyFixture(): void
    {
        $prices = new RatePriceCurrency();
        $amounts = new Amounts($prices);
        $display = $prices->convert(100.0);

        $this->assertSame('€80.00', $amounts->format($display));
        $this->assertSame('<span class="price">€80.00</span>', $amounts->format($display, true));
        $this->assertNotSame($prices->convertAndFormat($display, false), $amounts->format($display), 'converting twice would show €64.00');
        $this->assertEqualsWithDelta(100.0, $amounts->toBase($display), 1e-9);
        $this->assertLessThan(100.0, $amounts->toBase(79.99));
    }

    /**
     * Amounts over a PriceCurrency whose convert() multiplies by $rate.
     */
    private function amounts(float $rate): Amounts
    {
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('convert')->willReturnCallback(static fn ($amount) => (float) $amount * $rate);

        return new Amounts($priceCurrency);
    }
}
