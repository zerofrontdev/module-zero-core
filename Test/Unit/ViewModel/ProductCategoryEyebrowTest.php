<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Zero\Core\ViewModel\ProductCategoryEyebrow;

/**
 * The eyebrow's category: this store's tree and active categories only.
 */
class ProductCategoryEyebrowTest extends TestCase
{
    public function testScopedToTheStoreRootAndActive(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getName')->willReturn('Hoodies & Sweatshirts');
        $collection = $this->collection([$category]);
        $collection->expects($this->once())->method('setStoreId')->with(5)->willReturnSelf();
        $collection->expects($this->once())->method('addAttributeToSelect')->with('name')->willReturnSelf();
        $collection->expects($this->once())->method('addAttributeToFilter')->with('is_active', 1)->willReturnSelf();
        $collection->expects($this->once())->method('addFieldToFilter')->with('path', ['like' => '1/7/%'])->willReturnSelf();
        $collection->expects($this->once())->method('setPage')->with(1, 1)->willReturnSelf();

        $this->assertSame('Hoodies & Sweatshirts', $this->eyebrow($collection)->getCategoryName());
    }

    public function testNoCategoryInThisStoreNoEyebrow(): void
    {
        $collection = $this->collection([]);
        foreach (['setStoreId', 'addAttributeToSelect', 'addAttributeToFilter', 'addFieldToFilter', 'setPage'] as $method) {
            $collection->method($method)->willReturnSelf();
        }

        $this->assertSame('', $this->eyebrow($collection)->getCategoryName());
    }

    public function testNoProductNoEyebrow(): void
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturn(null);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $this->assertSame('', (new ProductCategoryEyebrow($registry, $storeManager))->getCategoryName());
    }

    /**
     * @param Category[] $categories what load() found
     * @return Collection&\PHPUnit\Framework\MockObject\MockObject
     */
    private function collection(array $categories): Collection
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('load')->willReturnSelf();
        $collection->method('count')->willReturn(count($categories));
        $collection->method('getIterator')->willReturn(new \ArrayIterator($categories));

        return $collection;
    }

    private function eyebrow(Collection $collection): ProductCategoryEyebrow
    {
        $product = $this->createStub(Product::class);
        $product->method('getCategoryCollection')->willReturn($collection);
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnMap([['product', $product]]);
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(5);
        $store->method('getRootCategoryId')->willReturn(7);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ProductCategoryEyebrow($registry, $storeManager);
    }
}
