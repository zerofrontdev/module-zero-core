<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Fixture;

use PHPUnit\Framework\TestCase;

/**
 * The fixture itself: convert() applies the rate, format() never does.
 */
class RatePriceCurrencyTest extends TestCase
{
    public function testConvertMultipliesFormatDoesNot(): void
    {
        $prices = new RatePriceCurrency();

        $this->assertSame(0.8, $prices->rate());
        $this->assertEqualsWithDelta(80.0, $prices->convert(100.0), 1e-9);
        $this->assertSame('€100.00', $prices->format(100.0, false));
        $this->assertSame('<span class="price">€100.00</span>', $prices->format(100.0));
        $this->assertSame('€80.00', $prices->convertAndFormat(100.0, false));
    }

    public function testRoundingAndAnotherCurrency(): void
    {
        $prices = new RatePriceCurrency(1.25, '£', 'GBP');

        $this->assertSame(4.17, $prices->convertAndRound(3.333));
        $this->assertSame(9.88, $prices->round(9.876));
        $this->assertSame('£1,234.50', $prices->format(1234.5, false));
        $this->assertSame('£', $prices->getCurrencySymbol());
        $this->assertSame('GBP', $prices->getCurrency()->getCode());
    }
}
