<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Which CMS pages this store view has (the theme's fixed footer links ask
 * before linking: a link to a page the store doesn't have is a 404). One
 * query for all of the store's active page identifiers, once per request.
 */
class CmsPages implements ArgumentInterface
{
    /** @var array<string, true>|null */
    private ?array $active = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function has(string $identifier): bool
    {
        if ($this->active === null) {
            $collection = $this->collectionFactory->create()
                ->addStoreFilter((int) $this->storeManager->getStore()->getId())
                ->addFieldToFilter('is_active', 1);
            $this->active = array_fill_keys($collection->getColumnValues('identifier'), true);
        }

        return isset($this->active[$identifier]);
    }
}
