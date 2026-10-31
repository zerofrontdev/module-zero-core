<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Sidebar;

use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;
use Zero\Core\Block\Sidebar;
use Zero\Core\Controller\PrivateFragmentActionInterface;

/**
 * The sidebar's Compare Products and My Wish List (Block\Sidebar) for pages
 * cached before they became the session bootstrap's `sidebar` fragment: their
 * shell asks here on zero:session-ready and swaps the answer in (innerHTML),
 * so this answers without the wrapper. Empty when both are empty.
 */
class Index implements PrivateFragmentActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly LayoutFactory $layoutFactory
    ) {
    }

    /**
     * @return Raw
     */
    public function execute()
    {
        /** @var Sidebar $sidebar */
        $sidebar = $this->layoutFactory->create()->createBlock(Sidebar::class, 'zero.customer.sidebar');

        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setContents($sidebar->itemsHtml());

        return $result;
    }
}
