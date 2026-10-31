<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The viewed product's first category name, for the small eyebrow label above
 * the product title (product/view/page-title.phtml). Same lookup as core's
 * ListProduct (getCategoryCollection()->setPage(1, 1)), limited to this
 * store's tree and active categories (a product shared between websites
 * otherwise shows another store's or a disabled category); the product comes
 * from the registry like core product-page blocks.
 */
class ProductCategoryEyebrow implements ArgumentInterface
{
    /**
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The viewed product's own first real associated category name, or an empty string when it has none
     * (a product with no category assigned at all — a real, valid catalog state, not an error).
     */
    public function getCategoryName(): string
    {
        $product = $this->registry->registry('product');
        if (!$product) {
            return '';
        }

        /*
         * getCategoryCollection() selects no attributes beyond the ids, so `name`
         * must be added before load().
         */
        $store = $this->storeManager->getStore();
        $categories = $product->getCategoryCollection()
            ->setStoreId((int) $store->getId())
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('path', ['like' => '1/' . (int) $store->getRootCategoryId() . '/%'])
            ->setPage(1, 1)
            ->load();
        if (!$categories->count()) {
            return '';
        }

        $category = $categories->getIterator()->current();

        return (string) $category->getName();
    }
}
