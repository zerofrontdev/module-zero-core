<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\ViewModel\Homepage;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\ImageFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Homepage\Settings;
use Zero\Core\Model\Pricing\Amounts;
use Zero\Core\Model\Pricing\CachedPrices;
use Zero\Core\ViewModel\Homepage\FeaturedProducts;

/**
 * A homepage tile's price is the price box's amount, already in display
 * currency: formatted, not converted a second time (buildTiles() through
 * reflection, the class has no narrower seam).
 */
class FeaturedProductsTilePriceTest extends TestCase
{
    private const RATE = 0.8;

    public function testTilePriceIsFormattedNotConvertedAgain(): void
    {
        $tiles = $this->tiles([7 => 80.0, 8 => 49.77]);

        $this->assertSame(['€80.00', '€49.77'], array_column($tiles, 'price'));
        $this->assertSame(['Product 7', 'Product 8'], array_column($tiles, 'name'));
        $this->assertSame(['01', '02'], array_column($tiles, 'badge'));
        $this->assertSame(['/product-7.html', '/product-8.html'], array_column($tiles, 'url'));
    }

    /**
     * @param array<int, float> $finals product id => final price (display currency)
     * @return array<int, array<string, mixed>>
     */
    private function tiles(array $finals): array
    {
        // A store whose display currency is 0.8 of base: convert() multiplies.
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('convert')->willReturnCallback(static fn ($amount) => (float) $amount * self::RATE);
        $priceCurrency->method('format')->willReturnCallback(static fn ($amount) => sprintf('€%.2f', $amount));
        $priceCurrency->method('convertAndFormat')
            ->willReturnCallback(static fn ($amount) => sprintf('€%.2f', (float) $amount * self::RATE));

        $products = [];
        foreach ($finals as $id => $final) {
            $product = $this->createStub(Product::class);
            $product->method('getId')->willReturn($id);
            $product->method('getName')->willReturn('Product ' . $id);
            $product->method('getProductUrl')->willReturn('/product-' . $id . '.html');
            $products[] = $product;
        }
        $prices = $this->createStub(CachedPrices::class);
        $prices->method('get')->willReturnCallback(static fn (Product $product) => [
            'regular' => $finals[$product->getId()],
            'final' => $finals[$product->getId()],
        ]);

        $imageHelper = $this->createStub(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('/media/tile.jpg');

        // categoryNames(): the products have no categories.
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $viewModel = new FeaturedProducts(
            $this->createStub(ProductCollectionFactory::class),
            $this->createStub(CategoryCollectionFactory::class),
            $resource,
            $imageHelper,
            new Amounts($priceCurrency),
            $this->createStub(ImageFactory::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(Visibility::class),
            $prices,
            $this->createStub(Settings::class)
        );
        $buildTiles = new \ReflectionMethod($viewModel, 'buildTiles');

        return $buildTiles->invoke($viewModel, $products, static fn (int $index): string => sprintf('%02d', $index + 1));
    }
}
