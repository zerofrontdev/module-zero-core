<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Search;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;

/**
 * Live search suggestions for the header search box as an HTML fragment,
 * replacing stock's `quickSearch` widget (not run on this theme). Same data
 * source as stock's `search/ajax/suggest` (see ViewModel\SearchSuggest).
 *
 * Full page cached, per query (the URL) and per customer group and currency
 * (the vary cookie), like the results page: 0.6 s a keystroke uncached on a
 * 100 000-product catalogue. Block\Search\Suggest tags it with the products
 * it shows. Nothing in it is the visitor's own.
 */
class Suggest implements HttpGetActionInterface
{
    private const BLOCK_NAME = 'zero.search.suggest';

    /**
     * @param LayoutFactory $resultLayoutFactory
     */
    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory
    ) {
    }

    /**
     * @return Layout
     */
    public function execute()
    {
        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement(self::BLOCK_NAME);

        return $resultLayout;
    }
}
