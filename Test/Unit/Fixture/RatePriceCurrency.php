<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Fixture;

use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * A display currency that is not the base one (default rate 0.8, base x 0.8
 * = display), for the unit tests of every class that shows or compares
 * prices: convert() multiplies by the rate, format() formats the amount as
 * given and never converts. With a rate of 1 a price shown converted twice,
 * or a display amount compared with a base threshold, looks right; with 0.8
 * it does not (docs/ARCHITECTURE.md "Testing").
 *
 *     $prices = new RatePriceCurrency();          // 0.8, EUR
 *     $prices->convert(100.0);                     // 80.0
 *     $prices->format(80.0, false);                // '€80.00'
 *     $prices->convertAndFormat(100.0, false);     // '€80.00'
 */
class RatePriceCurrency implements PriceCurrencyInterface
{
    public function __construct(
        private readonly float $rate = 0.8,
        private readonly string $symbol = '€',
        private readonly string $code = 'EUR'
    ) {
    }

    public function rate(): float
    {
        return $this->rate;
    }

    /**
     * @inheritdoc
     */
    public function convert($amount, $scope = null, $currency = null)
    {
        return (float) $amount * $this->rate;
    }

    /**
     * @inheritdoc
     */
    public function convertAndRound($amount, $scope = null, $currency = null, $precision = self::DEFAULT_PRECISION)
    {
        return round($this->convert($amount), (int) $precision);
    }

    /**
     * Magento's markup: `<span class="price">€80.00</span>`, or the bare
     * amount without the container.
     *
     * @inheritdoc
     */
    public function format(
        $amount,
        $includeContainer = true,
        $precision = self::DEFAULT_PRECISION,
        $scope = null,
        $currency = null
    ) {
        $text = $this->symbol . number_format((float) $amount, (int) $precision, '.', ',');

        return $includeContainer ? '<span class="price">' . $text . '</span>' : $text;
    }

    /**
     * @inheritdoc
     */
    public function convertAndFormat(
        $amount,
        $includeContainer = true,
        $precision = self::DEFAULT_PRECISION,
        $scope = null,
        $currency = null
    ) {
        return $this->format($this->convert($amount), $includeContainer, $precision);
    }

    /**
     * @inheritdoc
     */
    public function round($price)
    {
        return round((float) $price, self::DEFAULT_PRECISION);
    }

    /**
     * A data object with the currency code (getCode(), getCurrencyCode()),
     * not Magento's Currency model.
     *
     * @inheritdoc
     */
    public function getCurrency($scope = null, $currency = null)
    {
        return new DataObject(['code' => $this->code, 'currency_code' => $this->code]);
    }

    /**
     * @inheritdoc
     */
    public function getCurrencySymbol($scope = null, $currency = null)
    {
        return $this->symbol;
    }
}
