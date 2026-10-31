<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Zero\Core\ViewModel\StickyAddToCart;

/**
 * Whether the sticky bar (and quick view) adds to the cart straight away or
 * sends the shopper to the options.
 */
class StickyAddToCartTest extends TestCase
{
    public function testQuoteOnlyIsNotAddedDirectly(): void
    {
        $this->assertFalse($this->addsDirectly('simple', ['zero_quote_only' => '1']));
        $this->assertFalse($this->addsDirectly('virtual', ['zero_quote_only' => 1]));
    }

    public function testAProductWithNothingToChooseIsAddedDirectly(): void
    {
        $this->assertTrue($this->addsDirectly('simple'));
        $this->assertTrue($this->addsDirectly('simple', ['zero_quote_only' => '0']));
        $this->assertTrue($this->addsDirectly('downloadable'));
    }

    public function testChoicesSendToTheOptions(): void
    {
        $this->assertFalse($this->addsDirectly('configurable'));
        $this->assertFalse($this->addsDirectly('bundle'));
        $this->assertFalse($this->addsDirectly('simple', [], true));
        $this->assertFalse($this->addsDirectly('downloadable', ['links_purchased_separately' => 1]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function addsDirectly(string $typeId, array $data = [], bool $requiredOption = false): bool
    {
        $option = $this->createStub(Option::class);
        $option->method('getIsRequire')->willReturn($requiredOption);
        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getData')->willReturnCallback(static fn ($key = '') => $data[$key] ?? null);
        $product->method('getOptions')->willReturn([$option]);

        return (new StickyAddToCart($this->createStub(ScopeConfigInterface::class)))->addsDirectly($product);
    }
}
