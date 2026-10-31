<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Pricing;

use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Display-currency amounts. PriceInfo amounts (getPriceInfo()->getPrice(...)
 * ->getAmount()->getValue()) are already in the store's display currency:
 * format them, never convert them again (convertAndFormat() would apply the
 * rate twice). Thresholds kept in config (free shipping from, minimum order)
 * are in base currency: compare toBase($amount) with them.
 */
class Amounts
{
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * The amount as the store shows it (symbol, decimals), not converted.
     */
    public function format(float $displayAmount, bool $includeContainer = false): string
    {
        return $this->priceCurrency->format($displayAmount, $includeContainer);
    }

    /**
     * The display amount in base currency: divided by the current rate
     * (1 when the rate is missing or not positive).
     */
    public function toBase(float $displayAmount): float
    {
        $rate = (float) $this->priceCurrency->convert(1.0);

        return $rate > 0 ? $displayAmount / $rate : $displayAmount;
    }
}
